<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`store`/`update`/`destroy` on roles, plus permission-on-role
 * assignment. Deliberately has no route/method for assigning a role to a
 * user - that lives on `UserController` (`POST /users/{user}/roles/{role}`),
 * since assignment is always managed from the `Users` pages, never here.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $roles = Role::query()
            ->withCount('users')
            ->when($search, fn ($query, $search) => $query->where('role_name', 'like', "%{$search}%"))
            ->orderBy('role_name')
            ->paginate(10)
            ->withQueryString();

        // `through()` transforms each item while keeping the paginator's
        // own shape (`data`, `links`, `meta`) - a `Resource::collection()`
        // wrapped around a paginator would lose that pagination metadata,
        // since it's only added by `JsonResource::toResponse()`, not
        // `toArray()`, and Inertia props only ever go through the latter.
        $roles->through(fn (Role $role) => (new RoleResource($role))->resolve());

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StoreRoleRequest $request, RoleService $roleService): RedirectResponse
    {
        $roleService->createRole($request->validated());

        return redirect()->route('roles.index')->with('flash.banner', 'Role created.');
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleService $roleService): RedirectResponse
    {
        $roleService->updateRole($role, $request->validated());

        return redirect()->route('roles.index')->with('flash.banner', 'Role updated.');
    }

    public function destroy(Role $role, RoleService $roleService): RedirectResponse
    {
        $roleService->deleteRole($role);

        return redirect()->route('roles.index')->with('flash.banner', 'Role deleted.');
    }

    /**
     * Dedicated permission-assignment page, deliberately separate from the
     * name-only edit modal on `Roles/Index.vue`.
     */
    public function permissions(Role $role): Response
    {
        $role->load('permissions');

        // `->resolve()` is required here (and can't just pass the Resource
        // instance to `Inertia::render()` directly): `JsonResource`
        // implements `Responsable`, and Inertia calls `toResponse()` on any
        // `Responsable` prop, which wraps the array in a `data` key - fine
        // for a real HTTP response, wrong for a prop this page expects to
        // read directly as `role`/`permissions`.
        return Inertia::render('Roles/Permissions', [
            'role' => (new RoleResource($role))->resolve(),
            'permissions' => PermissionResource::collection(
                Permission::orderBy('resource')->orderBy('action')->get()
            )->resolve(),
        ]);
    }

    public function assignPermission(Role $role, Permission $permission, RoleService $roleService): RedirectResponse
    {
        $roleService->assignPermissionToRole($role, $permission);

        return redirect()->route('roles.permissions', $role)->with('flash.banner', 'Permission assigned.');
    }

    public function removePermission(Role $role, Permission $permission, RoleService $roleService): RedirectResponse
    {
        $roleService->removePermissionFromRole($role, $permission);

        return redirect()->route('roles.permissions', $role)->with('flash.banner', 'Permission removed.');
    }
}
