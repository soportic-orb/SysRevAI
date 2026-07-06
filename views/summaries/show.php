<?php

declare(strict_types=1);

use SysRevAI\Core\Session;

/** @var array $review */
/** @var array $reference */
/** @var string $lang */
/** @var string[] $langs */
/** @var ?array $summary */
/** @var ?array $summaryRow */
/** @var string[] $sections */
/** @var string $backUrl Where the reviewer came from — the exact filtered/paginated references list, or the bare list if they didn't. */
$id = (int) $review['id'];
$refId = (int) $reference['id'];
$authors = json_decode((string) $reference['authors_json'], true) ?: [];
$langNames = ['ca' => 'Català', 'es' => 'Español', 'en' => 'English'];
$backUrl = $backUrl ?? '/reviews/' . $id . '/references';
?>
<div class="page page--narrow" data-translate-url="/reviews/<?= $id ?>/translate"
     data-csrf="<?= e(csrf_token()) ?>"
     data-loading="<?= e(__('summary.translating')) ?>"
     data-error="<?= e(__('summary.translate_failed')) ?>">

    <div class="page__head summary-head">
        <a class="back-link" href="<?= e($backUrl) ?>"
           title="<?= e(__('summary.back_to_references')) ?>"
           aria-label="<?= e(__('summary.back_to_references')) ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
        </a>
        <div class="summary-head__main">
            <h1 class="page__title"><?= e(__('summary.title')) ?></h1>
            <p class="muted">
                <strong><?= e((string) ($reference['title'] ?: '—')) ?></strong><br>
                <?= e(implode('; ', array_slice($authors, 0, 4))) ?>
                <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (($flash = Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if (!empty($reference['abstract'])): ?>
        <div class="section-card translate-block">
            <div class="translate-block__head">
                <h2 class="section__subtitle"><?= e(__('summary.abstract')) ?></h2>
                <div class="translate-controls">
                    <select class="select select--sm" data-translate-target>
                        <?php foreach ($langs as $l): ?>
                            <option value="<?= e($l) ?>" <?= $l === $lang ? 'selected' : '' ?>><?= e($langNames[$l] ?? $l) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn--ghost btn--sm summary-translate-btn" data-translate-btn>
                        <?php $iconName = 'language'; $iconClass = 'summary-translate-btn__icon'; require config('paths.base') . '/views/partials/icon.php'; ?>
                        <span><?= e(__('summary.translate')) ?></span>
                    </button>
                </div>
            </div>
            <div data-translate-src><?= nl2br(e((string) $reference['abstract'])) ?></div>
            <div class="translate-result" data-translate-result hidden></div>
        </div>
    <?php endif; ?>

    <div class="section-card translate-block">
        <div class="translate-block__head">
            <h2 class="section__subtitle"><?= e(__('summary.ai_summary')) ?></h2>
            <form method="get" action="/reviews/<?= $id ?>/references/<?= $refId ?>/summary" class="translate-controls">
                <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                <select class="select select--sm" name="lang" onchange="this.form.submit()">
                    <?php foreach ($langs as $l): ?>
                        <option value="<?= e($l) ?>" <?= $l === $lang ? 'selected' : '' ?>><?= e($langNames[$l] ?? $l) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($summary === null): ?>
            <p class="muted"><?= e(__('summary.empty')) ?></p>
            <form method="post" action="/reviews/<?= $id ?>/references/<?= $refId ?>/summary" data-ai-action>
                <?= csrf_field() ?>
                <input type="hidden" name="lang" value="<?= e($lang) ?>">
                <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                <button class="btn btn--primary">&#10024; <?= e(__('summary.generate')) ?></button>
            </form>
        <?php else: ?>
            <dl class="summary-dl">
                <?php foreach ($sections as $s): ?>
                    <dt><?= e(__('summary.section_' . $s)) ?></dt>
                    <dd data-translate-src><?= nl2br(e((string) ($summary[$s] ?? ''))) ?></dd>
                <?php endforeach; ?>
            </dl>
            <p class="muted small">
                <?= e(__('summary.model')) ?>: <code><?= e((string) ($summaryRow['model_used'] ?? '')) ?></code> ·
                <?= e((string) ($summaryRow['created_at'] ?? '')) ?>
            </p>
            <form method="post" action="/reviews/<?= $id ?>/references/<?= $refId ?>/summary" style="display:inline" data-ai-action>
                <?= csrf_field() ?>
                <input type="hidden" name="lang" value="<?= e($lang) ?>">
                <input type="hidden" name="back" value="<?= e($backUrl) ?>">
                <button class="btn btn--ghost btn--sm">&#10227; <?= e(__('summary.regenerate')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    var page = document.querySelector('[data-translate-url]');
    if (!page) return;
    var url = page.getAttribute('data-translate-url');
    var csrf = page.getAttribute('data-csrf');
    var loading = page.getAttribute('data-loading');
    var errorMsg = page.getAttribute('data-error');

    page.querySelectorAll('.translate-block').forEach(function (block) {
        var btn = block.querySelector('[data-translate-btn]');
        if (!btn) return;
        var sel = block.querySelector('[data-translate-target]');
        var srcs = block.querySelectorAll('[data-translate-src]');
        var dest = block.querySelector('[data-translate-result]');

        btn.addEventListener('click', function () {
            var target = sel.value;
            var pieces = Array.prototype.map.call(srcs, function (n) { return n.textContent || ''; });
            var text = pieces.join('\n\n').trim();
            if (!text) return;

            btn.disabled = true;
            // Save the WHOLE button markup (icon + label) so the icon
            // survives the loading-state toggle when we restore below.
            var originalHtml = btn.innerHTML;
            btn.textContent = loading;
            if (dest) { dest.hidden = false; dest.textContent = loading; }
            window.SysRevAI && window.SysRevAI.showAiOverlay && window.SysRevAI.showAiOverlay();

            var fd = new FormData();
            fd.append('_csrf', csrf);
            fd.append('text', text);
            fd.append('target_lang', target);

            fetch(url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok) {
                        if (dest) dest.textContent = d.translated;
                    } else if (dest) {
                        dest.textContent = errorMsg + (d && d.error ? ' (' + d.error + ')' : '');
                    }
                })
                .catch(function () { if (dest) dest.textContent = errorMsg; })
                .finally(function () {
                    btn.disabled = false; btn.innerHTML = originalHtml;
                    window.SysRevAI && window.SysRevAI.hideAiOverlay && window.SysRevAI.hideAiOverlay();
                });
        });
    });
})();
</script>
