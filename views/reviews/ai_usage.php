<?php

declare(strict_types=1);

/** @var array $review */
/** @var array<int,array<string,mixed>> $rows  newest-first */
/** @var array{input_tokens:int,output_tokens:int,cost_usd:float} $totals */
/** @var float $usdToEur */
/** @var array<string,array{0:float,1:float}> $priceTable  model => [in USD/Mtok, out USD/Mtok] */

$id        = (int) $review['id'];
$totalTok  = $totals['input_tokens'] + $totals['output_tokens'];
$totalUsd  = (float) $totals['cost_usd'];
$totalEur  = $totalUsd * $usdToEur;

$fmtTokens = static fn (int $n): string => number_format($n, 0, '.', ' ');
$fmtEur    = static function (float $eur): string {
    if ($eur < 0.005) {
        return number_format($eur, 5, ',', '.') . ' €';
    }
    return number_format($eur, $eur < 1 ? 4 : 2, ',', '.') . ' €';
};
$fmtUsd    = static fn (float $u): string => '$' . number_format($u, $u < 1 ? 5 : 2, '.', ',');
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('ai_usage.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('ai_usage.intro')) ?></p>
    </div>

    <!-- Totals strip: tokens spent, USD billed, EUR projection. Same
         four-card pattern as the screening stats so the page feels at
         home. -->
    <div class="screen-stats">
        <div class="screen-stat">
            <span class="screen-stat__value"><?= e($fmtTokens((int) $totals['input_tokens'])) ?></span>
            <span class="screen-stat__label"><?= e(__('ai_usage.input_tokens')) ?></span>
        </div>
        <div class="screen-stat screen-stat--team">
            <span class="screen-stat__value"><?= e($fmtTokens((int) $totals['output_tokens'])) ?></span>
            <span class="screen-stat__label"><?= e(__('ai_usage.output_tokens')) ?></span>
        </div>
        <div class="screen-stat screen-stat--done">
            <span class="screen-stat__value"><?= e($fmtTokens($totalTok)) ?></span>
            <span class="screen-stat__label"><?= e(__('ai_usage.total_tokens')) ?></span>
        </div>
        <div class="screen-stat screen-stat--total">
            <span class="screen-stat__value"><?= e($fmtEur($totalEur)) ?></span>
            <span class="screen-stat__label"><?= e(__('ai_usage.estimated_cost')) ?></span>
            <span class="screen-stat__pct"><?= e($fmtUsd($totalUsd)) ?></span>
        </div>
    </div>

    <p class="muted ai-usage-note">
        <?= e(__('ai_usage.eur_disclaimer', number_format($usdToEur, 3))) ?>
    </p>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('ai_usage.empty')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= e(__('ai_usage.col_when')) ?></th>
                        <th><?= e(__('ai_usage.col_feature')) ?></th>
                        <th><?= e(__('ai_usage.col_model')) ?></th>
                        <th class="num"><?= e(__('ai_usage.col_input')) ?></th>
                        <th class="num"><?= e(__('ai_usage.col_output')) ?></th>
                        <th class="num"><?= e(__('ai_usage.col_cost_eur')) ?></th>
                        <th class="num muted"><?= e(__('ai_usage.col_cost_usd')) ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $usd = (float) $r['cost_usd'];
                            $eur = $usd * $usdToEur;
                            $featureKey = 'ai_usage.feature_' . (string) $r['feature'];
                            $featureLabel = __($featureKey);
                            // The translator returns the key unchanged if
                            // there's no entry; fall back to the raw value.
                            if ($featureLabel === $featureKey) {
                                $featureLabel = (string) $r['feature'];
                            }
                        ?>
                            <tr>
                                <td class="muted"><?= e((string) $r['created_at']) ?></td>
                                <td><?= e($featureLabel) ?></td>
                                <td><code><?= e((string) $r['model']) ?></code></td>
                                <td class="num"><?= e($fmtTokens((int) $r['input_tokens'])) ?></td>
                                <td class="num"><?= e($fmtTokens((int) $r['output_tokens'])) ?></td>
                                <td class="num"><?= e($fmtEur($eur)) ?></td>
                                <td class="num muted"><?= e($fmtUsd($usd)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <details class="ai-usage-pricing">
            <summary><?= e(__('ai_usage.pricing_summary')) ?></summary>
            <table class="table table--compact">
                <thead><tr>
                    <th><?= e(__('ai_usage.col_model')) ?></th>
                    <th class="num"><?= e(__('ai_usage.col_in_rate')) ?></th>
                    <th class="num"><?= e(__('ai_usage.col_out_rate')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($priceTable as $model => $rates): ?>
                        <tr>
                            <td><code><?= e($model) ?></code></td>
                            <td class="num"><?= e(number_format($rates[0], 2, ',', '.')) ?> $</td>
                            <td class="num"><?= e(number_format($rates[1], 2, ',', '.')) ?> $</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="muted"><?= e(__('ai_usage.pricing_source')) ?></p>
        </details>
    <?php endif; ?>
</div>
