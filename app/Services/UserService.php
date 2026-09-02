<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Services\Concerns\LogsAuditEvents;
use Illuminate\Validation\ValidationException;

class UserService
{
    use LogsAuditEvents;

    public function assignRoleToUser(User $user, Role $role): void
    {
        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->logAssigned('User', $user->id, [
            'role_id' => $role->id,
            'role_name' => $role->role_name,
        ]);
    }

    /**
     * Applies the same last-admin check as `RoleService::removePermissionFromRole()`,
     * but from the user's side: a self-lockout must be caught however it's
     * attempted, whether by removing the permission from the role or by
     * removing the role from the last user who has it. Rejects if removing
     * this role from this user would leave zero users anywhere holding a
     * role that grants `ROLE:WRITE` - checking both this user's other roles
     * and every other user's roles.
     *
     * Two separate queries are needed (rather than one combined check)
     * because there are two independent ways this user could still end up
     * with `ROLE:WRITE` access after this removal: through one of their
     * *other* roles, or because some other user entirely still holds a
     * role that grants it. Either one alone is enough to make the removal
     * safe; only when both are false is there truly nobody left who could
     * administer roles/permissions - the exact scenario this guards
     * against, mirroring the risk `RoleService::removePermissionFromRole()`
     * guards against from the opposite direction.
     */
    public function removeRoleFromUser(User $user, Role $role): void
    {
        if ($this->grantsRoleWrite($role) && ! $this->userRetainsRoleWrite($user, $role) && ! $this->anotherUserHasRoleWrite($user)) {
            throw ValidationException::withMessages([
                'role' => 'Removing this role would leave no user anywhere with ROLE:WRITE access.',
            ]);
        }

        $user->roles()->detach($role->id);

        $this->logRemoved('User', $user->id, [
            'role_id' => $role->id,
            'role_name' => $role->role_name,
        ]);
    }

    protected function grantsRoleWrite(Role $role): bool
    {
        return $role->permissions()->where('resource', 'ROLE')->where('action', 'WRITE')->exists();
    }

    /**
     * Whether this user retains ROLE:WRITE through a role other than the
     * one being removed.
     */
    protected function userRetainsRoleWrite(User $user, Role $role): bool
    {
        return $user->roles()
            ->where('roles.id', '!=', $role->id)
            ->whereHas('permissions', function ($query) {
                $query->where('resource', 'ROLE')->where('action', 'WRITE');
            })
            ->exists();
    }

    /**
     * Whether any other user holds ROLE:WRITE through any role.
     */
    protected function anotherUserHasRoleWrite(User $user): bool
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->whereHas('roles.permissions', function ($query) {
                $query->where('resource', 'ROLE')->where('action', 'WRITE');
            })
            ->exists();
    }
}
