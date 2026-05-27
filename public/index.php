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

$base = dirname(__DIR__);

if (!is_file($base . '/config/installed.lock')) {
    header('Location: install/');
    exit;
}

require $base . '/src/bootstrap.php';

SysRevAI\Core\App::run();
