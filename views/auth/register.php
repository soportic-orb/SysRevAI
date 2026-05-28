<?php

declare(strict_types=1);

/** @var ?string $error */
/** @var array $old */
/** @var int $minLen */
/** @var string $emailDomain */
/** @var bool $manualApprove */
?>
<section class="auth-card">
    <h1 class="auth-card__title"><?= e(__('auth.register_title')) ?></h1>
    <p class="lead">
        <?= e($manualApprove ? __('auth.register_intro_manual') : __('auth.register_intro_open')) ?>
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/register" class="form-grid">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="name"><?= e(__('profile.name')) ?></label>
            <input class="input" id="name" name="name" type="text"
                   value="<?= e((string) ($old['name'] ?? '')) ?>" required autofocus>
        </div>

        <div class="field">
            <label class="field-label" for="email"><?= e(__('auth.email')) ?></label>
            <input class="input" id="email" name="email" type="email"
                   value="<?= e((string) ($old['email'] ?? '')) ?>" required autocomplete="email"
                   <?php if ($emailDomain !== ''): ?>placeholder="usuario@<?= e($emailDomain) ?>"<?php endif; ?>>
            <?php if ($emailDomain !== ''): ?>
                <span class="field-help"><?= e(__('auth.register_domain_help', $emailDomain)) ?></span>
            <?php endif; ?>
        </div>

        <div class="field">
            <label class="field-label" for="password"><?= e(__('auth.password')) ?></label>
            <input class="input" id="password" name="password" type="password"
                   required autocomplete="new-password" minlength="<?= $minLen ?>">
            <span class="field-help"><?= e(__('profile.password_policy', $minLen)) ?></span>
        </div>

        <div class="field">
            <label class="field-label" for="confirm"><?= e(__('profile.password_confirm')) ?></label>
            <input class="input" id="confirm" name="confirm" type="password"
                   required autocomplete="new-password" minlength="<?= $minLen ?>">
        </div>

        <button type="submit" class="btn btn--primary btn--block"><?= e(__('auth.register_submit')) ?></button>
    </form>

    <p class="auth-card__footer">
        <?= e(__('auth.have_account')) ?>
        <a href="/login"><?= e(__('auth.sign_in')) ?></a>
    </p>
</section>
