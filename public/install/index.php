<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| SysRevAI Web Installer — front controller
|------------------------------------------------------------------------------
| Self-contained wizard. Depends only on native PHP + PDO so it can run before
| Composer dependencies or .env exist. Steps 0 (welcome) and 1 (requirements)
| are fully functional; steps 2–7 are scaffolded placeholders.
*/

require __DIR__ . '/lib.php';

// Once installed, the installer is permanently sealed.
if (is_locked()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden — SysRevAI is already installed.\n";
    echo "To reinstall, remove config/installed.lock manually via SSH.";
    exit;
}

installer_session_start();

/* ── Handle POST actions (navigation, locale) ──────────────────────────── */

$flash_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(400);
        exit('Invalid CSRF token. Reload the installer and try again.');
    }

    $action = (string) ($_POST['action'] ?? '');
    $from   = (int) ($_POST['step'] ?? 0);

    $redirect = static function (int $step, bool $error = false): never {
        // Use a path that anchors at the installer directory so the URL
        // can never spiral when the request URI already contains stray
        // segments (e.g. /install/install/...).
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/');
        header('Location: ' . $base . '/index.php?step=' . $step . ($error ? '&error=1' : ''));
        exit;
    };

    switch ($action) {
        case 'set_locale':
            $loc = (string) ($_POST['locale'] ?? 'ca');
            if (in_array($loc, ['ca', 'es', 'en'], true)) {
                $_SESSION['install']['locale'] = $loc;
            }
            $redirect(0);

        case 'back':
            $redirect(max(0, $from - 1));

        // Step 2 — attempt Composer install.
        case 'deps_install':
            $_SESSION['install']['deps_result'] = attempt_composer_install();
            $redirect(2);

        // Step 3 — test the DB connection (and optionally create the database).
        case 'db_test':
            $cfg = [
                'host'     => trim((string) ($_POST['host'] ?? '127.0.0.1')),
                'port'     => (int) ($_POST['port'] ?? 3306),
                'database' => trim((string) ($_POST['database'] ?? '')),
                'username' => trim((string) ($_POST['username'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
                'prefix'   => preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($_POST['prefix'] ?? 'sra_')) ?? 'sra_',
                'charset'  => preg_replace('/[^a-z0-9]/', '', strtolower((string) ($_POST['charset'] ?? 'utf8mb4'))) ?: 'utf8mb4',
                'create'   => !empty($_POST['create_db']),
            ];
            $_SESSION['install']['db'] = $cfg;
            $res = db_test($cfg, $cfg['create']);
            $_SESSION['install']['db_test_result'] = $res;
            $_SESSION['install']['db_tested'] = $res['ok'];
            $redirect(3);

        // Step 4 — run migrations + seed.
        case 'run_migrations':
            $cfg = $_SESSION['install']['db'] ?? null;
            if (!$cfg) {
                $redirect(3, true);
            }
            $res = run_migrations($cfg);
            if ($res['ok']) {
                seed_defaults($cfg, []);
                $_SESSION['install']['migrated'] = true;
            }
            $_SESSION['install']['migrate_result'] = $res;
            $redirect(4);

        // Step 7 — finalize. Renders inline (cannot redirect: the lock seals re-entry).
        case 'finalize':
            $result = [];
            $db      = $_SESSION['install']['db'] ?? [];
            $general = $_SESSION['install']['general'] ?? [];
            $admin   = $_SESSION['install']['admin'] ?? [];
            $appKey  = generate_app_key();

            $result['env']   = write_env(['db' => $db, 'general' => $general, 'app_key' => $appKey]);
            $result['admin'] = create_admin($db, $admin);
            seed_defaults($db, $general); // update defaults with the real general settings

            if ($result['env']['ok'] && $result['admin']['ok']) {
                $result['lock'] = write_lock($appKey);
            } else {
                $result['lock'] = ['ok' => false, 'error' => 'skipped (previous step failed)'];
            }
            $result['success'] = $result['env']['ok'] && $result['admin']['ok'] && $result['lock']['ok'];
            $_SESSION['install']['finalize_result'] = $result;
            install_log(7, $result['success'] ? 'ok' : 'fail', 'finalize');
            // Fall through to render step 7 inline.
            $step          = 7;
            $forcedRender  = true;
            break;

        case 'next':
            // Per-step capture + validation before advancing.
            $valid = true;
            switch ($from) {
                case 1:
                    $valid = check_requirements()['blocking_ok'];
                    break;
                case 3:
                    $valid = !empty($_SESSION['install']['db_tested']);
                    break;
                case 4:
                    $valid = !empty($_SESSION['install']['migrated']);
                    break;
                case 5:
                    $_SESSION['install']['general'] = [
                        'site_name'   => trim((string) ($_POST['site_name'] ?? 'SysRevAI')) ?: 'SysRevAI',
                        'app_url'     => trim((string) ($_POST['base_url'] ?? detect_base_url())),
                        'locale'      => in_array(($_POST['default_lang'] ?? 'ca'), ['ca', 'es', 'en'], true) ? $_POST['default_lang'] : 'ca',
                        'timezone'    => trim((string) ($_POST['timezone'] ?? 'Europe/Madrid')) ?: 'Europe/Madrid',
                        'force_https' => !empty($_POST['force_https']),
                    ];
                    break;
                case 6:
                    $errors = [];
                    $name  = trim((string) ($_POST['full_name'] ?? ''));
                    $email = trim((string) ($_POST['email'] ?? ''));
                    $pw    = (string) ($_POST['password'] ?? '');
                    $pw2   = (string) ($_POST['confirm'] ?? '');
                    $loc   = in_array(($_POST['preferred_lang'] ?? 'ca'), ['ca', 'es', 'en'], true) ? $_POST['preferred_lang'] : 'ca';

                    if ($name === '') {
                        $errors[] = t('step6.name_required');
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = t('step6.email_invalid');
                    }
                    if (!password_meets_policy($pw)) {
                        $errors[] = t('step6.pw_weak');
                    }
                    if ($pw !== $pw2) {
                        $errors[] = t('step6.pw_mismatch');
                    }

                    if ($errors === []) {
                        $_SESSION['install']['admin'] = [
                            'name'          => $name,
                            'email'         => $email,
                            'password_hash' => password_hash($pw, PASSWORD_ARGON2ID),
                            'locale'        => $loc,
                        ];
                        unset($_SESSION['install']['step6_errors']);
                    } else {
                        $_SESSION['install']['step6_errors'] = $errors;
                        $_SESSION['install']['step6_old'] = ['name' => $name, 'email' => $email, 'locale' => $loc];
                        $valid = false;
                    }
                    break;
            }

            if ($valid) {
                mark_step_complete($from);
                install_log($from, 'ok', 'step completed');
                $redirect(min($from + 1, INSTALLER_TOTAL_STEPS - 1));
            }
            install_log($from, 'fail', 'validation failed at step ' . $from);
            $redirect($from, true);
    }
}

/* ── Resolve the step to render ────────────────────────────────────────── */

if (empty($forcedRender)) {
    $requested = isset($_GET['step']) ? (int) $_GET['step'] : 0;
    $step      = resolve_step($requested);
}
$hasError  = isset($_GET['error']);
$L         = lang();

$stepFile = __DIR__ . "/steps/step{$step}.php";
if (!is_file($stepFile)) {
    $step     = 0;
    $stepFile = __DIR__ . '/steps/step0.php';
}

?>
<!DOCTYPE html>
<html lang="<?= h(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= h(t('installer_title')) ?> — <?= h(t('steps.' . $step)) ?></title>
    <link rel="stylesheet" href="assets/installer.css">
</head>
<body>
<div class="installer">
    <header class="installer__header">
        <div class="brand">
            <span class="brand__mark">SR</span>
            <span class="brand__name"><?= h(t('app_name')) ?></span>
        </div>
        <p class="installer__progress-label">
            <?= h(t('progress', $step + 1, INSTALLER_TOTAL_STEPS)) ?>
            — <?= h(t('steps.' . $step)) ?>
        </p>
        <div class="progress" role="progressbar"
             aria-valuemin="1" aria-valuemax="<?= INSTALLER_TOTAL_STEPS ?>"
             aria-valuenow="<?= $step + 1 ?>">
            <div class="progress__bar"
                 style="width: <?= (int) round((($step + 1) / INSTALLER_TOTAL_STEPS) * 100) ?>%"></div>
        </div>
        <ol class="steps-mini">
            <?php for ($i = 0; $i < INSTALLER_TOTAL_STEPS; $i++): ?>
                <li class="steps-mini__item
                    <?= $i === $step ? 'is-active' : '' ?>
                    <?= step_completed($i) ? 'is-done' : '' ?>">
                    <span class="steps-mini__dot"><?= $i + 1 ?></span>
                    <span class="steps-mini__label"><?= h(t('steps.' . $i)) ?></span>
                </li>
            <?php endfor; ?>
        </ol>
    </header>

    <main class="installer__body">
        <?php if ($hasError && $flash_error === null): ?>
            <div class="alert alert--error"><?= h(t('step1.fix_needed')) ?></div>
        <?php endif; ?>
        <?php require $stepFile; ?>
    </main>

    <footer class="installer__footer">
        <span><?= h(t('app_name')) ?> · Open source (AGPL-3.0)</span>
    </footer>
</div>
</body>
</html>
