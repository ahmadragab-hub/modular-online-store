<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Shared\Support\AdminPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPreferenceController extends Controller
{
    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(AdminPreferences::locales()))],
        ]);

        $locale = $validated['locale'];
        $request->session()->put(AdminPreferences::SESSION_LOCALE, $locale);

        return redirect()
            ->back()
            ->withCookie(cookie(AdminPreferences::COOKIE_LOCALE, $locale, 60 * 24 * 365));
    }

    public function theme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(AdminPreferences::themes())],
        ]);

        $theme = $validated['theme'];
        $request->session()->put(AdminPreferences::SESSION_THEME, $theme);

        return redirect()
            ->back()
            ->withCookie(cookie(AdminPreferences::COOKIE_THEME, $theme, 60 * 24 * 365));
    }
}
