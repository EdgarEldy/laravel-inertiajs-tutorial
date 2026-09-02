<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Seed only this branch's own resource permissions (`ROLE:*`,
     * `PERMISSION:*`, `USER:*`), assigned to a seeded `ADMIN` role. Every
     * later branch seeds its own resource's permissions from its own
     * migration instead of being pre-declared here.
     */
    public function run(): void
    {
        $admin = Role::firstOrCreate(['role_name' => 'ADMIN']);
        Role::firstOrCreate(['role_name' => 'USER']);

        $permissions = [
            ['resource' => 'ROLE', 'action' => 'READ'],
            ['resource' => 'ROLE', 'action' => 'WRITE'],
            ['resource' => 'PERMISSION', 'action' => 'READ'],
            ['resource' => 'PERMISSION', 'action' => 'WRITE'],
            ['resource' => 'USER', 'action' => 'READ'],
            ['resource' => 'USER', 'action' => 'WRITE'],
        ];

        foreach ($permissions as $attributes) {
            $permission = Permission::firstOrCreate($attributes);

            $admin->permissions()->syncWithoutDetaching([$permission->id]);
        }
    }
}
