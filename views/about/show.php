<?php

declare(strict_types=1);

/** @var string $version */
?>
<section class="auth-card">
    <h1 class="auth-card__title"><?= e(__('about.title')) ?></h1>
    <p class="lead"><?= e(__('about.tagline')) ?></p>

    <dl class="kv">
        <dt><?= e(__('admin.about.version')) ?></dt><dd><?= e($version) ?></dd>
        <dt><?= e(__('admin.about.license')) ?></dt><dd>AGPL-3.0</dd>
        <dt><?= e(__('admin.about.repo')) ?></dt>
        <dd><a href="https://github.com/soportic-orb/sysrevai" target="_blank" rel="noopener noreferrer">github.com/soportic-orb/sysrevai</a></dd>
    </dl>

    <p class="lead" style="margin-top:18px"><?= e(__('admin.about.support_text')) ?></p>
    <a class="btn btn--donate btn--block" href="<?= e(DONATE_URL) ?>" target="_blank" rel="noopener noreferrer">
        &#10084; <?= e(__('admin.about.donate_btn')) ?>
    </a>

    <p style="margin-top:18px; text-align:center"><a href="/login"><?= e(__('about.back_login')) ?></a></p>
</section>
