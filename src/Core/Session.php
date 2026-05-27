<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Secure session bootstrap for the application (distinct from the installer's
 * isolated session).
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure   = (bool) config('security.force_https', false);
        $lifetime = (int) config('security.session_lifetime', 120) * 60;

        session_name('sysrevai');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $secure,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_start();

        self::enforceIdleTimeout($lifetime);
    }

    private static function enforceIdleTimeout(int $lifetime): void
    {
        $now  = time();
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last !== null && ($now - (int) $last) > $lifetime) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** One-shot flash message helpers. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}
