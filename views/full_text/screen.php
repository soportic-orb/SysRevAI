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
/** @var string $stage */
/** @var ?array $fullText */
/** @var array $chatHistory */
$id = (int) $review['id'];
$total = $completed + $pending;
$pct = $total > 0 ? (int) round($completed / $total * 100) : 100;
$authors = $reference ? (json_decode((string) $reference['authors_json'], true) ?: []) : [];
$basePath = '/reviews/' . $id . '/full-text';
?>
<div class="page">
    <div class="page__head page__head--row">
        <div>
            <div class="breadcrumb"><a href="/reviews/<?= $id ?>"><?= e((string) $review['title']) ?></a> /</div>
            <h1 class="page__title">
                <?= e(__('fulltext.title')) ?>
                <?php $phaseKey = 'fulltext'; require config('paths.base') . '/views/partials/phase_info.php'; ?>
            </h1>
        </div>
        <div class="btn-row">
            <?php if ($canCoordinate && $conflicts > 0): ?>
                <a class="btn btn--ghost" href="<?= e($basePath) ?>/conflicts"><?= e(__('screening.conflicts', $conflicts)) ?></a>
            <?php endif; ?>
            <?php if ($canCoordinate): ?>
                <form method="post" action="<?= e($basePath) ?>/coordinator">
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

    <?php if (($flash = \SysRevAI\Core\Session::pullFlash('success')) !== null): ?>
        <div class="alert alert--success"><?= e((string) $flash) ?></div>
    <?php endif; ?>
    <?php if (($err = \SysRevAI\Core\Session::pullFlash('error')) !== null): ?>
        <div class="alert alert--error"><?= e((string) $err) ?></div>
    <?php endif; ?>

    <?php if ($reference === null): ?>
        <div class="empty-state">
            <p><?= e(__('fulltext.all_done')) ?></p>
            <?php if ($canCoordinate): ?>
                <form method="post" action="<?= e($basePath) ?>/start" style="display:inline">
                    <?= csrf_field() ?>
                    <button class="btn btn--ghost"><?= e(__('fulltext.start')) ?></button>
                </form>
            <?php endif; ?>
            <a class="btn btn--primary" href="/reviews/<?= $id ?>"><?= e(__('reviews.protocol')) ?></a>
        </div>
    <?php else: ?>
        <div class="ft-layout">
            <section class="ft-main">
                <div class="screen-card">
                    <h2 class="screen-card__title"><?= e((string) ($reference['title'] ?: '—')) ?></h2>
                    <p class="muted screen-card__meta">
                        <?= e(implode('; ', array_slice($authors, 0, 6))) ?><?= count($authors) > 6 ? ' et al.' : '' ?>
                        <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
                        <?php if (!empty($reference['journal'])): ?> · <em><?= e((string) $reference['journal']) ?></em><?php endif; ?>
                    </p>

                    <?php if ($fullText === null): ?>
                        <div class="alert alert--warn"><?= e(__('fulltext.no_pdf')) ?></div>
                        <form method="post" action="/reviews/<?= $id ?>/references/<?= (int) $reference['id'] ?>/pdf" enctype="multipart/form-data" class="form-grid">
                            <?= csrf_field() ?>
                            <div class="field">
                                <label class="field-label" for="pdf"><?= e(__('fulltext.upload_label')) ?></label>
                                <input class="input" id="pdf" name="pdf" type="file" accept="application/pdf,.pdf" required>
                                <span class="field-help"><?= e(__('fulltext.upload_help')) ?></span>
                            </div>
                            <div><button class="btn btn--primary"><?= e(__('fulltext.upload_btn')) ?></button></div>
                        </form>
                    <?php else: ?>
                        <div class="pdf-viewer">
                            <iframe src="/reviews/<?= $id ?>/references/<?= (int) $reference['id'] ?>/pdf"
                                    title="<?= e((string) ($fullText['original_filename'] ?? 'PDF')) ?>"
                                    width="100%" height="540"></iframe>
                            <p class="muted" style="margin:8px 0 0">
                                <?= e((string) ($fullText['original_filename'] ?? '')) ?> ·
                                <?= e(__('fulltext.pages', (int) $fullText['page_count'])) ?>
                            </p>
                        </div>

                        <form method="post" action="<?= e($basePath) ?>/decide" class="screen-actions" id="screenForm">
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
                                <button type="submit" name="decision" value="include" class="btn btn--include">
                                    <?= e(__('screening.include')) ?> <kbd>I</kbd>
                                </button>
                                <button type="submit" name="decision" value="maybe" class="btn btn--maybe">
                                    <?= e(__('screening.maybe')) ?> <kbd>M</kbd>
                                </button>
                                <button type="submit" name="decision" value="exclude" class="btn btn--exclude">
                                    <?= e(__('screening.exclude')) ?> <kbd>E</kbd>
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="ft-aside">
                <div class="screen-protocol">
                    <h3 class="section__subtitle"><?= e(__('reviews.protocol')) ?></h3>
                    <?php foreach (['population', 'intervention', 'comparison', 'outcome'] as $f): ?>
                        <?php if (!empty($pico[$f])): ?>
                            <p><strong><?= e(__('reviews.pico_' . $f)) ?>:</strong> <?= e((string) $pico[$f]) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <p class="muted screen-legend"><?= e(__('screening.shortcuts')) ?></p>
                </div>

                <?php if ($fullText !== null): ?>
                <div class="chat-panel">
                    <h3 class="section__subtitle"><?= e(__('fulltext.chat_title')) ?></h3>
                    <div class="chat-history" id="chatHistory">
                        <?php if ($chatHistory === []): ?>
                            <p class="muted"><?= e(__('fulltext.chat_empty')) ?></p>
                        <?php else: ?>
                            <?php foreach ($chatHistory as $m): ?>
                                <div class="chat-msg chat-msg--<?= e((string) $m['role']) ?>"><?= nl2br(e((string) $m['content'])) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form class="chat-form" id="chatForm"
                          data-url="/reviews/<?= $id ?>/references/<?= (int) $reference['id'] ?>/chat"
                          data-error="<?= e(__('screening.ai_error')) ?>">
                        <?= csrf_field() ?>
                        <textarea class="input" name="message" rows="2" placeholder="<?= e(__('fulltext.chat_placeholder')) ?>" required></textarea>
                        <button class="btn btn--primary btn--sm" type="submit"><?= e(__('fulltext.chat_send')) ?></button>
                    </form>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php if ($reference !== null && $fullText !== null): ?>
