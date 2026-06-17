<?php

declare(strict_types=1);

/** @var array $review */
/** @var array $report */

$id      = (int) $review['id'];
$features = $report['features'] ?? [];
$totals   = $report['totals']   ?? ['calls' => 0, 'input_tokens' => 0, 'output_tokens' => 0, 'cost_usd' => 0.0];
$period   = $report['period']   ?? ['first_at' => null, 'last_at' => null];

$shortDate = static function (?string $ts): string {
    if (!$ts) return '—';
    $t = strtotime($ts);
    return $t !== false ? date('Y-m-d', $t) : (string) $ts;
};
?>
<div class="page ai-declaration-page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title"><?= e(__('ai_declaration.title')) ?></h1>
            <p class="page__subtitle muted">
                <?= e(sprintf(__('ai_declaration.subtitle'), (string) $review['title'])) ?>
            </p>
        </div>
        <div class="btn-row">
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/ai-declaration/word"
               title="<?= e(__('ai_declaration.export_word_title')) ?>">
                &#11015; <?= e(__('ai_declaration.export_word')) ?>
            </a>
            <a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/exports">
                ← <?= e(__('ai_declaration.back_to_exports')) ?>
            </a>
        </div>
    </div>

    <section class="section-card">
        <h2 class="section__subtitle"><?= e(__('ai_declaration.summary_heading')) ?></h2>
        <?php if ($features === []): ?>
            <p class="muted"><?= e(__('ai_declaration.summary_empty')) ?></p>
        <?php else: ?>
            <p>
                <?php
                $phaseLabels = array_map(static fn ($f) => $f['label'], $features);
                $joined = implode(', ', array_slice($phaseLabels, 0, -1));
                if (count($phaseLabels) >= 2) {
                    $joined .= ' ' . __('ai_declaration.and') . ' ' . end($phaseLabels);
                } else {
                    $joined = $phaseLabels[0] ?? '';
                }
                echo e(sprintf(__('ai_declaration.summary_intro'),
                    (string) $review['title'], $joined));
                ?>
                <?= e(sprintf(__('ai_declaration.summary_tail'), (int) $totals['calls'])) ?>
            </p>
            <ul class="ai-declaration__totals muted">
                <li><?= e(sprintf(__('ai_declaration.totals_calls'),
                        number_format((int) $totals['calls']))) ?></li>
                <li><?= e(sprintf(__('ai_declaration.totals_tokens_short'),
                        number_format((int) $totals['input_tokens']),
                        number_format((int) $totals['output_tokens']))) ?></li>
                <li><?= e(sprintf(__('ai_declaration.totals_period'),
                        $shortDate($period['first_at']), $shortDate($period['last_at']))) ?></li>
            </ul>
        <?php endif; ?>
    </section>

    <section class="section-card" style="padding:0">
        <div class="table-wrap">
            <table class="table ai-declaration__table">
                <thead>
                    <tr>
                        <th><?= e(__('ai_declaration.col_phase')) ?></th>
                        <th><?= e(__('ai_declaration.col_description')) ?></th>
                        <th class="num"><?= e(__('ai_declaration.col_calls')) ?></th>
                        <th><?= e(__('ai_declaration.col_period')) ?></th>
                        <th><?= e(__('ai_declaration.col_models')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($features === []): ?>
                        <tr><td colspan="5" class="muted" style="text-align:center; padding:18px">
                            <?= e(__('ai_declaration.empty_row')) ?>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($features as $f): ?>
                            <tr>
                                <td><strong><?= e($f['label']) ?></strong></td>
                                <td class="muted"><?= e($f['description']) ?></td>
                                <td class="num"><?= e((string) $f['calls']) ?></td>
                                <td class="muted">
                                    <?= e($shortDate($f['first_at']) . ' → ' . $shortDate($f['last_at'])) ?>
                                </td>
                                <td class="muted"><?= e(implode(', ', $f['models'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section-card">
        <h2 class="section__subtitle"><?= e(__('ai_declaration.closing_heading')) ?></h2>
        <p class="muted"><?= e(__('ai_declaration.closing_body')) ?></p>
    </section>
</div>
