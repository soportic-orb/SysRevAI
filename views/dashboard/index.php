<?php

declare(strict_types=1);

use SysRevAI\Core\View;

/** @var array $user */
/** @var array $reviews */
/** @var array $articles */
$name = (string) ($user['name'] ?? '');
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title"><?= e(__('dashboard.greeting', $name)) ?></h1>
            <p class="page__subtitle"><?= e(__('dashboard.subtitle')) ?></p>
        </div>
        <div class="btn-row">
            <a href="/tools/articles/new" class="btn btn--ghost"><?= e(__('articles.new_btn')) ?></a>
            <a href="/reviews/new" class="btn btn--primary"><?= e(__('reviews.new')) ?></a>
        </div>
    </div>

    <section class="dashboard-section">
        <header class="dashboard-section__head">
            <h2 class="dashboard-section__title"><?= e(__('dashboard.active_reviews')) ?></h2>
            <a class="dashboard-section__link" href="/reviews"><?= e(__('dashboard.view_all')) ?></a>
        </header>
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
    </section>

    <section class="dashboard-section">
        <header class="dashboard-section__head">
            <h2 class="dashboard-section__title"><?= e(__('dashboard.active_articles')) ?></h2>
            <a class="dashboard-section__link" href="/tools/articles"><?= e(__('dashboard.view_all')) ?></a>
        </header>
        <?php if ($articles === []): ?>
            <div class="empty-state">
                <p><?= e(__('dashboard.no_articles')) ?></p>
                <a href="/tools/articles/new" class="btn btn--primary"><?= e(__('dashboard.upload_first_article')) ?></a>
            </div>
        <?php else: ?>
            <div class="cards">
                <?php foreach ($articles as $article): ?>
                    <?php View::partial('partials/article_card', ['article' => $article]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
