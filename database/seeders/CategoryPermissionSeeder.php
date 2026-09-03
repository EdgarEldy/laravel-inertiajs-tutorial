<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CategoryPermissionSeeder extends Seeder
{
    /**
     * Seed only this branch's own resource permissions (`CATEGORY:*`),
     * assigned to the `ADMIN` role - the same incremental-seeding
     * convention `PermissionSeeder` set for `ROLE`/`PERMISSION`/`USER`.
     * `ADMIN` is guaranteed to already exist by the time this runs (created
     * by `PermissionSeeder`), but `firstOrCreate` is used defensively
     * rather than assuming a specific seeder run order.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['role_name' => 'ADMIN']);

        $permissions = [
            ['resource' => 'CATEGORY', 'action' => 'READ'],
            ['resource' => 'CATEGORY', 'action' => 'WRITE'],
        ];

        foreach ($permissions as $attributes) {
            $permission = Permission::firstOrCreate($attributes);

            $admin->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
