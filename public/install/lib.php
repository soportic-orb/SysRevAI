<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Installer shared library
|------------------------------------------------------------------------------
| Total isolation: this file (and everything under public/install/) depends ONLY
| on native PHP + PDO. It MUST work before vendor/ exists and before .env is
| written. Do not require anything from src/ here.
*/

const INSTALLER_TOTAL_STEPS = 8; // screens 0..7, displayed as 1/8..8/8
const INSTALLER_SESSION     = 'sysrevai_install';

/** Absolute path to the project root (three levels up from this file). */
function base_path(string $append = ''): string
{
    $root = dirname(__DIR__, 2);
    return $append === '' ? $root : $root . '/' . ltrim($append, '/');
}

/** True once installation has completed and the lock file exists. */
function is_locked(): bool
{
    return is_file(base_path('config/installed.lock'));
}

/** Start an isolated session for the wizard state. */
function installer_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(INSTALLER_SESSION);
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
    ]);
    if (!isset($_SESSION['install'])) {
        $_SESSION['install'] = ['locale' => null, 'completed_steps' => []];
    }
}

/** Currently selected installer locale (session → default 'ca'). */
function current_locale(): string
{
    $allowed = ['ca', 'es', 'en'];
    $locale  = $_SESSION['install']['locale'] ?? 'ca';
    return in_array($locale, $allowed, true) ? $locale : 'ca';
}

/** Load the translation array for the active locale (cached per request). */
function lang(): array
{
    static $cache = [];
    $locale = current_locale();
    if (!isset($cache[$locale])) {
        $file = __DIR__ . "/lang/{$locale}.php";
        $cache[$locale] = is_file($file) ? require $file : require __DIR__ . '/lang/ca.php';
    }
    return $cache[$locale];
}

/** Translate a dot-notation key, returning the key itself if missing. */
function t(string $key, mixed ...$args): string
{
    $value = lang();
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $key;
        }
        $value = $value[$segment];
    }
    if (!is_string($value)) {
        return $key;
    }
    return $args === [] ? $value : vsprintf($value, $args);
}

/** HTML-escape helper (installer is isolated, so define its own). */
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ── CSRF ──────────────────────────────────────────────────────────────── */

function csrf_token(): string
{
    if (empty($_SESSION['install']['csrf'])) {
        $_SESSION['install']['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['install']['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    return is_string($sent) && hash_equals(csrf_token(), $sent);
}

/* ── Logging ───────────────────────────────────────────────────────────── */

/** Append a line to storage/logs/install.log (best-effort). */
function install_log(int $step, string $result, string $message = ''): void
{
    $dir = base_path('storage/logs');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = sprintf(
        "[%s] step=%d result=%s %s%s",
        date('Y-m-d H:i:s'),
        $step,
        $result,
        $message,
        PHP_EOL
    );
    @file_put_contents($dir . '/install.log', $line, FILE_APPEND | LOCK_EX);
}

/* ── Wizard navigation ─────────────────────────────────────────────────── */

function mark_step_complete(int $step): void
{
    $done = $_SESSION['install']['completed_steps'] ?? [];
    if (!in_array($step, $done, true)) {
        $done[] = $step;
        $_SESSION['install']['completed_steps'] = $done;
    }
}

function step_completed(int $step): bool
{
    return in_array($step, $_SESSION['install']['completed_steps'] ?? [], true);
}

/**
 * Resolve the step the user is allowed to view: cannot jump ahead past the
 * first not-yet-completed step (prevents skipping via direct URL).
 */
function resolve_step(int $requested): int
{
    $requested = max(0, min($requested, INSTALLER_TOTAL_STEPS - 1));
    for ($i = 0; $i < $requested; $i++) {
        if (!step_completed($i)) {
            return $i;
        }
    }
    return $requested;
}

/* ── Requirements (Step 1) ─────────────────────────────────────────────── */

/** Convert a php.ini shorthand byte value (e.g. "128M") to an integer of bytes. */
function ini_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return $value === '-1' ? PHP_INT_MAX : 0;
    }
    $unit = strtolower($value[strlen($value) - 1]);
    $num  = (int) $value;
    return match ($unit) {
        'g'     => $num * 1024 * 1024 * 1024,
        'm'     => $num * 1024 * 1024,
        'k'     => $num * 1024,
        default => (int) $value,
    };
}

