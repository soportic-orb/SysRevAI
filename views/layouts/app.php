<?php

declare(strict_types=1);

/** Main application layout: top bar + content + footer. */
/** @var string $content */
$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
$user    = auth_user();
$version = (string) config('app.version', '0.1.0-dev');

/* ── Review subnav context ──────────────────────────────────────────────────
 * Whenever the request path matches /reviews/{numeric-id}[/...], expose a
 * persistent secondary navigation bar under the topbar so the user always
 * has the review's actions one click away — even from /screen, /extraction
 * or any deep page inside the review. */
$reviewSubnav = null;
$reviewSubnavActive = '';
$_path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if (preg_match('#^/reviews/(\d+)(/([^/?]*))?#', $_path, $_m)) {
    $_rid = (int) $_m[1];
    try {
        $_r = \SysRevAI\Models\Review::find($_rid);
    } catch (\Throwable) {
        $_r = null;
    }
    if (is_array($_r)) {
        $reviewSubnav = $_r;
        // Resolve the "active" tab from the URL segment after the ID.
        $reviewSubnavActive = match ($_m[3] ?? '') {
            ''               => 'overview',
            'screen'         => 'screening',
            'full-text'      => 'fulltext',
            'extraction'     => 'extraction',
            'risk-of-bias'   => 'rob',
            'exports'        => 'exports',
            'references'     => 'references',
            'import'         => 'import',
            'team'           => 'team',
            'protocol'       => 'protocol',
            default          => '',
        };
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?> — <?= e(__('nav.dashboard')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/sysrevai-icon.svg')) ?>">
    <script>
        /* Boot-time theme: read from localStorage, fall back to the OS
         * preference. Applied before the stylesheet to avoid a flash of
         * light theme on dark-mode users. */
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
        <a href="/dashboard"><?= e(__('nav.dashboard')) ?></a>
        <a href="/reviews"><?= e(__('nav.reviews')) ?></a>
        <a href="/search"><?= e(__('nav.search')) ?></a>
        <?php if (\SysRevAI\Core\Auth::hasRole('owner', 'admin')): ?>
            <a href="/admin/settings"><?= e(__('nav.settings')) ?></a>
        <?php endif; ?>
    </nav>
    <div class="topbar__user">
        <?php require config('paths.base') . '/views/partials/theme_toggle.php'; ?>
        <?php if ($user !== null): ?>
            <?php
                try { $unread = \SysRevAI\Models\Notification::unreadCount((int) $user['id']); }
                catch (\Throwable) { $unread = 0; }
                require config('paths.base') . '/views/partials/notification_bell.php';
            ?>
            <a class="topbar__name" href="/profile"><?= e((string) $user['name']) ?></a>
            <form method="post" action="/logout" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm"><?= e(__('nav.logout')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</header>

<?php if ($reviewSubnav !== null): ?>
    <?php
        $review = $reviewSubnav;
        $activeKey = $reviewSubnavActive;
        require config('paths.base') . '/views/partials/review_subnav.php';
    ?>
<?php endif; ?>

<main class="content">
    <?= $content ?>
</main>

<footer class="appfooter">
    <span><?= e($appName) ?> v<?= e($version) ?> · <?= e(__('footer.powered_by')) ?> · <?= e(__('footer.author')) ?></span>
    <?php
        // Donation link — always present in the footer (policy), never a pop-up.
        $style = 'footer';
        $donateLabel = __('footer.support');
        require config('paths.base') . '/views/partials/donate_link.php';
    ?>
</footer>

<?php if ($reviewSubnav !== null): ?>
    <?php require config('paths.base') . '/views/partials/copilot_widget.php'; ?>
<?php endif; ?>

<script>
    /* Wire the sun/moon button to flip and persist the theme. */
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
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
