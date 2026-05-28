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
use SysRevAI\Controllers\Admin\ReportsController;
use SysRevAI\Controllers\Admin\SettingsController;
use SysRevAI\Controllers\Admin\UsersController;
use SysRevAI\Controllers\AboutController;
use SysRevAI\Controllers\AuthController;
use SysRevAI\Controllers\CommentsController;
use SysRevAI\Controllers\DashboardController;
use SysRevAI\Controllers\DuplicatesController;
use SysRevAI\Controllers\FullTextRetrievalController;
use SysRevAI\Controllers\ImportController;
use SysRevAI\Controllers\RetrievalQueueController;
use SysRevAI\Controllers\InvitationsController;
use SysRevAI\Controllers\MembersController;
use SysRevAI\Controllers\NotificationsController;
use SysRevAI\Controllers\ProfileController;
use SysRevAI\Controllers\ReferencesController;
use SysRevAI\Controllers\ReviewsController;
use SysRevAI\Controllers\ChatController;
use SysRevAI\Controllers\ExportController;
use SysRevAI\Controllers\ExtractionController;
use SysRevAI\Controllers\FullTextScreeningController;
use SysRevAI\Controllers\SummariesController;
use SysRevAI\Controllers\TranslateController;
use SysRevAI\Controllers\PdfController;
use SysRevAI\Controllers\RiskOfBiasController;
use SysRevAI\Controllers\ScreeningController;
use SysRevAI\Controllers\SearchController;
use SysRevAI\Core\Auth;
use SysRevAI\Core\Router;

$router = new Router();

$router->get('/', static fn () => redirect(Auth::check() ? '/dashboard' : '/login'));

