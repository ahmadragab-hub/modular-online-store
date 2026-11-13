<?php

namespace App\Http\Middleware;

use App\Shared\Support\StorefrontPreferences;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ApplyStorefrontPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        $theme = $this->resolveTheme($request);

        App::setLocale($locale);

        View::share([
            'storefrontLocale' => $locale,
            'storefrontTheme' => $theme,
            'storefrontTextDirection' => StorefrontPreferences::textDirection($locale),
            'storefrontHtmlLang' => StorefrontPreferences::htmlLang($locale),
            'storefrontLocales' => StorefrontPreferences::locales(),
            'storefrontThemes' => StorefrontPreferences::themes(),
        ]);

        $request->attributes->set('storefront.locale', $locale);
        $request->attributes->set('storefront.theme', $theme);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $sessionLocale = $request->hasSession()
            ? $request->session()->get(StorefrontPreferences::SESSION_LOCALE)
            : null;

        $candidates = [
            $sessionLocale,
            $request->cookie(StorefrontPreferences::COOKIE_LOCALE),
            StorefrontPreferences::defaultLocale(),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && StorefrontPreferences::isValidLocale($candidate)) {
                return $candidate;
            }
        }

        return StorefrontPreferences::defaultLocale();
    }

    private function resolveTheme(Request $request): string
    {
        $sessionTheme = $request->hasSession()
            ? $request->session()->get(StorefrontPreferences::SESSION_THEME)
            : null;

        $candidates = [
            $sessionTheme,
            $request->cookie(StorefrontPreferences::COOKIE_THEME),
            StorefrontPreferences::defaultTheme(),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && StorefrontPreferences::isValidTheme($candidate)) {
                return $candidate;
            }
        }

        return StorefrontPreferences::defaultTheme();
    }
}
