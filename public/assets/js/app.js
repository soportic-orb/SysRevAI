/* SysRevAI — small front-end enhancements (no framework). */
(function () {
    'use strict';

    var bell = document.querySelector('[data-bell]');
    if (!bell) {
        return;
    }

    var toggle = bell.querySelector('[data-bell-toggle]');
    var panel = bell.querySelector('[data-bell-panel]');
    var list = bell.querySelector('[data-bell-list]');
    var badge = bell.querySelector('[data-bell-count]');

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function render(items) {
        if (!list) { return; }
        if (!items || items.length === 0) {
            list.innerHTML = '<div class="bell__empty">—</div>';
            return;
        }
        list.innerHTML = items.map(function (n) {
            var cls = n.is_read ? 'bell__item' : 'bell__item is-unread';
            var href = n.action_url || '/notifications';
            return '<a class="' + cls + '" href="' + escapeHtml(href) + '">' +
                '<strong>' + escapeHtml(n.title) + '</strong>' +
                (n.message ? '<span>' + escapeHtml(n.message).slice(0, 120) + '</span>' : '') +
                '</a>';
        }).join('');
    }

    function refresh() {
        fetch('/notifications/poll', { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) { return; }
                if (badge) {
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.removeAttribute('hidden');
                    } else {
                        badge.setAttribute('hidden', '');
                    }
                }
                render(data.items);
            })
            .catch(function () { /* offline: ignore */ });
    }

    if (toggle && panel) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            var hidden = panel.hasAttribute('hidden');
            if (hidden) { panel.removeAttribute('hidden'); refresh(); }
            else { panel.setAttribute('hidden', ''); }
        });
        document.addEventListener('click', function (e) {
            if (!bell.contains(e.target)) { panel.setAttribute('hidden', ''); }
        });
    }

    refresh();
    setInterval(refresh, 45000);
})();

