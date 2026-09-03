<?php

use App\Services\AuditLogger;
use App\Services\Concerns\LogsAuditEvents;
use Tests\TestCase;

// This test only needs the container (to swap the `AuditLogger` binding for
// a mock), not the full HTTP/routing stack a Feature test would boot - so it
// binds Laravel's TestCase directly here instead of relying on the global
// `pest()->extend(...)->in('Feature')` config, and deliberately skips
// `RefreshDatabase`: nothing here ever touches the database, since
// `AuditLogger::log()` itself is mocked away entirely.
uses(TestCase::class);

/**
 * A minimal stand-in for a real RBAC service: just enough to prove
 * `LogsAuditEvents`'s own helpers forward the right `action` string and
 * leave the rest of the payload untouched, without dragging a whole
 * `RoleService`/`PermissionService`/`UserService` (and their own unrelated
 * business rules) into what should be an isolated trait test.
 */
function makeAuditEventLogger(): object
{
    return new class
    {
        use LogsAuditEvents;

        public function run(string $helper, string $entityType, int $entityId, array $details = []): void
        {
            $this->{$helper}($entityType, $entityId, $details);
        }
    };
}

test('each LogsAuditEvents helper forwards its own fixed action string plus the exact entity/details payload', function (string $helper, string $expectedAction) {
    $mock = Mockery::mock(AuditLogger::class);
    $mock->shouldReceive('log')
        ->once()
        ->with($expectedAction, 'Role', 42, ['role_name' => 'ADMIN']);

    app()->instance(AuditLogger::class, $mock);

    makeAuditEventLogger()->run($helper, 'Role', 42, ['role_name' => 'ADMIN']);
})->with([
    'created' => ['logCreated', 'created'],
    'updated' => ['logUpdated', 'updated'],
    'deleted' => ['logDeleted', 'deleted'],
    'assigned' => ['logAssigned', 'assigned'],
    'removed' => ['logRemoved', 'removed'],
]);

test('every helper defaults details to an empty array when none is given', function (string $helper, string $expectedAction) {
    $mock = Mockery::mock(AuditLogger::class);
    $mock->shouldReceive('log')
        ->once()
        ->with($expectedAction, 'Permission', 7, []);

    app()->instance(AuditLogger::class, $mock);

    makeAuditEventLogger()->run($helper, 'Permission', 7);
})->with([
    'created' => ['logCreated', 'created'],
    'updated' => ['logUpdated', 'updated'],
    'deleted' => ['logDeleted', 'deleted'],
    'assigned' => ['logAssigned', 'assigned'],
    'removed' => ['logRemoved', 'removed'],
]);

test('the trait resolves AuditLogger fresh from the container on every call rather than caching an instance', function () {
    $first = Mockery::mock(AuditLogger::class);
    $first->shouldReceive('log')->once()->with('created', 'Role', 1, []);
    app()->instance(AuditLogger::class, $first);
    makeAuditEventLogger()->run('logCreated', 'Role', 1);

    $second = Mockery::mock(AuditLogger::class);
    $second->shouldReceive('log')->once()->with('updated', 'Role', 1, []);
    app()->instance(AuditLogger::class, $second);
    makeAuditEventLogger()->run('logUpdated', 'Role', 1);
});
