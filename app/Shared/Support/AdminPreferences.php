<?php

namespace App\Shared\Support;

use Illuminate\Support\Arr;

final class AdminPreferences
{
    public const SESSION_LOCALE = 'admin.locale';

    public const SESSION_THEME = 'admin.theme';

    public const COOKIE_LOCALE = 'admin_locale';

    public const COOKIE_THEME = 'admin_theme';

    /**
     * @return array<string, array{label: string, native?: string, dir: string, html: string}>
     */
    public static function locales(): array
    {
        return config('localization.locales', []);
    }

    public static function defaultLocale(): string
    {
        $default = (string) config('localization.default', 'en');

        return Arr::has(self::locales(), $default) ? $default : 'en';
    }

    public static function isValidLocale(string $locale): bool
    {
        return Arr::has(self::locales(), $locale);
    }

    public static function localeMeta(string $locale): array
    {
        return self::locales()[$locale] ?? self::locales()[self::defaultLocale()];
    }

    public static function textDirection(string $locale): string
    {
        return self::localeMeta($locale)['dir'] ?? 'ltr';
    }

    public static function htmlLang(string $locale): string
    {
        return self::localeMeta($locale)['html'] ?? $locale;
    }

    /**
     * @return list<string>
     */
    public static function themes(): array
    {
        return config('localization.themes', ['light', 'dark']);
    }

    public static function defaultTheme(): string
    {
        return (string) config('localization.default_theme', 'light');
    }

    public static function isValidTheme(string $theme): bool
    {
        return in_array($theme, self::themes(), true);
    }
}
