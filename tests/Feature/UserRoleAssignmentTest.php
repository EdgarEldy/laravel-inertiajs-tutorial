<?php

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

test('a user with USER:READ can view the users index', function () {
    $user = $this->userWithPermissions([['USER', 'READ']]);

    $response = $this->actingAs($user)->get('/users');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Users/Index'));
});

test('a user without USER:READ is denied the users index', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/users');

    $response->assertForbidden();
});

test('a guest is redirected away from the users index', function () {
    $response = $this->get('/users');

    $response->assertRedirect('/login');
});

test('a user with USER:READ can view another user\'s show page, including their roles', function () {
    $user = $this->userWithPermissions([['USER', 'READ']]);
    $target = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get("/users/{$target->id}");

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Users/Show')
        ->has('user')
        ->has('availableRoles')
    );
});

test('a user without USER:READ is denied a user\'s show page', function () {
    $user = $this->userWithNoPermissions();
    $target = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get("/users/{$target->id}");

    $response->assertForbidden();
});

test('a user with USER:WRITE can assign a role to a user, and it is audit logged', function () {
    $actor = $this->userWithPermissions([['USER', 'WRITE']]);
    $target = $this->userWithNoPermissions();
    $role = Role::factory()->create();

    $response = $this->actingAs($actor)->post("/users/{$target->id}/roles/{$role->id}");

    $response->assertRedirect(route('users.show', $target));
    $this->assertTrue($target->fresh()->roles->contains($role));

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $actor->id,
        'action' => 'assigned',
        'entity_type' => 'User',
        'entity_id' => $target->id,
    ]);
});

test('a user without USER:WRITE cannot assign a role to a user', function () {
    $actor = $this->userWithPermissions([['USER', 'READ']]);
    $target = $this->userWithNoPermissions();
    $role = Role::factory()->create();

    $response = $this->actingAs($actor)->post("/users/{$target->id}/roles/{$role->id}");

    $response->assertForbidden();
    $this->assertFalse($target->fresh()->roles->contains($role));
});

test('a user with USER:WRITE can remove a role from a user when it does not risk a last-admin lockout, and it is audit logged', function () {
    $actor = $this->userWithPermissions([['USER', 'WRITE']]);
    $target = $this->userWithNoPermissions();
    $role = Role::factory()->create();
    $target->roles()->attach($role);

    $response = $this->actingAs($actor)->delete("/users/{$target->id}/roles/{$role->id}");

    $response->assertRedirect(route('users.show', $target));
    $this->assertFalse($target->fresh()->roles->contains($role));

    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $actor->id,
        'action' => 'removed',
        'entity_type' => 'User',
        'entity_id' => $target->id,
    ]);
});

test('a user without USER:WRITE cannot remove a role from a user', function () {
    $actor = $this->userWithPermissions([['USER', 'READ']]);
    $target = $this->userWithNoPermissions();
    $role = Role::factory()->create();
    $target->roles()->attach($role);

    $response = $this->actingAs($actor)->delete("/users/{$target->id}/roles/{$role->id}");

    $response->assertForbidden();
    $this->assertTrue($target->fresh()->roles->contains($role));
});

test('removing the last role granting ROLE:WRITE from the last user who holds it is rejected as a last-admin lockout', function () {
    // The "second direction" of the last-admin lockout: unlike the
    // role-permission-side test, the target user being edited here is not
    // necessarily the acting user - USER:WRITE alone lets an actor manage
    // someone else's roles, so this must be caught even when the person
    // clicking "remove" is not the one about to be locked out.
    $roleWritePermission = Permission::factory()->create(['resource' => 'ROLE', 'action' => 'WRITE']);
    $userWritePermission = Permission::factory()->create(['resource' => 'USER', 'action' => 'WRITE']);

    $soleAdminRole = Role::factory()->create(['role_name' => 'SOLE_ADMIN_ROLE']);
    $soleAdminRole->permissions()->attach($roleWritePermission);

    $actorRole = Role::factory()->create(['role_name' => 'USER_MANAGER']);
    $actorRole->permissions()->attach($userWritePermission);

    $actor = $this->userWithNoPermissions();
    $actor->roles()->attach($actorRole);

    $lastAdmin = $this->userWithNoPermissions();
    $lastAdmin->roles()->attach($soleAdminRole);

    $auditLogCountBefore = AuditLog::count();

    $response = $this->actingAs($actor)->delete("/users/{$lastAdmin->id}/roles/{$soleAdminRole->id}");

    $response->assertRedirect();
    $response->assertSessionHasErrors('role');

    // The role must still be attached - the removal was rejected, not
    // silently partially applied.
    $this->assertTrue($lastAdmin->fresh()->roles->contains($soleAdminRole));

    // No "removed" audit row for this rejected attempt.
    expect(AuditLog::count())->toBe($auditLogCountBefore);
});

test('removing a ROLE:WRITE-granting role from a user is allowed when they retain it through another role', function () {
    $roleWritePermission = Permission::factory()->create(['resource' => 'ROLE', 'action' => 'WRITE']);
    $userWritePermission = Permission::factory()->create(['resource' => 'USER', 'action' => 'WRITE']);

    $roleA = Role::factory()->create(['role_name' => 'ROLE_A']);
    $roleA->permissions()->attach($roleWritePermission);

    $roleB = Role::factory()->create(['role_name' => 'ROLE_B']);
    $roleB->permissions()->attach($roleWritePermission);

    $actorRole = Role::factory()->create(['role_name' => 'USER_MANAGER']);
    $actorRole->permissions()->attach($userWritePermission);

    $actor = $this->userWithNoPermissions();
    $actor->roles()->attach($actorRole);

    $admin = $this->userWithNoPermissions();
    $admin->roles()->attach([$roleA->id, $roleB->id]);

    $response = $this->actingAs($actor)->delete("/users/{$admin->id}/roles/{$roleA->id}");

    $response->assertRedirect(route('users.show', $admin));
    $response->assertSessionHasNoErrors();
    $this->assertFalse($admin->fresh()->roles->contains($roleA));
    $this->assertTrue($admin->fresh()->roles->contains($roleB));
});

test('removing a ROLE:WRITE-granting role from the last user who has it is allowed when another user still holds a different ROLE:WRITE-granting role', function () {
    $roleWritePermission = Permission::factory()->create(['resource' => 'ROLE', 'action' => 'WRITE']);
    $userWritePermission = Permission::factory()->create(['resource' => 'USER', 'action' => 'WRITE']);

    $roleA = Role::factory()->create(['role_name' => 'ROLE_A']);
    $roleA->permissions()->attach($roleWritePermission);

    $roleB = Role::factory()->create(['role_name' => 'ROLE_B']);
    $roleB->permissions()->attach($roleWritePermission);

    $actorRole = Role::factory()->create(['role_name' => 'USER_MANAGER']);
    $actorRole->permissions()->attach($userWritePermission);

    $actor = $this->userWithNoPermissions();
    $actor->roles()->attach($actorRole);

    $adminOne = $this->userWithNoPermissions();
    $adminOne->roles()->attach($roleA);

    $adminTwo = $this->userWithNoPermissions();
    $adminTwo->roles()->attach($roleB);

    $response = $this->actingAs($actor)->delete("/users/{$adminOne->id}/roles/{$roleA->id}");

    $response->assertRedirect(route('users.show', $adminOne));
    $response->assertSessionHasNoErrors();
    $this->assertFalse($adminOne->fresh()->roles->contains($roleA));
});
