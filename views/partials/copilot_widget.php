<?php

declare(strict_types=1);

/**
 * Floating Scientific Copilot — a chat bubble in the bottom-right corner,
 * available on every authenticated page when the admin hasn't disabled the
 * feature. Two modes share the same widget:
 *
 *   - Review-scoped: when $reviewSubnav is set (user is on /reviews/{id}/*).
 *     Routes through /reviews/{id}/copilot/* so the model receives the
 *     protocol context and the optional page snapshot.
 *   - Global: when $reviewSubnav is null (everywhere else). Routes through
 *     /copilot/* and the model answers platform how-to + methodology
 *     questions; it defers review-specific questions until the user opens
 *     the review.
 *
 * The transcript for each mode lives in the same `copilot_messages` table
 * keyed on (review_id, user_id) — NULL review_id for the global thread.
 *
 * @var ?array $reviewSubnav  Current review row, or null when off-review.
 */
$copilotReviewId = is_array($reviewSubnav ?? null) ? (int) $reviewSubnav['id'] : 0;
$copilotIsGlobal = $copilotReviewId === 0;
$copilotBaseUrl  = $copilotIsGlobal ? '/copilot' : '/reviews/' . $copilotReviewId . '/copilot';
$copilotSubtitle = $copilotIsGlobal
    ? __('copilot.subtitle_global')
    : __('copilot.subtitle');
$copilotGreeting = $copilotIsGlobal
    ? __('copilot.greeting_global')
    : __('copilot.greeting');
