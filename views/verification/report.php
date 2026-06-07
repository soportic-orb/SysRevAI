<?php

declare(strict_types=1);

/** @var array $report */
/** @var int   $cap */
$rows   = (array) ($report['rows']   ?? []);
$counts = (array) ($report['counts'] ?? []);
$capped = (bool)  ($report['capped'] ?? false);

$verdictStyles = [
    'verified'   => ['tag' => 'success', 'label' => 'verification.verdict_verified'],
    'partial'    => ['tag' => 'warn',    'label' => 'verification.verdict_partial'],
    'discrepant' => ['tag' => 'warn',    'label' => 'verification.verdict_discrepant'],
    'not_found'  => ['tag' => 'error',   'label' => 'verification.verdict_not_found'],
];
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('verification.report_title')) ?></h1>
        <p class="page__subtitle"><?= e(__('verification.report_intro')) ?></p>
    </div>

    <?php if ($capped): ?>
        <div class="alert alert--warn" data-no-toast>
            <?= e(__('verification.capped', $cap)) ?>
        </div>
    <?php endif; ?>

    <div class="section-card verify-summary">
        <?php foreach (['verified', 'partial', 'discrepant', 'not_found'] as $v): ?>
            <span class="tag tag--soft verify-summary__pill">
                <?= e(__($verdictStyles[$v]['label'])) ?>:
                <strong><?= (int) ($counts[$v] ?? 0) ?></strong>
            </span>
        <?php endforeach; ?>
        <a class="btn btn--ghost btn--sm verify-summary__back" href="/tools/verify-citations">
            <?= e(__('verification.back_btn')) ?>
        </a>
    </div>

    <div class="section-card" style="padding:0; margin-top:14px">
        <div class="table-wrap">
            <table class="table verify-table">
                <thead><tr>
                    <th><?= e(__('verification.col_input')) ?></th>
                    <th><?= e(__('verification.col_sources')) ?></th>
                    <th><?= e(__('verification.col_verdict')) ?></th>
                    <th><?= e(__('verification.col_notes')) ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $r):
                        $input   = (array) ($r['input']   ?? []);
                        $matches = (array) ($r['matches'] ?? []);
                        $diffs   = (array) ($r['diffs']   ?? []);
                        $verdict = (string) ($r['verdict'] ?? 'not_found');
                        $vMeta   = $verdictStyles[$verdict] ?? $verdictStyles['not_found'];
                    ?>
                        <tr>
                            <td class="verify-table__input">
                                <?php if (!empty($input['title'])): ?>
                                    <strong><?= e((string) $input['title']) ?></strong><br>
                                <?php endif; ?>
                                <?php if (!empty($input['translated_title'])): ?>
                                    <div class="muted verify-table__translated">
                                        <?= e(__('verification.translated_to')) ?>
                                        <em>«<?= e((string) $input['translated_title']) ?>»</em>
                                    </div>
                                <?php endif; ?>
                                <span class="muted">
                                    <?php if (!empty($input['year'])): ?><?= (int) $input['year'] ?> · <?php endif; ?>
                                    <?php if (!empty($input['journal'])): ?><em><?= e((string) $input['journal']) ?></em><?php endif; ?>
                                </span>
                                <?php if (!empty($input['doi']) || !empty($input['pmid'])): ?>
                                    <div class="muted" style="margin-top:4px">
                                        <?php if (!empty($input['doi'])): ?>DOI: <?= e((string) $input['doi']) ?><?php endif; ?>
                                        <?php if (!empty($input['doi']) && !empty($input['pmid'])): ?> · <?php endif; ?>
                                        <?php if (!empty($input['pmid'])): ?>PMID: <?= e((string) $input['pmid']) ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($matches === []): ?>
                                    <span class="muted"><?= e(__('verification.no_matches')) ?></span>
                                <?php else: ?>
                                    <?php foreach ($matches as $name => $m): ?>
                                        <span class="tag tag--soft verify-table__source">
                                            <?= e((string) $name) ?>
                                            <?php if (!empty($m['year'])): ?>
                                                · <?= (int) $m['year'] ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="tag tag--<?= e($vMeta['tag']) ?>">
                                    <?= e(__($vMeta['label'])) ?>
                                </span>
                            </td>
                            <td class="muted verify-table__notes">
                                <?php foreach ($diffs as $name => $issues): ?>
                                    <?php foreach ((array) $issues as $issue): ?>
                                        <div>
                                            <?= e((string) $name) ?>: <?= e(__('verification.issue_' . $issue)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
