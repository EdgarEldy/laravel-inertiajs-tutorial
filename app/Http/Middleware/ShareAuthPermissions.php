<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the authenticated user's resource/action permissions with every
 * Inertia page as `auth.permissions`, for the small `can()` UI helper only
 * - the server-side `Gate::before` check is what's actually authoritative.
 *
 * This lives in its own middleware, attached after Jetstream's own
 * `ShareInertiaData` (which shares `auth.user`), rather than inside
 * `HandleInertiaRequests`: Jetstream's middleware sets `auth` as a plain
 * array, and Inertia's `share()` merges plain arrays at the top level, so
 * whichever of the two middleware runs last would silently overwrite the
 * other's `auth` contribution instead of merging with it. Running after
 * `ShareInertiaData` and setting the value with a dot-notation key merges
 * `permissions` alongside the existing `user` key instead of replacing it.
 */
class ShareAuthPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('auth.permissions', function () use ($request) {
            $user = $request->user();

            if (! $user) {
                return [];
            }

            return $user->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->map(fn ($permission) => "{$permission->resource}:{$permission->action}")
                ->unique()
                ->values();
        });

        return $next($request);
    }
}
