<?php

declare(strict_types=1);

/** @var array $review */
/** @var array $rows */
/** @var string $basePath */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var int[] $perPageOptions */
/** @var string $reviewerFilter '' | 'none' | numeric reviewer id */
/** @var array<int,string> $reviewers [user_id => name] */
$id = (int) $review['id'];
$basePath = $basePath ?? '/reviews/' . $id . '/screen';
$pages = (int) ceil($total / max(1, $perPage));
$qs = static function (array $extra) use ($reviewerFilter, $perPage): string {
    return http_build_query(array_merge(['reviewer' => $reviewerFilter, 'per_page' => $perPage], $extra));
};
?>
<div class="page">
    <div class="alert alert--warn coord-banner" data-no-toast>
        &#9888; <?= e(__('screening.coord_banner')) ?>
        <form method="post" action="<?= e($basePath) ?>/coordinator" style="display:inline">
            <?= csrf_field() ?>
            <button class="btn btn--ghost btn--sm"><?= e(__('screening.coord_exit')) ?></button>
        </form>
    </div>

    <div class="page__head">
        <h1 class="page__title"><?= e(__('screening.coordinator_view')) ?> <span class="muted">(<?= $total ?>)</span></h1>
    </div>

    <form method="get" action="<?= e($basePath) ?>" class="toolbar">
        <select class="select select--sm" name="reviewer" onchange="this.form.submit()"
                aria-label="<?= e(__('screening.coord_filter_reviewer')) ?>">
            <option value="" <?= $reviewerFilter === '' ? 'selected' : '' ?>><?= e(__('screening.coord_filter_all')) ?></option>
            <option value="none" <?= $reviewerFilter === 'none' ? 'selected' : '' ?>><?= e(__('screening.coord_filter_none')) ?></option>
            <?php foreach ($reviewers as $uid => $name): ?>
                <option value="<?= $uid ?>" <?= $reviewerFilter === (string) $uid ? 'selected' : '' ?>><?= e($name) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="muted toolbar__perpage">
            <?= e(__('common.per_page')) ?>
            <select class="select select--sm" name="per_page" onchange="this.form.submit()"
                    aria-label="<?= e(__('common.per_page')) ?>">
                <?php foreach ($perPageOptions as $opt): ?>
                    <option value="<?= (int) $opt ?>" <?= $perPage === $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <?php if ($rows === []): ?>
        <div class="empty-state"><p><?= e(__('screening.all_done')) ?></p></div>
    <?php else: ?>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(__('references.col_study')) ?></th><th><?= e(__('screening.decisions')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <!-- Clicking a reference exits coordinator mode (screen()
                                         renders the coordinator table instead of the normal
                                         screening view while that session flag is on) and
                                         opens this exact reference to screen. -->
                                    <form method="post" action="<?= e($basePath) ?>/coordinator/open">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="reference_id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="coord-row-link">
                                            <strong><?= e((string) ($r['title'] ?: '—')) ?></strong>
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if (($r['decisions'] ?? []) === []): ?>
                                        <span class="muted"><?= e(__('screening.no_decisions')) ?></span>
                                    <?php else: ?>
                                        <?php foreach ($r['decisions'] as $d): ?>
                                            <span class="tag tag--<?= e((string) $d['decision']) ?>"><?= e((string) $d['reviewer_name']) ?>: <?= e(__('screening.' . $d['decision'])) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
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
