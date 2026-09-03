<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The application supports English and French. A visitor's chosen locale
 * is stored in the session (set by the language switcher, see the frontend
 * i18n wiring added in feature/jetstream-auth) so it persists across
 * requests without needing a locale segment in every URL. Falls back to
 * config('app.locale') for a request with nothing in session yet - a first
 * visit, or a guest whose session was flushed.
 */
class SetLocale
{
    public const SUPPORTED_LOCALES = ['en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale'));

        if (in_array($locale, self::SUPPORTED_LOCALES, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
