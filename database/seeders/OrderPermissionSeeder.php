<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class OrderPermissionSeeder extends Seeder
{
    /**
     * Seed only this branch's own resource permissions (`ORDER:*`),
     * assigned to the `ADMIN` role - see `CategoryPermissionSeeder` for the
     * same incremental-seeding convention.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['role_name' => 'ADMIN']);

        $permissions = [
            ['resource' => 'ORDER', 'action' => 'READ'],
            ['resource' => 'ORDER', 'action' => 'WRITE'],
        ];

        foreach ($permissions as $attributes) {
            $permission = Permission::firstOrCreate($attributes);

            $admin->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
