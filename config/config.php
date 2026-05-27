<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Application configuration
|------------------------------------------------------------------------------
| Returns the runtime configuration as an array, sourced from environment
| variables (loaded from .env by the bootstrap). Values here are only the
| low-level bootstrap settings; everything else (API keys, integrations,
| feature toggles) lives encrypted in the `settings` table and is read through
| the setting() helper.
|
| env() is defined in src/Helpers/functions.php.
*/

return [
    'app' => [
        'name'      => env('APP_NAME', 'SysRevAI'),
        'env'       => env('APP_ENV', 'production'),
        'debug'     => (bool) env('APP_DEBUG', false),
        'url'       => env('APP_URL', 'http://localhost'),
        'timezone'  => env('APP_TIMEZONE', 'Europe/Madrid'),
        'locale'    => env('APP_LOCALE', 'ca'),
        'key'       => env('APP_KEY', ''),
        'version'   => '0.1.0-dev',
    ],

    'database' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'sysrevai'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => env('DB_CHARSET', 'utf8mb4'),
        'prefix'   => env('DB_PREFIX', 'sra_'),
    ],

    'security' => [
        'force_https'      => (bool) env('FORCE_HTTPS', false),
        'session_lifetime' => (int) env('SESSION_LIFETIME', 120),
    ],

    'paths' => [
        'base'        => dirname(__DIR__),
        'storage'     => dirname(__DIR__) . '/storage',
        'logs'        => dirname(__DIR__) . '/storage/logs',
        'cache'       => dirname(__DIR__) . '/storage/cache',
        'credentials' => dirname(__DIR__) . '/storage/credentials',
        'backups'     => dirname(__DIR__) . '/storage/backups',
        'uploads'     => dirname(__DIR__) . '/public/uploads',
        'migrations'  => dirname(__DIR__) . '/database/migrations',
    ],

    'supported_locales' => ['ca', 'es', 'en', 'fr', 'de', 'pt', 'it', 'eu', 'gl'],
];
