<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Application kernel: starts the session, resolves the active locale and
 * dispatches the request through the router defined in config/routes.php.
 */
final class App
{
    public static function run(): void
    {
        Session::start();
        self::resolveLocale();

        if ((bool) config('security.force_https', false) && !self::isSecure()) {
            redirect('https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
        }

        self::sendSecurityHeaders();

        /** @var Router $router */
        $router = require config('paths.base') . '/config/routes.php';
        $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    }

    private static function resolveLocale(): void
    {
        // Priority: logged-in user's locale → session choice → configured default.
        $locale = (string) config('app.locale', 'ca');
        if (($override = Session::get('_locale')) !== null) {
            $locale = (string) $override;
        }
        if (Auth::check() && ($user = Auth::user()) !== null && !empty($user['locale'])) {
            $locale = (string) $user['locale'];
        }
        I18n::setLocale($locale);
    }

    private static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if ((bool) config('security.force_https', false)) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