function human_bytes(int $bytes): string
{
    if ($bytes >= PHP_INT_MAX) {
        return t('step1.unlimited');
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $n = (float) $bytes;
    while ($n >= 1024 && $i < count($units) - 1) {
        $n /= 1024;
        $i++;
    }
    return round($n, $n < 10 && $i > 0 ? 1 : 0) . ' ' . $units[$i];
}

/**
 * Build the full requirements report. Each item:
 *   ['label','status' => ok|fail|warn,'detail','fix'?]
 * Returns ['groups' => [...], 'blocking_ok' => bool].
 */
function check_requirements(): array
{
    $required_ext = [
        'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl',
        'fileinfo', 'zip', 'xml', 'intl',
    ];

    $groups = [];

    // PHP version
    $phpOk = PHP_VERSION_ID >= 80200;
    $groups['group_php'] = [[
        'label'  => t('step1.php_version'),
        'status' => $phpOk ? 'ok' : 'fail',
        'detail' => t('common.detected') . ': ' . PHP_VERSION,
    ]];

    // Extensions
    $extItems = [];
    foreach ($required_ext as $ext) {
        $loaded = extension_loaded($ext);
        $extItems[] = [
            'label'  => $ext,
            'status' => $loaded ? 'ok' : 'fail',
            'detail' => $loaded ? t('common.status_ok') : t('common.status_fail'),
            'fix'    => $loaded ? null : t('step1.fix_ext', $ext),
        ];
    }
    // gd OR imagick satisfies the image requirement.
    $gd = extension_loaded('gd') || extension_loaded('imagick');
    $extItems[] = [
        'label'  => 'gd / imagick',
        'status' => $gd ? 'ok' : 'fail',
        'detail' => $gd ? t('common.status_ok') : t('common.status_fail'),
        'fix'    => $gd ? null : t('step1.fix_ext', 'gd'),
    ];
    $groups['group_ext'] = $extItems;

    // Writable paths
    $paths = [
        'storage', 'storage/logs', 'storage/cache',
        'storage/credentials', 'storage/backups',
        'public/uploads', 'config',
    ];
    $writeItems = [];
    foreach ($paths as $rel) {
        $abs = base_path($rel);
        $writable = is_dir($abs) && is_writable($abs);
        $writeItems[] = [
            'label'  => $rel . '/',
            'status' => $writable ? 'ok' : 'fail',
            'detail' => $writable ? t('common.status_ok') : t('common.status_fail'),
            'fix'    => $writable ? null : t('step1.fix_write'),
        ];
    }
    $groups['group_write'] = $writeItems;

    // PHP limits (warnings, non-blocking)
    $mem    = ini_to_bytes((string) ini_get('memory_limit'));
    $upload = ini_to_bytes((string) ini_get('upload_max_filesize'));
    $post   = ini_to_bytes((string) ini_get('post_max_size'));
    $exec   = (int) ini_get('max_execution_time');
    $MB = 1024 * 1024;

    $limitItems = [
        [
            'label'  => t('step1.mem_limit'),
            'status' => ($mem === 0 || $mem >= 128 * $MB) ? 'ok' : 'warn',
            'detail' => t('common.detected') . ': ' . human_bytes($mem),
            'fix'    => ($mem === 0 || $mem >= 128 * $MB) ? null : 'memory_limit — ' . t('step1.fix_ini'),
        ],
        [
            'label'  => t('step1.upload_size'),
            'status' => $upload >= 50 * $MB ? 'ok' : 'warn',
            'detail' => t('common.detected') . ': ' . human_bytes($upload),
            'fix'    => $upload >= 50 * $MB ? null : 'upload_max_filesize — ' . t('step1.fix_ini'),
        ],
        [
            'label'  => t('step1.post_size'),
            'status' => $post >= 50 * $MB ? 'ok' : 'warn',
            'detail' => t('common.detected') . ': ' . human_bytes($post),
            'fix'    => $post >= 50 * $MB ? null : 'post_max_size — ' . t('step1.fix_ini'),
        ],
        [
            'label'  => t('step1.exec_time'),
            'status' => ($exec === 0 || $exec >= 60) ? 'ok' : 'warn',
            'detail' => t('common.detected') . ': ' . ($exec === 0 ? t('step1.unlimited') : $exec . ' s'),
            'fix'    => ($exec === 0 || $exec >= 60) ? null : 'max_execution_time — ' . t('step1.fix_ini'),
        ],
    ];
    $groups['group_limits'] = $limitItems;

    // Blocking = no 'fail' anywhere (warnings are allowed through).
    $blockingOk = true;
    foreach ($groups as $items) {
        foreach ($items as $item) {
            if ($item['status'] === 'fail') {
                $blockingOk = false;
                break 2;
            }
        }
    }

    return ['groups' => $groups, 'blocking_ok' => $blockingOk];
}
