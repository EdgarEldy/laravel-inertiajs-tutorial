<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Store the visitor's chosen locale in session and redirect back to
     * whatever page the language switcher was clicked from - no page
     * reload beyond that single redirect, and no locale segment needed in
     * every route.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', SetLocale::SUPPORTED_LOCALES)],
        ]);

        session(['locale' => $request->string('locale')->toString()]);

        return back();
    }
}
