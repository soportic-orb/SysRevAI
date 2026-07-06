<?php

declare(strict_types=1);

/** @var array $review */
/** @var array $rows */
/** @var string $basePath */
/** @var string $stageName */
$id = (int) $review['id'];
$basePath = $basePath ?? '/reviews/' . $id . '/screen';
$stageName = $stageName ?? __('screening.title');
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="<?= e($basePath) ?>"><?= e($stageName) ?></a> /</div>
        <h1 class="page__title"><?= e(__('screening.history_title')) ?></h1>
        <p class="page__subtitle muted"><?= e(__('screening.history_intro')) ?></p>
    </div>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('screening.history_none')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <ul class="history-list">
                <?php foreach ($rows as $r):
                    $authors = json_decode((string) $r['authors_json'], true) ?: [];
                ?>
                    <li class="history-list__row">
                        <a class="history-list__link" href="<?= e($basePath) ?>?reference_id=<?= (int) $r['reference_id'] ?>">
                            <div class="history-list__main">
                                <strong class="history-list__title"><?= e((string) ($r['title'] ?: '—')) ?></strong>
                                <span class="muted history-list__meta">
                                    <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                    <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                    <?php if (!empty($r['journal'])): ?> · <?= e((string) $r['journal']) ?><?php endif; ?>
                                </span>
                                <?php if (!empty($r['reason'])): ?>
                                    <span class="muted history-list__reason"><?= e((string) $r['reason']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="history-list__side">
                                <span class="tag tag--<?= e((string) $r['decision']) ?>"><?= e(__('screening.' . $r['decision'])) ?></span>
                                <span class="muted history-list__date"><?= e((string) $r['updated_at']) ?></span>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
