<?php

declare(strict_types=1);

/** Admin settings layout: topbar + section sidebar + content. */
/** @var string $content */
/** @var string $activeSection */
$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
$user    = auth_user();
$version = (string) config('app.version', '0.1.0-dev');

// Admin navigation: section key => URL. Settings-form sections live under
// /admin/settings/{key}; Users and Maintenance have their own controllers.
$nav = [
    'general'     => '/admin/settings/general',
    'claude'      => '/admin/settings/claude',
    'translate'   => '/admin/settings/translate',
    'email'       => '/admin/settings/email',
    'apis'        => '/admin/settings/apis',
    'security'    => '/admin/settings/security',
    'users'       => '/admin/users',
    'reviews'     => '/admin/settings/reviews',
    'files'       => '/admin/settings/files',
    'languages'   => '/admin/settings/languages',
    'fulltext'    => '/admin/settings/fulltext',
    'reports'     => '/admin/reports/fulltext-coverage',
    'maintenance' => '/admin/maintenance',
    'about'       => '/admin/settings/about',
];
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> — <?= e(__('admin.title')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="topbar__brand" href="/dashboard">
        <span class="brand__mark">SR</span>
        <span class="brand__name"><?= e($appName) ?></span>
    </a>
    <nav class="topbar__nav">
        <a href="/dashboard"><?= e(__('nav.dashboard')) ?></a>
        <a href="/reviews"><?= e(__('nav.reviews')) ?></a>
        <a href="/search"><?= e(__('nav.search')) ?></a>
        <a href="/admin/settings" class="is-active"><?= e(__('nav.settings')) ?></a>
    </nav>
    <div class="topbar__user">
        <?php if ($user !== null): ?>
            <?php
                try { $unread = \SysRevAI\Models\Notification::unreadCount((int) $user['id']); }
                catch (\Throwable) { $unread = 0; }
                require config('paths.base') . '/views/partials/notification_bell.php';
            ?>
            <a class="topbar__name" href="/profile/notifications"><?= e((string) $user['name']) ?></a>
            <form method="post" action="/logout" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm"><?= e(__('nav.logout')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</header>

<div class="admin">
    <aside class="admin__sidebar">
        <h2 class="admin__title"><?= e(__('admin.title')) ?></h2>
        <nav class="admin__nav">
            <?php foreach ($nav as $key => $url): ?>
                <a href="<?= e($url) ?>"
                   class="admin__link <?= $key === $activeSection ? 'is-active' : '' ?>">
                    <?= e(__('admin.sections.' . $key)) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <main class="admin__content">
        <?php if (($flash = \SysRevAI\Core\Session::pullFlash('admin_success')) !== null): ?>
            <div class="alert alert--success"><?= e((string) $flash) ?></div>
        <?php endif; ?>
        <?php if (($err = \SysRevAI\Core\Session::pullFlash('admin_error')) !== null): ?>
            <div class="alert alert--error"><?= e((string) $err) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
</div>

<footer class="appfooter">
    <span><?= e($appName) ?> v<?= e($version) ?> · <?= e(__('footer.powered_by')) ?></span>
    <?php
        $style = 'footer';
        $donateLabel = __('footer.support');
        require config('paths.base') . '/views/partials/donate_link.php';
    ?>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
