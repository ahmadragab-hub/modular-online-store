<?php

namespace App\Http\Controllers\Web\Storefront;

use App\Domains\Store\Queries\ListDisplayEnabledCurrenciesQuery;
use App\Domains\Store\Queries\ResolveDisplayCurrencyCodeQuery;
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

    public function displayCurrency(
        Request $request,
        ResolveDisplayCurrencyCodeQuery $resolveDisplayCurrencyCodeQuery,
        ListDisplayEnabledCurrenciesQuery $listDisplayEnabledCurrenciesQuery,
    ): RedirectResponse {
        $enabledCodes = $listDisplayEnabledCurrenciesQuery->execute()
            ->pluck('code')
            ->map(fn (mixed $code): string => (string) $code)
            ->all();

        $validated = $request->validate([
            'display_currency_code' => ['required', 'string', 'size:3', Rule::in($enabledCodes)],
        ]);

        $currency = $resolveDisplayCurrencyCodeQuery->resolve($validated['display_currency_code']);

        $request->session()->put(StorefrontPreferences::SESSION_DISPLAY_CURRENCY, $currency);

        return redirect()
            ->back()
            ->withCookie(cookie(
                StorefrontPreferences::COOKIE_DISPLAY_CURRENCY,
                $currency,
                60 * 24 * 365,
            ));
    }
}
