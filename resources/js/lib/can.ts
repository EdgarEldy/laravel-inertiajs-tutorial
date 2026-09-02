import { usePage } from '@inertiajs/vue3';

/**
 * UI convenience only - reads the `auth.permissions` list shared globally by
 * `ShareAuthPermissions` (see that middleware for why the permission list
 * isn't shared from `HandleInertiaRequests` directly). This never replaces
 * the server-side `Gate::before` check: every route is still protected by
 * `->middleware('can:RESOURCE:ACTION')` regardless of what this returns, so
 * hiding a button here only avoids showing a control the backend would
 * reject anyway - it is not the actual security boundary.
 */
export function can(permission: string): boolean {
    const page = usePage<{ auth?: { permissions?: string[] } }>();

    return page.props.auth?.permissions?.includes(permission) ?? false;
}
