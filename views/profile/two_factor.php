<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $user */
/** @var bool $enabled */
/** @var string $mode */
/** @var string $secret */
/** @var string $secretGroups */
/** @var string $otpauth */
/** @var string $active */
?>
<div class="profile-layout">
    <?php require config('paths.base') . '/views/partials/profile_nav.php'; ?>

    <main class="profile-main">
        <h1 class="page__title"><?= e(__('profile.tab_2fa')) ?></h1>

        <?php if (($flash = Session::pullFlash('success')) !== null): ?>
            <div class="alert alert--success"><?= e((string) $flash) ?></div>
        <?php endif; ?>
        <?php if (($err = Session::pullFlash('error')) !== null): ?>
            <div class="alert alert--error"><?= e((string) $err) ?></div>
        <?php endif; ?>

        <?php if ($enabled): ?>
            <div class="section-card">
                <p>
                    <span class="badge badge--ok">&#10003;</span>
                    <?= e(__('profile.tfa_status_on')) ?>
                </p>
                <p class="field-help"><?= e(__('profile.tfa_disable_intro')) ?></p>
            </div>
            <form method="post" action="/profile/two-factor/disable" class="form-grid section-card">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="current_password"><?= e(__('profile.password_current')) ?></label>
                    <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div><button class="btn btn--ghost"><?= e(__('profile.tfa_disable_btn')) ?></button></div>
            </form>
        <?php else: ?>
            <div class="section-card">
                <p class="section__intro"><?= e(__('profile.tfa_setup_intro')) ?></p>
                <ol class="profile-2fa-steps">
                    <li><?= e(__('profile.tfa_step1')) ?></li>
                    <li><?= e(__('profile.tfa_step2')) ?></li>
                    <li><?= e(__('profile.tfa_step3')) ?></li>
                </ol>

                <div class="tfa-key">
                    <label class="field-label"><?= e(__('profile.tfa_key')) ?></label>
                    <code class="tfa-key__value"><?= e($secretGroups) ?></code>
                    <p class="field-help">
                        <?= e(__('profile.tfa_uri_help')) ?>
                        <a class="link-ext" href="<?= e($otpauth) ?>"><?= e(__('profile.tfa_open_app')) ?></a>
                    </p>
                </div>
            </div>

            <form method="post" action="/profile/two-factor/enable" class="form-grid section-card">
                <?= csrf_field() ?>
                <div class="field">
                    <label class="field-label" for="code"><?= e(__('profile.tfa_code_label')) ?></label>
                    <input class="input" id="code" name="code" type="text" inputmode="numeric"
                           autocomplete="one-time-code" pattern="\d{6}" maxlength="6"
                           placeholder="000000" required>
                    <span class="field-help"><?= e(__('profile.tfa_code_help')) ?></span>
                </div>
                <div><button class="btn btn--primary"><?= e(__('profile.tfa_enable_btn')) ?></button></div>
            </form>
        <?php endif; ?>
    </main>
</div>
