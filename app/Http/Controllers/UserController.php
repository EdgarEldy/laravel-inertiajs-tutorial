<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `index`/`show` only - user creation stays exclusively Jetstream's
 * registration flow. This controller manages role assignment on existing
 * users, never a "create user" flow of its own.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $users = User::query()
            ->with('roles')
            ->when($search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        // See RoleController::index() for why `through()` is used instead
        // of `UserResource::collection()` on a paginator.
        $users->through(fn (User $user) => (new UserResource($user))->resolve());

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(User $user): Response
    {
        $user->load('roles.permissions');

        // `->resolve()` is required here - see RoleController::permissions()
        // for why a bare `JsonResource`/`ResourceCollection` can't be handed
        // to `Inertia::render()` directly.
        return Inertia::render('Users/Show', [
            'user' => (new UserResource($user))->resolve(),
            'availableRoles' => RoleResource::collection(Role::orderBy('role_name')->get())->resolve(),
        ]);
    }

    public function assignRole(User $user, Role $role, UserService $userService): RedirectResponse
    {
        $userService->assignRoleToUser($user, $role);

        return redirect()->route('users.show', $user)->with('flash.banner', 'Role assigned.');
    }

    public function removeRole(User $user, Role $role, UserService $userService): RedirectResponse
    {
        $userService->removeRoleFromUser($user, $role);

        return redirect()->route('users.show', $user)->with('flash.banner', 'Role removed.');
    }
}
