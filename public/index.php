<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Front controller
|------------------------------------------------------------------------------
| If the platform is not installed yet (no config/installed.lock), redirect to
| the guided web installer. Otherwise boot the application and dispatch the
| request through the router.
*/

// When running under the PHP built-in server (development), let it serve real
// files and directories (assets, the installer) directly. No effect under
// Apache/Nginx, where the web server handles static files itself.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $target = __DIR__ . $path;
    if ($path !== '/' && (is_file($target) || is_dir($target))) {
        return false;
    }
}

$base = dirname(__DIR__);

if (!is_file($base . '/config/installed.lock')) {
    header('Location: install/');
    exit;
}

require $base . '/src/bootstrap.php';

SysRevAI\Core\App::run();
