<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array<int,array<string,mixed>> $refs */
/** @var string $filename */
/** @var string $format */
/** @var array $errors */

$id    = (int) $review['id'];
$total = count($refs);
?>
<div class="page">
    <div class="page__head">
        <div class="breadcrumb"><a href="/reviews/<?= $id ?>/import"><?= e(__('import.title')) ?></a> /</div>
        <h1 class="page__title"><?= e(__('import.preview_title')) ?></h1>
        <p class="page__subtitle"><?= e(__('import.preview_intro', $total)) ?></p>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <p class="muted import-preview__meta">
        <?= e(__('import.preview_source')) ?>:
        <?php if ($format === 'freetext'): ?>
            <span class="tag tag--soft"><?= e(__('import.fmt_freetext')) ?></span>
        <?php else: ?>
            <strong><?= e($filename) ?></strong>
            · <span class="tag tag--soft"><?= e($format) ?></span>
        <?php endif; ?>
    </p>

    <?php if ($errors !== []): ?>
        <details class="alert alert--warn" data-no-toast>
            <summary><?= e(__('import.preview_parse_warnings', count($errors))) ?></summary>
            <ul>
                <?php foreach (array_slice($errors, 0, 25) as $line): ?>
                    <li><?= e((string) $line) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>

    <!--
        The whole preview lives inside a single <form>. The select-all
        checkbox is wired with JS; the per-row checkboxes carry the index
        into the stashed array so the server can re-hydrate the picked
        references without re-trusting the page payload.
    -->
    <form method="post"
          action="/reviews/<?= $id ?>/import/preview/confirm"
          id="importPreviewForm"
          data-ai-action>
        <?= csrf_field() ?>

        <div class="section-card search-bulk-card import-preview__toolbar">
            <div class="search-bulk-toolbar">
                <label class="checkbox search-bulk-toolbar__all">
                    <input type="checkbox" id="previewSelectAll" checked>
                    <?= e(__('import.preview_select_all')) ?>
                </label>
                <span class="muted search-bulk-toolbar__count">
                    <span id="previewSelectedCount"><?= $total ?></span> / <?= $total ?>
                </span>
                <span class="muted import-preview__toolbar-spacer"></span>
                <button type="submit"
                        class="btn btn--primary btn--sm"
                        id="previewImportBtn"
                        data-busy-label="<?= e(__('common.working')) ?>">
                    <?= e(__('import.preview_import_btn')) ?>
                </button>
            </div>
        </div>

        <div class="section-card" style="padding:0; margin-top: 12px">
            <div class="table-wrap">
                <table class="table import-preview-table">
                    <thead><tr>
                        <th class="import-preview-table__check"></th>
                        <th><?= e(__('references.col_study')) ?></th>
                        <th><?= e(__('references.col_ids')) ?></th>
                        <th><?= e(__('import.preview_col_source')) ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($refs as $idx => $r):
                            $authors  = (array) ($r['authors'] ?? []);
                            $doi      = trim((string) ($r['doi']  ?? ''));
                            $pmid     = trim((string) ($r['pmid'] ?? ''));
                            $hasId    = $doi !== '' || $pmid !== '';
                            $rowCls   = $hasId ? '' : 'is-unverified';
                        ?>
                            <tr class="<?= e($rowCls) ?>">
                                <td class="import-preview-table__check">
                                    <input type="checkbox"
                                           class="preview-row-check"
                                           name="selected[]"
                                           value="<?= (int) $idx ?>"
                                           checked
                                           aria-label="<?= e(__('import.preview_select_row')) ?>">
                                </td>
                                <td>
                                    <strong><?= e((string) ($r['title'] ?: '—')) ?></strong><br>
                                    <span class="muted">
                                        <?= e(implode('; ', array_slice($authors, 0, 3))) ?><?= count($authors) > 3 ? ' et al.' : '' ?>
                                        <?php if (!empty($r['year'])): ?> · <?= (int) $r['year'] ?><?php endif; ?>
                                        <?php if (!empty($r['journal'])): ?> · <em><?= e((string) $r['journal']) ?></em><?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($hasId): ?>
                                        <div class="muted import-preview-table__ids">
                                            <?php if ($doi !== ''): ?>
                                                DOI: <a href="https://doi.org/<?= e(rawurlencode($doi)) ?>"
                                                        target="_blank" rel="noopener noreferrer"
                                                        class="link-ext"><?= e($doi) ?></a><br>
                                            <?php endif; ?>
                                            <?php if ($pmid !== ''): ?>
                                                PMID: <a href="https://pubmed.ncbi.nlm.nih.gov/<?= e(rawurlencode($pmid)) ?>/"
                                                         target="_blank" rel="noopener noreferrer"
                                                         class="link-ext"><?= e($pmid) ?></a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="import-preview-table__unverified"
                                             title="<?= e(__('import.preview_unverified_tooltip')) ?>">
                                            <span class="search-outcome search-outcome--bad" aria-hidden="true">
                                                <?php $iconName = 'x'; require config('paths.base') . '/views/partials/icon.php'; ?>
                                            </span>
                                            <span><?= e(__('import.preview_unverified')) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="muted">
                                    <?php if ($format === 'freetext'): ?>
                                        <?= e(__('import.preview_source_freetext')) ?>
                                    <?php else: ?>
                                        <?= e($filename) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <form method="post"
          action="/reviews/<?= $id ?>/import/preview/discard"
          class="import-preview__discard"
          onsubmit="return confirm('<?= e(__('import.preview_discard_confirm')) ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn--ghost btn--sm">
            <?= e(__('import.preview_discard_btn')) ?>
        </button>
    </form>
</div>

<script>
(function () {
    'use strict';

    /* Wire the select-all + counter + import-button enable/disable. The
       checkboxes start checked so the user can hit Import straight away
       if they trust the parser, or quickly untick a few outliers. */
    var selectAll = document.getElementById('previewSelectAll');
    var counter   = document.getElementById('previewSelectedCount');
    var importBtn = document.getElementById('previewImportBtn');
    var rows      = document.querySelectorAll('.preview-row-check');

    function recompute() {
        var n = 0;
        rows.forEach(function (c) { if (c.checked) n++; });
        counter.textContent = String(n);
        importBtn.disabled = n === 0;
    }

    selectAll.addEventListener('change', function () {
        rows.forEach(function (c) { c.checked = selectAll.checked; });
        recompute();
    });
    rows.forEach(function (c) {
        c.addEventListener('change', function () {
            // If the user unticks any row, the master checkbox stops
            // representing "everything is selected".
            if (!c.checked) selectAll.checked = false;
            recompute();
        });
    });
    recompute();
})();
</script>
