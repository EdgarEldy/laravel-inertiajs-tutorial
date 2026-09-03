<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with ROLE:WRITE can view the permission-assignment page for a role', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create();

    $response = $this->actingAs($user)->get("/roles/{$role->id}/permissions");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Roles/Permissions')
        ->has('role')
        ->has('permissions')
    );
});

test('a user without ROLE:WRITE is denied the permission-assignment page', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);
    $role = Role::factory()->create();

    $response = $this->actingAs($user)->get("/roles/{$role->id}/permissions");

    $response->assertForbidden();
});

test('a user with ROLE:WRITE can assign a permission to a role, and it is audit logged', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->post("/roles/{$role->id}/permissions/{$permission->id}");

    $response->assertRedirect(route('roles.permissions', $role));
    $this->assertTrue($role->fresh()->permissions->contains($permission));

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $user->id,
        'action' => 'assigned',
        'entity_type' => 'Role',
        'entity_id' => $role->id,
    ]);
});

test('assigning a permission to a role a second time is idempotent (syncWithoutDetaching)', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);
    $role->permissions()->attach($permission);

    $this->actingAs($user)->post("/roles/{$role->id}/permissions/{$permission->id}");

    expect($role->fresh()->permissions()->where('permissions.id', $permission->id)->count())->toBe(1);
});

test('a user without ROLE:WRITE cannot assign a permission to a role', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);

    $response = $this->actingAs($user)->post("/roles/{$role->id}/permissions/{$permission->id}");

    $response->assertForbidden();
    $this->assertFalse($role->fresh()->permissions->contains($permission));
});

test('a user with ROLE:WRITE can remove a permission from a role that is not the last source of ROLE:WRITE, and it is audit logged', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);
    $role->permissions()->attach($permission);

    $response = $this->actingAs($user)->delete("/roles/{$role->id}/permissions/{$permission->id}");

    $response->assertRedirect(route('roles.permissions', $role));
    $this->assertFalse($role->fresh()->permissions->contains($permission));

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $user->id,
        'action' => 'removed',
        'entity_type' => 'Role',
        'entity_id' => $role->id,
    ]);
});

test('a user without ROLE:WRITE cannot remove a permission from a role', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);
    $role = Role::factory()->create();
    $permission = Permission::factory()->create(['resource' => 'CATEGORY', 'action' => 'READ']);
    $role->permissions()->attach($permission);

    $response = $this->actingAs($user)->delete("/roles/{$role->id}/permissions/{$permission->id}");

    $response->assertForbidden();
    $this->assertTrue($role->fresh()->permissions->contains($permission));
});

test('removing ROLE:WRITE from the only role that grants it anywhere is rejected as a last-admin lockout', function () {
    // This is the "first direction" of the last-admin lockout: the acting
    // user themselves holds ROLE:WRITE (needed to reach this route at all)
    // through the very role/permission pair being edited, and no other
    // role/user anywhere else grants ROLE:WRITE either.
    $roleWritePermission = Permission::factory()->create(['resource' => 'ROLE', 'action' => 'WRITE']);
    $role = Role::factory()->create(['role_name' => 'SOLE_ADMIN_ROLE']);
    $role->permissions()->attach($roleWritePermission);

    $user = $this->userWithNoPermissions();
    $user->roles()->attach($role);

    $auditLogCountBefore = AuditLog::count();

    $response = $this->actingAs($user)->delete("/roles/{$role->id}/permissions/{$roleWritePermission->id}");

    $response->assertRedirect();
    $response->assertSessionHasErrors('permission');

    // The permission must still be attached - the removal was rejected, not
    // silently partially applied.
    $this->assertTrue($role->fresh()->permissions->contains($roleWritePermission));

    // No "removed" audit row for this rejected attempt.
    expect(AuditLog::count())->toBe($auditLogCountBefore);
});

test('removing ROLE:WRITE from a role is allowed when another role still grants it to some user', function () {
    $roleWritePermission = Permission::factory()->create(['resource' => 'ROLE', 'action' => 'WRITE']);

    $roleA = Role::factory()->create(['role_name' => 'ROLE_A']);
    $roleA->permissions()->attach($roleWritePermission);

    $roleB = Role::factory()->create(['role_name' => 'ROLE_B']);
    $roleB->permissions()->attach($roleWritePermission);

    $actor = $this->userWithNoPermissions();
    $actor->roles()->attach($roleA);

    $otherAdmin = $this->userWithNoPermissions();
    $otherAdmin->roles()->attach($roleB);

    $response = $this->actingAs($actor)->delete("/roles/{$roleA->id}/permissions/{$roleWritePermission->id}");

    $response->assertRedirect(route('roles.permissions', $roleA));
    $response->assertSessionHasNoErrors();
    $this->assertFalse($roleA->fresh()->permissions->contains($roleWritePermission));
});
