<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class CustomerPermissionSeeder extends Seeder
{
    /**
     * Seed only this branch's own resource permissions (`CUSTOMER:*`),
     * assigned to the `ADMIN` role - see `CategoryPermissionSeeder` for the
     * same incremental-seeding convention.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['role_name' => 'ADMIN']);

        $permissions = [
            ['resource' => 'CUSTOMER', 'action' => 'READ'],
            ['resource' => 'CUSTOMER', 'action' => 'WRITE'],
        ];

        foreach ($permissions as $attributes) {
            $permission = Permission::firstOrCreate($attributes);

            $admin->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
