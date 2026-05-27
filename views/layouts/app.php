<?php

declare(strict_types=1);

/** Main application layout: top bar + content + footer. */
/** @var string $content */
$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
$user    = auth_user();
$version = (string) config('app.version', '0.1.0-dev');
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> — <?= e(__('nav.dashboard')) ?></title>
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
        <?php if (\SysRevAI\Core\Auth::hasRole('owner', 'admin')): ?>
            <a href="/admin/settings"><?= e(__('nav.settings')) ?></a>
        <?php endif; ?>
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

<main class="content">
    <?= $content ?>
</main>

<footer class="appfooter">
    <span><?= e($appName) ?> v<?= e($version) ?> · <?= e(__('footer.powered_by')) ?></span>
    <?php
        // Donation link — always present in the footer (policy), never a pop-up.
        $style = 'footer';
        $donateLabel = __('footer.support');
        require config('paths.base') . '/views/partials/donate_link.php';
    ?>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
