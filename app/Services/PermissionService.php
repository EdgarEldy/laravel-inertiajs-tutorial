<?php

namespace App\Services;

use App\Models\Permission;
use App\Services\Concerns\LogsAuditEvents;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for the permission catalog only. Deliberately has no method to
 * assign/remove a permission on a role - that assignment is owned entirely
 * by `RoleService` (see `assignPermissionToRole()`/`removePermissionFromRole()`),
 * since permissions are managed from the `Roles` pages, never the reverse.
 * Do not add a role-management method here even though `Role` and
 * `Permission` are related - see "Assignment direction" in the README.
 */
class PermissionService
{
    use LogsAuditEvents;

    public function createPermission(array $data): Permission
    {
        $permission = Permission::create([
            'resource' => $data['resource'],
            'action' => $data['action'],
        ]);

        $this->logCreated('Permission', $permission->id, [
            'resource' => $permission->resource,
            'action' => $permission->action,
        ]);

        return $permission;
    }

    public function updatePermission(Permission $permission, array $data): Permission
    {
        $permission->update([
            'resource' => $data['resource'],
            'action' => $data['action'],
        ]);

        $this->logUpdated('Permission', $permission->id, [
            'resource' => $permission->resource,
            'action' => $permission->action,
        ]);

        return $permission;
    }

    /**
     * Rejects deletion if any role still has this permission assigned - it
     * must be removed from every role first.
     */
    public function deletePermission(Permission $permission): void
    {
        if ($permission->roles()->exists()) {
            throw ValidationException::withMessages([
                'permission' => 'This permission is still assigned to at least one role and cannot be deleted.',
            ]);
        }

        $permissionId = $permission->id;
        $details = ['resource' => $permission->resource, 'action' => $permission->action];

        $permission->delete();

        $this->logDeleted('Permission', $permissionId, $details);
    }
}
