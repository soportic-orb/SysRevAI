<?php

declare(strict_types=1);

/** @var ?string $error */
/** @var string $email */
?>
<section class="auth-card">
    <h1 class="auth-card__title"><?= e(__('auth.login_title')) ?></h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="form-grid">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="email"><?= e(__('auth.email')) ?></label>
            <input class="input" id="email" name="email" type="email"
                   value="<?= e($email ?? '') ?>" required autofocus autocomplete="username">
        </div>

        <div class="field">
            <label class="field-label" for="password"><?= e(__('auth.password')) ?></label>
            <input class="input" id="password" name="password" type="password"
                   required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn--primary btn--block"><?= e(__('auth.sign_in')) ?></button>
    </form>
</section>
