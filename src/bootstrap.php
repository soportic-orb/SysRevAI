<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Application bootstrap
|------------------------------------------------------------------------------
| Sets up autoloading, environment and error handling. Required by the front
| controller before anything else. Kept framework-free and resilient: the app
| still boots (to surface a useful error) even if `composer install` has not
| been run, by falling back to a native PSR-4 autoloader and .env loader.
*/

define('SYSREVAI_BASE', dirname(__DIR__));

// 1. Composer autoloader (third-party deps) when available.
$vendorAutoload = SYSREVAI_BASE . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

// 2. Native PSR-4 autoloader for our own namespace (always registered; cheap
//    and ensures core classes load even without Composer).
spl_autoload_register(static function (string $class): void {
    $prefix = 'SysRevAI\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = SYSREVAI_BASE . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 3. Global helpers (Composer "files" autoload covers this when vendor exists).
if (!function_exists('env')) {
    require SYSREVAI_BASE . '/src/Helpers/functions.php';
}

// 4. Environment variables (.env). Prefer phpdotenv if present.
if (class_exists(\Dotenv\Dotenv::class)) {
    \Dotenv\Dotenv::createImmutable(SYSREVAI_BASE)->safeLoad();
} else {
    \SysRevAI\Core\Env::load(SYSREVAI_BASE . '/.env');
}

// 5. Error handling & timezone.
$debug = (bool) config('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', config('paths.logs') . '/php-error.log');
date_default_timezone_set((string) config('app.timezone', 'Europe/Madrid'));
