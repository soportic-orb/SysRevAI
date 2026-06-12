<?php

declare(strict_types=1);

/** @var array  $article */
/** @var bool   $isOwner */
/** @var string $initialHtml */
/** @var bool   $hasReport */

$id = (int) $article['id'];

// The floating Copilot widget stays available on this page (per spec);
// the in-page editor talks to its own /edit/copilot endpoint, separate
// from the generic chat used by the floating button.
?>
<div class="page article-editor-page">
    <div class="page__head article-head">
        <div class="article-head__title">
            <h1 class="page__title">
                <?= e((string) ($article['title'] ?: '—')) ?>
                <span class="muted">— <?= e(__('articles.editor.title')) ?></span>
            </h1>
            <p class="page__subtitle muted">
                <?= e(__('articles.editor.subtitle')) ?>
                <span class="article-editor__save-state" id="editorSaveState">·</span>
            </p>
        </div>
        <?php
            $articleActionsActive = 'editor';
            require config('paths.base') . '/views/partials/article_actions.php';
        ?>
    </div>

    <div class="article-editor"
         id="articleEditor"
         data-article-id="<?= $id ?>"
         data-csrf="<?= e(csrf_token()) ?>"
         data-has-report="<?= $hasReport ? '1' : '0' ?>">
        <noscript>
            <p class="muted"><?= e(__('articles.editor.noscript')) ?></p>
        </noscript>

        <div class="article-editor__fallback" id="editorFallback" hidden>
            <p class="muted"><?= e(__('articles.editor.cdn_failed')) ?></p>
        </div>

        <!-- Toolbar — Save snapshot (with versions dropdown) + Word export. -->
        <div class="article-editor__toolbar">
            <div class="article-editor__toolbar-group article-editor__versions" id="editorVersions">
                <button type="button" class="btn btn--primary btn--sm" id="editorSaveVersion">
                    <?= e(__('articles.editor.save_version')) ?>
                </button>
                <button type="button" class="btn btn--ghost btn--sm article-editor__versions-toggle"
                        id="editorVersionsToggle"
                        aria-haspopup="listbox" aria-expanded="false"
                        title="<?= e(__('articles.editor.versions_toggle_title')) ?>">
                    <span class="article-editor__versions-toggle-label"><?= e(__('articles.editor.versions_btn')) ?></span>
                    <span aria-hidden="true">▾</span>
                </button>
                <div class="article-editor__versions-menu" id="editorVersionsMenu" hidden role="listbox">
                    <p class="article-editor__versions-empty muted" id="editorVersionsEmpty">
                        <?= e(__('articles.editor.versions_empty')) ?>
                    </p>
                    <ul class="article-editor__versions-list" id="editorVersionsList"></ul>
                </div>
            </div>
            <div class="article-editor__toolbar-group article-editor__toolbar-group--end">
                <a class="btn btn--ghost btn--sm" href="/tools/articles/<?= $id ?>/edit/word"
                   title="<?= e(__('articles.editor.export_word_title')) ?>">
                    <?= e(__('articles.editor.export_word')) ?>
                </a>
            </div>
        </div>

        <textarea id="articleEditorTextarea" class="article-editor__textarea"><?= e($initialHtml) ?></textarea>

        <!-- Bubble button that appears on a non-empty selection. -->
        <button type="button" class="article-editor__bubble"
                id="editorBubble" hidden
                title="<?= e(__('articles.editor.bubble_hint')) ?>">
            &#10024; <?= e(__('articles.editor.bubble_label')) ?>
        </button>

        <!-- Floating panel: prompt input + Accept/Reject preview. -->
        <div class="article-editor__panel" id="editorPanel" hidden>
            <header class="article-editor__panel-head">
                <strong><?= e(__('articles.editor.panel_title')) ?></strong>
                <button type="button" class="article-editor__panel-close" id="editorPanelClose" aria-label="<?= e(__('articles.editor.close')) ?>">&times;</button>
            </header>
            <div class="article-editor__panel-body">
                <p class="muted article-editor__panel-context" id="editorPanelContext"></p>
                <textarea class="input article-editor__panel-input"
                          id="editorPanelInput"
                          rows="3"
                          placeholder="<?= e(__('articles.editor.prompt_placeholder')) ?>"></textarea>
                <div class="btn-row">
                    <button type="button" class="btn btn--primary btn--sm" id="editorPanelSend"
                            data-busy-label="<?= e(__('common.working')) ?>">
                        <?= e(__('articles.editor.ask_btn')) ?>
                    </button>
                </div>
                <div class="article-editor__proposal" id="editorProposal" hidden>
                    <p class="article-editor__proposal-explanation" id="editorProposalExplanation"></p>
                    <div class="article-editor__proposal-preview" id="editorProposalPreview"></div>
                    <div class="btn-row">
                        <button type="button" class="btn btn--primary btn--sm" id="editorAccept">
                            &#10003; <?= e(__('articles.editor.accept')) ?>
                        </button>
                        <button type="button" class="btn btn--ghost btn--sm" id="editorReject">
                            &times; <?= e(__('articles.editor.reject')) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* Expose the live editor state to the floating global Copilot widget.
   The widget calls SysRevAICopilotContext() on every send, so the
   model sees the document and selection AS-OF the moment the question
   was asked — autosave isn't enough. */
