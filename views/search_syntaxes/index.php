<?php

declare(strict_types=1);

/** @var array                                                       $review    */
/** @var array<int,array{id:int,database_key:string,syntax:string}>  $rows      */
/** @var array<int,array{key:string,label:string,syntax_hint:string}> $databases */

$id = (int) $review['id'];
?>
<div class="page review-syntaxes-page">
    <div class="page__head page__head--row">
        <div>
            <h1 class="page__title"><?= e(__('search_syntaxes.title')) ?></h1>
            <p class="page__subtitle muted"><?= e(__('search_syntaxes.intro')) ?></p>
        </div>
    </div>

    <section class="section-card review-syntaxes"
             id="reviewSyntaxes"
             data-csrf="<?= e(csrf_token()) ?>"
             data-review-id="<?= $id ?>"
             data-save-url="/reviews/<?= $id ?>/search-syntaxes"
             data-ai-url="/reviews/<?= $id ?>/search-syntaxes/ai-import">

        <div class="review-syntaxes__toolbar">
            <div class="review-syntaxes__add">
                <button type="button" class="btn btn--primary btn--sm" id="syntaxesAddBtn"
                        aria-haspopup="listbox" aria-expanded="false">
                    + <?= e(__('search_syntaxes.add')) ?>
                </button>
                <ul class="review-syntaxes__add-menu" id="syntaxesAddMenu" hidden role="listbox">
                    <?php foreach ($databases as $db): ?>
                        <li>
                            <button type="button" class="review-syntaxes__add-option"
                                    data-key="<?= e($db['key']) ?>"
                                    data-label="<?= e($db['label']) ?>">
                                <?= e($db['label']) ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="review-syntaxes__actions">
                <button type="button" class="btn btn--ghost btn--sm" id="syntaxesAiBtn"
                        data-busy-label="<?= e(__('search_syntaxes.ai_importing')) ?>"
                        title="<?= e(__('search_syntaxes.ai_import_title')) ?>">
                    &#10024; <?= e(__('search_syntaxes.ai_import')) ?>
                </button>
                <button type="button" class="btn btn--primary btn--sm" id="syntaxesSaveBtn">
                    <?= e(__('search_syntaxes.save')) ?>
                </button>
                <span class="review-syntaxes__status muted" id="syntaxesStatus"></span>
            </div>
        </div>

        <p class="muted review-syntaxes__hint">
            <?= e(__('search_syntaxes.hint')) ?>
        </p>

        <ul class="review-syntaxes__list" id="syntaxesList">
            <?php if ($rows === []): ?>
                <li class="review-syntaxes__empty muted" id="syntaxesEmpty">
                    <?= e(__('search_syntaxes.empty')) ?>
                </li>
            <?php else: ?>
                <?php foreach ($rows as $row):
                    $label = $row['database_key'];
                    foreach ($databases as $db) {
                        if ($db['key'] === $row['database_key']) { $label = $db['label']; break; }
                    }
                ?>
                    <li class="review-syntaxes__item" data-key="<?= e($row['database_key']) ?>">
                        <header class="review-syntaxes__item-head">
                            <strong class="review-syntaxes__item-label"><?= e($label) ?></strong>
                            <button type="button" class="btn btn--ghost btn--xs review-syntaxes__item-remove"
                                    title="<?= e(__('search_syntaxes.remove')) ?>"
                                    aria-label="<?= e(__('search_syntaxes.remove')) ?>">&times;</button>
                        </header>
                        <textarea class="input review-syntaxes__item-text"
                                  rows="6"
                                  placeholder="<?= e(__('search_syntaxes.placeholder')) ?>"
                        ><?= e($row['syntax']) ?></textarea>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </section>
</div>

