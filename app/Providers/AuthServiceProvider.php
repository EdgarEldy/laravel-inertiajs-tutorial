<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPermissionGate();
    }

    /**
     * A single `Gate::before` hook wires this project's resource/action
     * permissions into every one of Laravel's usual authorization surfaces
     * (route middleware, `$this->authorize()`, Vue-side `can()` checks) at
     * once. Any ability that isn't shaped like `RESOURCE:ACTION` falls
     * through untouched to normal Gate/Policy resolution.
     */
    protected function registerPermissionGate(): void
    {
        Gate::before(function (User $user, string $ability) {
            // `Gate::before` runs for *every* ability check anywhere in the
            // app, including ones this project doesn't own (Jetstream's
            // own `create`/`update`/`delete` team abilities, for example).
            // Only intercept abilities that actually look like our own
            // `RESOURCE:ACTION` convention, and return `null` (not `false`)
            // for anything else - `null` tells Laravel "no opinion, keep
            // resolving normally", whereas `false` would hard-deny an
            // ability this hook was never meant to judge. This one check
            // is also what makes every other authorization surface "just
            // work" without extra wiring: middleware's `can:X`, controller
            // `$this->authorize('X')`, and `Gate::check('X')` all funnel
            // through the same `Gate::before` hook internally.
            if (! preg_match('/^[A-Z_]+:[A-Z_]+$/', $ability)) {
                return null;
            }

            [$resource, $action] = explode(':', $ability, 2);

            return $user->hasPermission($resource, $action);
        });
    }
}
