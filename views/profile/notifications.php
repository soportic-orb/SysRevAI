<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var string[] $types */
/** @var array $prefs */
/** @var string $active */
$enabled = static function (array $prefs, string $type, string $channel): bool {
    $default = $channel === 'in_app';
    return $prefs[$type][$channel] ?? $default;
};
?>
<div class="profile-layout">
    <?php require config('paths.base') . '/views/partials/profile_nav.php'; ?>

    <main class="profile-main">
        <h1 class="page__title"><?= e(__('profile.notifications_title')) ?></h1>
        <p class="page__subtitle"><?= e(__('profile.notifications_intro')) ?></p>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <form method="post" action="/profile/notifications" class="section-card">
        <?= csrf_field() ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(__('profile.event')) ?></th>
                    <th><?= e(__('profile.in_app')) ?></th>
                    <th><?= e(__('profile.email')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($types as $type): ?>
                    <tr>
                        <td><?= e(__('profile.type_' . $type)) ?></td>
                        <td><input type="checkbox" name="pref[<?= $type ?>][in_app]" value="1" <?= $enabled($prefs, $type, 'in_app') ? 'checked' : '' ?>></td>
                        <td><input type="checkbox" name="pref[<?= $type ?>][email]" value="1" <?= $enabled($prefs, $type, 'email') ? 'checked' : '' ?>></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:16px"><button class="btn btn--primary"><?= e(__('admin.save')) ?></button></div>
    </form>
    </main>
</div>
