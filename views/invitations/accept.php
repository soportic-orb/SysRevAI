<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $invitation */
/** @var ?array $review */
/** @var string $mode    'authed' | 'login' | 'register' */
/** @var int $minLen */

$tokenUrl = '/invite/' . rawurlencode((string) $invitation['token']);
$email    = (string) $invitation['email'];
$err      = Session::pullFlash('invite_error');
?>
<section class="auth-card auth-card--center">
    <h1 class="auth-card__title"><?= e(__('invite.title')) ?></h1>
    <p class="lead">
        <?= e(__('invite.body', $review['title'] ?? '—', (string) $invitation['role'])) ?>
    </p>

    <?php if ($err !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'authed'): ?>
        <form method="post" action="<?= e($tokenUrl) ?>/accept">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--primary btn--block">
                <?= e(__('invite.accept')) ?>
            </button>
        </form>
        <p style="margin-top:14px">
            <a href="/dashboard"><?= e(__('invite.decline')) ?></a>
        </p>

    <?php elseif ($mode === 'login'): ?>
        <p class="muted"><?= e(__('invite.login_intro', $email)) ?></p>
        <form method="post" action="<?= e($tokenUrl) ?>/accept" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="flow" value="login">

            <div class="field">
                <label class="field-label" for="email"><?= e(__('auth.email')) ?></label>
                <input class="input" id="email" type="email" value="<?= e($email) ?>" disabled>
            </div>

            <div class="field">
                <label class="field-label" for="password"><?= e(__('auth.password')) ?></label>
                <input class="input" id="password" name="password" type="password"
                       required autocomplete="current-password" autofocus>
            </div>

            <button type="submit" class="btn btn--primary btn--block">
                <?= e(__('invite.login_btn')) ?>
            </button>
        </form>

    <?php else: /* 'register' */ ?>
        <p class="muted"><?= e(__('invite.register_intro', $email)) ?></p>
        <form method="post" action="<?= e($tokenUrl) ?>/accept" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="flow" value="register">

            <div class="field">
                <label class="field-label" for="email"><?= e(__('auth.email')) ?></label>
                <input class="input" id="email" type="email" value="<?= e($email) ?>" disabled>
            </div>

            <div class="field">
                <label class="field-label" for="name"><?= e(__('profile.name')) ?></label>
                <input class="input" id="name" name="name" type="text" required autofocus>
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

            <button type="submit" class="btn btn--primary btn--block">
                <?= e(__('invite.register_btn')) ?>
            </button>
        </form>
    <?php endif; ?>
</section>
