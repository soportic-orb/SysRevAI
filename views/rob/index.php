<?php

declare(strict_types=1);

use SysRevAI\Services\RiskOfBiasService;

/** @var array $review */
/** @var string $tool */
/** @var array $toolDef */
/** @var string[] $enabledTools */
/** @var array $traffic */
/** @var array $summary */
$id = (int) $review['id'];
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a> /</div>
            <h1 class="page__title"><?= e(__('rob.title')) ?></h1>
        </div>
        <form method="get" action="/reviews/<?= $id ?>/risk-of-bias">
            <select class="select select--sm" name="tool" onchange="this.form.submit()">
                <?php foreach ($enabledTools as $t): ?>
                    <option value="<?= e($t) ?>" <?= $t === $tool ? 'selected' : '' ?>><?= e(__('rob.tool_' . $t)) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($traffic === []): ?>
        <div class="empty-state"><p><?= e(__('rob.none')) ?></p></div>
    <?php else: ?>
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('rob.summary_title')) ?></h2>
            <div class="rob-summary"><canvas id="robSummary" height="220"></canvas></div>
            <div class="rob-legend">
                <?php foreach ($toolDef['judgements'] as $j): ?>
                    <span class="rob-legend__item">
                        <span class="rob-cell" style="background: <?= e(RiskOfBiasService::color($j)) ?>;"></span>
                        <?= e(__('rob.j_' . $j)) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-card" style="padding:0">
            <h2 class="section__subtitle" style="padding:18px 18px 0"><?= e(__('rob.traffic_title')) ?></h2>
            <div class="table-wrap">
                <table class="table rob-table">
                    <thead>
                        <tr>
                            <th><?= e(__('references.col_study')) ?></th>
                            <?php foreach ($toolDef['domains'] as $d): ?>
                                <th><?= e(__('rob.d_' . $d)) ?></th>
                            <?php endforeach; ?>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($traffic as $row): ?>
                        <tr>
                            <td><strong><?= e(mb_strimwidth((string) ($row['title'] ?? '—'), 0, 80, '…')) ?></strong></td>
                            <?php foreach ($toolDef['domains'] as $d): $j = $row['cells'][$d] ?? null; ?>
                                <td class="rob-cell-td" title="<?= $j ? e(__('rob.j_' . $j)) : '' ?>">
                                    <span class="rob-cell" style="background: <?= $j ? e(RiskOfBiasService::color($j)) : '#dde3e9' ?>;"></span>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/risk-of-bias/<?= (int) $row['reference_id'] ?>?tool=<?= e($tool) ?>">
                                    <?= e(__('rob.assess')) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
        <script>
        window.addEventListener('load', function () {
            if (typeof Chart === 'undefined') { return; }
            var labels = <?= json_encode(array_map(static fn ($d) => __('rob.d_' . $d), $summary['labels']), JSON_UNESCAPED_UNICODE) ?>;
            var datasets = <?= json_encode(array_map(static function ($ds) {
                return [
                    'label'           => __('rob.j_' . $ds['label']),
                    'backgroundColor' => $ds['backgroundColor'],
                    'data'            => $ds['data'],
                ];
            }, $summary['datasets']), JSON_UNESCAPED_UNICODE) ?>;
            new Chart(document.getElementById('robSummary'), {
                type: 'bar',
                data: { labels: labels, datasets: datasets },
                options: {
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    responsive: true,
                    scales: { x: { stacked: true, ticks: { precision: 0 } }, y: { stacked: true } }
                }
            });
        });
        </script>
    <?php endif; ?>
</div>
