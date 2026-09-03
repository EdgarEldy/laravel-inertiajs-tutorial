<?php

namespace App\Services\Concerns;

use App\Services\AuditLogger;

/**
 * Shared `log*` helpers over `AuditLogger::log()`, mixed into every RBAC
 * service (`RoleService`, `PermissionService`, `UserService`) so all three
 * record audit entries the same way instead of each hand-rolling its own
 * `AuditLogger::log(...)` calls.
 *
 * A trait (not a base class) is deliberate: these three services have no
 * other shared behavior or hierarchy to justify inheritance, only this one
 * cross-cutting concern. Without it, every service would be free to invent
 * its own `action` strings for the same kind of event ("created" vs "new"
 * vs "add"), which would quietly break anything reading `audit_logs.action`
 * later (a report, an alert, a filter) expecting a consistent vocabulary.
 */
trait LogsAuditEvents
{
    protected function auditLogger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    protected function logCreated(string $entityType, int $entityId, array $details = []): void
    {
        $this->auditLogger()->log('created', $entityType, $entityId, $details);
    }

    protected function logUpdated(string $entityType, int $entityId, array $details = []): void
    {
        $this->auditLogger()->log('updated', $entityType, $entityId, $details);
    }

    protected function logDeleted(string $entityType, int $entityId, array $details = []): void
    {
        $this->auditLogger()->log('deleted', $entityType, $entityId, $details);
    }

    protected function logAssigned(string $entityType, int $entityId, array $details = []): void
    {
        $this->auditLogger()->log('assigned', $entityType, $entityId, $details);
    }

    protected function logRemoved(string $entityType, int $entityId, array $details = []): void
    {
        $this->auditLogger()->log('removed', $entityType, $entityId, $details);
    }
}
