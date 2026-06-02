<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var string $q */
/** @var string $mode */
/** @var array  $results */
/** @var array  $externalMeta  per-source breakdown (count, error) */
/** @var ?string $externalError */
/** @var array  $openReviews */

$isExternal = $mode === 'external';
$placeholder = $isExternal
    ? __('search.placeholder_external')
    : __('search.placeholder');
?>
<div class="page">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('search.title')) ?></h1>
        <p class="page__subtitle">
            <?= e($isExternal ? __('search.intro_external') : __('search.intro')) ?>
        </p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <!-- Mode toggle: keeps the current query so the user can pivot between
         databases and their own corpus without retyping. -->
    <div class="search-mode" role="tablist" aria-label="<?= e(__('search.mode_aria')) ?>">
        <a class="search-mode__tab <?= !$isExternal ? 'is-active' : '' ?>"
           role="tab"
           aria-selected="<?= !$isExternal ? 'true' : 'false' ?>"
           href="/search?<?= e(http_build_query(['q' => $q, 'mode' => 'local'])) ?>">
            <?= e(__('search.mode_local')) ?>
        </a>
        <a class="search-mode__tab <?= $isExternal ? 'is-active' : '' ?>"
           role="tab"
           aria-selected="<?= $isExternal ? 'true' : 'false' ?>"
           href="/search?<?= e(http_build_query(['q' => $q, 'mode' => 'external'])) ?>">
            <?= e(__('search.mode_external')) ?>
        </a>
    </div>

    <form method="get" action="/search" class="toolbar">
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
        <input class="input" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholder) ?>" autofocus>
        <button class="btn btn--primary"
                data-busy-label="<?= e(__('common.working')) ?>"><?= e(__('search.go')) ?></button>
    </form>

    <?php if ($isExternal): ?>
        <p class="muted search-databases">
            <?= e(__('search.databases')) ?>:
            <span class="tag tag--soft">CrossRef</span>
            <span class="tag tag--soft">OpenAlex</span>
            <span class="tag tag--soft">Europe PMC</span>
        </p>
    <?php endif; ?>

    <?php if ($q === ''): ?>
        <p class="muted">
            <?= e($isExternal ? __('search.hint_external') : __('search.hint')) ?>
        </p>
    <?php elseif ($results === []): ?>
        <div class="empty-state">
            <p><?= e(__('search.no_results', $q)) ?></p>
            <?php if ($externalError !== null): ?>
                <p class="muted"><?= e(__('search.external_error')) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($isExternal && $externalMeta !== []): ?>
            <p class="muted">
                <?php foreach ($externalMeta as $name => $m): ?>
                    <span class="tag tag--soft"><?= e($name) ?>: <?= (int) $m['count'] ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>

    <?php elseif (!$isExternal): ?>
        <!-- Local: existing in-platform references. No bulk operations needed
             since these are already inside one of the user's reviews. -->
        <p class="muted"><?= e(__('search.count', count($results))) ?></p>
        <div class="section-card" style="padding:0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th><?= e(__('references.col_study')) ?></th>
                        <th><?= e(__('search.review')) ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($results as $r):
                            $authors = json_decode((string) $r['authors_json'], true) ?: [];
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e((string) ($r['title'] ?: '—')) ?></strong><br>
                                    <span class="muted">
                                        <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                        <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                        <?php if (!empty($r['journal'])): ?> · <em><?= e((string) $r['journal']) ?></em><?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/reviews/<?= (int) $r['review_id'] ?>"><?= e((string) $r['review_title']) ?></a>
                                    <br><span class="tag tag--soft"><?= e(__('references.st_' . $r['status'])) ?></span>
                                </td>
                                <td>
                                    <a class="btn btn--ghost btn--sm" href="/reviews/<?= (int) $r['review_id'] ?>/references/<?= (int) $r['id'] ?>/summary">
                                        <?= e(__('summary.title')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>
        <!-- External: fresh hits straight from the databases. Each row carries
             its full reference payload as a hidden JSON blob so the same
             import endpoint handles both single-row "Import" and the bulk
             "Import selected" flow. -->
        <p class="muted">
            <?= e(__('search.count', count($results))) ?>
            <?php if ($externalMeta !== []): ?>
                · <?php foreach ($externalMeta as $name => $m): ?>
                    <span class="tag tag--soft"><?= e($name) ?>: <?= (int) $m['count'] ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </p>

        <?php if ($openReviews === []): ?>
            <div class="alert alert--warn" data-no-toast>
                <?= e(__('search.no_open_reviews')) ?>
            </div>
        <?php endif; ?>

        <!--
            HTML5 form association: the bulk <form> only wraps the toolbar.
            The table sits next to it, and every row checkbox carries
            form="bulkImportForm" so it submits with the bulk form anyway.
            This keeps the per-row "Import" sub-forms valid (no nested
            forms) while preserving the "select rows + bulk action" UX.
        -->
        <form method="post" action="/search/import" id="bulkImportForm" class="section-card search-bulk-card">
            <?= csrf_field() ?>
            <input type="hidden" name="back_q" value="<?= e($q) ?>">
            <input type="hidden" name="back_mode" value="external">

            <div class="search-bulk-toolbar">
                <label class="checkbox search-bulk-toolbar__all">
                    <input type="checkbox" id="searchSelectAll">
                    <?= e(__('search.select_all')) ?>
                </label>
                <span class="muted search-bulk-toolbar__count" id="searchSelectedCount">0</span>
                <label class="field-label search-bulk-toolbar__label" for="bulkReviewId">
                    <?= e(__('search.import_target_label')) ?>
                </label>
                <select class="select select--sm" id="bulkReviewId" name="review_id"
                        <?= $openReviews === [] ? 'disabled' : '' ?>>
                    <option value=""><?= e(__('search.import_target_placeholder')) ?></option>
                    <?php foreach ($openReviews as $rv): ?>
                        <option value="<?= (int) $rv['id'] ?>"><?= e((string) $rv['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn--primary btn--sm"
                        id="bulkImportBtn"
                        data-busy-label="<?= e(__('common.working')) ?>"
                        disabled>
                    <?= e(__('search.import_selected')) ?>
                </button>
            </div>
        </form>

        <div class="section-card" style="padding:0; margin-top: 12px">
            <div class="table-wrap">
                <table class="table search-external-table">
                    <thead><tr>
                        <th class="search-external-table__check"></th>
                        <th><?= e(__('references.col_study')) ?></th>
                        <th><?= e(__('references.col_ids')) ?></th>
                        <th><?= e(__('search.sources')) ?></th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($results as $r):
                            $authors  = (array) ($r['authors']  ?? []);
                            $keywords = (array) ($r['keywords'] ?? []);
                            $rowJson  = json_encode([
                                'title'    => (string) ($r['title']    ?? ''),
                                'authors'  => array_values($authors),
                                'year'     => $r['year'] ?? null,
                                'journal'  => (string) ($r['journal']  ?? ''),
                                'abstract' => (string) ($r['abstract'] ?? ''),
                                'doi'      => (string) ($r['doi']      ?? ''),
                                'pmid'     => (string) ($r['pmid']     ?? ''),
                                'url'      => (string) ($r['url']      ?? ''),
                                'keywords' => array_values($keywords),
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $srcs = (array) ($r['_sources'] ?? []);
                        ?>
                            <tr>
                                <td class="search-external-table__check">
                                    <input type="checkbox"
                                           class="search-row-check"
                                           form="bulkImportForm"
                                           name="selected[]"
                                           value="<?= e((string) $rowJson) ?>"
                                           aria-label="<?= e(__('search.select_row')) ?>">
                                </td>
                                <td>
                                    <strong><?= e((string) ($r['title'] ?: '—')) ?></strong><br>
                                    <span class="muted">
                                        <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                        <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                        <?php if (!empty($r['journal'])): ?> · <em><?= e((string) $r['journal']) ?></em><?php endif; ?>
                                    </span>
                                </td>
                                <td class="muted">
                                    <?php if (!empty($r['doi'])): ?>DOI: <?= e((string) $r['doi']) ?><br><?php endif; ?>
                                    <?php if (!empty($r['pmid'])): ?>PMID: <?= e((string) $r['pmid']) ?><?php endif; ?>
                                </td>
                                <td>
                                    <?php foreach ($srcs as $src): ?>
                                        <span class="tag tag--soft"><?= e((string) $src) ?></span>
                                    <?php endforeach; ?>
                                </td>
                                <td class="search-external-table__row-action"
                                    data-row-json="<?= e((string) $rowJson) ?>">
                                    <!-- Per-row import dropdown is injected by JS from the <template> below. -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($openReviews !== []): ?>
            <template id="rowImportTemplate">
                <form method="post" action="/search/import" class="search-row-import">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="back_q" value="<?= e($q) ?>">
                    <input type="hidden" name="back_mode" value="external">
                    <input type="hidden" name="review_id" value="">
                    <input type="hidden" name="single" value="">
                    <select class="select select--sm search-row-import__select" aria-label="<?= e(__('search.import_target_label')) ?>">
                        <?php foreach ($openReviews as $rv): ?>
                            <option value="<?= (int) $rv['id'] ?>"><?= e((string) $rv['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn--ghost btn--sm"><?= e(__('search.import_one')) ?></button>
                </form>
            </template>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if ($isExternal && $results !== [] && $openReviews !== []): ?>
<script>
(function () {
    'use strict';

    /* Bulk toolbar: "select all" toggles every row checkbox; the counter
       and the import button track the live selection so the user can't
       submit an empty form. */
    var selectAll = document.getElementById('searchSelectAll');
    var counter   = document.getElementById('searchSelectedCount');
    var importBtn = document.getElementById('bulkImportBtn');
    var reviewSel = document.getElementById('bulkReviewId');
    var rows      = document.querySelectorAll('.search-row-check');

    function recompute() {
        var n = 0;
        rows.forEach(function (c) { if (c.checked) n++; });
        counter.textContent = String(n);
        importBtn.disabled = n === 0 || !reviewSel.value;
    }

    selectAll.addEventListener('change', function () {
        rows.forEach(function (c) { c.checked = selectAll.checked; });
        recompute();
    });
    rows.forEach(function (c) { c.addEventListener('change', recompute); });
    reviewSel.addEventListener('change', recompute);
    recompute();

    /* Per-row "Import" forms — cloned from a <template> so we don't
       repeat the open-reviews dropdown markup on every row. The cell's
       data-row-json attribute carries the same JSON blob the bulk
       checkbox uses, so per-row imports go through the same endpoint
       with no special-case server code. */
    var tmpl = document.getElementById('rowImportTemplate');
    if (tmpl) {
        document.querySelectorAll('.search-external-table__row-action').forEach(function (cell) {
            var json = cell.getAttribute('data-row-json');
            if (!json) return;
            var clone = tmpl.content.firstElementChild.cloneNode(true);
            clone.querySelector('input[name="single"]').value = json;
            var select = clone.querySelector('select');
            var hiddenReview = clone.querySelector('input[name="review_id"]');
            hiddenReview.value = select.value;
            select.addEventListener('change', function () {
                hiddenReview.value = select.value;
            });
            cell.appendChild(clone);
        });
    }
})();
</script>
<?php endif; ?>
