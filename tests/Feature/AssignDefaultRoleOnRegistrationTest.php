<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;

function registerRbacUser(string $email): void
{
    test()->post('/register', [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature(),
    ]);

    // Registration auto-logs the new user in, and Fortify's `/register`
    // route sits behind the `guest` middleware - so without logging back
    // out here, the *next* call would silently bounce to `/dashboard`
    // without ever reaching `RegisteredUserController` or creating a user,
    // rather than failing loudly. This matters specifically for a test
    // registering more than one user in sequence.
    test()->post('/logout');
}

test('the very first user ever registered is granted ADMIN', function () {
    registerRbacUser('first@example.com');

    $user = User::whereEmail('first@example.com')->firstOrFail();

    expect($user->roles()->pluck('role_name')->all())->toBe(['ADMIN']);
})->skip(fn () => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');

test('every user registered after the first is granted USER, not ADMIN, across more than two registrations', function () {
    // Registering three users (not just two) matters here: a listener that
    // secretly checked `User::id === 1` instead of "am I the first row"
    // would still pass a two-user test by coincidence, but this proves the
    // rule holds for the third and beyond too, not just "the second".
    registerRbacUser('user-one@example.com');
    registerRbacUser('user-two@example.com');
    registerRbacUser('user-three@example.com');

    $first = User::whereEmail('user-one@example.com')->firstOrFail();
    $second = User::whereEmail('user-two@example.com')->firstOrFail();
    $third = User::whereEmail('user-three@example.com')->firstOrFail();

    expect($first->roles()->pluck('role_name')->all())->toBe(['ADMIN']);
    expect($second->roles()->pluck('role_name')->all())->toBe(['USER']);
    expect($third->roles()->pluck('role_name')->all())->toBe(['USER']);
})->skip(fn () => ! Features::enabled(Features::registration()), 'Registration support is not enabled.');