/* ─────────────────────────────────────────────────────────────────────────
   Busy-button helper. Any <button> or <input type="submit"> annotated with
   data-busy-label="…" gets that label swapped in and is disabled when its
   form submits, so the user knows the platform is processing and doesn't
   click again or navigate away. Used by every "submit and wait" action
   (full-text retrieval, dependency install, etc.).
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;

        var busyBtn = form.querySelector('[data-busy-label]');
        if (!busyBtn || busyBtn.disabled) return;

        var label = busyBtn.getAttribute('data-busy-label') || '';
        if (label === '') return;

        // Preserve the original markup so a failed submission (validation
        // error, etc.) can be restored. We only swap once per submit.
        if (busyBtn.dataset.originalHtml === undefined) {
            busyBtn.dataset.originalHtml = busyBtn.innerHTML;
        }
        busyBtn.innerHTML = label;
        busyBtn.disabled = true;
        busyBtn.classList.add('is-busy');
    }, true);
})();

/* ─────────────────────────────────────────────────────────────────────────
   Collapsible cards. Any section with [data-collapsible] toggles its
   [data-collapsible-body] via a click / keyboard activation on its
   [data-collapsible-toggle] heading. The body starts hidden when
   [data-collapsed-default] is present on the panel.
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    document.querySelectorAll('[data-collapsible]').forEach(function (panel) {
        var toggle = panel.querySelector('[data-collapsible-toggle]');
        var body   = panel.querySelector('[data-collapsible-body]');
        if (!toggle || !body) return;

        var defaultCollapsed = panel.hasAttribute('data-collapsed-default');
        setOpen(!defaultCollapsed);

        toggle.addEventListener('click', function () { setOpen(panel.classList.contains('is-collapsed')); });
        toggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setOpen(panel.classList.contains('is-collapsed'));
            }
        });

        function setOpen(open) {
            panel.classList.toggle('is-collapsed', !open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            body.hidden = !open;
        }
    });
})();

/* ─────────────────────────────────────────────────────────────────────────
   Centred "AI is working" overlay. Visible whenever:
     • a form annotated with data-ai-action submits, OR
     • code explicitly calls window.SysRevAI.showAiOverlay()
   The overlay is rendered once in the layout (#aiOverlay). For full-page
   POST submissions we don't need to hide it explicitly — the next page
   replaces the DOM. For AJAX paths the caller is expected to invoke
   hideAiOverlay() when the request finishes (success or failure).
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    var overlay = null;
    function getOverlay() {
        if (overlay === null) overlay = document.getElementById('aiOverlay');
        return overlay;
    }

    /* Per-show state. Reset on every showAiOverlay() so a previous call's
       timers and explicit progress override never leak into the next. */
    var state = {
        barTimer:  null,   // setTimeout that flips dots → progress bar
        tickTimer: null,   // setInterval that drives the asymptotic fill
        startedAt: 0,
        estimate:  30000,
        explicit:  null    // null = auto curve, number = explicit override
    };

    function clearState() {
        if (state.barTimer)  { clearTimeout(state.barTimer);  state.barTimer  = null; }
        if (state.tickTimer) { clearInterval(state.tickTimer); state.tickTimer = null; }
        state.explicit = null;
    }

    function setBarProgress(pct) {
        var el = getOverlay();
        if (!el) return;
        pct = Math.max(0, Math.min(100, +pct || 0));
        var fill = el.querySelector('.ai-overlay__fill');
        var pctEl = el.querySelector('.ai-overlay__percent');
        if (fill)  fill.style.width = pct.toFixed(1) + '%';
        if (pctEl) pctEl.textContent = Math.round(pct) + '%';
    }

    /**
     * @param {Object} [opts]
     * @param {string} [opts.label]         Replace the "Working…" headline.
     * @param {number} [opts.estimate]      Expected duration in ms — the bar
     *                                      reaches ~85% at this point and then
     *                                      approaches 100% asymptotically.
     * @param {number} [opts.showBarAfter]  Delay before the dots are swapped
     *                                      for the progress bar (ms). Tasks
     *                                      that finish before this delay never
     *                                      see the bar.
     */
    window.SysRevAI = window.SysRevAI || {};
    window.SysRevAI.showAiOverlay = function (opts) {
        var el = getOverlay();
        if (!el) return;
        opts = opts || {};

        clearState();
        state.startedAt = Date.now();
        state.estimate  = Math.max(2000, +opts.estimate || 30000);

        var titleEl = el.querySelector('#aiOverlayTitle');
        var dotsEl  = el.querySelector('.ai-overlay__dots');
        var progEl  = el.querySelector('.ai-overlay__progress');

        if (opts.label && titleEl) titleEl.textContent = String(opts.label);
        if (dotsEl) dotsEl.hidden = false;
        if (progEl) progEl.hidden = true;
        setBarProgress(0);

        el.hidden = false;
        el.classList.add('is-visible');

        // After this delay, swap dots for progress bar and start filling it.
        var showBarAfter = Math.max(0, +opts.showBarAfter || 2500);
        state.barTimer = setTimeout(function () {
            if (dotsEl) dotsEl.hidden = true;
            if (progEl) progEl.hidden = false;
            state.tickTimer = setInterval(tickProgress, 250);
            tickProgress();
        }, showBarAfter);
    };

    /* Asymptotic curve so the bar never reaches 100% from the estimate
       alone — that 100% jump is reserved for hideAiOverlay() so the user
       gets a real completion signal. At elapsed = estimate the bar shows
       ~85%; at 2× estimate ~95%; settles below 99% no matter how long. */
    function tickProgress() {
        if (state.explicit !== null) {
            setBarProgress(state.explicit);
            return;
        }
        var t = (Date.now() - state.startedAt) / state.estimate;
        var p = (1 - Math.exp(-t * 1.9)) * 99;
        setBarProgress(Math.min(99, p));
    }

    /** Callers that know real progress can override the auto curve. */
    window.SysRevAI.setAiProgress = function (pct) {
        state.explicit = Math.max(0, Math.min(100, +pct || 0));
        // Reflect immediately even if the tick loop hasn't started yet.
        if (state.tickTimer === null) {
            var el = getOverlay();
            if (el) {
                var dotsEl = el.querySelector('.ai-overlay__dots');
                var progEl = el.querySelector('.ai-overlay__progress');
                if (dotsEl) dotsEl.hidden = true;
                if (progEl) progEl.hidden = false;
                if (state.barTimer) { clearTimeout(state.barTimer); state.barTimer = null; }
                state.tickTimer = setInterval(tickProgress, 250);
            }
        }
        tickProgress();
    };

    window.SysRevAI.hideAiOverlay = function () {
        var el = getOverlay();
        if (!el) return;
        clearState();
        el.hidden = true;
        el.classList.remove('is-visible');
    };

    /* Show overlay on any form submission flagged with data-ai-action.
       Forms can hint at the workload with data-ai-estimate (ms) and
       data-ai-label (string) so the bar fills at a believable rate. */
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (!form.hasAttribute('data-ai-action')) return;
        var opts = {};
        if (form.dataset.aiEstimate) opts.estimate     = +form.dataset.aiEstimate;
        if (form.dataset.aiLabel)    opts.label        = form.dataset.aiLabel;
        if (form.dataset.aiBarAfter) opts.showBarAfter = +form.dataset.aiBarAfter;
        window.SysRevAI.showAiOverlay(opts);
    }, true);

    /* Auto-hide if the user navigates back to this page via bfcache so the
       overlay never stays "stuck" from a previous submission. */
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) window.SysRevAI.hideAiOverlay();
    });
})();

