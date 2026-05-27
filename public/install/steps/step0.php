<?php

declare(strict_types=1);

/** Step 0 — Welcome & language selection. Rendered inside index.php. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit; // never call a step file directly
}
?>

<section class="card welcome">
    <div class="welcome__hero">
        <h1 class="welcome__title"><?= h(t('step0.welcome_title')) ?></h1>
        <p class="welcome__subtitle"><?= h(t('step0.welcome_subtitle')) ?></p>
    </div>

    <p class="lead"><?= h(t('step0.welcome_intro')) ?></p>

    <form method="post" action="index.php" class="lang-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_locale">
        <label for="locale" class="field-label"><?= h(t('step0.choose_language')) ?></label>
        <select id="locale" name="locale" class="select" onchange="this.form.submit()">
            <?php foreach (['ca' => 'Català', 'es' => 'Español', 'en' => 'English'] as $code => $name): ?>
                <option value="<?= h($code) ?>" <?= current_locale() === $code ? 'selected' : '' ?>>
                    <?= h($name) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <noscript>
            <button type="submit" class="btn btn--ghost btn--sm">OK</button>
        </noscript>
    </form>

    <div class="note">
        <span class="note__icon">i</span>
        <p><?= h(t('step0.note_no_keys')) ?></p>
    </div>

    <form method="post" action="index.php" class="actions">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="next">
        <input type="hidden" name="step" value="0">
        <span></span>
        <button type="submit" class="btn btn--primary"><?= h(t('nav.begin')) ?> &rarr;</button>
    </form>
</section>
