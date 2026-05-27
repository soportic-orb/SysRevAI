<?php

declare(strict_types=1);

use SysRevAI\Core\View;

/** @var array $user */
/** @var array $reviews */
$name = (string) ($user['name'] ?? '');
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title"><?= e(__('dashboard.greeting', $name)) ?></h1>
            <p class="page__subtitle"><?= e(__('dashboard.subtitle')) ?></p>
        </div>
        <a href="/reviews/new" class="btn btn--primary"><?= e(__('reviews.new')) ?></a>
    </div>

    <?php if ($reviews === []): ?>
        <div class="empty-state">
            <p><?= e(__('dashboard.no_reviews')) ?></p>
            <a href="/reviews/new" class="btn btn--primary"><?= e(__('dashboard.create_first')) ?></a>
        </div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($reviews as $review): ?>
                <?php View::partial('partials/review_card', ['review' => $review]); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
