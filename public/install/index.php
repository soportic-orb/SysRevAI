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

    if ($action === 'set_locale') {
        $loc = (string) ($_POST['locale'] ?? 'ca');
        if (in_array($loc, ['ca', 'es', 'en'], true)) {
            $_SESSION['install']['locale'] = $loc;
        }
        header('Location: index.php?step=0');
        exit;
    }

    if ($action === 'back') {
        header('Location: index.php?step=' . max(0, $from - 1));
        exit;
    }

    if ($action === 'next') {
        $valid = match ($from) {
            1       => check_requirements()['blocking_ok'],
            default => true, // step 0 + placeholder steps 2–7
        };

        if ($valid) {
            mark_step_complete($from);
            install_log($from, 'ok', 'step completed');
            $next = min($from + 1, INSTALLER_TOTAL_STEPS - 1);
            header('Location: index.php?step=' . $next);
            exit;
        }

        install_log($from, 'fail', 'validation failed, cannot advance');
        $flash_error = t('step1.fix_needed');
        header('Location: index.php?step=' . $from . '&error=1');
        exit;
    }
}

/* ── Resolve the step to render ────────────────────────────────────────── */

$requested = isset($_GET['step']) ? (int) $_GET['step'] : 0;
$step      = resolve_step($requested);
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
