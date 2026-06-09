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

/* ── Step 2: dependencies ──────────────────────────────────────────────── */

/** Required FQCNs that must exist once Composer has run. */
function required_classes(): array
{
    return [
        'GuzzleHttp\\Client'                  => 'guzzlehttp/guzzle',
        'Smalot\\PdfParser\\Parser'           => 'smalot/pdfparser',
        'PhpOffice\\PhpWord\\PhpWord'         => 'phpoffice/phpword',
        'PhpOffice\\PhpSpreadsheet\\Spreadsheet' => 'phpoffice/phpspreadsheet',
        'Dompdf\\Dompdf'                      => 'dompdf/dompdf',
        'Dotenv\\Dotenv'                      => 'vlucas/phpdotenv',
        'PHPMailer\\PHPMailer\\PHPMailer'     => 'phpmailer/phpmailer',
    ];
}

/** Attempt to run Composer to install dependencies. Returns ['ok','output','message']. */
function attempt_composer_install(): array
{
    $bin = detect_composer();
    if ($bin === null) {
        return ['ok' => false, 'output' => '', 'message' => t('step2.composer_missing')];
    }
    if (!function_exists('shell_exec')) {
        return ['ok' => false, 'output' => '', 'message' => 'shell_exec is disabled on this host.'];
    }
    $cmd = 'cd ' . escapeshellarg(base_path())
         . ' && ' . escapeshellcmd($bin)
         . ' install --no-dev --no-interaction --prefer-dist 2>&1';
    $output = (string) @shell_exec($cmd);
    $ok = is_file(base_path('vendor/autoload.php'));
    install_log(2, $ok ? 'ok' : 'fail', 'composer install attempted');
    return [
        'ok'      => $ok,
        'output'  => $output,
        'message' => $ok ? t('step2.install_ok') : t('step2.install_failed'),
    ];
}

/** Inspect the dependency state without installing anything. */
function dependencies_status(): array
{
    $autoload = base_path('vendor/autoload.php');
    $present  = is_file($autoload);

    if ($present && !class_exists('GuzzleHttp\\Client', false)) {
        require_once $autoload;
    }

    $missing = [];
    if ($present) {
        foreach (required_classes() as $class => $package) {
            if (!class_exists($class)) {
                $missing[] = $package;
            }
        }
    }

    return [
        'present'      => $present,
        'classes_ok'   => $present && $missing === [],
        'missing'      => $missing,
        'has_exec'     => function_exists('proc_open') && !in_array('proc_open', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true),
        'composer_bin' => $present ? null : detect_composer(),
    ];
}

/** Return a composer command if a binary is callable, else null. */
function detect_composer(): ?string
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

/* ── Step 3: database ──────────────────────────────────────────────────── */

/**
 * Build a PDO connection from a config array.
 * @param bool $withDb whether to select the database in the DSN.
 */
function db_connect(array $cfg, bool $withDb = true): PDO
{
    $charset = $cfg['charset'] ?: 'utf8mb4';
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$charset}";
    if ($withDb) {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$charset}";
    }
    return new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/**
 * Test a database connection, optionally creating the database first.
 * Returns ['ok' => bool, 'message' => string, 'created' => bool].
 */
function db_test(array $cfg, bool $create = false): array
{
    try {
        if ($create) {
            $server = db_connect($cfg, false);
            $charset = $cfg['charset'] ?: 'utf8mb4';
            $name = str_replace('`', '', (string) $cfg['database']);
            $server->exec(
                "CREATE DATABASE IF NOT EXISTS `{$name}` "
                . "CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci"
            );
        }
        db_connect($cfg, true); // selects the db; throws on failure
        return ['ok' => true, 'message' => t('step3.test_ok'), 'created' => $create];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage(), 'created' => false];
    }
}

/* ── Step 4: migrations + seed ─────────────────────────────────────────── */

/**
 * Apply every migration in database/migrations in order, substituting the
 * configured table prefix. DDL auto-commits in MySQL, so we report per-file
 * results and stop at the first failure rather than relying on rollback.
 *
 * Returns ['ok' => bool, 'log' => [['file','table','ok','error']], 'error' => ?string].
 */
function run_migrations(array $cfg): array
{
    $dir = base_path('database/migrations');
    $files = glob($dir . '/*.sql') ?: [];
    sort($files);

    $log = [];
    try {
        $pdo = db_connect($cfg, true);
    } catch (Throwable $e) {
        return ['ok' => false, 'log' => [], 'error' => $e->getMessage()];
    }

    foreach ($files as $file) {
        $name = basename($file);
        $sql  = (string) file_get_contents($file);
        $sql  = str_replace('{prefix}', (string) $cfg['prefix'], $sql);

        // Derive the table name for a friendly log line.
        $table = preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/i', $sql, $m) ? $m[1] : $name;

        try {
            $pdo->exec($sql);
            $log[] = ['file' => $name, 'table' => $table, 'ok' => true, 'error' => null];
            install_log(4, 'ok', "migration {$name} applied");
        } catch (Throwable $e) {
            $log[] = ['file' => $name, 'table' => $table, 'ok' => false, 'error' => $e->getMessage()];
            install_log(4, 'fail', "migration {$name}: " . $e->getMessage());
            return ['ok' => false, 'log' => $log, 'error' => $e->getMessage()];
        }
    }

    return ['ok' => true, 'log' => $log, 'error' => null];
}

