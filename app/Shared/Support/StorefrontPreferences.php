<?php

namespace App\Shared\Support;

use Illuminate\Support\Arr;

final class StorefrontPreferences
{
    public const SESSION_LOCALE = 'storefront.locale';

    public const SESSION_THEME = 'storefront.theme';

    public const COOKIE_LOCALE = 'storefront_locale';

    public const COOKIE_THEME = 'storefront_theme';

    public const SESSION_DISPLAY_CURRENCY = 'storefront.display_currency';

    public const COOKIE_DISPLAY_CURRENCY = 'storefront_display_currency';

    /**
     * @return array<string, array{label: string, native?: string, dir: string, html: string}>
     */
    public static function locales(): array
    {
        return config('localization.locales', []);
    }

    /**
     * @return list<string>
     */
    public static function localeKeys(): array
    {
        return array_keys(self::locales());
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
     * @return array<string, array{label: string}>
     */
    public static function themes(): array
    {
        return config('theme.themes', []);
    }

    /**
     * @return list<string>
     */
    public static function themeKeys(): array
    {
        return array_keys(self::themes());
    }

    public static function defaultTheme(): string
    {
        $default = (string) config('theme.default', 'light');

        return Arr::has(self::themes(), $default) ? $default : 'light';
    }

    public static function isValidTheme(string $theme): bool
    {
        return Arr::has(self::themes(), $theme);
    }
}
