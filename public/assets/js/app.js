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
