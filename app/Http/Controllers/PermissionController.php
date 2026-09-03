<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`store`/`update`/`destroy` on the permission catalog only.
 * Deliberately has no route/method for assigning a permission to a role -
 * that lives on `RoleController` (`POST /roles/{role}/permissions/{permission}`),
 * since assignment is always managed from the `Roles` pages, never here.
 */
class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $permissions = Permission::query()
            ->withCount('roles')
            ->when($search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('resource', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            }))
            ->orderBy('resource')
            ->orderBy('action')
            ->paginate(10)
            ->withQueryString();

        // See RoleController::index() for why `through()` is used instead
        // of `PermissionResource::collection()` on a paginator.
        $permissions->through(fn (Permission $permission) => (new PermissionResource($permission))->resolve());

        return Inertia::render('Permissions/Index', [
            'permissions' => $permissions,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(StorePermissionRequest $request, PermissionService $permissionService): RedirectResponse
    {
        $permissionService->createPermission($request->validated());

        return redirect()->route('permissions.index')->with('flash.banner', 'Permission created.');
    }

    public function update(UpdatePermissionRequest $request, Permission $permission, PermissionService $permissionService): RedirectResponse
    {
        $permissionService->updatePermission($permission, $request->validated());

        return redirect()->route('permissions.index')->with('flash.banner', 'Permission updated.');
    }

    public function destroy(Permission $permission, PermissionService $permissionService): RedirectResponse
    {
        $permissionService->deletePermission($permission);

        return redirect()->route('permissions.index')->with('flash.banner', 'Permission deleted.');
    }
}
