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
/** @var bool $canDelete */
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
        <!-- Citation-normalise bulk action.
             The form holds only the hidden CSRF + style. Row checkboxes
             carry form="referencesConvertForm" via the HTML5 attribute so
             they submit together without nesting forms in the table. The
             "Convert/normalize" button opens a modal that picks the style
             and then requestSubmit()s this form. -->
        <form method="post"
              action="/reviews/<?= $id ?>/references/convert"
              id="referencesConvertForm"
              class="section-card search-bulk-card"
              data-ai-action>
            <?= csrf_field() ?>
            <input type="hidden" name="style" id="referencesConvertStyle" value="apa">
            <div class="search-bulk-toolbar">
                <label class="checkbox search-bulk-toolbar__all">
                    <input type="checkbox" id="refsSelectAll">
                    <?= e(__('references.select_page')) ?>
                </label>
                <label class="checkbox search-bulk-toolbar__all" title="<?= e(__('references.select_all_in_review', $total)) ?>">
                    <input type="checkbox" id="refsSelectAllInReview">
                    <?= e(__('references.select_all_in_review', $total)) ?>
                </label>
                <span class="muted search-bulk-toolbar__count" id="refsSelectedCount">0</span>
                <span class="muted import-preview__toolbar-spacer"></span>
                <button type="button" class="btn btn--primary btn--sm"
                        id="convertOpenBtn" disabled>
                    <?= e(__('references.convert_btn')) ?>
                </button>
                <?php if ($canDelete): ?>
                    <button type="button" class="btn btn--danger btn--sm"
                            id="deleteBulkOpenBtn" disabled>
                        <?= e(__('references.delete_btn')) ?>
                    </button>
                <?php endif; ?>
                <!-- "Find duplicates" button targets a sibling form via
                     form="…" rather than wrapping a nested <form> here —
                     nested forms are invalid HTML and the browser would
                     drop the inner one, causing this click to silently
                     submit the outer convert form instead. -->
                <button type="submit" form="referencesFindDupsForm"
                        class="btn btn--ghost btn--sm"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    <?= e(__('references.find_duplicates_btn')) ?>
                </button>
            </div>
        </form>

        <!-- Sibling form for the find-duplicates action. Lives outside
             #referencesConvertForm so the submit button above can target
             it without nesting forms. data-ai-action raises the
             "Treballant, espera…" overlay because the dedup pass on a
             large review can take several seconds. -->
        <form method="post"
              action="/reviews/<?= $id ?>/references/find-duplicates"
              id="referencesFindDupsForm"
              data-ai-action
              style="display:none">
            <?= csrf_field() ?>
        </form>

        <?php if ($canDelete): ?>
            <!-- Bulk delete sibling form. Lives outside the table so the
                 row checkboxes can target it via form="referencesDeleteForm"
                 in addition to the conversion form (HTML5 form association
                 keeps the markup flat — no nested forms in the table). The
                 scope is set by the confirmation modal before submit. -->
            <form method="post"
                  action="/reviews/<?= $id ?>/references/delete-bulk"
                  id="referencesDeleteForm"
                  style="display:none">
                <?= csrf_field() ?>
                <input type="hidden" name="scope" id="deleteBulkScope" value="ids">
                <input type="hidden" name="status" value="<?= e($status) ?>">
                <input type="hidden" name="q" value="<?= e($search) ?>">
                <input type="hidden" name="back" value="/reviews/<?= $id ?>/references?<?= e($qs([])) ?>">
            </form>
        <?php endif; ?>

        <div class="section-card" style="padding:0; margin-top: 12px">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th class="search-external-table__check"></th>
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
                                <td class="search-external-table__check">
                                    <input type="checkbox"
                                           class="refs-row-check"
                                           form="referencesConvertForm"
                                           name="reference_ids[]"
                                           value="<?= (int) $r['id'] ?>"
                                           aria-label="<?= e(__('search.select_row')) ?>">
                                </td>
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
                                <td class="ref-actions">
                                    <a class="btn btn--ghost btn--sm" href="/reviews/<?= $id ?>/references/<?= $refId ?>/summary">&#10024; <?= e(__('summary.title')) ?></a>
                                    <?php
                                    // Delete is offered only while the reference hasn't yet
                                    // been pulled into any reviewer's decision history. We
                                    // approximate that by the row's status; the controller
                                    // re-checks against screening_decisions on POST so the
                                    // client can't bypass the guard.
                                    $canDeleteRow = $canDelete && in_array((string) $r['status'], ['imported', 'duplicate'], true);
                                    if ($canDeleteRow):
                                    ?>
                                        <form method="post" action="/reviews/<?= $id ?>/references/<?= $refId ?>/delete"
                                              style="display:inline"
                                              data-confirm="<?= e(__('references.delete_confirm')) ?>"
                                              data-confirm-tone="danger"
                                              data-confirm-button="<?= e(__('references.delete')) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn--ghost btn--sm btn--danger"
                                                    title="<?= e(__('references.delete')) ?>"
                                                    aria-label="<?= e(__('references.delete')) ?>">&times;</button>
                                        </form>
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

        <?php if ($canDelete): ?>
        <!-- Bulk-delete confirmation modal. Opens centred over the page
             from the toolbar. The body counter is filled in by JS from
             the selected ids (or the total-in-review if scope=filtered)
             before the dialog appears. -->
        <dialog class="info-modal info-modal--confirm" id="deleteBulkModal">
            <div class="info-modal__inner">
                <button type="button" class="info-modal__close"
                        data-info-close
                        aria-label="<?= e(__('common.close')) ?>">&times;</button>
                <h3><?= e(__('references.delete_bulk_modal_title')) ?></h3>
                <p><strong id="deleteBulkCount"></strong></p>
                <p class="muted"><?= e(__('references.delete_bulk_modal_intro')) ?></p>
                <div class="actions">
                    <button type="button" class="btn btn--ghost" data-info-close>
                        <?= e(__('reviews.cancel')) ?>
                    </button>
                    <button type="button" class="btn btn--danger-solid" id="deleteBulkConfirmBtn"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        <?= e(__('references.delete_bulk_confirm')) ?>
                    </button>
                </div>
            </div>
        </dialog>
        <?php endif; ?>

        <!-- Citation-style picker modal. Triggered by #convertOpenBtn,
             dismissed by the close button / backdrop click. Picking a
             style and hitting Confirm writes the selected style into the
             hidden input on #referencesConvertForm and submits it. -->
        <dialog class="info-modal info-modal--confirm" id="convertModal">
            <div class="info-modal__inner">
                <button type="button" class="info-modal__close"
                        data-info-close
                        aria-label="<?= e(__('common.close')) ?>">&times;</button>
                <h3><?= e(__('references.convert_modal_title')) ?></h3>
                <p class="muted"><?= e(__('references.convert_modal_intro')) ?></p>
                <div class="field">
                    <label class="field-label" for="convertModalStyle">
                        <?= e(__('citations.style_label')) ?>
                    </label>
                    <select class="select" id="convertModalStyle">
                        <?php foreach (\SysRevAI\Controllers\CitationsController::STYLES as $s): ?>
                            <option value="<?= e($s) ?>"><?= e(__('citations.style_' . $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="actions">
                    <button type="button" class="btn btn--ghost" data-info-close>
                        <?= e(__('reviews.cancel')) ?>
                    </button>
                    <button type="button" class="btn btn--primary" id="convertModalConfirm"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        <?= e(__('references.convert_modal_confirm')) ?>
                    </button>
                </div>
            </div>
        </dialog>
    <?php endif; ?>
</div>

<?php if ($rows !== []): ?>
<script>
(function () {
    'use strict';

    var all      = document.getElementById('refsSelectAll');
    var allReview= document.getElementById('refsSelectAllInReview');
    var counter  = document.getElementById('refsSelectedCount');
    var openBtn  = document.getElementById('convertOpenBtn');
    var deleteBtn= document.getElementById('deleteBulkOpenBtn');
    var modal    = document.getElementById('convertModal');
    var confirm  = document.getElementById('convertModalConfirm');
    var styleSel = document.getElementById('convertModalStyle');
    var hidden   = document.getElementById('referencesConvertStyle');
    var convertForm = document.getElementById('referencesConvertForm');
    var deleteForm  = document.getElementById('referencesDeleteForm');
    var deleteModal = document.getElementById('deleteBulkModal');
    var deleteCount = document.getElementById('deleteBulkCount');
    var deleteConfirm = document.getElementById('deleteBulkConfirmBtn');
    var scopeInput  = document.getElementById('deleteBulkScope');
    var rows     = document.querySelectorAll('.refs-row-check');
    var totalInReview = <?= (int) $total ?>;

    function pageSelectedCount() {
        var n = 0;
        rows.forEach(function (c) { if (c.checked) n++; });
        return n;
    }
    function recompute() {
        var n = pageSelectedCount();
        // When the cross-page toggle is on, what's "selected" for the
        // user's purposes is the whole review, not just the visible page.
        var effective = allReview && allReview.checked ? totalInReview : n;
        counter.textContent = String(effective);
        openBtn.disabled = n === 0; // conversion still operates on visible ids
        if (deleteBtn) deleteBtn.disabled = effective === 0;
    }

    all.addEventListener('change', function () {
        rows.forEach(function (c) { c.checked = all.checked; });
        recompute();
    });
    rows.forEach(function (c) {
        c.addEventListener('change', function () {
            if (!c.checked) all.checked = false;
            recompute();
        });
    });
    if (allReview) {
        allReview.addEventListener('change', function () {
            if (allReview.checked) {
                // Cross-page intent supersedes per-page selection.
                rows.forEach(function (c) { c.checked = true; });
                all.checked = true;
            }
            recompute();
        });
    }
    recompute();

    openBtn.addEventListener('click', function () {
        if (typeof modal.showModal === 'function') {
            modal.showModal();
        } else {
            modal.setAttribute('open', '');
            modal.classList.add('is-open');
        }
    });
    confirm.addEventListener('click', function () {
        hidden.value = styleSel.value || 'apa';
        if (typeof modal.close === 'function') modal.close();
        else { modal.removeAttribute('open'); modal.classList.remove('is-open'); }
        convertForm.requestSubmit();
    });

    // Delete-in-bulk flow. The delete form lives outside the table, so
    // we copy the checked ids into hidden inputs at submit time —
    // unless the user picked "select all in review", in which case we
    // switch the scope to "filtered" and let the controller resolve the
    // ids server-side from the current filter (status + q).
    if (deleteBtn && deleteForm) {
        deleteBtn.addEventListener('click', function () {
            var crossPage = allReview && allReview.checked;
            // Clean any inputs leftover from a previous open.
            deleteForm.querySelectorAll('input[name="reference_ids[]"]').forEach(function (n) { n.remove(); });

            var count;
            if (crossPage) {
                scopeInput.value = 'filtered';
                count = totalInReview;
            } else {
                scopeInput.value = 'ids';
                count = 0;
                rows.forEach(function (c) {
                    if (!c.checked) return;
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = 'reference_ids[]';
                    i.value = c.value;
                    deleteForm.appendChild(i);
                    count++;
                });
            }
            if (count === 0) return;

            deleteCount.textContent =
                <?= json_encode(__('references.delete_bulk_count', 0), JSON_UNESCAPED_UNICODE) ?>.replace('0', String(count));

            if (typeof deleteModal.showModal === 'function') {
                deleteModal.showModal();
            } else {
                deleteModal.setAttribute('open', '');
                deleteModal.classList.add('is-open');
            }
        });

        deleteConfirm.addEventListener('click', function () {
            if (typeof deleteModal.close === 'function') deleteModal.close();
            else { deleteModal.removeAttribute('open'); deleteModal.classList.remove('is-open'); }
            deleteForm.submit();
        });
    }
})();
</script>
<?php endif; ?>
