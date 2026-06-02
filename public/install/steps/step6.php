<?php

declare(strict_types=1);

/** Step 6 — Administrator account. */

if (!defined('INSTALLER_TOTAL_STEPS')) {
    exit;
}

$errors = $_SESSION['install']['step6_errors'] ?? [];
$old    = $_SESSION['install']['step6_old'] ?? [];
unset($_SESSION['install']['step6_errors']);

$name   = h((string) ($old['name'] ?? ''));
$email  = h((string) ($old['email'] ?? ''));
$locale = (string) ($old['locale'] ?? current_locale());
?>

<section class="card">
    <h1 class="card__title"><?= h(t('step6.title')) ?></h1>
    <p class="lead"><?= h(t('step6.intro')) ?></p>

    <?php if ($errors !== []): ?>
        <div class="alert alert--error">
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="index.php" class="form-grid" id="step6form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="next">
        <input type="hidden" name="step" value="6">

        <div class="field">
            <label class="field-label" for="full_name"><?= h(t('step6.full_name')) ?></label>
            <input class="input" id="full_name" name="full_name" value="<?= $name ?>" required>
        </div>

        <div class="field">
            <label class="field-label" for="email"><?= h(t('step6.email')) ?></label>
            <input class="input" id="email" name="email" type="email" value="<?= $email ?>" required>
        </div>

        <div class="form-row form-row--split">
            <div class="field">
                <label class="field-label" for="password"><?= h(t('step6.password')) ?></label>
                <input class="input" id="password" name="password" type="password" required
                       oninput="srStrength(this.value)">
                <span class="field-help"><?= h(t('step6.pw_req')) ?></span>
                <div class="pw-meter"><div class="pw-meter__bar" id="pwbar"></div></div>
                <span class="pw-meter__label" id="pwlabel"></span>
            </div>
            <div class="field">
                <label class="field-label" for="confirm"><?= h(t('step6.confirm')) ?></label>
                <input class="input" id="confirm" name="confirm" type="password" required>
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="preferred_lang"><?= h(t('step6.preferred_lang')) ?></label>
            <select class="select" id="preferred_lang" name="preferred_lang">
                <?php foreach (['ca' => 'Català', 'es' => 'Español', 'en' => 'English'] as $c => $n): ?>
                    <option value="<?= $c ?>" <?= $locale === $c ? 'selected' : '' ?>><?= h($n) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Acceptance of legal documents. We open the live preview routes
             that the installer wires up in installer.php (see step6_preview)
             so the admin-to-be can read the rendered templates with a
             generic placeholder substitution before agreeing. -->
        <label class="checkbox">
            <input type="checkbox" name="accept_legal" value="1" required
                   <?= !empty($old['accept_legal']) ? 'checked' : '' ?>>
            <?= h(t('step6.accept_legal_prefix')) ?>
            <a href="?action=preview_legal&doc=privacy" target="_blank" rel="noopener noreferrer"><?= h(t('step6.privacy_link')) ?></a>
            <?= h(t('step6.and')) ?>
            <a href="?action=preview_legal&doc=terms" target="_blank" rel="noopener noreferrer"><?= h(t('step6.terms_link')) ?></a>.
        </label>
    </form>

    <div class="actions">
        <form method="post" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="back">
            <input type="hidden" name="step" value="6">
            <button type="submit" class="btn btn--ghost">&larr; <?= h(t('nav.back')) ?></button>
        </form>
        <button type="submit" form="step6form" class="btn btn--primary"><?= h(t('nav.next')) ?> &rarr;</button>
    </div>
</section>

<script>
(function () {
    var labels = [<?= json_encode(t('step6.strength_weak')) ?>, <?= json_encode(t('step6.strength_fair')) ?>, <?= json_encode(t('step6.strength_strong')) ?>];
    window.srStrength = function (pw) {
        var score = 0;
        if (pw.length >= 12) score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/\d/.test(pw) && /[^A-Za-z0-9]/.test(pw)) score++;
        var pct = (score / 3) * 100;
        var colors = ['#d55e00', '#e69f00', '#009e73'];
        var bar = document.getElementById('pwbar');
        var lab = document.getElementById('pwlabel');
        bar.style.width = pct + '%';
        bar.style.background = colors[Math.max(0, score - 1)] || '#dde3e9';
        lab.textContent = pw ? (labels[Math.max(0, score - 1)] || labels[0]) : '';
    };
})();
</script>
