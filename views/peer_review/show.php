<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array  $review */
/** @var array  $reference */
/** @var ?array $rubric */
/** @var ?array $rubricRow */
/** @var bool   $hasText */
/** @var string[] $axes */
$id    = (int) $review['id'];
$refId = (int) $reference['id'];
?>
<div class="page">
    <div class="page__head">
        <div class="breadcrumb">
            <a href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a> /
        </div>
        <h1 class="page__title"><?= e(__('peer_review.title')) ?></h1>
        <p class="page__subtitle muted">
            <strong><?= e((string) ($reference['title'] ?: '—')) ?></strong>
            <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
            <?php if (!empty($reference['journal'])): ?> · <em><?= e((string) $reference['journal']) ?></em><?php endif; ?>
        </p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($rubric === null): ?>
        <div class="section-card peer-review__empty">
            <?php if (!$hasText): ?>
                <p><?= e(__('peer_review.empty_no_text')) ?></p>
                <a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/references">
                    <?= e(__('peer_review.back_btn')) ?>
                </a>
            <?php else: ?>
                <p><?= e(__('peer_review.empty_intro')) ?></p>
                <form method="post" action="/reviews/<?= $id ?>/references/<?= $refId ?>/peer-review" data-ai-action>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        &#10024; <?= e(__('peer_review.generate_btn')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="section-card peer-review__overall">
            <p class="peer-review__overall-text"><?= e((string) ($rubric['overall'] ?? '')) ?></p>
            <?php if ($rubricRow !== null && !empty($rubricRow['updated_at'])): ?>
                <p class="muted peer-review__meta">
                    <?= e(__('peer_review.generated_at', (string) $rubricRow['updated_at'])) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="section-card peer-review__scores">
            <?php foreach ($axes as $axis):
                $score = (int) ($rubric[$axis] ?? 0);
                $note  = (string) ($rubric[$axis . '_note'] ?? '');
                $tone  = $score >= 80 ? 'success' : ($score >= 50 ? 'warn' : 'fail');
            ?>
                <div class="peer-review__axis">
                    <div class="peer-review__axis-head">
                        <span class="peer-review__axis-label"><?= e(__('peer_review.axis_' . $axis)) ?></span>
                        <span class="peer-review__axis-score peer-review__axis-score--<?= e($tone) ?>"><?= $score ?>/100</span>
                    </div>
                    <div class="peer-review__axis-bar">
                        <span class="peer-review__axis-bar-fill peer-review__axis-bar-fill--<?= e($tone) ?>"
                              style="width: <?= $score ?>%"></span>
                    </div>
                    <?php if ($note !== ''): ?>
                        <p class="muted peer-review__axis-note"><?= e($note) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($rubric['summary'])): ?>
            <div class="section-card peer-review__block">
                <h2 class="section__subtitle"><?= e(__('peer_review.h_summary')) ?></h2>
                <p><?= e((string) $rubric['summary']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($rubric['devils_advocate'])): ?>
            <div class="section-card peer-review__block peer-review__block--devil">
                <h2 class="section__subtitle"><?= e(__('peer_review.h_devils_advocate')) ?></h2>
                <p><?= e((string) $rubric['devils_advocate']) ?></p>
            </div>
        <?php endif; ?>

        <div class="peer-review__rerun">
            <form method="post" action="/reviews/<?= $id ?>/references/<?= $refId ?>/peer-review"
                  data-ai-action
                  data-confirm="<?= e(__('peer_review.rerun_confirm')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    &#10024; <?= e(__('peer_review.rerun_btn')) ?>
                </button>
            </form>
            <a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/references">
                <?= e(__('peer_review.back_btn')) ?>
            </a>
        </div>
    <?php endif; ?>
</div>
