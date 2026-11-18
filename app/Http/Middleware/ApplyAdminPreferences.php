<?php

namespace App\Http\Middleware;

use App\Shared\Support\AdminPreferences;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class ApplyAdminPreferences
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        $theme = $this->resolveTheme($request);

        App::setLocale($locale);

        View::share([
            'adminLocale' => $locale,
            'adminTheme' => $theme,
            'adminTextDirection' => AdminPreferences::textDirection($locale),
            'adminHtmlLang' => AdminPreferences::htmlLang($locale),
            'adminLocales' => AdminPreferences::locales(),
            'adminThemes' => AdminPreferences::themes(),
        ]);

        $request->attributes->set('admin.locale', $locale);
        $request->attributes->set('admin.theme', $theme);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $candidates = [
            $request->hasSession() ? $request->session()->get(AdminPreferences::SESSION_LOCALE) : null,
            $request->cookie(AdminPreferences::COOKIE_LOCALE),
            AdminPreferences::defaultLocale(),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && AdminPreferences::isValidLocale($candidate)) {
                return $candidate;
            }
        }

        return AdminPreferences::defaultLocale();
    }

    private function resolveTheme(Request $request): string
    {
        $candidates = [
            $request->hasSession() ? $request->session()->get(AdminPreferences::SESSION_THEME) : null,
            $request->cookie(AdminPreferences::COOKIE_THEME),
            AdminPreferences::defaultTheme(),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && AdminPreferences::isValidTheme($candidate)) {
                return $candidate;
            }
        }

        return AdminPreferences::defaultTheme();
    }
}
