<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Per-session CSRF protection for application forms.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION[self::KEY])
            && hash_equals($_SESSION[self::KEY], $token);
    }

    /**
     * Abort the request with 419 if the submitted token is invalid.
     * Called by the router for every state-changing (POST/PUT/DELETE) request.
     */
    public static function check(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::verify(is_string($token) ? $token : null)) {
            http_response_code(419);
            header('Content-Type: text/plain; charset=utf-8');
            exit('419 — CSRF token mismatch. Please reload the page and try again.');
        }
    }
}
