<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Minimal .env loader.
 *
 * The bootstrap prefers vlucas/phpdotenv when vendor/ is present; this native
 * loader is the fallback so the application can still boot in minimal
 * environments (and during development before `composer install`).
 */
final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = self::unquote(trim($value));

            if ($key === '') {
                continue;
            }

            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }

    private static function unquote(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2) {
            $q = $value[0];
            if (($q === '"' || $q === "'") && $value[$len - 1] === $q) {
                $inner = substr($value, 1, -1);
                return $q === '"' ? str_replace('\"', '"', $inner) : $inner;
            }
        }
        return $value;
    }
}