/** Insert minimal seed data into the settings table (idempotent). */
function seed_defaults(array $cfg, array $general): array
{
    try {
        $pdo    = db_connect($cfg, true);
        $prefix = (string) $cfg['prefix'];
        $rows = [
            ['site.name',          (string) ($general['site_name'] ?? 'SysRevAI'), 'string',  'general', 1],
            ['site.url',           (string) ($general['app_url'] ?? ''),           'string',  'general', 1],
            ['app.version',        '0.1.0-dev',                                    'string',  'general', 1],
            ['app.locale',         (string) ($general['locale'] ?? 'ca'),          'string',  'general', 1],
            ['app.timezone',       (string) ($general['timezone'] ?? 'Europe/Madrid'), 'string', 'general', 1],
            ['security.force_https', !empty($general['force_https']) ? '1' : '0',  'bool',    'security', 0],
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO `{$prefix}settings` (`key`,`value`,`type`,`group`,`is_public`)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)"
        );
        foreach ($rows as $r) {
            $stmt->execute($r);
        }
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ── Step 6: admin account ─────────────────────────────────────────────── */

/**
 * Validate a password against the policy: ≥12 chars, upper, lower, digit, symbol.
 */
function password_meets_policy(string $pw): bool
{
    return strlen($pw) >= 12
        && preg_match('/[A-Z]/', $pw)
        && preg_match('/[a-z]/', $pw)
        && preg_match('/\d/', $pw)
        && preg_match('/[^A-Za-z0-9]/', $pw);
}

/** Insert the owner account. Returns ['ok' => bool, 'error' => ?string]. */
function create_admin(array $cfg, array $admin): array
{
    try {
        $pdo    = db_connect($cfg, true);
        $prefix = (string) $cfg['prefix'];
        // The legal_accepted_at column is added by migration 022. We use it
        // when present (fresh installs always include it); older databases
        // installed before the migration ran would not, so we degrade
        // gracefully by checking the column first.
        $hasLegalCol = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM `{$prefix}users` LIKE 'legal_accepted_at'");
            $hasLegalCol = $col !== false && $col->fetch(\PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable) {
            // ignore — fall back to the legacy insert.
        }

        if ($hasLegalCol) {
            $stmt = $pdo->prepare(
                "INSERT INTO `{$prefix}users`
                    (`name`,`email`,`password_hash`,`role`,`status`,`locale`,`is_active`,`email_verified_at`,`legal_accepted_at`)
                 VALUES (?,?,?, 'owner','active', ?, 1, NOW(), NOW())"
            );
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO `{$prefix}users`
                    (`name`,`email`,`password_hash`,`role`,`status`,`locale`,`is_active`,`email_verified_at`)
                 VALUES (?,?,?, 'owner','active', ?, 1, NOW())"
            );
        }
        $stmt->execute([
            $admin['name'],
            $admin['email'],
            $admin['password_hash'],
            $admin['locale'] ?? 'ca',
        ]);
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ── Step 7: finalization ──────────────────────────────────────────────── */

/** Generate a fresh APP_KEY (base64-encoded 32 random bytes). */
function generate_app_key(): string
{
    return 'base64:' . base64_encode(random_bytes(32));
}

/** Best-effort detection of the public base URL from the current request. */
function detect_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || ($_SERVER['SERVER_PORT'] ?? null) == 443;
    $scheme = $https ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Strip the trailing /install/... from the path to get the app root.
    $path   = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path   = preg_replace('#/install/.*$#', '', $path) ?? '';
    return rtrim("{$scheme}://{$host}{$path}", '/');
}

/** Write the .env file from collected wizard data. Returns ['ok','error']. */
function write_env(array $data): array
{
    $appKey = $data['app_key'] ?? generate_app_key();
    $esc = static fn (string $v): string => '"' . str_replace('"', '\"', $v) . '"';

    $lines = [
        '# Generated by the SysRevAI web installer on ' . date('c'),
        '',
        'APP_NAME=' . $esc((string) ($data['general']['site_name'] ?? 'SysRevAI')),
        'APP_ENV=production',
        'APP_DEBUG=false',
        'APP_URL=' . $esc((string) ($data['general']['app_url'] ?? '')),
        'APP_TIMEZONE=' . $esc((string) ($data['general']['timezone'] ?? 'Europe/Madrid')),
        'APP_LOCALE=' . ($data['general']['locale'] ?? 'ca'),
        'APP_KEY=' . $appKey,
        '',
        'DB_HOST=' . $esc((string) $data['db']['host']),
        'DB_PORT=' . (int) $data['db']['port'],
        'DB_DATABASE=' . $esc((string) $data['db']['database']),
        'DB_USERNAME=' . $esc((string) $data['db']['username']),
        'DB_PASSWORD=' . $esc((string) $data['db']['password']),
        'DB_CHARSET=' . ($data['db']['charset'] ?: 'utf8mb4'),
        'DB_PREFIX=' . $esc((string) $data['db']['prefix']),
        '',
        'FORCE_HTTPS=' . (!empty($data['general']['force_https']) ? 'true' : 'false'),
        'SESSION_LIFETIME=120',
        '',
    ];

    $path = base_path('.env');
    $ok = @file_put_contents($path, implode("\n", $lines)) !== false;
    if ($ok) {
        @chmod($path, 0600);
        return ['ok' => true, 'error' => null];
    }
    return ['ok' => false, 'error' => 'cannot write ' . $path];
}

/** Create config/installed.lock with timestamp, version and an integrity hash. */
function write_lock(string $appKey): array
{
    $payload = [
        'installed_at' => date('c'),
        'version'      => '0.1.0-dev',
        'integrity'    => hash('sha256', $appKey . '|' . date('Y-m-d')),
    ];
    $path = base_path('config/installed.lock');
    $ok = @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT)) !== false;
    return $ok ? ['ok' => true, 'error' => null] : ['ok' => false, 'error' => 'cannot write ' . $path];
}
