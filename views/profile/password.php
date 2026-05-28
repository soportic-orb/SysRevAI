<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $user */
/** @var string $active */
/** @var int $minLen */
?>
<div class="profile-layout">
    <?php require config('paths.base') . '/views/partials/profile_nav.php'; ?>

    <main class="profile-main">
        <h1 class="page__title"><?= e(__('profile.tab_password')) ?></h1>

        <?php if (($flash = Session::pullFlash('success')) !== null): ?>
            <div class="alert alert--success"><?= e((string) $flash) ?></div>
        <?php endif; ?>
        <?php if (($err = Session::pullFlash('error')) !== null): ?>
            <div class="alert alert--error"><?= e((string) $err) ?></div>
        <?php endif; ?>

        <form method="post" action="/profile/password" class="form-grid section-card">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="current_password"><?= e(__('profile.password_current')) ?></label>
                <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            </div>
            <div class="field">
                <label class="field-label" for="new_password"><?= e(__('profile.password_new')) ?></label>
                <input class="input" id="new_password" name="new_password" type="password" autocomplete="new-password" required>
                <span class="field-help"><?= e(__('profile.password_policy', $minLen)) ?></span>
            </div>
            <div class="field">
                <label class="field-label" for="confirm_password"><?= e(__('profile.password_confirm')) ?></label>
                <input class="input" id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required>
            </div>
            <div><button class="btn btn--primary"><?= e(__('profile.password_change')) ?></button></div>
        </form>
    </main>
</div>