window.SysRevAICopilotContext = function () {
    var html = '';
    var selHtml = '';
    var selText = '';
    try {
        if (window.tinymce && tinymce.activeEditor) {
            html = tinymce.activeEditor.getContent() || '';
            selHtml = tinymce.activeEditor.selection.getContent({ format: 'html' }) || '';
            selText = tinymce.activeEditor.selection.getContent({ format: 'text' }) || '';
        } else {
            var ta = document.getElementById('articleEditorTextarea');
            if (ta) html = ta.value || '';
        }
    } catch (e) { /* TinyMCE not ready yet — fall back to whatever we have */ }
    return {
        page:           'article-collab-editor',
        article_id:      <?= $id ?>,
        article_title:  <?= json_encode((string) ($article['title'] ?? ''), JSON_UNESCAPED_UNICODE) ?>,
        editor_html:     html.slice(0, 60000),
        selection_html:  selHtml.slice(0, 8000),
        selection_text:  selText.slice(0, 4000)
    };
};
</script>

<!-- TinyMCE (open-source, MIT) via jsDelivr; unpkg as fallback if the
     primary CDN is unreachable. The textarea stays visible until the
     editor takes over so the user always has SOMETHING to read. -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.4.1/tinymce.min.js" referrerpolicy="origin"
        onerror="(function(s){var f=document.createElement('script');f.src='https://unpkg.com/tinymce@7.4.1/tinymce.min.js';f.referrerPolicy='origin';s.parentNode.appendChild(f);})(this)"></script>
