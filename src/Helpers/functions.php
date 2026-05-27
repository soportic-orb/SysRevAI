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
     * Read a runtime setting from the `settings` table (DB-backed, cached).
     *
     * Placeholder until the Config singleton lands in Phase 2; returns the
     * provided default so early code can reference settings safely.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return $default;
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
