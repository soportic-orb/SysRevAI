<?php

declare(strict_types=1);

/**
 * Floating Scientific Copilot — a chat bubble in the bottom-right corner,
 * rendered by layouts/app.php whenever the user is on a /reviews/{id}/...
 * page. Conversation history is kept in localStorage (one key per review)
 * so the user can pick up where they left off.
 *
 * @var array $reviewSubnav  The current review row.
 */
$copilotReviewId = (int) $reviewSubnav['id'];
?>
<div class="copilot" id="copilot"
     data-url="/reviews/<?= $copilotReviewId ?>/copilot"
     data-csrf="<?= e(csrf_token()) ?>"
     data-key="sysrevai.copilot.<?= $copilotReviewId ?>">
    <button class="copilot__toggle" id="copilotToggle" type="button"
            aria-label="<?= e(__('copilot.toggle_aria')) ?>"
            title="<?= e(__('copilot.toggle_title')) ?>">
        <span aria-hidden="true">&#x1F4AC;</span>
    </button>
    <section class="copilot__panel" id="copilotPanel" hidden aria-label="<?= e(__('copilot.title')) ?>">
        <header class="copilot__head">
            <div>
                <h2 class="copilot__title"><?= e(__('copilot.title')) ?></h2>
                <p class="copilot__subtitle"><?= e(__('copilot.subtitle')) ?></p>
            </div>
            <div class="copilot__head-actions">
                <button type="button" class="copilot__icon-btn" id="copilotClear"
                        title="<?= e(__('copilot.clear')) ?>" aria-label="<?= e(__('copilot.clear')) ?>">&#x21BB;</button>
                <button type="button" class="copilot__icon-btn" id="copilotClose"
                        title="<?= e(__('copilot.close')) ?>" aria-label="<?= e(__('copilot.close')) ?>">&times;</button>
            </div>
        </header>
        <div class="copilot__messages" id="copilotMessages" aria-live="polite">
            <div class="copilot__greeting">
                <p><?= e(__('copilot.greeting')) ?></p>
            </div>
        </div>
        <form class="copilot__form" id="copilotForm">
            <textarea class="input copilot__input" id="copilotInput" rows="2"
                      placeholder="<?= e(__('copilot.placeholder')) ?>"
                      required></textarea>
            <button class="btn btn--primary btn--sm" type="submit"><?= e(__('copilot.send')) ?></button>
        </form>
    </section>
</div>
<script>
(function () {
    var root = document.getElementById('copilot');
    if (!root) return;

    var url       = root.getAttribute('data-url');
    var csrfToken = root.getAttribute('data-csrf');
    var storeKey  = root.getAttribute('data-key');
    var panel     = document.getElementById('copilotPanel');
    var toggleBtn = document.getElementById('copilotToggle');
    var closeBtn  = document.getElementById('copilotClose');
    var clearBtn  = document.getElementById('copilotClear');
    var msgs      = document.getElementById('copilotMessages');
    var form      = document.getElementById('copilotForm');
    var input     = document.getElementById('copilotInput');

    var labels = {
        thinking: <?= json_encode(__('copilot.thinking')) ?>,
        error:    <?= json_encode(__('copilot.error')) ?>,
        budget:   <?= json_encode(__('copilot.budget')) ?>,
        disabled: <?= json_encode(__('copilot.disabled')) ?>,
        noKey:    <?= json_encode(__('copilot.no_api_key')) ?>
    };

    /** Restore prior transcript from localStorage. */
    var history = [];
    try {
        var raw = localStorage.getItem(storeKey);
        if (raw) history = JSON.parse(raw) || [];
        if (!Array.isArray(history)) history = [];
    } catch (e) { history = []; }
    history.forEach(function (m) { append(m.role, m.content, false); });

    toggleBtn.addEventListener('click', function () { open(); });
    closeBtn.addEventListener('click', function () { close(); });
    clearBtn.addEventListener('click', function () {
        if (!confirm(<?= json_encode(__('copilot.clear_confirm')) ?>)) return;
        history = [];
        try { localStorage.removeItem(storeKey); } catch (e) {}
        // Restore greeting.
        msgs.innerHTML = '<div class="copilot__greeting"><p>' +
            <?= json_encode(__('copilot.greeting')) ?> + '</p></div>';
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) close();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = (input.value || '').trim();
        if (!text) return;
        input.value = '';
        append('user', text, true);
        var thinkingNode = appendBubble('assistant', labels.thinking);

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                _csrf: csrfToken,
                message: text,
                history: history.slice(-12)
            })
        })
        .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
        .then(function (res) {
            thinkingNode.remove();
            if (res.body && res.body.ok && res.body.reply) {
                append('assistant', res.body.reply, true);
                return;
            }
            var err = (res.body && res.body.error) || '';
            var msg = labels.error;
            if (err === 'no_api_key')      msg = labels.noKey;
            else if (err === 'feature_disabled') msg = labels.disabled;
            else if (err === 'budget_exceeded')  msg = labels.budget;
            appendBubble('assistant', msg);
        })
        .catch(function () {
            thinkingNode.remove();
            appendBubble('assistant', labels.error);
        });
    });

    /* Cmd/Ctrl+Enter sends. */
    input.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    function open() {
        panel.hidden = false;
        toggleBtn.setAttribute('aria-expanded', 'true');
        setTimeout(function () { input.focus(); }, 0);
        scrollToBottom();
    }
    function close() {
        panel.hidden = true;
        toggleBtn.setAttribute('aria-expanded', 'false');
    }

    function append(role, content, persist) {
        var greeting = msgs.querySelector('.copilot__greeting');
        if (greeting) greeting.remove();
        appendBubble(role, content);
        if (persist) {
            history.push({ role: role, content: content });
            try { localStorage.setItem(storeKey, JSON.stringify(history.slice(-30))); } catch (e) {}
        }
    }

    function appendBubble(role, content) {
        var greeting = msgs.querySelector('.copilot__greeting');
        if (greeting) greeting.remove();
        var div = document.createElement('div');
        div.className = 'copilot__msg copilot__msg--' + role;
        div.textContent = content;
        msgs.appendChild(div);
        scrollToBottom();
        return div;
    }
    function scrollToBottom() { msgs.scrollTop = msgs.scrollHeight; }
})();
</script>
