<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $user */
/** @var string $active */
$locales = (array) config('supported_locales', ['ca', 'es', 'en']);
?>
<div class="profile-layout">
    <?php require config('paths.base') . '/views/partials/profile_nav.php'; ?>

    <main class="profile-main">
        <h1 class="page__title"><?= e(__('profile.tab_profile')) ?></h1>

        <?php if (($flash = Session::pullFlash('success')) !== null): ?>
            <div class="alert alert--success"><?= e((string) $flash) ?></div>
        <?php endif; ?>
        <?php if (($err = Session::pullFlash('error')) !== null): ?>
            <div class="alert alert--error"><?= e((string) $err) ?></div>
        <?php endif; ?>

        <section class="section-card profile-avatar-card">
            <h2 class="section__subtitle"><?= e(__('profile.avatar_title')) ?></h2>
            <p class="section__intro"><?= e(__('profile.avatar_intro')) ?></p>
            <div class="profile-avatar-row">
                <?php $avatarUser = $user; $avatarSize = 96; require config('paths.base') . '/views/partials/avatar.php'; ?>
                <div class="profile-avatar-actions">
                    <form method="post" action="/profile/avatar" enctype="multipart/form-data" class="form-grid">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label class="field-label" for="avatar"><?= e(__('profile.avatar_choose')) ?></label>
                            <input class="input" type="file" id="avatar" name="avatar"
                                   accept="image/jpeg,image/png,image/webp,image/gif" required>
                            <span class="field-help"><?= e(__('profile.avatar_help')) ?></span>
                        </div>
                        <div><button class="btn btn--primary"
                                     data-busy-label="<?= e(__('common.working')) ?>">
                            <?= e(__('profile.avatar_upload')) ?>
                        </button></div>
                    </form>
                    <?php if (!empty($user['avatar_path'])): ?>
                        <form method="post" action="/profile/avatar/delete" class="inline-form"
                              data-confirm="<?= e(__('profile.avatar_remove_confirm')) ?>"
                              data-confirm-button="<?= e(__('profile.avatar_remove')) ?>"
                              style="margin-top:8px">
                            <?= csrf_field() ?>
                            <button class="btn btn--ghost btn--sm" type="submit">
                                <?= e(__('profile.avatar_remove')) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <form method="post" action="/profile" class="form-grid section-card">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field-label" for="name"><?= e(__('profile.name')) ?></label>
                <input class="input" id="name" name="name" value="<?= e((string) $user['name']) ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="email"><?= e(__('profile.email')) ?></label>
                <input class="input" id="email" name="email" type="email" value="<?= e((string) $user['email']) ?>" required>
            </div>
            <div class="field">
                <label class="field-label" for="locale"><?= e(__('profile.locale')) ?></label>
                <select class="select" id="locale" name="locale">
                    <?php $names = ['ca' => 'Català', 'es' => 'Español', 'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'pt' => 'Português', 'it' => 'Italiano', 'eu' => 'Euskara', 'gl' => 'Galego']; ?>
                    <?php foreach ($locales as $loc): ?>
                        <option value="<?= e($loc) ?>" <?= ((string) $user['locale']) === $loc ? 'selected' : '' ?>>
                            <?= e($names[$loc] ?? $loc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field-label"><?= e(__('profile.role')) ?></label>
                <div class="muted"><?= e((string) $user['role']) ?></div>
            </div>
            <div><button class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
        </form>
    </main>
</div>