<script>
(function () {
    var start = Date.now();
    var form = document.getElementById('screenForm');
    if (form) {
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
    }

    var cf = document.getElementById('chatForm');
    var ch = document.getElementById('chatHistory');
    if (cf && ch) {
        cf.addEventListener('submit', function (e) {
            e.preventDefault();
            var ta = cf.querySelector('textarea');
            var msg = ta.value.trim();
            if (!msg) return;
            appendBubble('user', msg);
            ta.value = '';
            var thinking = appendBubble('assistant', '…');
            var data = new FormData(cf);
            fetch(cf.getAttribute('data-url'), { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    thinking.remove();
                    if (d && d.ok && d.reply) {
                        appendBubble('assistant', d.reply);
                    } else {
                        appendBubble('assistant', cf.getAttribute('data-error'));
                    }
                })
                .catch(function () { thinking.remove(); appendBubble('assistant', cf.getAttribute('data-error')); });
        });
    }

    function appendBubble(role, text) {
        var empty = ch.querySelector('p.muted');
        if (empty) empty.remove();
        var div = document.createElement('div');
        div.className = 'chat-msg chat-msg--' + role;
        div.textContent = text;
        ch.appendChild(div);
        ch.scrollTop = ch.scrollHeight;
        return div;
    }
})();
</script>
<?php endif; ?>
