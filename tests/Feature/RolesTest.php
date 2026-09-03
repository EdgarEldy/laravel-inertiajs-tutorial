<?php

use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with ROLE:READ can view the roles index', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);
    Role::factory()->create(['role_name' => 'EDITOR']);

    $response = $this->actingAs($user)->get('/roles');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Roles/Index'));
});

test('a user without ROLE:READ is denied the roles index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/roles');

    $response->assertForbidden();
});

test('a guest is redirected away from the roles index', function () {
    $response = $this->get('/roles');

    $response->assertRedirect('/login');
});

test('a user with ROLE:WRITE can create a role, and it is audit logged', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);

    $response = $this->actingAs($user)->post('/roles', ['role_name' => 'MANAGER']);

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseHas('roles', ['role_name' => 'MANAGER']);

    $role = Role::where('role_name', 'MANAGER')->firstOrFail();

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $user->id,
        'action' => 'created',
        'entity_type' => 'Role',
        'entity_id' => $role->id,
    ]);
});

test('creating a role without a role_name fails validation', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $rolesBefore = Role::count();

    $response = $this->actingAs($user)->post('/roles', ['role_name' => '']);

    $response->assertSessionHasErrors('role_name');
    expect(Role::count())->toBe($rolesBefore);
});

test('creating a role with a role_name that already exists fails validation', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    Role::factory()->create(['role_name' => 'MANAGER']);

    $response = $this->actingAs($user)->post('/roles', ['role_name' => 'MANAGER']);

    $response->assertSessionHasErrors('role_name');
});

test('a user without ROLE:WRITE cannot create a role', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);

    $response = $this->actingAs($user)->post('/roles', ['role_name' => 'MANAGER']);

    $response->assertForbidden();
    $this->assertDatabaseMissing('roles', ['role_name' => 'MANAGER']);
});

test('a user with ROLE:WRITE can update a role name, and it is audit logged', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create(['role_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/roles/{$role->id}", ['role_name' => 'NEW_NAME']);

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'role_name' => 'NEW_NAME']);

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $user->id,
        'action' => 'updated',
        'entity_type' => 'Role',
        'entity_id' => $role->id,
    ]);
});

test('updating a role without a role_name fails validation', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create(['role_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/roles/{$role->id}", ['role_name' => '']);

    $response->assertSessionHasErrors('role_name');
});

test('updating a role to a name already used by another role fails validation', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    Role::factory()->create(['role_name' => 'TAKEN']);
    $role = Role::factory()->create(['role_name' => 'ORIGINAL']);

    $response = $this->actingAs($user)->put("/roles/{$role->id}", ['role_name' => 'TAKEN']);

    $response->assertSessionHasErrors('role_name');
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'role_name' => 'ORIGINAL']);
});

test('updating a role to its own current name does not fail the unique rule', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create(['role_name' => 'SAME_NAME']);

    $response = $this->actingAs($user)->put("/roles/{$role->id}", ['role_name' => 'SAME_NAME']);

    $response->assertSessionHasNoErrors();
});

test('a user without ROLE:WRITE cannot update a role', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);
    $role = Role::factory()->create(['role_name' => 'OLD_NAME']);

    $response = $this->actingAs($user)->put("/roles/{$role->id}", ['role_name' => 'NEW_NAME']);

    $response->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'role_name' => 'OLD_NAME']);
});

test('a user with ROLE:WRITE can delete a role with no users assigned, and it is audit logged', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create(['role_name' => 'UNUSED']);

    $response = $this->actingAs($user)->delete("/roles/{$role->id}");

    $response->assertRedirect(route('roles.index'));
    $this->assertDatabaseMissing('roles', ['id' => $role->id]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'deleted',
        'entity_type' => 'Role',
        'entity_id' => $role->id,
    ]);
});

test('deleting a role still assigned to a user is rejected as a field error, not an exception', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create(['role_name' => 'STILL_USED']);
    $otherUser = $this->userWithNoPermissions();
    $otherUser->roles()->attach($role);

    $response = $this->actingAs($user)->delete("/roles/{$role->id}");

    $response->assertRedirect();
    $response->assertSessionHasErrors('role');
    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});

test('a user without ROLE:WRITE cannot delete a role', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);
    $role = Role::factory()->create(['role_name' => 'UNUSED']);

    $response = $this->actingAs($user)->delete("/roles/{$role->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});
