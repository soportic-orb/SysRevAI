<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array     $article */
/** @var bool      $isOwner */
/** @var ?array    $report */
/** @var ?array    $reportRow */
/** @var bool      $hasText */
/** @var string[]  $axes */
$id = (int) $article['id'];
?>
<div class="page article-critical">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title">
                <?= e((string) ($article['title'] ?: '—')) ?>
                <span class="muted">— <?= e(__('articles.critical.title')) ?></span>
            </h1>
            <p class="page__subtitle muted"><?= e(__('articles.critical.subtitle')) ?></p>
        </div>
        <?php
            $articleActionsActive = 'critical-report';
            require config('paths.base') . '/views/partials/article_actions.php';
        ?>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($report === null): ?>
        <div class="section-card article-critical__empty">
            <?php if (!$hasText): ?>
                <p><?= e(__('articles.critical.empty_no_text')) ?></p>
            <?php else: ?>
                <p><?= e(__('articles.critical.empty_intro')) ?></p>
                <form method="post" action="/tools/articles/<?= $id ?>/critical-report" data-ai-action>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn--primary"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        &#10024; <?= e(__('articles.critical.generate_btn')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="section-card article-critical__overall">
            <p class="article-critical__overall-text"><?= e((string) ($report['overall'] ?? '')) ?></p>
            <?php if ($reportRow !== null && !empty($reportRow['updated_at'])): ?>
                <p class="muted article-critical__meta">
                    <?= e(__('articles.critical.generated_at', (string) $reportRow['updated_at'])) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="section-card article-critical__scores">
            <?php foreach ($axes as $axis):
                $score = (int) ($report[$axis] ?? 0);
                $note  = (string) ($report[$axis . '_note'] ?? '');
                $tone  = $score >= 80 ? 'success' : ($score >= 50 ? 'warn' : 'fail');
            ?>
                <div class="peer-review__axis">
                    <div class="peer-review__axis-head">
                        <span class="peer-review__axis-label"><?= e(__('articles.critical.axis_' . $axis)) ?></span>
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

        <?php if (!empty($report['summary'])): ?>
            <div class="section-card peer-review__block">
                <h2 class="section__subtitle"><?= e(__('articles.critical.h_summary')) ?></h2>
                <p><?= e((string) $report['summary']) ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['devils_advocate'])): ?>
            <div class="section-card peer-review__block peer-review__block--devil">
                <h2 class="section__subtitle"><?= e(__('articles.critical.h_devils_advocate')) ?></h2>
                <p><?= e((string) $report['devils_advocate']) ?></p>
            </div>
        <?php endif; ?>

        <?php $recs = (array) ($report['recommendations'] ?? []); ?>
        <?php if ($recs !== []): ?>
            <div class="section-card article-critical__recs">
                <h2 class="section__subtitle"><?= e(__('articles.critical.h_recommendations')) ?></h2>
                <div class="article-critical__rec-grid">
                    <?php foreach ($recs as $rec): ?>
                        <article class="article-critical__rec">
                            <h3 class="article-critical__rec-section"><?= e((string) ($rec['section'] ?? '')) ?></h3>
                            <?php $items = (array) ($rec['items'] ?? []); ?>
                            <?php if ($items !== []): ?>
                                <ul class="article-critical__rec-items">
                                    <?php foreach ($items as $it): ?>
                                        <li><?= e((string) $it) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="article-critical__rerun">
            <form method="post" action="/tools/articles/<?= $id ?>/critical-report"
                  data-ai-action
                  data-confirm="<?= e(__('articles.critical.rerun_confirm')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    &#10024; <?= e(__('articles.critical.rerun_btn')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
