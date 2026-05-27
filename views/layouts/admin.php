<?php

declare(strict_types=1);

/** Admin settings layout: topbar + section sidebar + content. */
/** @var string $content */
/** @var string $activeSection */
$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
$user    = auth_user();
$version = (string) config('app.version', '0.1.0-dev');

// Sections implemented so far. More land in subsequent phases.
$sections = ['general', 'claude', 'security', 'about'];
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
        <a href="/admin/settings" class="is-active"><?= e(__('nav.settings')) ?></a>
    </nav>
    <div class="topbar__user">
        <?php if ($user !== null): ?>
            <span class="topbar__name"><?= e((string) $user['name']) ?></span>
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
            <?php foreach ($sections as $s): ?>
                <a href="/admin/settings/<?= e($s) ?>"
                   class="admin__link <?= $s === $activeSection ? 'is-active' : '' ?>">
                    <?= e(__('admin.sections.' . $s)) ?>
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
</body>
</html>
