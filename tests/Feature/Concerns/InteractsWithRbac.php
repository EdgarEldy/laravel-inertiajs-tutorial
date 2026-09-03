<?php

namespace Tests\Feature\Concerns;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/**
 * Small RBAC fixture helpers shared across the feature test suite for
 * `feature/rbac`, so every test builds its users/roles/permissions the same
 * way instead of each file hand-rolling its own setup.
 */
trait InteractsWithRbac
{
    /**
     * Seeds this branch's own permission catalog (ROLE:*, PERMISSION:*,
     * USER:*) plus the ADMIN/USER roles, exactly as `PermissionSeeder` does
     * for a real install.
     */
    protected function seedRbacPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    /**
     * A verified user holding the seeded ADMIN role (every permission this
     * branch defines).
     */
    protected function adminUser(): User
    {
        $this->seedRbacPermissions();

        $admin = Role::where('role_name', 'ADMIN')->firstOrFail();

        $user = User::factory()->create();
        $user->roles()->attach($admin);

        return $user->fresh();
    }

    /**
     * A verified user with no roles/permissions at all - used for
     * unauthorized/permission-denied assertions.
     */
    protected function userWithNoPermissions(): User
    {
        return User::factory()->create();
    }

    /**
     * A verified user holding a freshly created role granting exactly the
     * given resource/action pairs, e.g. `userWithPermissions([['ROLE', 'READ']])`.
     *
     * @param  array<int, array{0: string, 1: string}>  $permissions
     */
    protected function userWithPermissions(array $permissions): User
    {
        $role = Role::factory()->create();

        foreach ($permissions as [$resource, $action]) {
            $permission = Permission::firstOrCreate(['resource' => $resource, 'action' => $action]);
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user->fresh();
    }
}
