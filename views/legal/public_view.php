<?php

declare(strict_types=1);

/** @var string $docType  'privacy' or 'terms' */
/** @var string $title */
/** @var string $content  raw, trusted HTML (service-rendered) */
/** @var string $language */

$appName = (string) (setting('site.name') ?? config('app.name', 'SysRevAI'));
$path = $docType === 'privacy' ? '/privacy' : '/terms';

$langs = [
    'es' => __('admin.legal.lang_es'),
    'ca' => __('admin.legal.lang_ca'),
    'en' => __('admin.legal.lang_en'),
];
?>
<!DOCTYPE html>
<html lang="<?= e($language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
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
    <link rel="stylesheet" href="<?= e(asset('css/legal.css')) ?>">
</head>
<body class="legal-body">
    <header class="legal-header">
        <a href="/" class="legal-header__home">&larr; <?= e(__('legal.back_home')) ?></a>
        <nav class="legal-header__langs" aria-label="<?= e(__('legal.language')) ?>">
            <?php foreach ($langs as $code => $label): ?>
                <a class="legal-header__lang <?= $language === $code ? 'is-active' : '' ?>"
                   href="<?= e($path) ?>?lang=<?= e($code) ?>"
                   hreflang="<?= e($code) ?>"><?= e(strtoupper($code)) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="legal-container">
        <h1 class="legal-title"><?= e($title) ?></h1>
        <article class="legal-article">
            <?= $content /* trusted: built by LegalDocumentService from the on-disk template or admin-vetted custom HTML */ ?>
        </article>
    </main>
</body>
</html>
