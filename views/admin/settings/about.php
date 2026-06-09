<?php

declare(strict_types=1);

require_once config('paths.base') . '/config/donate.php';

$version  = (string) config('app.version', '0.1.0-dev');
?>
<h1 class="section__title"><?= e(__('admin.sections.about')) ?></h1>

<div class="section-card">
    <dl class="kv">
        <dt><?= e(__('admin.about.version')) ?></dt><dd><?= e($version) ?></dd>
        <dt><?= e(__('admin.about.license')) ?></dt>
        <dd>
            AGPL-3.0
            <span class="muted" style="display:block; font-size:13px; margin-top:4px">
                <?= e(__('admin.about.based_on_intro')) ?>
                <a href="https://github.com/imbad0202/academic-research-skills"
                   target="_blank" rel="noopener noreferrer">academic-research-skills</a>
                <?= e(__('admin.about.based_on_license')) ?>
            </span>
        </dd>
        <dt><?= e(__('admin.about.repo')) ?></dt>
        <dd><a href="https://github.com/soportic-orb/sysrevai" target="_blank" rel="noopener noreferrer">github.com/soportic-orb/sysrevai</a></dd>
    </dl>
</div>

<div class="section-card">
    <h2 class="section__subtitle"><?= e(__('admin.about.support_title')) ?></h2>
    <p class="section__intro"><?= e(__('admin.about.support_text')) ?></p>
    <a class="btn btn--donate" href="<?= e(DONATE_URL) ?>" target="_blank" rel="noopener noreferrer">
        &#10084; <?= e(__('admin.about.donate_btn')) ?>
    </a>
</div>
