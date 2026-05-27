<?php

declare(strict_types=1);

$minLen   = (int) (setting('security.min_password_length') ?? 12);
$maxTries = (int) (setting('security.max_login_attempts') ?? 5);
$lockout  = (int) (setting('security.lockout_minutes') ?? 15);
$lifetime = (int) (setting('security.session_lifetime') ?? config('security.session_lifetime', 120));
$https    = (bool) (setting('security.force_https') ?? config('security.force_https', false));
$tfa      = (string) (setting('security.two_factor_mode') ?? 'optional');
?>
<h1 class="section__title"><?= e(__('admin.sections.security')) ?></h1>

<form method="post" action="/admin/settings/security" class="form-grid section-card">
    <?= csrf_field() ?>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="min_password_length"><?= e(__('admin.security.min_password_length')) ?></label>
            <input class="input" id="min_password_length" name="min_password_length" type="number" min="8" value="<?= $minLen ?>">
        </div>
        <div class="field">
            <label class="field-label" for="session_lifetime"><?= e(__('admin.security.session_lifetime')) ?></label>
            <input class="input" id="session_lifetime" name="session_lifetime" type="number" min="5" value="<?= $lifetime ?>">
        </div>
    </div>

    <div class="form-row form-row--split">
        <div class="field">
            <label class="field-label" for="max_login_attempts"><?= e(__('admin.security.max_login_attempts')) ?></label>
            <input class="input" id="max_login_attempts" name="max_login_attempts" type="number" min="1" value="<?= $maxTries ?>">
        </div>
        <div class="field">
            <label class="field-label" for="lockout_minutes"><?= e(__('admin.security.lockout_minutes')) ?></label>
            <input class="input" id="lockout_minutes" name="lockout_minutes" type="number" min="1" value="<?= $lockout ?>">
        </div>
    </div>

    <div class="field">
        <label class="field-label" for="two_factor_mode"><?= e(__('admin.security.two_factor')) ?></label>
        <select class="select" id="two_factor_mode" name="two_factor_mode">
            <?php foreach (['disabled', 'optional', 'required'] as $m): ?>
                <option value="<?= $m ?>" <?= $tfa === $m ? 'selected' : '' ?>><?= e(__('admin.security.tfa_' . $m)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="force_https" value="1" <?= $https ? 'checked' : '' ?>>
        <?= e(__('admin.security.force_https')) ?>
    </label>

    <div><button type="submit" class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
</form>
