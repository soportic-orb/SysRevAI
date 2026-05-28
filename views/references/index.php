<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $status */
/** @var string $search */
/** @var string[] $statuses */
/** @var array $metrics */
/** @var int $pendingDups */
/** @var array $ftStatus */
/** @var array $ftInFlight */
/** @var bool $ftEnabled */
$id = (int) $review['id'];
$pages = (int) ceil($total / $perPage);
$inFlight = array_flip($ftInFlight ?? []);

$ftIcon = static function (?array $row, bool $queued): array {
    if ($queued) {
        return ['class' => 'ft-dot ft-dot--queued', 'label' => __('fulltext.dot_queued')];
    }
    if ($row === null) {
        return ['class' => 'ft-dot ft-dot--never', 'label' => __('fulltext.dot_never')];
    }
    if ((int) $row['has_fulltext'] === 1) {
        $src = (string) ($row['fulltext_source'] ?? '?');
        return ['class' => 'ft-dot ft-dot--ok', 'label' => __('fulltext.dot_ok', $src)];
    }
    return ['class' => 'ft-dot ft-dot--none', 'label' => __('fulltext.dot_none')];
};
$qs = static function (array $extra) use ($status, $search): string {
    return http_build_query(array_merge(['status' => $status, 'q' => $search], $extra));
};
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a> /</div>
            <h1 class="page__title"><?= e(__('references.title')) ?> <span class="muted">(<?= $total ?>)</span></h1>
        </div>
        <div class="btn-row">
            <?php if ($pendingDups > 0): ?>
                <a class="btn btn--ghost" href="/reviews/<?= $id ?>/duplicates"><?= e(__('references.review_dups', $pendingDups)) ?></a>
            <?php endif; ?>
            <?php if ($ftEnabled): ?>
                <a class="btn btn--ghost" href="/reviews/<?= $id ?>/full-text-queue"><?= e(__('fulltext.queue_title')) ?></a>
                <a class="btn btn--ghost" href="/reviews/<?= $id ?>/full-text-coverage"><?= e(__('fulltext.coverage_title')) ?></a>
            <?php endif; ?>
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/import"><?= e(__('import.title')) ?></a>
        </div>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($ftEnabled && $rows !== []): ?>
        <div class="section-card section-card--inline" style="margin-bottom:16px">
            <span class="muted"><?= e(__('fulltext.bulk_title')) ?>:</span>
            <form method="post" action="/reviews/<?= $id ?>/full-text/enqueue-all" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    <?= e(__('fulltext.bulk_enqueue')) ?>
                </button>
            </form>
            <form method="post" action="/reviews/<?= $id ?>/full-text/retry-failed" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn--ghost btn--sm"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    <?= e(__('fulltext.bulk_retry')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <form method="get" action="/reviews/<?= $id ?>/references" class="toolbar">
        <select class="select select--sm" name="status" onchange="this.form.submit()">
            <option value=""><?= e(__('references.all_statuses')) ?></option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(__('references.st_' . $s)) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="input" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('references.search')) ?>">
        <button class="btn btn--ghost btn--sm"><?= e(__('references.search')) ?></button>
    </form>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('references.none')) ?></p>
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/import"><?= e(__('import.title')) ?></a>
        </div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= e(__('references.col_study')) ?></th>
                        <th><?= e(__('references.col_ids')) ?></th>
                        <th><?= e(__('references.col_status')) ?></th>
                        <?php if ($ftEnabled): ?><th><?= e(__('fulltext.col_ft')) ?></th><?php endif; ?>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r):
                            $authors = json_decode((string) $r['authors_json'], true) ?: [];
                            $refId = (int) $r['id'];
                            $statusRow = $ftStatus[$refId] ?? null;
                            $queued = isset($inFlight[$refId]);
                            $icon = $ftIcon($statusRow, $queued);
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) ($r['title'] ?: '—')) ?></strong><br>
                                    <span class="muted">
                                        <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                        <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                        <?php if (!empty($r['journal'])): ?> · <?= e((string) $r['journal']) ?><?php endif; ?>
                                    </span>
                                </td>
                                <td class="muted">
                                    <?php if (!empty($r['doi'])): ?>DOI: <?= e((string) $r['doi']) ?><br><?php endif; ?>
                                    <?php if (!empty($r['pmid'])): ?>PMID: <?= e((string) $r['pmid']) ?><?php endif; ?>
                                </td>
                                <td><span class="tag tag--soft"><?= e(__('references.st_' . $r['status'])) ?></span></td>
                                <?php if ($ftEnabled): ?>
                                    <td>
                                        <span class="<?= e($icon['class']) ?>" title="<?= e($icon['label']) ?>"></span>
                                        <?php if ($statusRow !== null && (int) $statusRow['has_fulltext'] === 1 && !empty($statusRow['fulltext_url'])): ?>
                                            <a class="link-ext" href="<?= e((string) $statusRow['fulltext_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(__('fulltext.view')) ?></a>
                                        <?php elseif (!$queued): ?>
                                            <form method="post" action="/reviews/<?= $id ?>/references/<?= $refId ?>/full-text" style="display:inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn--ghost btn--sm"
                                                        data-busy-label="<?= e(__('common.working')) ?>">
                                                    <?= e(__('fulltext.retrieve')) ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td><a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/references/<?= $refId ?>/summary">&#10024; <?= e(__('summary.title')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pages > 1): ?>
            <div class="pager">
                <?php if ($page > 1): ?><a class="btn btn--ghost btn--sm" href="?<?= e($qs(['page' => $page - 1])) ?>">&larr;</a><?php endif; ?>
                <span class="muted"><?= $page ?> / <?= $pages ?></span>
                <?php if ($page < $pages): ?><a class="btn btn--ghost btn--sm" href="?<?= e($qs(['page' => $page + 1])) ?>">&rarr;</a><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
