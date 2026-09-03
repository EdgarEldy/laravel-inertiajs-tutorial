<?php

namespace App\Listeners;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class AssignDefaultRoleOnRegistration
{
    /**
     * Bootstrap the very first admin without a circular permission
     * dependency: the first user ever created gets `ADMIN` (and, with it,
     * `USER:WRITE`/`ROLE:WRITE`, enabling every subsequent role
     * assignment); every other registration gets the plain `USER` role.
     *
     * Without this listener, nobody could ever grant the *first* role:
     * assigning a role requires `USER:WRITE`, but `USER:WRITE` only exists
     * as a permission on a role someone must already hold - a chicken-and-
     * egg problem with no user in the loop to break it. Reacting to
     * Jetstream's own `Registered` event here, unconditionally, is what
     * breaks the cycle without needing a manual seeding/console step after
     * every fresh install.
     */
    public function handle(Registered $event): void
    {
        $isFirstUser = User::query()->count() === 1;

        $role = Role::firstOrCreate([
            'role_name' => $isFirstUser ? 'ADMIN' : 'USER',
        ]);

        $event->user->roles()->syncWithoutDetaching([$role->id]);
    }
}