/* ─────────────────────────────────────────────────────────────────────────
   Info modal. Any [data-info-target="modalId"] button opens the matching
   <dialog id="modalId">. Uses the native HTMLDialogElement so we get the
   ESC-to-close, focus trap and backdrop for free; falls back to a class
   toggle on browsers without showModal() support.
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-info-target]');
        if (trigger) {
            var id = trigger.getAttribute('data-info-target');
            var dlg = document.getElementById(id);
            if (!dlg) return;
            e.preventDefault();
            if (typeof dlg.showModal === 'function') {
                dlg.showModal();
            } else {
                dlg.setAttribute('open', '');
                dlg.classList.add('is-open');
            }
            return;
        }

        var closer = e.target.closest('[data-info-close]');
        if (closer) {
            var open = closer.closest('dialog.info-modal');
            if (!open) return;
            e.preventDefault();
            if (typeof open.close === 'function') {
                open.close();
            } else {
                open.removeAttribute('open');
                open.classList.remove('is-open');
            }
        }
    });

    /* Click on the backdrop (outside the inner card) closes the modal. */
    document.addEventListener('click', function (e) {
        if (!(e.target instanceof HTMLDialogElement) || !e.target.classList.contains('info-modal')) return;
        var inner = e.target.querySelector('.info-modal__inner');
        if (inner && !inner.contains(e.target.ownerDocument.elementFromPoint(e.clientX, e.clientY))) {
            if (typeof e.target.close === 'function') e.target.close();
        }
    });
})();

