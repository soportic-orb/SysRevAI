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
<div class="page ft-screen-page">
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
        <div class="ft-screen-grid">

            <!-- ── 4/6 — Article + PDF ─────────────────────────────────── -->
            <section class="ft-screen-grid__main">
                <div class="screen-card">
                    <h2 class="screen-card__title"><?= e((string) ($reference['title'] ?: '—')) ?></h2>
                    <p class="muted screen-card__meta">
                        <?= e(implode('; ', array_slice($authors, 0, 6))) ?><?= count($authors) > 6 ? ' et al.' : '' ?>
                        <?php if (!empty($reference['year'])): ?> · <?= (int) $reference['year'] ?><?php endif; ?>
                        <?php if (!empty($reference['journal'])): ?> · <em><?= e((string) $reference['journal']) ?></em><?php endif; ?>
                        <?php if (!empty($reference['doi'])): ?> · DOI: <?= e((string) $reference['doi']) ?><?php endif; ?>
                    </p>

                    <?php if ($fullText === null): ?>
                        <div class="alert alert--warn" data-no-toast><?= e(__('fulltext.no_pdf')) ?></div>
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
                        <div class="pdf-viewer ft-pdf-viewer">
                            <iframe src="/reviews/<?= $id ?>/references/<?= (int) $reference['id'] ?>/pdf"
                                    title="<?= e((string) ($fullText['original_filename'] ?? 'PDF')) ?>"
                                    width="100%"></iframe>
                            <p class="muted" style="margin:8px 0 0">
                                <?= e((string) ($fullText['original_filename'] ?? '')) ?> ·
                                <?= e(__('fulltext.pages', (int) $fullText['page_count'])) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- ── 2/6 — Protocol (collapsed) · Chat · Decision ────────── -->
            <aside class="ft-screen-grid__side">

                <!-- Protocol — collapsed by default -->
                <section class="section-card collapse-card screen-protocol-card"
                         data-collapsible data-collapsed-default>
                    <button type="button" class="collapse-card__head"
                            data-collapsible-toggle aria-controls="ftProtocolBody" aria-expanded="false">
                        <span class="collapse-card__title"><?= e(__('reviews.protocol')) ?></span>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             class="icon icon--chevron" aria-hidden="true">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="collapse-card__body screen-protocol__body"
                         id="ftProtocolBody" data-collapsible-body hidden>
                        <?php if (!empty($review['question'])): ?>
                            <p><strong><?= e(__('reviews.question')) ?>:</strong><br><?= nl2br(e((string) $review['question'])) ?></p>
                        <?php endif; ?>
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
                    </div>
                </section>

                <?php if ($fullText !== null): ?>
                    <!-- AI Chat with this article (collapsible, open by default) -->
                    <section class="section-card collapse-card chat-panel"
                             data-collapsible>
                        <button type="button" class="collapse-card__head"
                                data-collapsible-toggle aria-controls="ftChatBody" aria-expanded="true">
                            <span class="collapse-card__title"><?= e(__('fulltext.chat_title')) ?></span>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="icon icon--chevron" aria-hidden="true">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="collapse-card__body chat-panel__body"
                             id="ftChatBody" data-collapsible-body>
                            <div class="chat-history" id="chatHistory">
                                <?php if ($chatHistory === []): ?>
                                    <p class="muted"><?= e(__('fulltext.chat_empty')) ?></p>
                                <?php else: ?>
                                    <?php foreach ($chatHistory as $m): ?>
                                        <div class="chat-msg chat-msg--<?= e((string) $m['role']) ?>"><?= $m['role'] === 'assistant' ? markdown((string) $m['content']) : nl2br(e((string) $m['content'])) ?></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <form class="chat-form" id="chatForm"
                                  data-url="/reviews/<?= $id ?>/references/<?= (int) $reference['id'] ?>/chat"
                                  data-err-default="<?= e(__('fulltext.chat_err_default')) ?>"
                                  data-err-no-key="<?= e(__('fulltext.chat_err_no_api_key')) ?>"
                                  data-err-disabled="<?= e(__('fulltext.chat_err_disabled')) ?>"
                                  data-err-budget="<?= e(__('fulltext.chat_err_budget')) ?>"
                                  data-err-no-text="<?= e(__('fulltext.chat_err_no_text')) ?>"
                                  data-err-invalid="<?= e(__('fulltext.chat_err_invalid')) ?>">
                                <?= csrf_field() ?>
                                <textarea class="input" name="message" rows="2" placeholder="<?= e(__('fulltext.chat_placeholder')) ?>" required></textarea>
                                <button class="btn btn--primary btn--sm" type="submit"><?= e(__('fulltext.chat_send')) ?></button>
                            </form>
                        </div>
                    </section>

                    <!-- Decision box (below the AI chat) -->
                    <section class="section-card screen-decision-card">
                        <h3 class="section__subtitle"><?= e(__('screening.assessment_title')) ?></h3>
                        <form method="post" action="<?= e($basePath) ?>/decide" class="screen-actions" id="screenForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="reference_id" value="<?= (int) $reference['id'] ?>">
                            <input type="hidden" name="time_spent" id="timeSpent" value="0">

                            <div class="field">
                                <label class="field-label" for="reason"><?= e(__('screening.exclude_reason')) ?></label>
                                <select class="select" name="reason" id="reason">
                                    <option value=""><?= e(__('screening.no_reason')) ?></option>
                                    <?php foreach ($reasons as $r): ?>
                                        <option value="<?= e((string) $r['label']) ?>"><?= e((string) $r['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label class="field-label" for="notes"><?= e(__('screening.notes_label')) ?></label>
                                <textarea class="input" id="notes" name="notes" rows="2"
                                          placeholder="<?= e(__('screening.notes')) ?>"></textarea>
                            </div>

                            <div class="decision-buttons decision-buttons--stack">
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

                            <p class="muted screen-legend"><?= e(__('screening.shortcuts')) ?></p>
                        </form>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php if ($reference !== null): ?>
<script>
/* Hand the Copilot a context object so it knows which page the user is on
   and which article they're screening right now. */
window.SysRevAICopilotContext = {
    page:               'fulltext-screening',
    reference_id:        <?= (int) $reference['id'] ?>,
    reference_title:    <?= json_encode((string) ($reference['title'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_year:      <?= (int) ($reference['year'] ?? 0) ?>,
    reference_journal:  <?= json_encode((string) ($reference['journal'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_doi:      <?= json_encode((string) ($reference['doi'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_pmid:     <?= json_encode((string) ($reference['pmid'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    reference_abstract: <?= json_encode((string) ($reference['abstract'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
    has_full_text:       <?= $fullText !== null ? 'true' : 'false' ?>
};
</script>
<?php endif; ?>

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
        /* Map server-side error codes to specific user-facing messages so
           the reviewer can see whether it's a feature toggle, a budget
           cap, a missing PDF text or a real network failure — rather than
           the previous generic "AI unavailable" wall. */
        var errLabel = function (code) {
            switch (code) {
                case 'no_api_key':       return cf.getAttribute('data-err-no-key');
                case 'feature_disabled': return cf.getAttribute('data-err-disabled');
                case 'budget_exceeded':  return cf.getAttribute('data-err-budget');
                case 'no_text':          return cf.getAttribute('data-err-no-text');
                case 'invalid_message':  return cf.getAttribute('data-err-invalid');
                default:                 return cf.getAttribute('data-err-default');
            }
        };

        cf.addEventListener('submit', function (e) {
            e.preventDefault();
            var ta = cf.querySelector('textarea');
            var msg = ta.value.trim();
            if (!msg) return;
            appendBubble('user', msg);
            /* Snapshot the form BEFORE clearing the textarea — otherwise
               new FormData(cf) sees the empty input and the server returns
               "invalid_message". */
            var data = new FormData(cf);
            data.set('message', msg);
            ta.value = '';
            var thinking = appendBubble('assistant', '…');
            fetch(cf.getAttribute('data-url'), { method: 'POST', body: data })
                .then(function (r) {
                    return r.json().catch(function () { return { ok: false, error: 'http_' + r.status }; });
                })
                .then(function (d) {
                    thinking.remove();
                    if (d && d.ok && d.reply) {
                        appendBubble('assistant', d.reply);
                    } else {
                        appendBubble('assistant', errLabel((d && d.error) || ''));
                    }
                })
                .catch(function () { thinking.remove(); appendBubble('assistant', errLabel('')); });
        });
    }

    function appendBubble(role, text) {
        var empty = ch.querySelector('p.muted');
        if (empty) empty.remove();
        var div = document.createElement('div');
        div.className = 'chat-msg chat-msg--' + role;
        /* Assistant replies get full Markdown rendering (bold, lists,
           tables, code). User messages stay plain-text — we never want a
           reviewer's literal typing to be interpreted as HTML. */
        if (role === 'assistant' && window.SysRevAI && window.SysRevAI.mdRender) {
            div.innerHTML = window.SysRevAI.mdRender(text);
        } else {
            div.textContent = text;
        }
        ch.appendChild(div);
        ch.scrollTop = ch.scrollHeight;
        return div;
    }
})();
</script>
<?php endif; ?>
