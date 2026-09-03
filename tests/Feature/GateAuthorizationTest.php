<?php

use Illuminate\Support\Facades\Gate;

test('Gate::before allows a RESOURCE:ACTION ability for a user who holds it (write action)', function () {
    $user = $this->userWithPermissions([['ROLE', 'WRITE']]);

    expect(Gate::forUser($user)->allows('ROLE:WRITE'))->toBeTrue();
});

test('Gate::before denies a RESOURCE:ACTION ability for a user who does not hold it (write action)', function () {
    $user = $this->userWithNoPermissions();

    expect(Gate::forUser($user)->denies('ROLE:WRITE'))->toBeTrue();
});

test('Gate::before allows a RESOURCE:ACTION ability for a user who holds it (read action)', function () {
    $user = $this->userWithPermissions([['ROLE', 'READ']]);

    expect(Gate::forUser($user)->allows('ROLE:READ'))->toBeTrue();
});

test('Gate::before denies a RESOURCE:ACTION ability for a user who does not hold it (read action)', function () {
    $user = $this->userWithNoPermissions();

    expect(Gate::forUser($user)->denies('ROLE:READ'))->toBeTrue();
});

test('Gate::before does not intercept an ability that is not shaped like RESOURCE:ACTION, leaving normal Gate resolution to decide', function () {
    Gate::define('some-unrelated-ability', fn () => true);

    $user = $this->userWithNoPermissions();

    // If Gate::before treated this the same as a RESOURCE:ACTION ability it
    // would deny it (the user holds no permissions at all) - the fact that
    // it resolves true instead proves the `preg_match` guard is correctly
    // returning null (pass-through) rather than false (hard deny) for
    // abilities outside this project's own convention.
    expect(Gate::forUser($user)->allows('some-unrelated-ability'))->toBeTrue();
});

test('a write route protected by can:ROLE:WRITE denies (403) an authenticated user without it end to end', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->post('/roles', ['role_name' => 'SHOULD_NOT_BE_CREATED']);

    $response->assertForbidden();
});

test('a read route protected by can:ROLE:READ denies (403) an authenticated user without it end to end', function () {
    $user = $this->userWithNoPermissions();

    $response = $this->actingAs($user)->get('/roles');

    $response->assertForbidden();
});
