<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $invitation */
/** @var int $minLen */
$old = (array) (Session::pullFlash('user_invite_old', []) ?? []);
$err = Session::pullFlash('user_invite_error');
$tokenUrl = '/user-invite/' . rawurlencode((string) $invitation['token']);
?>
<section class="auth-card">
    <h1 class="auth-card__title"><?= e(__('invite.user_title')) ?></h1>
    <p class="lead"><?= e(__('invite.user_intro', (string) $invitation['email'])) ?></p>

    <?php if ($err !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e($tokenUrl) ?>/accept" class="form-grid">
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="email"><?= e(__('auth.email')) ?></label>
            <input class="input" id="email" type="email" value="<?= e((string) $invitation['email']) ?>" disabled>
        </div>

        <div class="field">
            <label class="field-label" for="name"><?= e(__('profile.name')) ?></label>
            <input class="input" id="name" name="name" type="text"
                   value="<?= e((string) ($old['name'] ?? '')) ?>" required autofocus>
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
            <?= e(__('invite.user_accept_btn')) ?>
        </button>
    </form>
</section>
