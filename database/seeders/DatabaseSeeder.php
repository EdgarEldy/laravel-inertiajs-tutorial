<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $this->call(CategoryPermissionSeeder::class);
        $this->call(ProductPermissionSeeder::class);
        $this->call(CustomerPermissionSeeder::class);

        // User::factory(10)->create();

        $user = User::factory()->create([
            ...User::splitName('Test User'),
            'email' => 'test@example.com',
        ]);

        // Factory-created users never fire Jetstream's Registered event, so
        // AssignDefaultRoleOnRegistration never runs for them - without this,
        // the seeded dev user would have no role and no permissions at all.
        $user->roles()->attach(Role::where('role_name', 'ADMIN')->first());
    }
}
