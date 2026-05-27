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

use SysRevAI\Controllers\Admin\MaintenanceController;
use SysRevAI\Controllers\Admin\SettingsController;
use SysRevAI\Controllers\Admin\UsersController;
use SysRevAI\Controllers\AuthController;
use SysRevAI\Controllers\DashboardController;
use SysRevAI\Controllers\ReviewsController;
use SysRevAI\Core\Auth;
use SysRevAI\Core\Router;

$router = new Router();

$router->get('/', static fn () => redirect(Auth::check() ? '/dashboard' : '/login'));

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// Reviews (literal paths before the {id} pattern).
$router->get('/reviews', [ReviewsController::class, 'index'], ['auth']);
$router->get('/reviews/new', [ReviewsController::class, 'newForm'], ['auth']);
$router->post('/reviews', [ReviewsController::class, 'store'], ['auth']);
$router->get('/reviews/{id}/protocol', [ReviewsController::class, 'editProtocol'], ['auth']);
$router->post('/reviews/{id}/protocol', [ReviewsController::class, 'updateProtocol'], ['auth']);
$router->post('/reviews/{id}/archive', [ReviewsController::class, 'archive'], ['auth']);
$router->get('/reviews/{id}', [ReviewsController::class, 'show'], ['auth']);

// Admin → Settings (owner/admin only). Specific action routes are registered
// before the generic save route so they take precedence.
$admin = ['auth', 'admin'];
$router->get('/admin/settings', [SettingsController::class, 'index'], $admin);
$router->get('/admin/settings/{section}', [SettingsController::class, 'show'], $admin);
$router->post('/admin/settings/claude/verify', [SettingsController::class, 'verifyClaude'], $admin);
$router->post('/admin/settings/email/test', [SettingsController::class, 'sendTestEmail'], $admin);
$router->post('/admin/settings/translate/verify', [SettingsController::class, 'verifyTranslate'], $admin);
$router->post('/admin/settings/{section}', [SettingsController::class, 'save'], $admin);

// Admin → Users (order matters: literal paths before the {id} pattern).
$router->get('/admin/users', [UsersController::class, 'index'], $admin);
$router->post('/admin/users/registration', [UsersController::class, 'saveRegistration'], $admin);
$router->post('/admin/users', [UsersController::class, 'create'], $admin);
$router->post('/admin/users/{id}/delete', [UsersController::class, 'delete'], $admin);
$router->post('/admin/users/{id}', [UsersController::class, 'update'], $admin);

// Admin → Maintenance.
$router->get('/admin/maintenance', [MaintenanceController::class, 'index'], $admin);
$router->post('/admin/maintenance/mode', [MaintenanceController::class, 'toggleMaintenance'], $admin);
$router->post('/admin/maintenance/clear-cache', [MaintenanceController::class, 'clearCache'], $admin);
$router->post('/admin/maintenance/clear-logs', [MaintenanceController::class, 'clearLogs'], $admin);
$router->post('/admin/maintenance/backup', [MaintenanceController::class, 'createBackup'], $admin);
$router->post('/admin/maintenance/backup-delete', [MaintenanceController::class, 'deleteBackup'], $admin);
$router->get('/admin/maintenance/backup/{name}', [MaintenanceController::class, 'downloadBackup'], $admin);

return $router;
