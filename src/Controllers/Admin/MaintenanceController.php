<?php

declare(strict_types=1);

namespace SysRevAI\Controllers\Admin;

use SysRevAI\Core\Config;
use SysRevAI\Core\Database;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\SystemUpdate;
use SysRevAI\Services\BackupService;
use SysRevAI\Services\UpdateService;

/**
 * Admin → Maintenance: maintenance mode, cache/log cleanup, database backups,
 * system information and the recent activity log.
 */
final class MaintenanceController
{
    public function index(): void
    {
        echo View::render('admin/maintenance/index', [
            'activeSection'    => 'maintenance',
            'maintenanceMode'  => (bool) (setting('maintenance.enabled') ?? false),
            'system'           => $this->systemInfo(),
            'backups'          => BackupService::list(),
            'activity'         => $this->recentActivity(),
            'updateInfo'       => Session::pullFlash('update_info'),
            'updateHistory'    => SystemUpdate::recent(5),
            'updateRepo'       => UpdateService::REPO,
            'updateBranch'     => UpdateService::BRANCH,
            'composer'         => $this->composerStatus(),
            'composerOutput'   => Session::pullFlash('composer_output'),
        ], 'layouts/admin');
    }

    /**
     * Re-run `composer install` from inside the admin panel so an
     * operator can pull in dependencies that a code update added
     * (e.g. dompdf/dompdf for the article-report PDF export) without
     * shell access. Mirrors the wizard's step-2 logic from
     * public/install/lib.php so we don't depend on that file at runtime.
     */
    public function composerInstall(): void
    {
        if (!function_exists('shell_exec')) {
            Session::flash('admin_error', __('admin.maintenance.composer_no_exec'));
            redirect('/admin/maintenance');
        }
        $bin = $this->detectComposer();
        if ($bin === null) {
            Session::flash('admin_error', __('admin.maintenance.composer_missing'));
            redirect('/admin/maintenance');
        }

        @set_time_limit(180);
        $base = (string) config('paths.base');
        $cmd  = 'cd ' . escapeshellarg($base)
              . ' && ' . escapeshellcmd($bin)
              . ' install --no-dev --no-interaction --prefer-dist 2>&1';
        $output = (string) @shell_exec($cmd);

        $status = $this->composerStatus();
        $ok = $status['classes_ok'];

        ActivityLog::record('maintenance.composer_install', [
            'ok'      => $ok,
            'missing' => $status['missing'],
        ]);
        Session::flash('composer_output', $output);
        Session::flash(
            $ok ? 'admin_success' : 'admin_error',
            $ok
                ? __('admin.maintenance.composer_install_ok')
                : __('admin.maintenance.composer_install_failed')
        );
        redirect('/admin/maintenance');
    }

    /**
     * Inspect every Composer dependency we ship with so the
     * Maintenance page can show what's missing and surface the "install"
     * button only when there's something to fix. Mirrors
     * public\install\lib.php::required_classes().
     *
     * @return array{present:bool,classes_ok:bool,missing:list<string>,has_composer:bool}
     */
    private function composerStatus(): array
    {
        $required = [
            'GuzzleHttp\\Client'                  => 'guzzlehttp/guzzle',
            'Smalot\\PdfParser\\Parser'           => 'smalot/pdfparser',
            'PhpOffice\\PhpWord\\PhpWord'         => 'phpoffice/phpword',
            'PhpOffice\\PhpSpreadsheet\\Spreadsheet' => 'phpoffice/phpspreadsheet',
            'Dompdf\\Dompdf'                      => 'dompdf/dompdf',
            'Dotenv\\Dotenv'                      => 'vlucas/phpdotenv',
            'PHPMailer\\PHPMailer\\PHPMailer'     => 'phpmailer/phpmailer',
        ];
        $autoload = (string) config('paths.base') . '/vendor/autoload.php';
        $present  = is_file($autoload);
        $missing  = [];
        if ($present) {
            foreach ($required as $class => $package) {
                if (!class_exists($class)) {
                    $missing[] = $package;
                }
            }
        } else {
            $missing = array_values($required);
        }
        return [
            'present'      => $present,
            'classes_ok'   => $present && $missing === [],
            'missing'      => $missing,
            'has_composer' => $this->detectComposer() !== null,
        ];
    }

    private function detectComposer(): ?string
    {
        if (!function_exists('shell_exec')) {
            return null;
        }
        foreach (['composer', 'composer.phar'] as $bin) {
            $out = @shell_exec(escapeshellcmd($bin) . ' --version 2>/dev/null');
            if (is_string($out) && stripos($out, 'composer') !== false) {
                return $bin;
            }
        }
        return null;
    }

