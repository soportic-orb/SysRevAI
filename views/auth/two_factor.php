<?php

declare(strict_types=1);

/** @var ?string $error */
?>
<div class="auth-card">
    <h1 class="auth-card__title"><?= e(__('auth.tfa_title')) ?></h1>
    <p class="lead"><?= e(__('auth.tfa_intro')) ?></p>

    <?php if ($error !== null): ?>
        <div class="alert alert--error"><?= e((string) $error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login/2fa" class="form-grid">
        <?= csrf_field() ?>
        <div class="field">
            <label class="field-label" for="code"><?= e(__('profile.tfa_code_label')) ?></label>
            <input class="input" id="code" name="code" type="text" inputmode="numeric"
                   autocomplete="one-time-code" pattern="\d{6}" maxlength="6"
                   placeholder="000000" required autofocus>
        </div>
        <div><button class="btn btn--primary btn--block"><?= e(__('auth.tfa_verify')) ?></button></div>
    </form>
</div>
