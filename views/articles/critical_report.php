<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array     $article */
/** @var bool      $isOwner */
/** @var ?array    $report */
/** @var ?array    $reportRow */
/** @var bool      $hasText */
/** @var string[]  $axes */
/** @var string[]  $languages Report-language whitelist ('ca' first = default). */
$id = (int) $article['id'];
// Language the current report was written in — '' for reports generated
// before the selector existed (those keep current-locale headings).
$reportLang = (string) ($report['language'] ?? '');
?>
<div class="page article-critical">
    <div class="page__head article-head">
        <div class="article-head__title">
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
                <form method="post" action="/tools/articles/<?= $id ?>/critical-report"
                      class="article-critical__generate-form"
                      data-ai-action
                      data-ai-estimate="60000"
                      data-ai-label="<?= e(__('articles.critical.working_label')) ?>">
                    <?= csrf_field() ?>
                    <label class="muted article-critical__lang">
                        <?= e(__('articles.critical.language_label')) ?>
                        <select class="select select--sm" name="report_language">
                            <?php foreach ($languages as $lng): ?>
                                <option value="<?= e($lng) ?>" <?= $lng === 'ca' ? 'selected' : '' ?>><?= e(__('articles.critical.lang_' . $lng)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
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
            <div class="article-critical__overall-cta">
                <a class="btn btn--primary btn--sm"
                   href="/tools/articles/<?= $id ?>?from_report=1"
                   title="<?= e(__('articles.critical.work_with_copilot_hint')) ?>">
                    &#10024; <?= e(__('articles.critical.work_with_copilot')) ?>
                </a>
            </div>
        </div>

        <div class="section-card article-critical__scores">
            <?php foreach ($axes as $axis):
                $score = (int) ($report[$axis] ?? 0);
                $note  = (string) ($report[$axis . '_note'] ?? '');
                $tone  = $score >= 80 ? 'success' : ($score >= 50 ? 'warn' : 'fail');
            ?>
                <div class="peer-review__axis">
                    <div class="peer-review__axis-head">
                        <span class="peer-review__axis-label"><?= e(__in($reportLang, 'articles.critical.axis_' . $axis)) ?></span>
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

        <?php
            // Helper: render a paragraph of prose, splitting on blank
            // lines so the model's multi-paragraph notes keep their shape.
            $renderProse = static function (string $text): void {
                foreach (preg_split('/\n{2,}/', trim($text)) ?: [] as $chunk) {
                    $chunk = trim((string) $chunk);
                    if ($chunk === '') {
                        continue;
                    }
                    echo '<p>' . nl2br(e($chunk)) . '</p>';
                }
            };
        ?>

        <?php if (!empty($report['summary'])): ?>
            <div class="section-card peer-review__block">
                <h2 class="section__subtitle"><?= e(__in($reportLang, 'articles.critical.h_summary')) ?></h2>
                <?php $renderProse((string) $report['summary']); ?>
            </div>
        <?php endif; ?>

        <?php
            $strengths  = (array) ($report['key_strengths']  ?? []);
            $weaknesses = (array) ($report['key_weaknesses'] ?? []);
        ?>
        <?php if ($strengths !== [] || $weaknesses !== []): ?>
            <div class="section-card article-critical__sw">
                <div class="article-critical__sw-grid">
                    <?php if ($strengths !== []): ?>
                        <div class="article-critical__sw-col article-critical__sw-col--strengths">
                            <h3 class="article-critical__sw-title"><?= e(__in($reportLang, 'articles.critical.h_key_strengths')) ?></h3>
                            <ul>
                                <?php foreach ($strengths as $it): ?>
                                    <li><?= e((string) $it) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <?php if ($weaknesses !== []): ?>
                        <div class="article-critical__sw-col article-critical__sw-col--weaknesses">
                            <h3 class="article-critical__sw-title"><?= e(__in($reportLang, 'articles.critical.h_key_weaknesses')) ?></h3>
                            <ul>
                                <?php foreach ($weaknesses as $it): ?>
                                    <li><?= e((string) $it) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['methodology_critique'])): ?>
            <div class="section-card peer-review__block">
                <h2 class="section__subtitle"><?= e(__in($reportLang, 'articles.critical.h_methodology_critique')) ?></h2>
                <?php $renderProse((string) $report['methodology_critique']); ?>
            </div>
        <?php endif; ?>

        <?php
            $statBullets = (array) ($report['statistical_concerns']  ?? []);
            $ethBullets  = (array) ($report['ethical_concerns']      ?? []);
            $repBullets  = (array) ($report['reproducibility_notes'] ?? []);
        ?>
        <?php if ($statBullets !== [] || $ethBullets !== [] || $repBullets !== []): ?>
            <div class="section-card article-critical__concerns">
                <?php foreach ([
                    ['title' => 'h_statistical_concerns',  'items' => $statBullets],
                    ['title' => 'h_ethical_concerns',      'items' => $ethBullets],
                    ['title' => 'h_reproducibility',       'items' => $repBullets],
                ] as $block): ?>
                    <?php if ($block['items'] !== []): ?>
                        <div class="article-critical__concern">
                            <h3 class="article-critical__concern-title"><?= e(__in($reportLang, 'articles.critical.' . $block['title'])) ?></h3>
                            <ul>
                                <?php foreach ($block['items'] as $it): ?>
                                    <li><?= e((string) $it) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['literature_positioning'])): ?>
            <div class="section-card peer-review__block">
                <h2 class="section__subtitle"><?= e(__in($reportLang, 'articles.critical.h_literature_positioning')) ?></h2>
                <?php $renderProse((string) $report['literature_positioning']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['publication_outlook'])): ?>
            <div class="section-card peer-review__block">
                <h2 class="section__subtitle"><?= e(__in($reportLang, 'articles.critical.h_publication_outlook')) ?></h2>
                <?php $renderProse((string) $report['publication_outlook']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($report['devils_advocate'])): ?>
            <div class="section-card peer-review__block peer-review__block--devil">
                <h2 class="section__subtitle"><?= e(__in($reportLang, 'articles.critical.h_devils_advocate')) ?></h2>
                <?php $renderProse((string) $report['devils_advocate']); ?>
            </div>
        <?php endif; ?>

        <?php $recs = (array) ($report['recommendations'] ?? []); ?>
        <?php if ($recs !== []): ?>
            <div class="section-card article-critical__recs">
                <h2 class="section__subtitle"><?= e(__in($reportLang, 'articles.critical.h_recommendations')) ?></h2>
                <div class="article-critical__rec-grid">
                    <?php foreach ($recs as $rec): ?>
                        <article class="article-critical__rec">
                            <h3 class="article-critical__rec-section"><?= e((string) ($rec['section'] ?? '')) ?></h3>
                            <?php $items = (array) ($rec['items'] ?? []); ?>
                            <?php if ($items !== []): ?>
                                <ul class="article-critical__rec-items">
                                    <?php foreach ($items as $it):
                                        $text     = is_array($it) ? (string) ($it['text'] ?? '') : (string) $it;
                                        $priority = is_array($it) ? (string) ($it['priority'] ?? 'medium') : 'medium';
                                    ?>
                                        <li class="article-critical__rec-item">
                                            <span class="article-critical__prio article-critical__prio--<?= e($priority) ?>"
                                                  title="<?= e(__in($reportLang, 'articles.critical.priority_' . $priority)) ?>"><?= e(__in($reportLang, 'articles.critical.priority_' . $priority)) ?></span>
                                            <span><?= e($text) ?></span>
                                        </li>
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
                  class="article-critical__generate-form"
                  data-ai-action
                  data-ai-estimate="60000"
                  data-ai-label="<?= e(__('articles.critical.working_label')) ?>"
                  data-confirm="<?= e(__('articles.critical.rerun_confirm')) ?>">
                <?= csrf_field() ?>
                <label class="muted article-critical__lang">
                    <?= e(__('articles.critical.language_label')) ?>
                    <select class="select select--sm" name="report_language">
                        <?php $selectedLang = in_array($reportLang, $languages, true) ? $reportLang : 'ca'; ?>
                        <?php foreach ($languages as $lng): ?>
                            <option value="<?= e($lng) ?>" <?= $lng === $selectedLang ? 'selected' : '' ?>><?= e(__('articles.critical.lang_' . $lng)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn--ghost btn--sm"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    &#10024; <?= e(__('articles.critical.rerun_btn')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
