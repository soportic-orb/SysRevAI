<?php

declare(strict_types=1);

/** @var array $review */
$id = (int) $review['id'];
$modeKey = 'reviews.mode_' . ($review['screening_mode'] ?? 'double_blind');
?>
<a class="review-card" href="/reviews/<?= $id ?>">
    <div class="review-card__head">
        <h3 class="review-card__title"><?= e((string) $review['title']) ?></h3>
        <span class="tag tag--<?= e((string) $review['status']) ?>"><?= e(__('reviews.status_' . $review['status'])) ?></span>
    </div>
    <?php if (!empty($review['question'])): ?>
        <p class="review-card__q"><?= e(mb_strimwidth((string) $review['question'], 0, 140, '…')) ?></p>
    <?php endif; ?>
    <div class="review-card__meta">
        <span class="tag tag--soft"><?= e(__($modeKey)) ?></span>
    </div>
</a>
