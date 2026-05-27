<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Application routes
|------------------------------------------------------------------------------
| Returns a configured Router. Middleware: 'guest' (only logged-out), 'auth'
| (only logged-in), 'admin' (owner/admin). The router enforces CSRF on all
| state-changing requests automatically.
*/

use SysRevAI\Controllers\Admin\SettingsController;
use SysRevAI\Controllers\AuthController;
use SysRevAI\Controllers\DashboardController;
use SysRevAI\Core\Auth;
use SysRevAI\Core\Router;

$router = new Router();

$router->get('/', static fn () => redirect(Auth::check() ? '/dashboard' : '/login'));

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// Admin → Settings (owner/admin only). The verify route is registered before
// the generic save route for clarity (distinct paths either way).
$router->get('/admin/settings', [SettingsController::class, 'index'], ['auth', 'admin']);
$router->get('/admin/settings/{section}', [SettingsController::class, 'show'], ['auth', 'admin']);
$router->post('/admin/settings/claude/verify', [SettingsController::class, 'verifyClaude'], ['auth', 'admin']);
$router->post('/admin/settings/{section}', [SettingsController::class, 'save'], ['auth', 'admin']);

return $router;