$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/login/2fa', [AuthController::class, 'show2fa'], ['guest']);
$router->post('/login/2fa', [AuthController::class, 'verify2fa'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// Public about page (no auth) and global search (auth).
$router->get('/about', [AboutController::class, 'show']);
$router->get('/search', [SearchController::class, 'index'], ['auth']);

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);

// Reviews (literal paths before the {id} pattern).
$router->get('/reviews', [ReviewsController::class, 'index'], ['auth']);
$router->get('/reviews/new', [ReviewsController::class, 'newForm'], ['auth']);
$router->post('/reviews', [ReviewsController::class, 'store'], ['auth']);
$router->get('/reviews/{id}/protocol', [ReviewsController::class, 'editProtocol'], ['auth']);
$router->post('/reviews/{id}/protocol/extract', [ReviewsController::class, 'extractProtocol'], ['auth']);
$router->post('/reviews/{id}/protocol', [ReviewsController::class, 'updateProtocol'], ['auth']);
$router->post('/reviews/{id}/archive', [ReviewsController::class, 'archive'], ['auth']);
$router->get('/reviews/{id}/copilot/history', [ReviewsController::class, 'copilotHistory'], ['auth']);
$router->post('/reviews/{id}/copilot/clear', [ReviewsController::class, 'copilotClear'], ['auth']);
$router->post('/reviews/{id}/copilot', [ReviewsController::class, 'copilot'], ['auth']);

// Team / collaboration on a review.
$router->get('/reviews/{id}/team', [MembersController::class, 'index'], ['auth']);
$router->post('/reviews/{id}/team/invite', [MembersController::class, 'invite'], ['auth']);
$router->post('/reviews/{id}/team/update', [MembersController::class, 'updateMember'], ['auth']);
$router->post('/reviews/{id}/team/remove', [MembersController::class, 'removeMember'], ['auth']);
$router->post('/reviews/{id}/team/revoke', [MembersController::class, 'revokeInvitation'], ['auth']);

// Review comments.
$router->post('/reviews/{id}/comments/delete', [CommentsController::class, 'delete'], ['auth']);
$router->post('/reviews/{id}/comments', [CommentsController::class, 'store'], ['auth']);

// Import & references & duplicates.
$router->get('/reviews/{id}/references', [ReferencesController::class, 'index'], ['auth']);
$router->get('/reviews/{id}/import', [ImportController::class, 'form'], ['auth']);
$router->post('/reviews/{id}/import', [ImportController::class, 'process'], ['auth']);
$router->get('/reviews/{id}/duplicates', [DuplicatesController::class, 'index'], ['auth']);
$router->post('/reviews/{id}/duplicates/check-ai', [DuplicatesController::class, 'checkAi'], ['auth']);
$router->post('/reviews/{id}/duplicates/resolve', [DuplicatesController::class, 'resolve'], ['auth']);

// Full-text retrieval per review.
$router->post('/reviews/{id}/full-text/enqueue-all', [FullTextRetrievalController::class, 'enqueueAll'], ['auth']);
$router->post('/reviews/{id}/full-text/retry-failed', [FullTextRetrievalController::class, 'retryFailed'], ['auth']);
$router->get('/reviews/{id}/full-text-coverage', [FullTextRetrievalController::class, 'coverage'], ['auth']);
$router->get('/reviews/{id}/full-text-queue/poll', [RetrievalQueueController::class, 'poll'], ['auth']);
$router->post('/reviews/{id}/full-text-queue/cancel-all', [RetrievalQueueController::class, 'cancelAll'], ['auth']);
$router->get('/reviews/{id}/full-text-queue', [RetrievalQueueController::class, 'index'], ['auth']);
$router->post('/reviews/{id}/references/{refId}/full-text', [FullTextRetrievalController::class, 'retrieve'], ['auth']);

// Title/abstract screening.
$router->get('/reviews/{id}/screen/suggest', [ScreeningController::class, 'suggest'], ['auth']);
$router->get('/reviews/{id}/screen/conflicts', [ScreeningController::class, 'conflicts'], ['auth']);
$router->post('/reviews/{id}/screen/conflicts/resolve', [ScreeningController::class, 'resolveConflict'], ['auth']);
$router->post('/reviews/{id}/screen/decide', [ScreeningController::class, 'decide'], ['auth']);
$router->post('/reviews/{id}/screen/start', [ScreeningController::class, 'start'], ['auth']);
$router->post('/reviews/{id}/screen/coordinator', [ScreeningController::class, 'toggleCoordinator'], ['auth']);
$router->get('/reviews/{id}/screen', [ScreeningController::class, 'screen'], ['auth']);

// Full-text screening (stage='ft') reuses the screening machinery.
$router->get('/reviews/{id}/full-text/conflicts', [FullTextScreeningController::class, 'conflicts'], ['auth']);
$router->post('/reviews/{id}/full-text/conflicts/resolve', [FullTextScreeningController::class, 'resolveConflict'], ['auth']);
$router->post('/reviews/{id}/full-text/decide', [FullTextScreeningController::class, 'decide'], ['auth']);
$router->post('/reviews/{id}/full-text/start', [FullTextScreeningController::class, 'start'], ['auth']);
$router->post('/reviews/{id}/full-text/coordinator', [FullTextScreeningController::class, 'toggleCoordinator'], ['auth']);
$router->get('/reviews/{id}/full-text', [FullTextScreeningController::class, 'screen'], ['auth']);

// PDFs and per-article chat (nested under a reference).
$router->post('/reviews/{id}/references/{refId}/pdf', [PdfController::class, 'upload'], ['auth']);
$router->get('/reviews/{id}/references/{refId}/pdf', [PdfController::class, 'serve'], ['auth']);
$router->post('/reviews/{id}/references/{refId}/chat', [ChatController::class, 'send'], ['auth']);
$router->get('/reviews/{id}/references/{refId}/summary', [SummariesController::class, 'show'], ['auth']);
$router->post('/reviews/{id}/references/{refId}/summary', [SummariesController::class, 'generate'], ['auth']);

// Generic translation endpoint (member access). Returns JSON.
$router->post('/reviews/{id}/translate', [TranslateController::class, 'translate'], ['auth']);

// Exports.
$router->get('/reviews/{id}/exports', [ExportController::class, 'index'], ['auth']);
$router->get('/reviews/{id}/exports/prisma', [ExportController::class, 'prisma'], ['auth']);
$router->get('/reviews/{id}/exports/csv', [ExportController::class, 'csv'], ['auth']);
$router->get('/reviews/{id}/exports/excel', [ExportController::class, 'excel'], ['auth']);
$router->get('/reviews/{id}/exports/word', [ExportController::class, 'word'], ['auth']);
$router->get('/reviews/{id}/exports/revman', [ExportController::class, 'revman'], ['auth']);

// Data extraction (literal /template before the {refId} pattern).
// Risk of bias (per (reference, reviewer, tool, domain)).
$router->get('/reviews/{id}/risk-of-bias', [RiskOfBiasController::class, 'index'], ['auth']);
$router->post('/reviews/{id}/risk-of-bias/{refId}/ai', [RiskOfBiasController::class, 'ai'], ['auth']);
$router->get('/reviews/{id}/risk-of-bias/{refId}', [RiskOfBiasController::class, 'edit'], ['auth']);
$router->post('/reviews/{id}/risk-of-bias/{refId}', [RiskOfBiasController::class, 'save'], ['auth']);

$router->get('/reviews/{id}/extraction', [ExtractionController::class, 'index'], ['auth']);
$router->get('/reviews/{id}/extraction/template', [ExtractionController::class, 'templateEdit'], ['auth']);
$router->post('/reviews/{id}/extraction/template', [ExtractionController::class, 'templateSave'], ['auth']);
$router->post('/reviews/{id}/extraction/{refId}/ai', [ExtractionController::class, 'ai'], ['auth']);
$router->post('/reviews/{id}/extraction/{refId}/approve', [ExtractionController::class, 'approve'], ['auth']);
$router->get('/reviews/{id}/extraction/{refId}', [ExtractionController::class, 'edit'], ['auth']);
$router->post('/reviews/{id}/extraction/{refId}', [ExtractionController::class, 'save'], ['auth']);

$router->get('/reviews/{id}', [ReviewsController::class, 'show'], ['auth']);

// Invitations (token links).
$router->get('/invite/{token}', [InvitationsController::class, 'show'], ['auth']);
$router->post('/invite/{token}/accept', [InvitationsController::class, 'accept'], ['auth']);

// Notifications.
$router->get('/notifications/poll', [NotificationsController::class, 'poll'], ['auth']);
$router->get('/notifications', [NotificationsController::class, 'index'], ['auth']);
$router->post('/notifications/read-all', [NotificationsController::class, 'markAllRead'], ['auth']);
$router->post('/notifications/read', [NotificationsController::class, 'markRead'], ['auth']);

// Profile.
$router->get('/profile', [ProfileController::class, 'profile'], ['auth']);
$router->post('/profile', [ProfileController::class, 'saveProfile'], ['auth']);
$router->post('/profile/avatar', [ProfileController::class, 'uploadAvatar'], ['auth']);
$router->post('/profile/avatar/delete', [ProfileController::class, 'deleteAvatar'], ['auth']);
$router->get('/profile/password', [ProfileController::class, 'password'], ['auth']);
$router->post('/profile/password', [ProfileController::class, 'savePassword'], ['auth']);
$router->get('/profile/two-factor', [ProfileController::class, 'twoFactor'], ['auth']);
$router->post('/profile/two-factor/enable', [ProfileController::class, 'enableTwoFactor'], ['auth']);
$router->post('/profile/two-factor/disable', [ProfileController::class, 'disableTwoFactor'], ['auth']);
$router->get('/profile/notifications', [ProfileController::class, 'notifications'], ['auth']);
$router->post('/profile/notifications', [ProfileController::class, 'saveNotifications'], ['auth']);

// Admin → Settings (owner/admin only). Specific action routes are registered
// before the generic save route so they take precedence.
$admin = ['auth', 'admin'];
$router->get('/admin/settings', [SettingsController::class, 'index'], $admin);
$router->get('/admin/settings/{section}', [SettingsController::class, 'show'], $admin);
$router->post('/admin/settings/claude/verify', [SettingsController::class, 'verifyClaude'], $admin);
$router->post('/admin/settings/email/test', [SettingsController::class, 'sendTestEmail'], $admin);
$router->post('/admin/settings/translate/verify', [SettingsController::class, 'verifyTranslate'], $admin);
$router->post('/admin/settings/fulltext/verify', [SettingsController::class, 'verifyFulltextSource'], $admin);
$router->post('/admin/settings/fulltext/test', [SettingsController::class, 'testFulltextChain'], $admin);
$router->post('/admin/settings/files/install', [SettingsController::class, 'installDependency'], $admin);
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
$router->post('/admin/maintenance/update-check', [MaintenanceController::class, 'checkUpdates'], $admin);
$router->post('/admin/maintenance/update-apply', [MaintenanceController::class, 'applyUpdate'], $admin);

// Admin → Reports.
$router->get('/admin/reports/fulltext-coverage.csv', [ReportsController::class, 'fulltextCoverageCsv'], $admin);
$router->get('/admin/reports/fulltext-coverage', [ReportsController::class, 'fulltextCoverage'], $admin);

return $router;
