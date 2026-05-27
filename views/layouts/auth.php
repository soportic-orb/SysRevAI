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
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="auth-body">
    <main class="auth-wrap">
        <div class="auth-brand">
            <span class="brand__mark">SR</span>
            <span class="brand__name"><?= e($appName) ?></span>
        </div>
        <?= $content ?>
    </main>
</body>
</html>
