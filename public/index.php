<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Front controller (bootstrap stub)
|------------------------------------------------------------------------------
| The full MVC router lands in Phase 1. For now this entry point performs the
| "is the platform installed?" check described in the spec: if there is no
| config/installed.lock, every request to the root is redirected to the guided
| web installer.
*/

$lockFile = dirname(__DIR__) . '/config/installed.lock';

if (!is_file($lockFile)) {
    header('Location: install/');
    exit;
}

// Installed, but the application router is not implemented yet (later phase).
http_response_code(503);
header('Content-Type: text/plain; charset=utf-8');
echo "SysRevAI is installed. The application will be available once the core "
   . "router lands in an upcoming build phase.";
