<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Concerns\LogsAuditEvents;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for roles plus permission-on-role assignment. Deliberately has no
 * method to assign/remove a role on a user - that assignment is owned
 * entirely by `UserService` (see `assignRoleToUser()`/`removeRoleFromUser()`),
 * since roles are managed from the `Users` pages, never the reverse. The
 * `User $user` import below is used only for the last-admin lockout query
 * in `removePermissionFromRole()`, not for any user-assignment method.
 */
class RoleService
{
    use LogsAuditEvents;

    public function createRole(array $data): Role
    {
        $role = Role::create([
            'role_name' => $data['role_name'],
        ]);

        $this->logCreated('Role', $role->id, ['role_name' => $role->role_name]);

        return $role;
    }

    public function updateRole(Role $role, array $data): Role
    {
        $role->update([
            'role_name' => $data['role_name'],
        ]);

        $this->logUpdated('Role', $role->id, ['role_name' => $role->role_name]);

        return $role;
    }

    /**
     * Rejects deletion if any user is still assigned this role - it must be
     * unassigned from every user first.
     */
    public function deleteRole(Role $role): void
    {
        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'This role is still assigned to at least one user and cannot be deleted.',
            ]);
        }

        $roleId = $role->id;
        $roleName = $role->role_name;

        $role->delete();

        $this->logDeleted('Role', $roleId, ['role_name' => $roleName]);
    }

    public function assignPermissionToRole(Role $role, Permission $permission): void
    {
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        $this->logAssigned('Role', $role->id, [
            'permission_id' => $permission->id,
            'permission' => "{$permission->resource}:{$permission->action}",
        ]);
    }

    /**
     * Rejects removing `ROLE:WRITE` from a role if doing so would leave
     * zero users anywhere holding a role that grants `ROLE:WRITE` - the
     * role being edited is only one of possibly several sources, so every
     * other role granting it is checked too.
     *
     * This exists because a self-lockout can be triggered from either
     * direction: revoking `ROLE:WRITE` from a role here, or (see
     * `UserService::removeRoleFromUser()`) detaching a role from the last
     * user who holds it. Guarding only one direction would leave the other
     * as an open door to an application nobody can administer anymore -
     * no user left able to grant roles/permissions to anyone, including
     * themselves, with no console-only escape hatch assumed to exist.
     * "Zero users anywhere" (not just users of this one role) is the
     * actual invariant being protected, since another role might grant the
     * same permission to a different user.
     */
    public function removePermissionFromRole(Role $role, Permission $permission): void
    {
        if ($this->grantsRoleWrite($permission) && ! $this->anotherRoleGrantsRoleWrite($role)) {
            throw ValidationException::withMessages([
                'permission' => 'Removing this permission would leave no user anywhere with ROLE:WRITE access.',
            ]);
        }

        $role->permissions()->detach($permission->id);

        $this->logRemoved('Role', $role->id, [
            'permission_id' => $permission->id,
            'permission' => "{$permission->resource}:{$permission->action}",
        ]);
    }

    protected function grantsRoleWrite(Permission $permission): bool
    {
        return $permission->resource === 'ROLE' && $permission->action === 'WRITE';
    }

    /**
     * Whether any user still holds ROLE:WRITE through a role other than
     * the one being edited.
     */
    protected function anotherRoleGrantsRoleWrite(Role $role): bool
    {
        return User::query()
            ->whereHas('roles', function ($query) use ($role) {
                $query->where('roles.id', '!=', $role->id)
                    ->whereHas('permissions', function ($query) {
                        $query->where('resource', 'ROLE')->where('action', 'WRITE');
                    });
            })
            ->exists();
    }
}
