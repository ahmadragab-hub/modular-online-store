<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Http\Controllers\Controller;
use App\Shared\Support\StorefrontPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(StorefrontPreferences::localeKeys())],
        ]);

        $locale = $validated['locale'];

        $request->session()->put(StorefrontPreferences::SESSION_LOCALE, $locale);

        return redirect()
            ->back()
            ->withCookie(cookie(
                StorefrontPreferences::COOKIE_LOCALE,
                $locale,
                60 * 24 * 365,
            ));
    }

    public function theme(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(StorefrontPreferences::themeKeys())],
        ]);

        $theme = $validated['theme'];

        $request->session()->put(StorefrontPreferences::SESSION_THEME, $theme);

        return redirect()
            ->back()
            ->withCookie(cookie(
                StorefrontPreferences::COOKIE_THEME,
                $theme,
                60 * 24 * 365,
            ));
    }
}
