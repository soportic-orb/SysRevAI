<?php

declare(strict_types=1);

/** @var string $page     'privacy' or 'terms' */
/** @var string $title */
/** @var string $bodyHtml — raw admin-managed HTML */
?>
<div class="page page--narrow legal-page">
    <div class="page__head">
        <h1 class="page__title"><?= e($title) ?></h1>
    </div>

    <article class="section-card legal-body">
        <?php if (trim($bodyHtml) === ''): ?>
            <p class="muted"><?= e(__('legal.not_published_yet')) ?></p>
        <?php else: ?>
            <?= $bodyHtml /* trusted: edited by admins via /admin/settings/legal */ ?>
        <?php endif; ?>
    </article>
</div>
