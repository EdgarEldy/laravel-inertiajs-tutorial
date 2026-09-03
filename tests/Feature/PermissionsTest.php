<?php

use App\Models\Permission;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with PERMISSION:READ can view the permissions index', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'READ']]);
    Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->get('/permissions');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Permissions/Index'));
});

test('a user without PERMISSION:READ is denied the permissions index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/permissions');

    $response->assertForbidden();
});

test('a guest is redirected away from the permissions index', function () {
    $response = $this->get('/permissions');

    $response->assertRedirect('/login');
});

test('a user with PERMISSION:WRITE can create a permission, and it is audit logged', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);

    $response = $this->actingAs($user)->post('/permissions', [
        'resource' => 'CATEGORY',
        'action' => 'READ',
    ]);

    $response->assertRedirect(route('permissions.index'));
    $this->assertDatabaseHas('permissions', ['resource' => 'CATEGORY', 'action' => 'READ']);

    $permission = Permission::where('resource', 'CATEGORY')->where('action', 'READ')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $user->id,
        'action' => 'created',
        'entity_type' => 'Permission',
        'entity_id' => $permission->id,
    ]);
});

test('creating a permission without a resource or action fails validation', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);

    $response = $this->actingAs($user)->post('/permissions', ['resource' => '', 'action' => '']);

    $response->assertSessionHasErrors(['resource', 'action']);
});

test('creating a permission with a resource/action combination that already exists fails validation', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);
    Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->post('/permissions', [
        'resource' => 'CATEGORY',
        'action' => 'READ',
    ]);

    $response->assertSessionHasErrors('resource');
});

test('a user without PERMISSION:WRITE cannot create a permission', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'READ']]);

    $response = $this->actingAs($user)->post('/permissions', [
        'resource' => 'CATEGORY',
        'action' => 'READ',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('permissions', ['resource' => 'CATEGORY', 'action' => 'READ']);
});

test('a user with PERMISSION:WRITE can update a permission, and it is audit logged', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->put("/permissions/{$permission->id}", [
        'resource' => 'CATEGORY',
        'action' => 'WRITE',
    ]);

    $response->assertRedirect(route('permissions.index'));
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'resource' => 'CATEGORY', 'action' => 'WRITE']);

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $user->id,
        'action' => 'updated',
        'entity_type' => 'Permission',
        'entity_id' => $permission->id,
    ]);
});

test('updating a permission to a resource/action combination already used by another permission fails validation', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);
    Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'WRITE']);
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->put("/permissions/{$permission->id}", [
        'resource' => 'CATEGORY',
        'action' => 'WRITE',
    ]);

    $response->assertSessionHasErrors('resource');
});

test('a user without PERMISSION:WRITE cannot update a permission', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'READ']]);
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->put("/permissions/{$permission->id}", [
        'resource' => 'CATEGORY',
        'action' => 'WRITE',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'action' => 'READ']);
});

test('a user with PERMISSION:WRITE can delete a permission assigned to no role, and it is audit logged', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->delete("/permissions/{$permission->id}");

    $response->assertRedirect(route('permissions.index'));
    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'deleted',
        'entity_type' => 'Permission',
        'entity_id' => $permission->id,
    ]);
});

test('deleting a permission still assigned to a role is rejected as a field error, not an exception', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'WRITE']]);
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);
    $role = Role::factory()->create();
    $role->permissions()->attach($permission);

    $response = $this->actingAs($user)->delete("/permissions/{$permission->id}");

    $response->assertRedirect();
    $response->assertSessionHasErrors('permission');
    $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
});

test('a user without PERMISSION:WRITE cannot delete a permission', function () {
    $user = $this->userWithPermissions([['PERMISSION', 'READ']]);
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->delete("/permissions/{$permission->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
});