/* ─────────────────────────────────────────────────────────────────────────
   Toast notifications.

   Promotes any inline .alert--success / .alert--error / .alert--warn
   element on the page to a top-center toast (auto-dismiss after 5 s,
   close button, slide-in / slide-out animation). The original inline
   element is removed so the message appears in only one place.

   Opt-out: structural / persistent alerts can keep their inline
   placement with `data-no-toast` on the .alert element.

   Direct API: window.SysRevAI.toast(message, type, options).
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    var TYPE_FROM_CLASS = {
        'alert--success': 'success',
        'alert--error':   'error',
        'alert--warn':    'warn'
    };
    var ICONS = {
        success: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>',
        warn:    '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4"></path><circle cx="12" cy="17" r=".6" fill="currentColor"></circle><path d="M10.3 2.7 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.7a2 2 0 0 0-3.4 0z"></path></svg>',
        error:   '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><line x1="9" y1="9" x2="15" y2="15"></line><line x1="9" y1="15" x2="15" y2="9"></line></svg>'
    };

    function stack() {
        var el = document.getElementById('toastStack');
        if (!el) {
            // Fallback: build the stack on demand if the layout didn't include it.
            el = document.createElement('div');
            el.id = 'toastStack';
            el.className = 'toast-stack';
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        return el;
    }

    function dismiss(node) {
        if (!node || node.classList.contains('is-leaving')) return;
        node.classList.add('is-leaving');
        setTimeout(function () { if (node.parentNode) node.parentNode.removeChild(node); }, 220);
    }

    function show(message, type, options) {
        type = type === 'success' || type === 'warn' || type === 'error' ? type : 'success';
        options = options || {};
        var ttl = options.ttl != null ? options.ttl : 5000;

        var box = document.createElement('div');
        box.className = 'toast toast--' + type;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = document.createElement('span');
        icon.className = 'toast__icon';
        icon.innerHTML = ICONS[type] || '';
        box.appendChild(icon);

        var body = document.createElement('div');
        body.className = 'toast__body';
        body.textContent = String(message);
        box.appendChild(body);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast__close';
        close.setAttribute('aria-label', 'Close');
        close.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="6" y1="18" x2="18" y2="6"></line></svg>';
        close.addEventListener('click', function () { dismiss(box); });
        box.appendChild(close);

        stack().appendChild(box);
        if (ttl > 0) setTimeout(function () { dismiss(box); }, ttl);
        return box;
    }

    /* Auto-migrate any inline .alert--* into a toast. */
    function promoteInline() {
        document.querySelectorAll('.alert--success, .alert--error, .alert--warn').forEach(function (el) {
            if (el.hasAttribute('data-no-toast')) return;
            // Find the matching alert--* class to detect the type.
            var type = null;
            el.classList.forEach(function (c) {
                if (TYPE_FROM_CLASS[c]) type = TYPE_FROM_CLASS[c];
            });
            if (!type) return;
            var text = (el.textContent || '').trim();
            if (text === '') return;
            show(text, type);
            el.parentNode && el.parentNode.removeChild(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', promoteInline);
    } else {
        promoteInline();
    }

    window.SysRevAI = window.SysRevAI || {};
    window.SysRevAI.toast = show;
})();

/* ─────────────────────────────────────────────────────────────────────────
   Tiny Markdown renderer for AI chat bubbles. Mirrors the subset that
   src/Helpers/Markdown.php supports: bold, italic, inline code, fenced
   code blocks, headings, ordered/unordered lists, blockquotes, links and
   pipe tables. Always HTML-escapes the input first, so a stray <script>
   from the model can never become a tag.
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderInline(s) {
        // Inline code first so its body isn't touched by bold/italic.
        var codes = [];
        s = s.replace(/`([^`\n]+)`/g, function (_, body) {
            codes.push('<code class="md-code">' + body + '</code>');
            return ' IC' + (codes.length - 1) + ' ';
        });
        // Links: [text](http(s):// | mailto:)
        s = s.replace(/\[([^\]]+)\]\(((?:https?:|mailto:)[^)\s]+)\)/gi, function (_, text, href) {
            return '<a href="' + escapeHtml(href).replace(/"/g, '&quot;') +
                   '" target="_blank" rel="noopener noreferrer">' + text + '</a>';
        });
        s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        s = s.replace(/(^|[^\w*])\*([^*\n]+)\*(?![\w*])/g, '$1<em>$2</em>');
        s = s.replace(/(^|[^\w_])_([^_\n]+)_(?![\w_])/g, '$1<em>$2</em>');
        s = s.replace(/ IC(\d+) /g, function (_, i) { return codes[+i] || ''; });
        return s;
    }

    function renderList(lines, ordered) {
        var tag = ordered ? 'ol' : 'ul';
        var cls = ordered ? 'md-ol' : 'md-ul';
        var pattern = ordered ? /^\s*\d+\.\s+(.*)$/ : /^\s*[*\-]\s+(.*)$/;
        var items = [];
        var cur = null;
        for (var i = 0; i < lines.length; i++) {
            var m = lines[i].match(pattern);
            if (m) {
                if (cur !== null) items.push(cur);
                cur = m[1];
            } else {
                cur = (cur === null ? '' : cur + ' ') + lines[i].trim();
            }
        }
        if (cur !== null) items.push(cur);
        var html = '';
        for (var j = 0; j < items.length; j++) {
            html += '<li>' + renderInline(items[j]) + '</li>';
        }
        return '<' + tag + ' class="' + cls + '">' + html + '</' + tag + '>';
    }

    function renderTable(raw) {
        var rows = raw.split('\n').filter(function (r) { return r.trim() !== ''; });
        if (rows.length < 2) return '';
        function split(row) {
            row = row.trim().replace(/^\||\|$/g, '');
            return row.split('|').map(function (c) { return c.trim(); });
        }
        var head = split(rows[0]);
        var body = rows.slice(2);
        var html = '<table class="md-table"><thead><tr>';
        for (var i = 0; i < head.length; i++) html += '<th>' + renderInline(head[i]) + '</th>';
        html += '</tr></thead><tbody>';
        for (var r = 0; r < body.length; r++) {
            var cells = split(body[r]);
            html += '<tr>';
            for (var c = 0; c < cells.length; c++) html += '<td>' + renderInline(cells[c]) + '</td>';
            html += '</tr>';
        }
        html += '</tbody></table>';
        return html;
    }

    function renderBlock(block) {
        var lines = block.split('\n');
        var first = lines[0];

        var hMatch = first.match(/^(#{1,4})\s+(.+)$/);
        if (hMatch && lines.length === 1) {
            var level = Math.min(4, hMatch[1].length) + 2;
            return '<h' + level + ' class="md-h">' + renderInline(hMatch[2]) + '</h' + level + '>';
        }
        if (/^>\s?/.test(first)) {
            var stripped = lines.map(function (l) { return l.replace(/^>\s?/, ''); }).join('\n');
            return '<blockquote class="md-quote">' + renderBlock(stripped) + '</blockquote>';
        }
        if (/^(\s*)[*\-]\s+/.test(first)) return renderList(lines, false);
        if (/^\s*\d+\.\s+/.test(first))   return renderList(lines, true);

        var esc = lines.map(renderInline);
        return '<p class="md-p">' + esc.join('<br>') + '</p>';
    }

    function mdRender(text) {
        if (text == null) return '';
        var src = String(text).replace(/\r\n?/g, '\n').replace(/^\s+|\s+$/g, '');
        if (src === '') return '';
        src = escapeHtml(src);

        // Extract fenced code blocks.
        var codeBlocks = [];
        src = src.replace(/```([a-zA-Z0-9_+\-]*)\n([\s\S]*?)```/g, function (_, lang, body) {
            var cls = lang ? ' class="lang-' + lang.replace(/[^a-zA-Z0-9_+\-]/g, '') + '"' : '';
            codeBlocks.push('<pre class="md-pre"><code' + cls + '>' + body + '</code></pre>');
            return ' CB' + (codeBlocks.length - 1) + ' ';
        });

        // Extract pipe tables.
        var tables = [];
        src = src.replace(/(?:^|\n)((?:\|[^\n]+\|\n)\|[\s:|\-]+\|\n(?:\|[^\n]+\|\n?)+)/g, function (_, t) {
            tables.push(renderTable(t));
            return '\n TB' + (tables.length - 1) + ' \n';
        });

        var blocks = src.split(/\n{2,}/);
        var out = [];
        for (var i = 0; i < blocks.length; i++) {
            var b = blocks[i].replace(/\s+$/, '');
            if (b === '') continue;
            if (/^ (?:CB|TB)\d+ $/.test(b.trim())) {
                out.push(b.trim());
            } else {
                out.push(renderBlock(b));
            }
        }
        var html = out.join('\n');
        html = html.replace(/ CB(\d+) /g, function (_, i) { return codeBlocks[+i] || ''; });
        html = html.replace(/ TB(\d+) /g, function (_, i) { return tables[+i] || ''; });
        return html;
    }

    window.SysRevAI = window.SysRevAI || {};
    window.SysRevAI.mdRender = mdRender;
})();

/* ─────────────────────────────────────────────────────────────────────────
   Copy-to-clipboard. Any element with [data-copy="…"] copies its
   attribute value to the system clipboard on click and briefly swaps
   its label to [data-copy-ok] (defaults to "Copied!") so the user
   sees the action took. Falls back to a hidden textarea+execCommand
   on browsers without the async clipboard API. Used by the
   invitation-link badges on /reviews/{id}/team and /admin/users.
   ───────────────────────────────────────────────────────────────────────── */
(function () {
    'use strict';
    document.addEventListener('click', function (ev) {
        var trigger = ev.target.closest('[data-copy]');
        if (!trigger) return;
        ev.preventDefault();
        var text = trigger.getAttribute('data-copy') || '';
        if (text === '') return;

        var done = function () {
            var ok = trigger.getAttribute('data-copy-ok') || 'Copied!';
            var orig = trigger.textContent;
            trigger.textContent = ok;
            trigger.classList.add('is-copied');
            setTimeout(function () {
                trigger.textContent = orig;
                trigger.classList.remove('is-copied');
            }, 1500);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () { fallback(); });
        } else {
            fallback();
        }
        function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(ta);
            done();
        }
    });
})();