<script>
(function () {
    'use strict';
    var root = document.getElementById('articleEditor');
    if (!root) return;

    // Wait for TinyMCE to load from the CDN (or its fallback). If it
    // never arrives — offline, CDN blocked, etc. — unhide the small
    // fallback notice and leave the raw textarea visible so the user
    // can still read and copy their text.
    var attempts = 0;
    function waitForTinyMce(cb) {
        if (typeof tinymce !== 'undefined') { cb(); return; }
        if (attempts++ > 40) {
            var fb = document.getElementById('editorFallback');
            if (fb) fb.hidden = false;
            return;
        }
        setTimeout(function () { waitForTinyMce(cb); }, 200);
    }
    waitForTinyMce(boot);

    function boot() {

    var articleId = root.getAttribute('data-article-id');
    var csrf      = root.getAttribute('data-csrf');
    var saveUrl   = '/tools/articles/' + articleId + '/edit/save';
    var imageUrl  = '/tools/articles/' + articleId + '/edit/image';
    var copilotUrl = '/tools/articles/' + articleId + '/edit/copilot';

    var saveState = document.getElementById('editorSaveState');
    var bubble    = document.getElementById('editorBubble');
    var panel     = document.getElementById('editorPanel');
    var panelCtx  = document.getElementById('editorPanelContext');
    var panelInput= document.getElementById('editorPanelInput');
    var sendBtn   = document.getElementById('editorPanelSend');
    var proposal  = document.getElementById('editorProposal');
    var explainEl = document.getElementById('editorProposalExplanation');
    var previewEl = document.getElementById('editorProposalPreview');
    var acceptBtn = document.getElementById('editorAccept');
    var rejectBtn = document.getElementById('editorReject');
    var closeBtn  = document.getElementById('editorPanelClose');

    var labels = {
        saved:     <?= json_encode(__('articles.editor.saved_now')) ?>,
        saving:    <?= json_encode(__('articles.editor.saving')) ?>,
        unsaved:   <?= json_encode(__('articles.editor.unsaved')) ?>,
        error:     <?= json_encode(__('copilot.error')) ?>,
        empty:     <?= json_encode(__('articles.editor.prompt_empty')) ?>,
        ctxSel:    <?= json_encode(__('articles.editor.ctx_selection')) ?>,
        ctxDoc:    <?= json_encode(__('articles.editor.ctx_document')) ?>,
        copyFail:  <?= json_encode(__('articles.editor.image_too_large')) ?>
    };

    var savedSelection = null;     // bookmark for the active selection
    var pendingProposal = null;    // last Copilot proposal awaiting user decision

    tinymce.init({
        selector: '#articleEditorTextarea',
        license_key: 'gpl',
        plugins: 'autolink lists link image table code charmap searchreplace visualblocks wordcount fullscreen',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link image table | alignleft aligncenter alignright | searchreplace | code fullscreen',
        menubar: 'edit insert format table',
        height: 720,
        min_height: 480,
        branding: false,
        promotion: false,
        statusbar: true,
        // Keep TinyMCE's default whitelist — restricting it strips
        // <div>/<span> and other elements PhpWord emits for DOCX
        // imports, leaving the editor visually empty. We extend it
        // instead so superscripts / subscripts also survive.
        extended_valid_elements: 'sup,sub',
        relative_urls: false,
        convert_urls: false,
        paste_data_images: false,
        images_upload_handler: function (blobInfo) {
            return new Promise(function (resolve, reject) {
                if (blobInfo.blob().size > 10 * 1024 * 1024) {
                    reject({ message: labels.copyFail, remove: true });
                    return;
                }
                var fd = new FormData();
                fd.append('file', blobInfo.blob(), blobInfo.filename());
                fd.append('_csrf', csrf);
                fetch(imageUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: fd
                })
                .then(function (r) { return r.json(); })
                .then(function (body) {
                    if (body && body.ok && body.location) resolve(body.location);
                    else reject({ message: (body && body.error) || 'upload failed', remove: true });
                })
                .catch(function () { reject({ message: 'upload failed', remove: true }); });
            });
        },
        setup: function (editor) {
            editor.on('init', function () {
                saveState.textContent = labels.saved;
            });

            // ── Autosave ───────────────────────────────────────────────
            var saveTimer = null;
            editor.on('input change SetContent', function () {
                saveState.textContent = labels.unsaved;
                if (saveTimer) clearTimeout(saveTimer);
                saveTimer = setTimeout(function () { autosave(editor); }, 1500);
            });
            window.addEventListener('beforeunload', function () {
                if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
                if (saveState.textContent === labels.unsaved || saveState.textContent === labels.saving) {
                    // Synchronous beacon-style save on unload.
                    try {
                        navigator.sendBeacon(
                            saveUrl,
                            new Blob([JSON.stringify({ _csrf: csrf, html: editor.getContent() })],
                                     { type: 'application/json' })
                        );
                    } catch (e) {}
                }
            });

            // ── Selection bubble ───────────────────────────────────────
            // NodeChange fires on every cursor move (typing, arrows,
            // mouse) and is the most reliable signal for selection
            // state in TinyMCE 7. Collapsing the selection — clicking
            // somewhere else inside the document, hitting Esc, etc. —
            // should make the floating "Comentar amb Copilot" bubble
            // disappear immediately so it never sticks around without
            // an underlying selection.
            function refreshBubble() {
                if (!panel.hidden) {
                    // The side panel is owning the selection — no bubble.
                    bubble.hidden = true;
                    return;
                }
                var sel = editor.selection.getContent({ format: 'text' }) || '';
                if (sel.trim() !== '' && !editor.selection.isCollapsed()) {
                    positionBubble(editor);
                } else {
                    bubble.hidden = true;
                }
            }
            editor.on('NodeChange SelectionChange MouseUp KeyUp Click', refreshBubble);
            editor.on('blur', function () {
                // Don't hide if focus moved to the bubble or side panel.
                setTimeout(function () {
                    if (!document.activeElement || !root.contains(document.activeElement)) {
                        bubble.hidden = true;
                    }
                }, 80);
            });
        }
    });

    function positionBubble(editor) {
        var rng = editor.selection.getRng();
        if (!rng || !rng.getBoundingClientRect) { bubble.hidden = true; return; }
        var rect = rng.getBoundingClientRect();
        if (!rect || (rect.width === 0 && rect.height === 0)) { bubble.hidden = true; return; }
        var iframe = editor.getContainer().querySelector('iframe');
        var iframeRect = iframe ? iframe.getBoundingClientRect() : { top: 0, left: 0 };
        var top = iframeRect.top + rect.top + window.scrollY - 40;
        var left = iframeRect.left + rect.left + window.scrollX + (rect.width / 2) - 80;
        bubble.style.top = Math.max(window.scrollY + 60, top) + 'px';
        bubble.style.left = Math.max(8, left) + 'px';
        bubble.hidden = false;
    }

    bubble.addEventListener('click', function () {
        openPanel(true);
    });
    closeBtn.addEventListener('click', closePanel);

    /* ── Drag the Copilot side panel by its header ─────────────────────
       The panel's default tether is top:100px;right:28px (CSS). As soon
       as the user grabs the header we switch to absolute viewport
       coords; the position is persisted to localStorage so the choice
       survives reloads. mousedown on the close button is ignored so
       the X still closes the panel. */
    var panelHead = panel.querySelector('.article-editor__panel-head');
    var panelPosKey = 'sysrevai.article_editor.panel_position';
    var panelDrag = null;

    function clampPanelTo(left, top) {
        var r = panel.getBoundingClientRect();
        var w = r.width  || 380;
        var h = r.height || 320;
        left = Math.min(Math.max(8, left), Math.max(8, window.innerWidth  - w - 8));
        top  = Math.min(Math.max(8, top),  Math.max(8, window.innerHeight - h - 8));
        panel.style.left = left + 'px';
        panel.style.top  = top  + 'px';
        panel.classList.add('article-editor__panel--positioned');
    }
    function applyStoredPanelPosition() {
        try {
            var raw = localStorage.getItem(panelPosKey);
            if (!raw) return;
            var p = JSON.parse(raw);
            if (typeof p.left === 'number' && typeof p.top === 'number') {
                clampPanelTo(p.left, p.top);
            }
        } catch (e) {}
    }

    if (panelHead) {
        panelHead.addEventListener('mousedown', function (e) {
            // Ignore drags that start on a button so the close X still works.
            if (e.target.closest('button, a, svg, input, textarea')) return;
            var rect = panel.getBoundingClientRect();
            panelDrag = { dx: e.clientX - rect.left, dy: e.clientY - rect.top };
            panelHead.classList.add('article-editor__panel-head--dragging');
            e.preventDefault();
        });
        document.addEventListener('mousemove', function (e) {
            if (!panelDrag) return;
            clampPanelTo(e.clientX - panelDrag.dx, e.clientY - panelDrag.dy);
        });
        document.addEventListener('mouseup', function () {
            if (!panelDrag) return;
            panelDrag = null;
            panelHead.classList.remove('article-editor__panel-head--dragging');
            try {
                var r = panel.getBoundingClientRect();
                localStorage.setItem(panelPosKey, JSON.stringify({ left: r.left, top: r.top }));
            } catch (e) {}
        });
    }
    window.addEventListener('resize', function () {
        if (!panel.classList.contains('article-editor__panel--positioned')) return;
        var r = panel.getBoundingClientRect();
        clampPanelTo(r.left, r.top);
    });

    function openPanel(withSelection) {
        var editor = tinymce.activeEditor;
        if (!editor) return;
        savedSelection = editor.selection.getBookmark(2, true);
        var sel = withSelection ? editor.selection.getContent({ format: 'text' }) : '';
        panelCtx.textContent = sel && sel.trim() !== ''
            ? (labels.ctxSel + ' « ' + truncate(sel, 140) + ' »')
            : labels.ctxDoc;
        hideProposal();
        panel.hidden = false;
        bubble.hidden = true;
        panelInput.value = '';
        // Restore the user's last drag position. We can't measure the
        // panel while it's `hidden`, so defer to the next tick.
        setTimeout(applyStoredPanelPosition, 0);
        panelInput.focus();
    }
    function closePanel() {
        panel.hidden = true;
        savedSelection = null;
        pendingProposal = null;
    }

    sendBtn.addEventListener('click', function () {
        var prompt = (panelInput.value || '').trim();
        if (!prompt) {
            panelInput.focus();
            return;
        }
        var editor = tinymce.activeEditor;
        if (!editor) return;
        if (savedSelection) editor.selection.moveToBookmark(savedSelection);
        var selectionHtml = editor.selection.getContent({ format: 'html' }) || '';
        var fullHtml = editor.getContent();

        sendBtn.disabled = true;
        var originalLabel = sendBtn.textContent;
        sendBtn.textContent = sendBtn.getAttribute('data-busy-label') || originalLabel;

        fetch(copilotUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({
                _csrf: csrf,
                prompt: prompt,
                full_html: fullHtml,
                selection_html: selectionHtml
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            if (body && body.ok && body.data && typeof body.data.html === 'string') {
                showProposal(body.data);
            } else {
                showError((body && body.error) || labels.error);
            }
        })
        .catch(function () { showError(labels.error); })
        .finally(function () {
            sendBtn.disabled = false;
            sendBtn.textContent = originalLabel;
        });
    });

    function showProposal(data) {
        pendingProposal = data;
        explainEl.textContent = data.explanation || '';
        previewEl.innerHTML = data.html;
        proposal.hidden = false;
    }
    function hideProposal() {
        proposal.hidden = true;
        previewEl.innerHTML = '';
        explainEl.textContent = '';
        pendingProposal = null;
    }
    function showError(msg) {
        hideProposal();
        explainEl.textContent = String(msg);
        proposal.hidden = false;
        previewEl.innerHTML = '';
    }

    acceptBtn.addEventListener('click', function () {
        if (!pendingProposal) { hideProposal(); return; }
        var editor = tinymce.activeEditor;
        if (!editor) return;
        if (savedSelection) editor.selection.moveToBookmark(savedSelection);
        var html = pendingProposal.html;
        switch (pendingProposal.action) {
            case 'insert_after_selection':
                // Move caret to end of selection then insert.
                editor.selection.collapse(false);
                editor.insertContent('<p></p>' + html);
                break;
            case 'append_to_document':
                editor.setContent(editor.getContent() + html);
                break;
            case 'replace_selection':
            default:
                editor.selection.setContent(html);
                break;
        }
        editor.undoManager.add();
        hideProposal();
        panelInput.value = '';
        autosave(editor);
    });
    rejectBtn.addEventListener('click', function () {
        hideProposal();
        panelInput.focus();
    });

    /* ── Versions: save snapshot + dropdown to restore ───────────────── */
    var versionsUrl = '/tools/articles/' + articleId + '/edit/versions';
    var saveVerBtn  = document.getElementById('editorSaveVersion');
    var verToggle   = document.getElementById('editorVersionsToggle');
    var verMenu     = document.getElementById('editorVersionsMenu');
    var verList     = document.getElementById('editorVersionsList');
    var verEmpty    = document.getElementById('editorVersionsEmpty');
    var versionsLoaded = false;

    saveVerBtn.addEventListener('click', function () {
        var editor = tinymce.activeEditor;
        if (!editor) return;
        var label = window.prompt(
            <?= json_encode(__('articles.editor.versions_label_prompt')) ?>,
            ''
        );
        if (label === null) return; // user cancelled
        saveVerBtn.disabled = true;
        fetch(versionsUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf, html: editor.getContent(), label: label })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            if (body && body.ok && body.version) {
                versionsLoaded = false; // force reload next time
                if (window.SysRevAI && typeof window.SysRevAI.toast === 'function') {
                    window.SysRevAI.toast(
                        <?= json_encode(__('articles.editor.versions_saved_toast')) ?>,
                        'success'
                    );
                }
                saveState.textContent = labels.saved;
            } else {
                window.alert(labels.error);
            }
        })
        .catch(function () { window.alert(labels.error); })
        .finally(function () { saveVerBtn.disabled = false; });
    });

    verToggle.addEventListener('click', function () {
        var willOpen = verMenu.hidden;
        verMenu.hidden = !willOpen;
        verToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) loadVersionsInto(verList);
    });

    // Close the dropdown on click outside.
    document.addEventListener('click', function (e) {
        if (verMenu.hidden) return;
        var versionsRoot = document.getElementById('editorVersions');
        if (versionsRoot && !versionsRoot.contains(e.target)) {
            verMenu.hidden = true;
            verToggle.setAttribute('aria-expanded', 'false');
        }
    });

    function loadVersionsInto(listEl) {
        if (versionsLoaded) return; // populated already
        listEl.innerHTML = '';
        verEmpty.hidden = false;
        verEmpty.textContent = <?= json_encode(__('articles.editor.versions_loading')) ?>;
        fetch(versionsUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (body) {
                if (!body || !body.ok || !Array.isArray(body.versions)) {
                    verEmpty.textContent = labels.error;
                    return;
                }
                versionsLoaded = true;
                if (body.versions.length === 0) {
                    verEmpty.textContent = <?= json_encode(__('articles.editor.versions_empty')) ?>;
                    return;
                }
                verEmpty.hidden = true;
                body.versions.forEach(function (v) {
                    var li = document.createElement('li');
                    li.className = 'article-editor__versions-item';
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'article-editor__versions-restore';
                    var title = document.createElement('span');
                    title.className = 'article-editor__versions-label';
                    title.textContent = v.label && v.label.length
                        ? v.label
                        : <?= json_encode(__('articles.editor.versions_unnamed')) ?>;
                    var meta = document.createElement('span');
                    meta.className = 'article-editor__versions-meta muted';
                    meta.textContent = formatVersionMeta(v);
                    btn.appendChild(title);
                    btn.appendChild(meta);
                    btn.addEventListener('click', function () { restoreVersion(v.id); });
                    li.appendChild(btn);
                    listEl.appendChild(li);
                });
            })
            .catch(function () { verEmpty.textContent = labels.error; });
    }

    function restoreVersion(vid) {
        if (!window.confirm(<?= json_encode(__('articles.editor.versions_restore_confirm')) ?>)) return;
        fetch(versionsUrl + '/' + vid, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (body) {
                if (!body || !body.ok || typeof body.html !== 'string') {
                    window.alert(labels.error);
                    return;
                }
                var editor = tinymce.activeEditor;
                if (!editor) return;
                editor.setContent(body.html);
                editor.undoManager.add();
                verMenu.hidden = true;
                verToggle.setAttribute('aria-expanded', 'false');
                autosave(editor);
            })
            .catch(function () { window.alert(labels.error); });
    }

    function formatVersionMeta(v) {
        var parts = [];
        if (v.created_at) {
            try {
                var d = new Date(v.created_at);
                if (!isNaN(d.getTime())) {
                    parts.push(d.toLocaleString(document.documentElement.lang || undefined,
                        { dateStyle: 'short', timeStyle: 'short' }));
                }
            } catch (e) {}
        }
        if (v.saved_by_name) parts.push(v.saved_by_name);
        return parts.join(' · ');
    }

    function autosave(editor) {
        saveState.textContent = labels.saving;
        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ _csrf: csrf, html: editor.getContent() })
        })
        .then(function (r) { return r.json(); })
        .then(function (body) {
            saveState.textContent = (body && body.ok) ? labels.saved : labels.unsaved;
        })
        .catch(function () { saveState.textContent = labels.unsaved; });
    }

    function truncate(s, n) {
        s = String(s).replace(/\s+/g, ' ').trim();
        return s.length > n ? s.substring(0, n) + '…' : s;
    }
    } // boot()
})();
</script>
