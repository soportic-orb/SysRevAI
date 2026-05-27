<?php

declare(strict_types=1);

/** @var array $user */
/** @var array<string,int> $metrics */
$name = (string) ($user['name'] ?? '');
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('dashboard.greeting', $name)) ?></h1>
        <p class="page__subtitle"><?= e(__('dashboard.subtitle')) ?></p>
    </div>

    <div class="metrics">
        <?php foreach ($metrics as $key => $value): ?>
            <div class="metric">
                <span class="metric__value"><?= (int) $value ?></span>
                <span class="metric__label"><?= e(__('dashboard.metrics.' . $key)) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="empty-state">
        <p><?= e(__('dashboard.no_reviews')) ?></p>
        <a href="/reviews/new" class="btn btn--primary"><?= e(__('dashboard.create_first')) ?></a>
    </div>
</div>
