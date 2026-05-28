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
