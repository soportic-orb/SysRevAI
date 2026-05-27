<?php

declare(strict_types=1);

/** Step 3 — Database configuration. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$db     = $_SESSION['install']['db'] ?? [];
$result = $_SESSION['install']['db_test_result'] ?? null;

$val = static fn (string $k, string $default = ''): string =>
    h((string) ($db[$k] ?? $default));
?>

<section class="card">
    <h1 class="card__title"><?= h(t('step3.title')) ?></h1>
    <p class="lead"><?= h(t('step3.intro')) ?></p>

    <?php if ($result !== null): ?>
        <div class="alert <?= $result['ok'] ? 'alert--success' : 'alert--error' ?>">
            <?php if ($result['ok']): ?>
                <?= h(t('step3.test_ok')) ?>
                <?php if (!empty($result['created'])): ?> — <?= h(t('step3.db_created')) ?><?php endif; ?>
            <?php else: ?>
                <?= h(t('step3.test_fail', $result['message'])) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="db_test">
        <input type="hidden" name="step" value="3">

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="host"><?= h(t('step3.host')) ?></label>
                <input class="input" id="host" name="host" value="<?= $val('host', '127.0.0.1') ?>" required>
            </div>
            <div class="field field--narrow">
                <label class="field-label" for="port"><?= h(t('step3.port')) ?></label>
                <input class="input" id="port" name="port" type="number" value="<?= $val('port', '3306') ?>" required>
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="database"><?= h(t('step3.database')) ?></label>
            <input class="input" id="database" name="database" value="<?= $val('database') ?>" required>
            <label class="checkbox">
                <input type="checkbox" name="create_db" value="1" <?= !empty($db['create']) ? 'checked' : '' ?>>
                <?= h(t('step3.create_db')) ?>
            </label>
        </div>

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="username"><?= h(t('step3.username')) ?></label>
                <input class="input" id="username" name="username" value="<?= $val('username') ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="password"><?= h(t('step3.password')) ?></label>
                <input class="input" id="password" name="password" type="password" value="<?= $val('password') ?>">
            </div>
        </div>

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="prefix"><?= h(t('step3.prefix')) ?></label>
                <input class="input" id="prefix" name="prefix" value="<?= $val('prefix', 'sra_') ?>">
                <span class="field-help"><?= h(t('step3.prefix_help')) ?></span>
            </div>
            <div class="field">
                <label class="field-label" for="charset"><?= h(t('step3.charset')) ?></label>
                <input class="input" id="charset" name="charset" value="<?= $val('charset', 'utf8mb4') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn--ghost"><?= h(t('step3.btn_test')) ?></button>
    </form>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="3">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="next">
            <input type="hidden" name="step" value="3">
            <button type="submit" class="btn btn--primary" <?= !empty($_SESSION['install']['db_tested']) ? '' : 'disabled title="' . h(t('step3.must_test')) . '"' ?>>
                <?= h(t('nav.next')) ?> &rarr;
            </button>
        </form>
    </div>
</section>
