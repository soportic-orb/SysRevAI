<?php

declare(strict_types=1);

/** Admin settings layout: topbar + section sidebar + content. */
/** @var string $content */
/** @var string $activeSection */
$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
$user    = auth_user();

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
    'legal'       => '/admin/legal/privacy',
    'about'       => '/admin/settings/about',
];
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> — <?= e(__('admin.title')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/sysrevai-icon.svg')) ?>">
    <script>
        (function () {
            try {
                var t = localStorage.getItem('sysrevai.theme');
                if (t !== 'light' && t !== 'dark') {
                    t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="topbar__brand" href="/dashboard">
        <img class="brand__mark" src="<?= e(asset('img/sysrevai-icon.svg')) ?>" alt="">
        <span class="brand__name"><?= e($appName) ?></span>
    </a>
    <nav class="topbar__nav">
        <a href="/dashboard">
            <?php $iconName = 'dashboard'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
            <?= e(__('nav.dashboard')) ?>
        </a>
        <a href="/reviews">
            <?php $iconName = 'reviews'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
            <?= e(__('nav.reviews')) ?>
        </a>
        <a href="/search">
            <?php $iconName = 'search'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
            <?= e(__('nav.search')) ?>
        </a>
        <a href="/citations">
            <?php $iconName = 'references'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
            <?= e(__('nav.citations')) ?>
        </a>
        <a href="/admin/settings" class="is-active">
            <?php $iconName = 'settings'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
            <?= e(__('nav.settings')) ?>
        </a>
    </nav>
    <div class="topbar__user">
        <?php require config('paths.base') . '/views/partials/theme_toggle.php'; ?>
        <?php if ($user !== null): ?>
            <?php
                try { $unread = \SysRevAI\Models\Notification::unreadCount((int) $user['id']); }
                catch (\Throwable) { $unread = 0; }
                require config('paths.base') . '/views/partials/notification_bell.php';
            ?>
            <a class="topbar__name" href="/profile">
                <?php $avatarUser = $user; $avatarSize = 28; require config('paths.base') . '/views/partials/avatar.php'; ?>
                <span class="topbar__name-text"><?= e((string) $user['name']) ?></span>
            </a>
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
    <span>
        <?= e($appName) ?> · <?= e(__('footer.powered_by')) ?>
        <a class="appfooter__github"
           href="https://github.com/soportic-orb/SysRevAI"
           target="_blank" rel="noopener noreferrer">
            <?php $iconName = 'github'; $iconClass = 'nav-icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
            <?= e(__('footer.github')) ?>
        </a>
        · <?= e(__('footer.author')) ?>
    </span>
    <a href="/privacy"><?= e(__('footer.privacy')) ?></a>
    <a href="/terms"><?= e(__('footer.terms')) ?></a>
    <?php
        $style = 'footer';
        $donateLabel = __('footer.support');
        require config('paths.base') . '/views/partials/donate_link.php';
    ?>
</footer>
<script>
    (function () {
        var btn = document.getElementById('themeToggle');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var html = document.documentElement;
            var next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            try { localStorage.setItem('sysrevai.theme', next); } catch (e) {}
        });
    })();
</script>

<?php require config('paths.base') . '/views/partials/ai_loading_overlay.php'; ?>

<?php require config('paths.base') . '/views/partials/toast_stack.php'; ?>

<?php require config('paths.base') . '/views/partials/confirm_modal.php'; ?>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
