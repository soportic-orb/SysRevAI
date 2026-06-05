<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Core internationalization. Locale arrays live in /lang/{code}.php; DB-backed
 * overrides (TranslationOverride model) are layered on top so admins can edit
 * any string from the UI without touching the codebase. Custom locales the
 * admin adds via the Languages editor inherit only DB content — the
 * file-array lookup falls through to the fallback locale, exactly as it does
 * for any other missing key.
 */
final class I18n
{
    private static string $locale = 'ca';
    private static string $fallback = 'ca';
    /** @var array<string,array> */
    private static array $loaded = [];
    /** @var array<string,array<string,string>> locale => key_path => value */
    private static array $overrides = [];
    /** @var array<string,bool> locale => loaded? */
    private static array $overridesLoaded = [];

    public static function setLocale(string $locale): void
    {
        $allowed = self::allowedLocales();
        self::$locale = in_array($locale, $allowed, true) ? $locale : self::$fallback;
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    /**
     * Locales the runtime currently accepts. Static config defaults plus any
     * custom locales the admin registered via the editor.
     *
     * @return list<string>
     */
    public static function allowedLocales(): array
    {
        $base = (array) config('supported_locales', ['ca', 'es', 'en']);
        $custom = [];
        try {
            $custom = array_keys((array) (setting('ui.custom_locales') ?? []));
        } catch (\Throwable) {
            // Settings table not present yet (during install).
        }
        $merged = array_values(array_unique(array_merge($base, array_map('strval', $custom))));
        return $merged;
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

    /**
     * Flatten a nested locale array into a dot-notation map. Used by the
     * Languages editor so the admin sees every key the file ships with.
     *
     * @return array<string,string>
     */
    public static function fileMap(string $locale): array
    {
        $data = self::loadLocale($locale);
        $out = [];
        self::flatten($data, '', $out);
        return $out;
    }

    /** @return array<string,string> Current DB overrides for the locale. */
    public static function overrideMap(string $locale): array
    {
        self::ensureOverridesLoaded($locale);
        return self::$overrides[$locale] ?? [];
    }

    /**
     * Forget the cached overrides for a locale so the next lookup goes back
     * to the DB. Called after the admin saves through the editor.
     */
    public static function flushOverrides(?string $locale = null): void
    {
        if ($locale === null) {
            self::$overrides = [];
            self::$overridesLoaded = [];
            return;
        }
        unset(self::$overrides[$locale], self::$overridesLoaded[$locale]);
    }

    private static function lookup(string $locale, string $key): ?string
    {
        self::ensureOverridesLoaded($locale);
        if (array_key_exists($key, self::$overrides[$locale] ?? [])) {
            return self::$overrides[$locale][$key];
        }
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

    private static function ensureOverridesLoaded(string $locale): void
    {
        if (isset(self::$overridesLoaded[$locale])) {
            return;
        }
        self::$overridesLoaded[$locale] = true;
        self::$overrides[$locale] = [];
        try {
            $rows = \SysRevAI\Models\TranslationOverride::forLocale($locale);
            self::$overrides[$locale] = $rows;
        } catch (\Throwable) {
            // Table missing — quiet fail keeps the platform running.
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $out
     */
    private static function flatten(array $data, string $prefix, array &$out): void
    {
        foreach ($data as $k => $v) {
            $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                self::flatten($v, $path, $out);
            } elseif (is_string($v)) {
                $out[$path] = $v;
            }
        }
    }
}