/* Centred confirm modal. Any <form data-confirm="message"> now routes
   its submission through #appConfirmModal instead of using the native
   window.confirm() prompt. The same form continues to submit normally
   once the user clicks the confirm button, so existing data-ai-action
   and data-busy-label handlers still kick in afterwards.

   Per-form overrides:
     data-confirm-title="…"   replaces the modal title
     data-confirm-button="…"  replaces the "yes" button label
     data-confirm-tone="danger" colours the "yes" button red

   Registered on the capture phase so it runs before data-busy-label
   and data-ai-action handlers; those only fire on the post-confirmation
   re-submission, when the data-confirm attribute is temporarily set
   aside via form._sraConfirmed. */
(function () {
    'use strict';
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        var msg = form.getAttribute('data-confirm');
        if (!msg) return;
        if (form._sraConfirmed) { form._sraConfirmed = false; return; }
        event.preventDefault();
        event.stopPropagation();

        var modal = document.getElementById('appConfirmModal');
        if (!modal) {
            // Layout didn't include the partial; fall back to native confirm
            // so the action isn't silently lost.
            if (window.confirm(msg)) form.submit();
            return;
        }
        var titleEl = document.getElementById('appConfirmTitle');
        var bodyEl  = document.getElementById('appConfirmBody');
        var yesBtn  = document.getElementById('appConfirmYes');
        if (!titleEl || !bodyEl || !yesBtn) return;

        var title = form.getAttribute('data-confirm-title');
        var label = form.getAttribute('data-confirm-button');
        var tone  = (form.getAttribute('data-confirm-tone') || '').toLowerCase();

        if (title) titleEl.textContent = title;
        else titleEl.textContent = titleEl.getAttribute('data-default') || titleEl.textContent;
        bodyEl.textContent = msg;
        yesBtn.textContent = label || yesBtn.getAttribute('data-default-label') || 'OK';
        yesBtn.classList.remove('btn--primary', 'btn--danger-solid');
        yesBtn.classList.add(tone === 'danger' ? 'btn--danger-solid' : 'btn--primary');

        // Replace the click handler each time so a leftover binding from
        // a previous open never resubmits a different form.
        var clone = yesBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(clone, yesBtn);
        clone.addEventListener('click', function () {
            if (typeof modal.close === 'function') modal.close();
            else { modal.removeAttribute('open'); modal.classList.remove('is-open'); }
            form._sraConfirmed = true;
            // requestSubmit fires the submit event again so data-ai-action
            // / data-busy-label handlers run as if the user had clicked
            // through directly.
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });

        if (typeof modal.showModal === 'function') modal.showModal();
        else { modal.setAttribute('open', ''); modal.classList.add('is-open'); }
    }, true);
})();
