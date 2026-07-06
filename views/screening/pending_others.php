<?php

declare(strict_types=1);

/** @var array $review */
/** @var array $rows Reference rows, each with a 'decisions' key (always exactly one entry here). */
/** @var string $basePath */
/** @var string $stageName */
$id = (int) $review['id'];
$basePath = $basePath ?? '/reviews/' . $id . '/screen';
$stageName = $stageName ?? __('screening.title');
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="<?= e($basePath) ?>"><?= e($stageName) ?></a> /</div>
        <h1 class="page__title"><?= e(__('screening.pending_others_title')) ?></h1>
        <p class="page__subtitle muted"><?= e(__('screening.pending_others_intro')) ?></p>
    </div>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('screening.pending_others_none')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <ul class="history-list">
                <?php foreach ($rows as $r):
                    $authors = json_decode((string) $r['authors_json'], true) ?: [];
                    $d = $r['decisions'][0] ?? null;
                ?>
                    <li class="history-list__row">
                        <a class="history-list__link" href="<?= e($basePath) ?>?reference_id=<?= (int) $r['id'] ?>">
                            <div class="history-list__main">
                                <strong class="history-list__title"><?= e((string) ($r['title'] ?: '—')) ?></strong>
                                <span class="muted history-list__meta">
                                    <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                    <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                    <?php if (!empty($r['journal'])): ?> · <?= e((string) $r['journal']) ?><?php endif; ?>
                                </span>
                            </div>
                            <?php if ($d !== null): ?>
                                <div class="history-list__side">
                                    <span class="tag tag--<?= e((string) $d['decision']) ?>"><?= e((string) $d['reviewer_name']) ?>: <?= e(__('screening.' . $d['decision'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