?>
<div class="copilot" id="copilot"
     data-url="<?= e($copilotBaseUrl) ?>"
     data-history-url="<?= e($copilotBaseUrl) ?>/history"
     data-clear-url="<?= e($copilotBaseUrl) ?>/clear"
     data-csrf="<?= e(csrf_token()) ?>"
     data-expand-key="sysrevai.copilot.expanded"
     data-scope="<?= $copilotIsGlobal ? 'global' : 'review' ?>">
    <button class="copilot__toggle" id="copilotToggle" type="button"
            aria-label="<?= e(__('copilot.toggle_aria')) ?>"
            title="<?= e(__('copilot.toggle_title')) ?>"
            aria-expanded="false" aria-controls="copilotPanel">
        <svg class="copilot__toggle-icon" viewBox="0 0 24 24" width="24" height="24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 18v-5.5c0 -.667 .167 -1.333 .5 -2"></path>
            <path d="M12 7.5c0 -1 -.01 -4.07 -4 -3.5c-3.5 .5 -4 2.5 -4 3.5c0 1.5 0 4 3 4c4 0 5 -2.5 5 -4"></path>
            <path d="M4 12c-1.333 .667 -2 1.333 -2 2c0 1 0 3 1.5 4c3 2 6.5 3 8.5 3s5.499 -1 8.5 -3c1.5 -1 1.5 -3 1.5 -4c0 -.667 -.667 -1.333 -2 -2"></path>
            <path d="M20 18v-5.5c0 -.667 -.167 -1.333 -.5 -2"></path>
            <path d="M12 7.5l0 -.297l.01 -.269l.027 -.298l.013 -.105l.033 -.215c.014 -.073 .029 -.146 .046 -.22l.06 -.223c.336 -1.118 1.262 -2.237 3.808 -1.873c2.838 .405 3.703 1.797 3.93 2.842l.036 .204c0 .033 .01 .066 .013 .098l.016 .185l0 .171l0 .49l-.015 .394l-.02 .271c-.122 1.366 -.655 2.845 -2.962 2.845c-3.256 0 -4.524 -1.656 -4.883 -3.081l-.053 -.242a3.865 3.865 0 0 1 -.036 -.235l-.021 -.227a3.518 3.518 0 0 1 -.007 -.215l.005 0"></path>
            <path d="M10 15v2"></path>
            <path d="M14 15v2"></path>
        </svg>
    </button>
    <section class="copilot__panel" id="copilotPanel" hidden aria-label="<?= e(__('copilot.title')) ?>">
        <header class="copilot__head">
            <div>
                <h2 class="copilot__title"><?= e(__('copilot.title')) ?></h2>
                <p class="copilot__subtitle"><?= e($copilotSubtitle) ?></p>
            </div>
            <div class="copilot__head-actions">
                <button type="button" class="copilot__icon-btn" id="copilotClear"
                        title="<?= e(__('copilot.clear')) ?>" aria-label="<?= e(__('copilot.clear')) ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                         stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                    </svg>
                </button>
                <button type="button" class="copilot__icon-btn" id="copilotExpand"
                        title="<?= e(__('copilot.expand')) ?>" aria-label="<?= e(__('copilot.expand')) ?>"
                        aria-pressed="false">
                    <svg class="copilot__icon-expand" viewBox="0 0 24 24" width="16" height="16" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <polyline points="9 21 3 21 3 15"></polyline>
                        <line x1="21" y1="3" x2="14" y2="10"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                    </svg>
                    <svg class="copilot__icon-collapse" viewBox="0 0 24 24" width="16" height="16" fill="none"
                         stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="4 14 10 14 10 20"></polyline>
                        <polyline points="20 10 14 10 14 4"></polyline>
                        <line x1="14" y1="10" x2="21" y2="3"></line>
                        <line x1="3" y1="21" x2="10" y2="14"></line>
                    </svg>
                </button>
                <button type="button" class="copilot__icon-btn" id="copilotClose"
                        title="<?= e(__('copilot.close')) ?>" aria-label="<?= e(__('copilot.close')) ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                         stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                        <line x1="6" y1="18" x2="18" y2="6"></line>
                    </svg>
                </button>
            </div>
        </header>
        <div class="copilot__messages" id="copilotMessages" aria-live="polite">
            <div class="copilot__greeting" id="copilotGreeting">
                <p><?= e($copilotGreeting) ?></p>
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
    'use strict';
    var root = document.getElementById('copilot');
    if (!root) return;

    var sendUrl    = root.getAttribute('data-url');
    var historyUrl = root.getAttribute('data-history-url');
    var clearUrl   = root.getAttribute('data-clear-url');
    var csrfToken  = root.getAttribute('data-csrf');
    var expandKey  = root.getAttribute('data-expand-key');

    var panel    = document.getElementById('copilotPanel');
    var toggle   = document.getElementById('copilotToggle');
    var closeBtn = document.getElementById('copilotClose');
    var clearBtn = document.getElementById('copilotClear');
    var expand   = document.getElementById('copilotExpand');
    var msgs     = document.getElementById('copilotMessages');
    var form     = document.getElementById('copilotForm');
    var input    = document.getElementById('copilotInput');
    var greeting = document.getElementById('copilotGreeting');

    var labels = {
        error:    <?= json_encode(__('copilot.error')) ?>,
        budget:   <?= json_encode(__('copilot.budget')) ?>,
        disabled: <?= json_encode(__('copilot.disabled')) ?>,
        noKey:    <?= json_encode(__('copilot.no_api_key')) ?>,
        confirm:  <?= json_encode(__('copilot.clear_confirm')) ?>
    };

    var hydrated = false;
    var sending  = false;

    /* Restore expanded state from localStorage. */
    try { if (localStorage.getItem(expandKey) === '1') setExpanded(true); } catch (e) {}

    /* Toggle / close — keep them tiny and bullet-proof. */
    toggle.addEventListener('click', openPanel);
    closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        closePanel();
    });
    expand.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setExpanded(!root.classList.contains('is-expanded'));
    });
    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!confirm(labels.confirm)) return;
        fetch(clearUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: csrfToken })
        }).finally(function () {
            msgs.querySelectorAll('.copilot__msg, .copilot__typing').forEach(function (n) { n.remove(); });
            if (greeting) {
                msgs.insertBefore(greeting, msgs.firstChild);
                greeting.hidden = false;
            }
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) closePanel();
    });

    /* Cmd/Ctrl+Enter sends. */
    input.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sending) return;
        var text = (input.value || '').trim();
        if (!text) return;

        appendBubble('user', text);
        input.value = '';
        sending = true;
        var typing = appendTyping();

        /* Page context (when the host page provides one) lets the Copilot
           answer questions about the article the reviewer is currently
           looking at. It is read live on every send so SPAs / hot view
           changes are reflected. */
        var pageContext = window.SysRevAICopilotContext || null;

        fetch(sendUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                _csrf: csrfToken,
                message: text,
                page_context: pageContext
            })
        })
        .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
        .then(function (res) {
            typing.remove();
            if (res.body && res.body.ok && res.body.reply) {
                appendBubble('assistant', res.body.reply);
                return;
            }
            appendBubble('assistant', errorMessage((res.body && res.body.error) || ''));
        })
        .catch(function () {
            typing.remove();
            appendBubble('assistant', labels.error);
        })
        .finally(function () {
            sending = false;
            input.focus();
        });
    });

    function openPanel() {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        hydrate();
        setTimeout(function () { input.focus(); scrollToBottom(); }, 0);
    }
    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    }

    function setExpanded(on) {
        root.classList.toggle('is-expanded', !!on);
        expand.setAttribute('aria-pressed', on ? 'true' : 'false');
        try { localStorage.setItem(expandKey, on ? '1' : '0'); } catch (e) {}
    }

    function hydrate() {
        if (hydrated) return;
        hydrated = true;
        fetch(historyUrl, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok || !Array.isArray(d.messages) || d.messages.length === 0) return;
                if (greeting) greeting.hidden = true;
                d.messages.forEach(function (m) { appendBubble(m.role, m.content); });
                scrollToBottom();
            })
            .catch(function () { /* offline-tolerant */ });
    }

    function appendBubble(role, content) {
        if (greeting) greeting.hidden = true;
        var div = document.createElement('div');
        div.className = 'copilot__msg copilot__msg--' + role;
        /* Render Markdown for the assistant so bold, lists, tables and
           code blocks appear formatted. User input stays as plain text. */
        if (role === 'assistant' && window.SysRevAI && window.SysRevAI.mdRender) {
            div.innerHTML = window.SysRevAI.mdRender(content);
        } else {
            div.textContent = content;
        }
        msgs.appendChild(div);
        scrollToBottom();
        return div;
    }

    function appendTyping() {
        if (greeting) greeting.hidden = true;
        var bubble = document.createElement('div');
        bubble.className = 'copilot__msg copilot__msg--assistant copilot__typing';
        var dots = document.createElement('span');
        dots.className = 'copilot__dots';
        dots.setAttribute('aria-label', <?= json_encode(__('copilot.thinking')) ?>);
        dots.innerHTML = '<span></span><span></span><span></span>';
        bubble.appendChild(dots);
        msgs.appendChild(bubble);
        scrollToBottom();
        return bubble;
    }

    function errorMessage(err) {
        if (err === 'no_api_key')       return labels.noKey;
        if (err === 'feature_disabled') return labels.disabled;
        if (err === 'budget_exceeded')  return labels.budget;
        return labels.error;
    }

    function scrollToBottom() { msgs.scrollTop = msgs.scrollHeight; }
})();
</script>