<script>
(function () {
    'use strict';
    var root = document.getElementById('reviewSyntaxes');
    if (!root) return;
    var csrf    = root.getAttribute('data-csrf');
    var saveUrl = root.getAttribute('data-save-url');
    var aiUrl   = root.getAttribute('data-ai-url');

    var addBtn  = document.getElementById('syntaxesAddBtn');
    var addMenu = document.getElementById('syntaxesAddMenu');
    var aiBtn   = document.getElementById('syntaxesAiBtn');
    var saveBtn = document.getElementById('syntaxesSaveBtn');
    var status  = document.getElementById('syntaxesStatus');
    var list    = document.getElementById('syntaxesList');
    var emptyEl = document.getElementById('syntaxesEmpty');

    var labels = {
        saving:   <?= json_encode(__('search_syntaxes.saving')) ?>,
        saved:    <?= json_encode(__('search_syntaxes.saved')) ?>,
        error:    <?= json_encode(__('search_syntaxes.error')) ?>,
        empty:    <?= json_encode(__('search_syntaxes.empty')) ?>,
        remove:   <?= json_encode(__('search_syntaxes.remove')) ?>,
        ph:       <?= json_encode(__('search_syntaxes.placeholder')) ?>,
        importing:<?= json_encode(__('search_syntaxes.ai_importing')) ?>,
        nothing:  <?= json_encode(__('search_syntaxes.ai_nothing_found')) ?>,
        added:    <?= json_encode(__('search_syntaxes.ai_added')) ?>
    };

    /* ── Add-menu toggle + close-on-outside-click ─────────────────────── */
    addBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = addMenu.hidden;
        addMenu.hidden = !open;
        addBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
        if (addMenu.hidden) return;
        if (!root.contains(e.target)) {
            addMenu.hidden = true;
            addBtn.setAttribute('aria-expanded', 'false');
        }
    });
    addMenu.querySelectorAll('.review-syntaxes__add-option').forEach(function (btn) {
        btn.addEventListener('click', function () {
            appendRow(btn.getAttribute('data-key'), btn.getAttribute('data-label'), '', { focus: true });
            addMenu.hidden = true;
            addBtn.setAttribute('aria-expanded', 'false');
        });
    });

    /* ── Remove rows ──────────────────────────────────────────────────── */
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.review-syntaxes__item-remove');
        if (!btn) return;
        var item = btn.closest('.review-syntaxes__item');
        if (item) item.remove();
        ensureEmptyState();
    });

    /* ── Save ─────────────────────────────────────────────────────────── */
    saveBtn.addEventListener('click', function () { saveNow(); });

    function saveNow() {
        status.textContent = labels.saving;
        saveBtn.disabled = true;
        return fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf, rows: collectRows() })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            status.textContent = (body && body.ok) ? labels.saved : labels.error;
        })
        .catch(function () { status.textContent = labels.error; })
        .finally(function () { saveBtn.disabled = false; });
    }

    /* ── AI import ────────────────────────────────────────────────────── */
    aiBtn.addEventListener('click', function () {
        var originalLabel = aiBtn.textContent;
        aiBtn.disabled = true;
        aiBtn.textContent = aiBtn.getAttribute('data-busy-label') || originalLabel;
        status.textContent = labels.importing;

        fetch(aiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            if (!body || !body.ok || !body.data) {
                status.textContent = labels.error;
                return;
            }
            // Add a row for every database key the AI found a syntax for,
            // skipping any database already present on the page so we
            // never trample text the user has already typed.
            var present = new Set();
            list.querySelectorAll('.review-syntaxes__item').forEach(function (el) {
                present.add(el.getAttribute('data-key'));
            });
            var added = 0;
            Object.keys(body.data).forEach(function (key) {
                var syntax = body.data[key];
                if (!syntax || present.has(key)) return;
                var label = databaseLabel(key);
                appendRow(key, label, syntax, { focus: false });
                added++;
            });
            status.textContent = added === 0
                ? labels.nothing
                : labels.added.replace('%s', String(added));
        })
        .catch(function () { status.textContent = labels.error; })
        .finally(function () {
            aiBtn.disabled = false;
            aiBtn.textContent = originalLabel;
        });
    });

    /* ── Helpers ──────────────────────────────────────────────────────── */
    function databaseLabel(key) {
        var opt = addMenu.querySelector('.review-syntaxes__add-option[data-key="' + cssEscape(key) + '"]');
        return opt ? opt.getAttribute('data-label') : key;
    }
    function cssEscape(s) {
        return String(s).replace(/[^a-zA-Z0-9_\-]/g, '_');
    }
    function appendRow(key, label, syntax, opts) {
        if (emptyEl && emptyEl.parentNode) emptyEl.remove();
        var li = document.createElement('li');
        li.className = 'review-syntaxes__item';
        li.setAttribute('data-key', key);
        var head = document.createElement('header');
        head.className = 'review-syntaxes__item-head';
        var name = document.createElement('strong');
        name.className = 'review-syntaxes__item-label';
        name.textContent = label;
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn--ghost btn--xs review-syntaxes__item-remove';
        remove.title = labels.remove;
        remove.setAttribute('aria-label', labels.remove);
        remove.innerHTML = '&times;';
        head.appendChild(name);
        head.appendChild(remove);
        var ta = document.createElement('textarea');
        ta.className = 'input review-syntaxes__item-text';
        ta.rows = 6;
        ta.placeholder = labels.ph;
        ta.value = syntax || '';
        li.appendChild(head);
        li.appendChild(ta);
        list.appendChild(li);
        if (opts && opts.focus) ta.focus();
    }
    function ensureEmptyState() {
        if (list.querySelectorAll('.review-syntaxes__item').length === 0) {
            if (!document.getElementById('syntaxesEmpty')) {
                var li = document.createElement('li');
                li.id = 'syntaxesEmpty';
                li.className = 'review-syntaxes__empty muted';
                li.textContent = labels.empty;
                list.appendChild(li);
                emptyEl = li;
            }
        }
    }
    function collectRows() {
        var out = [];
        list.querySelectorAll('.review-syntaxes__item').forEach(function (li) {
            var ta = li.querySelector('.review-syntaxes__item-text');
            out.push({
                database_key: li.getAttribute('data-key') || '',
                syntax:       ta ? ta.value : ''
            });
        });
        return out;
    }
})();
</script>
