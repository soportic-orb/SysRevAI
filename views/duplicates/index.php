<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $duplicates */
/** @var array $confirmedDupes  References auto-flagged as exact duplicates */
/** @var bool  $canDelete */
$id = (int) $review['id'];
?>
<div class="page page--narrow">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a> /</div>
        <h1 class="page__title"><?= e(__('duplicates.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('duplicates.intro')) ?></p>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php
    // Confirmed (exact) duplicates: marked automatically by the dedup
    // pass. Show them as a bulk-deletable list so the user can wipe
    // them with one click instead of opening each row individually.
    $confirmedDupes = (array) ($confirmedDupes ?? []);
    $canDelete = (bool) ($canDelete ?? false);
    if ($confirmedDupes !== [] && $canDelete):
    ?>
        <section class="section-card" style="margin-bottom:16px">
            <h2 class="section__subtitle"><?= e(__('duplicates.confirmed_title')) ?> <span class="muted">(<?= count($confirmedDupes) ?>)</span></h2>
            <p class="muted"><?= e(__('duplicates.confirmed_intro')) ?></p>

            <form method="post" action="/reviews/<?= $id ?>/references/delete-bulk" id="dupBulkForm">
                <?= csrf_field() ?>
                <input type="hidden" name="scope" value="ids">
                <input type="hidden" name="back" value="/reviews/<?= $id ?>/duplicates">

                <div class="search-bulk-toolbar">
                    <label class="checkbox search-bulk-toolbar__all">
                        <input type="checkbox" id="dupSelectAll">
                        <?= e(__('search.select_all')) ?>
                    </label>
                    <span class="muted search-bulk-toolbar__count" id="dupSelectedCount">0</span>
                    <span class="muted import-preview__toolbar-spacer"></span>
                    <button type="button" class="btn btn--danger btn--sm" id="dupDeleteSelected" disabled>
                        <?= e(__('references.delete_btn')) ?>
                    </button>
                    <button type="button" class="btn btn--danger-solid btn--sm" id="dupDeleteAll">
                        <?= e(__('references.delete_all_btn')) ?>
                    </button>
                </div>

                <div class="table-wrap" style="margin-top:12px">
                    <table class="table">
                        <thead><tr>
                            <th class="search-external-table__check"></th>
                            <th><?= e(__('references.col_study')) ?></th>
                            <th><?= e(__('references.col_ids')) ?></th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($confirmedDupes as $r):
                                $authors = json_decode((string) ($r['authors_json'] ?? ''), true) ?: [];
                            ?>
                                <tr>
                                    <td class="search-external-table__check">
                                        <input type="checkbox" class="dup-row-check"
                                               name="reference_ids[]" value="<?= (int) $r['id'] ?>">
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </section>

        <dialog class="info-modal" id="dupDeleteModal">
            <div class="info-modal__inner">
                <button type="button" class="info-modal__close" data-info-close
                        aria-label="<?= e(__('common.close')) ?>">&times;</button>
                <h3><?= e(__('references.delete_bulk_modal_title')) ?></h3>
                <p><strong id="dupDeleteCount"></strong></p>
                <p class="muted"><?= e(__('references.delete_bulk_modal_intro')) ?></p>
                <div class="actions">
                    <button type="button" class="btn btn--ghost" data-info-close>
                        <?= e(__('reviews.cancel')) ?>
                    </button>
                    <button type="button" class="btn btn--danger-solid" id="dupDeleteConfirm"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        <?= e(__('references.delete_bulk_confirm')) ?>
                    </button>
                </div>
            </div>
        </dialog>
    <?php endif; ?>

    <?php if ($duplicates === []): ?>
        <?php if ($confirmedDupes === []): ?>
            <div class="empty-state"><p><?= e(__('duplicates.none')) ?></p></div>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($duplicates as $d): ?>
            <div class="section-card dup-pair">
                <div class="dup-side">
                    <span class="muted"><?= e(__('duplicates.keep')) ?></span>
                    <strong><?= e((string) ($d['a_title'] ?: '—')) ?></strong>
                    <span class="muted"><?= e((string) $d['a_journal']) ?> <?= !empty($d['a_year']) ? '· ' . (int) $d['a_year'] : '' ?></span>
                </div>
                <div class="dup-side">
                    <span class="muted"><?= e(__('duplicates.candidate')) ?></span>
                    <strong><?= e((string) ($d['b_title'] ?: '—')) ?></strong>
                    <span class="muted"><?= e((string) $d['b_journal']) ?> <?= !empty($d['b_year']) ? '· ' . (int) $d['b_year'] : '' ?></span>
                </div>
                <div class="dup-meta">
                    <span class="tag tag--soft"><?= e((string) $d['method']) ?> · <?= e((string) round((float) $d['confidence'] * 100)) ?>%</span>
                    <div class="btn-row">
                        <form method="post" action="/reviews/<?= $id ?>/duplicates/resolve">
                            <?= csrf_field() ?>
                            <input type="hidden" name="duplicate_id" value="<?= (int) $d['id'] ?>">
                            <input type="hidden" name="decision" value="confirm">
                            <button class="btn btn--danger btn--sm"><?= e(__('duplicates.confirm')) ?></button>
                        </form>
                        <form method="post" action="/reviews/<?= $id ?>/duplicates/resolve">
                            <?= csrf_field() ?>
                            <input type="hidden" name="duplicate_id" value="<?= (int) $d['id'] ?>">
                            <input type="hidden" name="decision" value="reject">
                            <button class="btn btn--ghost btn--sm"><?= e(__('duplicates.reject')) ?></button>
                        </form>
                        <form method="post" action="/reviews/<?= $id ?>/duplicates/check-ai" data-ai-action>
                            <?= csrf_field() ?>
                            <input type="hidden" name="duplicate_id" value="<?= (int) $d['id'] ?>">
                            <button class="btn btn--ghost btn--sm">&#10024; <?= e(__('duplicates.ai_check')) ?></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($confirmedDupes !== [] && $canDelete): ?>
<script>
(function () {
    'use strict';
    var form        = document.getElementById('dupBulkForm');
    var rows        = form.querySelectorAll('.dup-row-check');
    var all         = document.getElementById('dupSelectAll');
    var counter     = document.getElementById('dupSelectedCount');
    var deleteSel   = document.getElementById('dupDeleteSelected');
    var deleteAll   = document.getElementById('dupDeleteAll');
    var modal       = document.getElementById('dupDeleteModal');
    var countEl     = document.getElementById('dupDeleteCount');
    var confirmBtn  = document.getElementById('dupDeleteConfirm');
    var countTmpl   = <?= json_encode(__('references.delete_bulk_count', 0), JSON_UNESCAPED_UNICODE) ?>;
    var total       = <?= (int) count($confirmedDupes) ?>;

    function recompute() {
        var n = 0;
        rows.forEach(function (c) { if (c.checked) n++; });
        counter.textContent = String(n);
        deleteSel.disabled = n === 0;
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
    recompute();

    function openModal(count) {
        countEl.textContent = countTmpl.replace('0', String(count));
        if (typeof modal.showModal === 'function') modal.showModal();
        else { modal.setAttribute('open', ''); modal.classList.add('is-open'); }
    }
    deleteSel.addEventListener('click', function () {
        var n = 0;
        rows.forEach(function (c) { if (c.checked) n++; });
        if (n > 0) openModal(n);
    });
    deleteAll.addEventListener('click', function () {
        rows.forEach(function (c) { c.checked = true; });
        all.checked = true;
        recompute();
        openModal(total);
    });
    confirmBtn.addEventListener('click', function () {
        if (typeof modal.close === 'function') modal.close();
        else { modal.removeAttribute('open'); modal.classList.remove('is-open'); }
        form.submit();
    });
})();
</script>
<?php endif; ?>
