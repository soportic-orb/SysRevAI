<?php

declare(strict_types=1);

use SysRevAI\Core\Session;
use SysRevAI\Core\View;

/** @var array $active */
/** @var array $archived */
?>
<div class="page">
    <div class="page__head page__head--row">
        <h1 class="page__title"><?= e(__('nav.reviews')) ?></h1>
        <a href="/reviews/new" class="btn btn--primary"><?= e(__('reviews.new')) ?></a>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <?php if ($active === [] && $archived === []): ?>
        <div class="empty-state">
            <p><?= e(__('dashboard.no_reviews')) ?></p>
            <a href="/reviews/new" class="btn btn--primary"><?= e(__('dashboard.create_first')) ?></a>
        </div>
    <?php else: ?>
        <?php if ($active !== []): ?>
            <div class="cards">
                <?php foreach ($active as $review): ?>
                    <?php View::partial('partials/review_card', ['review' => $review]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($archived !== []): ?>
            <h2 class="section__subtitle" style="margin-top:28px"><?= e(__('reviews.archived')) ?></h2>
            <div class="cards">
                <?php foreach ($archived as $review): ?>
                    <?php View::partial('partials/review_card', ['review' => $review]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
