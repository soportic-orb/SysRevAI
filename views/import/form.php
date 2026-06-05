<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var string[] $formats */
/** @var array $logs */
$id = (int) $review['id'];
?>
<div class="page page--narrow">
    <div class="page__head">
        <h1 class="page__title"><?= e(__('import.title')) ?></h1>
        <p class="page__subtitle"><?= e(__('import.intro')) ?></p>
    </div>

    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <!-- data-ai-action raises the global "working" overlay on submit so
         the user knows we're parsing / calling Claude before bouncing
         them to the preview screen. -->
    <form method="post" action="/reviews/<?= $id ?>/import"
          class="form-grid section-card" enctype="multipart/form-data"
          data-ai-action>
        <?= csrf_field() ?>

        <div class="field">
            <label class="field-label" for="format"><?= e(__('import.format')) ?></label>
            <select class="select" id="format" name="format">
                <option value="auto"><?= e(__('import.auto_detect')) ?></option>
                <?php foreach ($formats as $f): ?>
                    <option value="<?= e($f) ?>"><?= e(__('import.fmt_' . $f)) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="field-help"><?= e(__('import.format_help')) ?></span>
        </div>

        <!-- Hidden when the user picks "Free text (AI)" because the file
             upload field doesn't apply to that mode — the textarea below
             is the only valid input. -->
        <div class="field" id="fileField" data-hide-on-freetext>
            <label class="field-label" for="file"><?= e(__('import.file')) ?></label>
            <input class="input" id="file" name="file" type="file" accept=".ris,.nbib,.bib,.csv,.xml,.enw,.txt">
            <span class="field-help"><?= e(__('import.file_help')) ?></span>
        </div>

        <div class="field">
            <label class="field-label" for="paste"><?= e(__('import.paste')) ?></label>
            <textarea class="input" id="paste" name="paste" rows="8" placeholder="<?= e(__('import.paste_help')) ?>"></textarea>
            <span class="field-help"><?= e(__('import.paste_freetext_hint')) ?></span>
        </div>

        <div><button type="submit" class="btn btn--primary"><?= e(__('import.submit')) ?></button></div>
    </form>

    <script>
    /* Show the global "AI is working" overlay only when the user chose
       the AI-driven free-text format. Other formats are parsed locally
       and the regular page navigation is fast enough not to need it.
       Also toggles the visibility of fields marked [data-hide-on-freetext]
       so the file picker disappears the moment the user picks AI mode. */
    (function () {
        var form = document.querySelector('form[action$="/import"]');
        var fmt = document.getElementById('format');
        if (!form || !fmt) return;

        var hideTargets = document.querySelectorAll('[data-hide-on-freetext]');
        function syncFreetext() {
            var hide = fmt.value === 'freetext';
            hideTargets.forEach(function (el) { el.hidden = hide; });
        }
        fmt.addEventListener('change', syncFreetext);
        syncFreetext();

        form.addEventListener('submit', function () {
            if (fmt.value === 'freetext' && window.SysRevAI && window.SysRevAI.showAiOverlay) {
                window.SysRevAI.showAiOverlay();
            }
        });
    })();
    </script>

    <?php if ($logs !== []): ?>
        <div class="section-card">
            <h2 class="section__subtitle"><?= e(__('import.history')) ?></h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th><?= e(__('import.file')) ?></th><th><?= e(__('import.format')) ?></th><th><?= e(__('import.imported')) ?></th><th><?= e(__('import.duplicates')) ?></th><th><?= e(__('admin.maintenance.when')) ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e((string) $log['filename']) ?></td>
                                <td><?= e((string) $log['format']) ?></td>
                                <td><?= (int) $log['total_imported'] ?></td>
                                <td><?= (int) $log['total_duplicates'] ?></td>
                                <td class="muted"><?= e((string) $log['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Owner-only escape hatch: wipe the import audit list. The
                 references the imports created are not deleted (that's a
                 separate destructive action on the References page). -->
            <form method="post" action="/reviews/<?= $id ?>/import/clear-logs"
                  class="import-clear-form"
                  data-confirm="<?= e(__('import.clear_confirm')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                    <?= e(__('import.clear_btn')) ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
