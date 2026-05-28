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
