<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Core internationalization. Loads plain-PHP locale files from /lang and
 * resolves dot-notation keys. A later phase layers DB-backed overrides on top.
 */
final class I18n
{
    private static string $locale = 'ca';
    private static string $fallback = 'ca';
    /** @var array<string,array> */
    private static array $loaded = [];

    public static function setLocale(string $locale): void
    {
        $allowed = (array) config('supported_locales', ['ca', 'es', 'en']);
        self::$locale = in_array($locale, $allowed, true) ? $locale : self::$fallback;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function get(string $key, array $args = []): string
    {
        $value = self::lookup(self::$locale, $key);
        if ($value === null && self::$locale !== self::$fallback) {
            $value = self::lookup(self::$fallback, $key);
        }
        if ($value === null) {
            return $key;
        }
        return $args === [] ? $value : vsprintf($value, $args);
    }

    private static function lookup(string $locale, string $key): ?string
    {
        $data = self::loadLocale($locale);
        $value = $data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }
        return is_string($value) ? $value : null;
    }

    private static function loadLocale(string $locale): array
    {
        if (!isset(self::$loaded[$locale])) {
            $file = config('paths.base') . "/lang/{$locale}.php";
            self::$loaded[$locale] = is_file($file) ? (array) require $file : [];
        }
        return self::$loaded[$locale];
    }
}
