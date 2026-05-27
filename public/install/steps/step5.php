<?php

declare(strict_types=1);

/** Step 5 — General platform settings. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$g = $_SESSION['install']['general'] ?? [];
$siteName = h((string) ($g['site_name'] ?? 'SysRevAI'));
$baseUrl  = h((string) ($g['app_url'] ?? detect_base_url()));
$tz       = h((string) ($g['timezone'] ?? 'Europe/Madrid'));
$locale   = (string) ($g['locale'] ?? current_locale());
$https    = !empty($g['force_https']);
?>

<section class="card">
    <h1 class="card__title"><?= h(t('step5.title')) ?></h1>
    <p class="lead"><?= h(t('step5.intro')) ?></p>

    <form method="post" action="index.php" class="form-grid" id="step5form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="next">
        <input type="hidden" name="step" value="5">

        <div class="field">
            <label class="field-label" for="site_name"><?= h(t('step5.site_name')) ?></label>
            <input class="input" id="site_name" name="site_name" value="<?= $siteName ?>" required>
        </div>

        <div class="field">
            <label class="field-label" for="base_url"><?= h(t('step5.base_url')) ?></label>
            <input class="input" id="base_url" name="base_url" value="<?= $baseUrl ?>">
        </div>

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="default_lang"><?= h(t('step5.default_lang')) ?></label>
                <select class="select" id="default_lang" name="default_lang">
                    <?php foreach (['ca' => 'Català', 'es' => 'Español', 'en' => 'English'] as $c => $n): ?>
                        <option value="<?= $c ?>" <?= $locale === $c ? 'selected' : '' ?>><?= h($n) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="timezone"><?= h(t('step5.timezone')) ?></label>
                <input class="input" id="timezone" name="timezone" value="<?= $tz ?>">
            </div>
        </div>

        <div class="field">
            <label class="checkbox">
                <input type="checkbox" name="force_https" value="1" <?= $https ? 'checked' : '' ?>>
                <?= h(t('step5.force_https')) ?>
            </label>
        </div>

        <div class="note"><span class="note__icon">i</span><p><?= h(t('step5.note_keys')) ?></p></div>
    </form>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="5">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>
        <button type="submit" form="step5form" class="btn btn--primary"><?= h(t('nav.next')) ?> &rarr;</button>
    </div>
</section>
