<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Record a single audit log entry for a sensitive mutation. The acting
     * user is read from the current authentication context rather than
     * passed in, since every caller of this method runs within an
     * authenticated, permission-checked request.
     */
    public function log(string $action, string $entityType, int $entityId, array $details = []): void
    {
        AuditLog::create([
            'actor_user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
        ]);
    }
}