    public function checkUpdates(): void
    {
        $result = (new UpdateService())->check();
        ActivityLog::record('maintenance.update_check', [
            'ok'         => $result['ok'],
            'up_to_date' => $result['up_to_date'] ?? null,
            'remote'     => $result['short'] ?? null,
        ]);
        Session::flash('update_info', $result);
        if (!$result['ok']) {
            Session::flash('admin_error', __('admin.maintenance.update_check_failed') . ' (' . ($result['error'] ?? '?') . ')');
        }
        redirect('/admin/maintenance');
    }

    public function applyUpdate(): void
    {
        $result = (new UpdateService())->apply();
        if ($result['ok']) {
            $msg = __('admin.maintenance.update_applied');
            if (($result['migrations_run'] ?? 0) > 0) {
                $msg .= ' (' . (int) $result['migrations_run'] . ' migrations)';
            }
            Session::flash('admin_success', $msg);
        } else {
            $hint = match ($result['error'] ?? '') {
                'not_a_git_checkout' => __('admin.maintenance.update_not_git'),
                'migration_failed'   => __('admin.maintenance.update_migration_failed'),
                default              => __('admin.maintenance.update_failed'),
            };
            $extra = isset($result['output']) ? ': ' . $result['output'] : '';
            Session::flash('admin_error', $hint . $extra);
        }
        redirect('/admin/maintenance');
    }

    public function toggleMaintenance(): void
    {
        Config::set('maintenance.enabled', !empty($_POST['enabled']), 'bool', 'maintenance');
        ActivityLog::record('maintenance.mode', ['enabled' => !empty($_POST['enabled'])]);
        Session::flash('admin_success', __('admin.saved'));
        redirect('/admin/maintenance');
    }

    public function clearCache(): void
    {
        $count = $this->deleteFiles((string) config('paths.cache'), '*');
        ActivityLog::record('maintenance.clear_cache', ['files' => $count]);
        Session::flash('admin_success', __('admin.maintenance.cache_cleared', $count));
        redirect('/admin/maintenance');
    }

    public function clearLogs(): void
    {
        $count = $this->deleteFiles((string) config('paths.logs'), '*.log');
        ActivityLog::record('maintenance.clear_logs', ['files' => $count]);
        Session::flash('admin_success', __('admin.maintenance.logs_cleared', $count));
        redirect('/admin/maintenance');
    }

    public function createBackup(): void
    {
        $result = BackupService::create();
        ActivityLog::record('maintenance.backup', ['ok' => $result['ok']]);
        Session::flash($result['ok'] ? 'admin_success' : 'admin_error', $result['message']);
        redirect('/admin/maintenance');
    }

    public function downloadBackup(string $name): void
    {
        $path = BackupService::path($name);
        if ($path === null) {
            http_response_code(404);
            echo View::render('errors/404', [], 'layouts/auth');
            return;
        }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
    }

    public function deleteBackup(): void
    {
        $name = (string) ($_POST['name'] ?? '');
        if (BackupService::delete($name)) {
            ActivityLog::record('maintenance.backup_deleted', ['name' => $name]);
            Session::flash('admin_success', __('admin.maintenance.backup_deleted'));
        } else {
            Session::flash('admin_error', __('admin.maintenance.backup_missing'));
        }
        redirect('/admin/maintenance');
    }

    private function systemInfo(): array
    {
        $uploads = (string) config('paths.uploads');
        $info = [
            'php_version'    => PHP_VERSION,
            'extensions'     => count(get_loaded_extensions()),
            'app_version'    => (string) config('app.version', '0.1.0-dev'),
            'uploads_size'   => $this->humanBytes($this->dirSize($uploads)),
            'disk_free'      => $this->humanBytes((int) @disk_free_space($uploads)),
            'mysql_version'  => '—',
            'db_size'        => '—',
        ];

        try {
            $info['mysql_version'] = (string) Database::pdo()->query('SELECT VERSION()')->fetchColumn();
            $db = config('database.database');
            $row = Database::selectOne(
                "SELECT ROUND(SUM(data_length + index_length)) AS bytes
                 FROM information_schema.TABLES WHERE table_schema = ?",
                [$db]
            );
            $info['db_size'] = $this->humanBytes((int) ($row['bytes'] ?? 0));
        } catch (\Throwable) {
            // DB not reachable (e.g. dev without MySQL): leave placeholders.
        }

        return $info;
    }

    private function recentActivity(): array
    {
        try {
            return ActivityLog::recent(10);
        } catch (\Throwable) {
            return [];
        }
    }

    private function deleteFiles(string $dir, string $pattern): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $count = 0;
        foreach (glob($dir . '/' . $pattern) ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep' && @unlink($file)) {
                $count++;
            }
        }
        return $count;
    }

    private function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }
        return round($n, $i > 1 ? 1 : 0) . ' ' . $units[$i];
    }
}
