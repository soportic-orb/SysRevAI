<?php

declare(strict_types=1);

/** @var array $review */
/** @var ?array $reference */
/** @var array $pico */
/** @var array $reasons */
/** @var int $pending */
/** @var int $completed */
/** @var int $conflicts */
/** @var bool $canCoordinate */
$id = (int) $review['id'];
$total = $completed + $pending;
$pct = $total > 0 ? (int) round($completed / $total * 100) : 100;
$authors = $reference ? (json_decode((string) $reference['authors_json'], true) ?: []) : [];
?>
<div class="page page--narrow">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a> /</div>
            <h1 class="page__title"><?= e(__('screening.title')) ?></h1>
        </div>
        <div class="btn-row">
            <?php if ($canCoordinate && $conflicts > 0): ?>
                <a class="btn btn--ghost" href="/reviews/<?= $id ?>/screen/conflicts"><?= e(__('screening.conflicts', $conflicts)) ?></a>
            <?php endif; ?>
            <?php if ($canCoordinate): ?>
                <form method="post" action="/reviews/<?= $id ?>/screen/coordinator">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost"><?= e(__('screening.coordinator_view')) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="screen-progress">
        <div class="progress"><div class="progress__bar" style="width: <?= $pct ?>%"></div></div>
        <span class="muted"><?= e(__('screening.pending_you', $pending)) ?> · <?= e(__('screening.done_you', $completed)) ?></span>
    </div>

    <?php if ($reference === null): ?>
        <div class="empty-state">
            <p><?= e(__('screening.all_done')) ?></p>
            <?php if ($canCoordinate): ?>
                <form method="post" action="/reviews/<?= $id ?>/screen/start" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost"><?= e(__('screening.start')) ?></button>
                </form>
            <?php endif; ?>
            <a class="btn btn--primary" href="/reviews/<?= $id ?>/references"><?= e(__('references.title')) ?></a>
        </div>
    <?php else: ?>
        <div class="screen-grid">
            <div class="screen-card">
                <h2 class="screen-card__title"><?= e((string) ($reference['title'] ?: '—')) ?></h2>
                <p class="muted screen-card__meta">
                    <?= e(implode('; ', array_slice($authors, 0, 6))) ?><?= count($authors) > 6 ? ' et al.' : '' ?>
                    <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
                    <?php if (!empty($reference['journal'])): ?> · <em><?= e((string) $reference['journal']) ?></em><?php endif; ?>
                </p>
                <div class="screen-card__abstract">
                    <?= $reference['abstract'] ? nl2br(e((string) $reference['abstract'])) : '<span class="muted">' . e(__('screening.no_abstract')) . '</span>' ?>
                </div>

                <div class="ai-suggest">
                    <button type="button" class="btn btn--ghost btn--sm" id="suggestBtn"
                            data-url="/reviews/<?= $id ?>/screen/suggest?reference_id=<?= (int) $reference['id'] ?>"
                            data-loading="<?= e(__('screening.suggest_loading')) ?>"
                            data-error="<?= e(__('screening.ai_error')) ?>">
                        &#10024; <?= e(__('screening.suggest_ai')) ?>
                    </button>
                    <div class="ai-suggest__panel" id="suggestPanel" hidden></div>
                </div>

                <form method="post" action="/reviews/<?= $id ?>/screen/decide" class="screen-actions" id="screenForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="reference_id" value="<?= (int) $reference['id'] ?>">
                    <input type="hidden" name="time_spent" id="timeSpent" value="0">

                    <div class="screen-reason">
                        <label class="field-label" for="reason"><?= e(__('screening.exclude_reason')) ?></label>
                        <select class="select" name="reason" id="reason">
                            <option value=""><?= e(__('screening.no_reason')) ?></option>
                            <?php foreach ($reasons as $r): ?>
                                <option value="<?= e((string) $r['label']) ?>"><?= e((string) $r['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input class="input" name="notes" placeholder="<?= e(__('screening.notes')) ?>">

                    <div class="decision-buttons">
                        <button type="submit" name="decision" value="include" class="btn btn--include" data-key="i">
                            <?= e(__('screening.include')) ?> <kbd>I</kbd>
                        </button>
                        <button type="submit" name="decision" value="maybe" class="btn btn--maybe" data-key="m">
                            <?= e(__('screening.maybe')) ?> <kbd>M</kbd>
                        </button>
                        <button type="submit" name="decision" value="exclude" class="btn btn--exclude" data-key="e">
                            <?= e(__('screening.exclude')) ?> <kbd>E</kbd>
                        </button>
                    </div>
                </form>
            </div>

            <aside class="screen-protocol">
                <h3 class="section__subtitle"><?= e(__('reviews.protocol')) ?></h3>
                <?php foreach (['population', 'intervention', 'comparison', 'outcome', 'study_design'] as $f): ?>
                    <?php if (!empty($pico[$f])): ?>
                        <p><strong><?= e(__('reviews.pico_' . $f)) ?>:</strong> <?= e((string) $pico[$f]) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (!empty($review['inclusion_criteria'])): ?>
                    <p><strong><?= e(__('reviews.inclusion')) ?>:</strong><br><?= nl2br(e((string) $review['inclusion_criteria'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($review['exclusion_criteria'])): ?>
                    <p><strong><?= e(__('reviews.exclusion')) ?>:</strong><br><?= nl2br(e((string) $review['exclusion_criteria'])) ?></p>
                <?php endif; ?>
                <p class="muted screen-legend"><?= e(__('screening.shortcuts')) ?></p>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php if ($reference !== null): ?>
<script>
(function () {
    var start = Date.now();
    var form = document.getElementById('screenForm');
    form.addEventListener('submit', function () {
        document.getElementById('timeSpent').value = Math.round((Date.now() - start) / 1000);
    });
    document.addEventListener('keydown', function (e) {
        if (e.target.matches('input,select,textarea')) return;
        var map = { i: 'include', e: 'exclude', m: 'maybe' };
        var dec = map[e.key.toLowerCase()];
        if (dec) {
            var btn = form.querySelector('button[value="' + dec + '"]');
            if (btn) { document.getElementById('timeSpent').value = Math.round((Date.now() - start) / 1000); btn.click(); }
        }
    });

    var sBtn = document.getElementById('suggestBtn');
    var sPanel = document.getElementById('suggestPanel');
    if (sBtn) {
        sBtn.addEventListener('click', function () {
            sPanel.hidden = false;
            sPanel.className = 'ai-suggest__panel';
            sPanel.textContent = sBtn.getAttribute('data-loading');
            fetch(sBtn.getAttribute('data-url'), { headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.ok && d.data) {
                        var rec = String(d.data.recommendation || '').toLowerCase();
                        var conf = d.data.confidence != null ? Math.round(d.data.confidence * 100) + '%' : '';
                        sPanel.className = 'ai-suggest__panel is-' + rec;
                        sPanel.innerHTML = '<strong>' + rec.toUpperCase() + '</strong> ' + conf +
                            '<br>' + (d.data.reason ? String(d.data.reason).replace(/[<>&]/g, '') : '');
                    } else {
                        sPanel.textContent = sBtn.getAttribute('data-error');
                    }
                })
                .catch(function () { sPanel.textContent = sBtn.getAttribute('data-error'); });
        });
    }
})();
</script>
<?php endif; ?>
