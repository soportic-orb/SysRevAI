<?php

declare(strict_types=1);

/** Minimal layout for login and error pages. No donation link here by policy. */
/** @var string $content */
$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($appName) ?></title>
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
<body class="auth-body">
    <main class="auth-wrap">
        <div class="auth-brand">
            <img class="brand__mark" src="<?= e(asset('img/sysrevai-icon.svg')) ?>" alt="">
            <span class="brand__name"><?= e($appName) ?></span>
        </div>
        <?= $content ?>
    </main>
</body>
</html>
