<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Global helper functions
|------------------------------------------------------------------------------
| Autoloaded via composer "files". Keep this lean: only truly global helpers
| belong here. Anything stateful should live in a Core class.
*/

if (!function_exists('env')) {
    /**
     * Read an environment variable with an optional default, normalising the
     * common string literals ("true", "false", "null", "") to native types.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}

if (!function_exists('e')) {
    /**
     * Escape a string for safe HTML output.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    /**
     * Dot-notation access to the config array, e.g. config('database.host').
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require dirname(__DIR__, 2) . '/config/config.php';
        }

        $value = $config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('setting')) {
    /**
     * Read a runtime setting from the `settings` table (DB-backed, cached in
     * the Config singleton). Sensitive values are transparently decrypted.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return \SysRevAI\Core\Config::get($key, $default);
    }
}

if (!function_exists('__')) {
    /** Translate a core i18n key (dot notation), with optional sprintf args. */
    function __(string $key, mixed ...$args): string
    {
        return \SysRevAI\Core\I18n::get($key, $args);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return \SysRevAI\Core\Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \SysRevAI\Core\Csrf::token();
    }
}

if (!function_exists('redirect')) {
    /** Send a Location redirect and stop execution. */
    function redirect(string $to, int $status = 302): never
    {
        header('Location: ' . $to, true, $status);
        exit;
    }
}

if (!function_exists('base_url')) {
    /** Absolute base URL of the application, without a trailing slash. */
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return \SysRevAI\Core\Auth::user();
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return \SysRevAI\Core\I18n::locale();
    }
}

if (!function_exists('accent_color')) {
    /** Configured accent (#rrggbb), falls back to platform default. */
    function accent_color(): string
    {
        $v = (string) (setting('ui.accent_color') ?? '#c9f24c');
        return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : '#c9f24c';
    }
}

if (!function_exists('darken_hex')) {
    /** Return a darkened #rrggbb (percent 0–100). */
    function darken_hex(string $hex, int $percent): string
    {
        if (!preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
            return $hex;
        }
        $factor = max(0, min(100, $percent)) / 100;
        $r = (int) round(hexdec(substr($m[1], 0, 2)) * (1 - $factor));
        $g = (int) round(hexdec(substr($m[1], 2, 2)) * (1 - $factor));
        $b = (int) round(hexdec(substr($m[1], 4, 2)) * (1 - $factor));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}

if (!function_exists('on_color_text')) {
    /** Pick a readable text color (#000 / #fff) for the given #rrggbb. */
    function on_color_text(string $hex): string
    {
        if (!preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
            return '#ffffff';
        }
        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));
        // Perceived luminance (Rec. 601).
        $l = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $l > 0.6 ? '#1b2733' : '#ffffff';
    }
}
