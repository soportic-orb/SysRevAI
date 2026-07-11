<?php

declare(strict_types=1);

use SysRevAI\Helpers\Markdown;

/** @var int   $articleChatId */
/** @var array $articleHistory */

// Pre-render assistant messages to HTML server-side so the formatting
// (bold, lists, tables, code) survives the initial page load too.
$articleHistoryForJs = [];
foreach ($articleHistory as $m) {
    $role = (string) ($m['role'] ?? '');
    $content = (string) ($m['content'] ?? '');
    $articleHistoryForJs[] = [
        'role'    => $role,
        'content' => $content,
        'html'    => $role === 'assistant' ? Markdown::render($content) : '',
    ];
}
?>
<div class="article-chat" id="articleChat"
     data-url="/tools/articles/<?= $articleChatId ?>/chat"
     data-history-url="/tools/articles/<?= $articleChatId ?>/chat/history"
     data-clear-url="/tools/articles/<?= $articleChatId ?>/chat/clear"
     data-csrf="<?= e(csrf_token()) ?>">
    <header class="article-chat__head">
        <div>
            <h2 class="section__subtitle"><?= e(__('articles.chat_title')) ?></h2>
            <p class="muted article-chat__subtitle"><?= e(__('articles.chat_subtitle')) ?></p>
        </div>
        <div class="article-chat__head-actions">
            <!-- Conversational mode strip — exactly one (or none) of these
                 four is active at a time. See ClaudeService::modeOverlay(). -->
            <button type="button" class="article-chat__icon-btn article-chat__icon-btn--toggle"
                    id="articleDevilAdvocate"
                    title="<?= e(__('copilot.devil_advocate_title')) ?>"
                    aria-label="<?= e(__('copilot.devil_advocate_aria')) ?>"
                    aria-pressed="false">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22V11"></path>
                    <path d="M6 7V3"></path>
                    <path d="M12 7V3"></path>
                    <path d="M18 7V3"></path>
                    <path d="M5 7h14a1 1 0 0 1 1 1v2a4 4 0 0 1-8 0 4 4 0 0 1-8 0V8a1 1 0 0 1 1-1z"></path>
                </svg>
            </button>
            <button type="button" class="article-chat__icon-btn article-chat__icon-btn--toggle"
                    id="articleSocratic"
                    title="<?= e(__('copilot.socratic_title')) ?>"
                    aria-label="<?= e(__('copilot.socratic_aria')) ?>"
                    aria-pressed="false">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M9.5 9a2.5 2.5 0 0 1 4.5 1.5c0 1.5-2 1.5-2 3.5"></path>
                    <path d="M12 17.5v.01"></path>
                </svg>
            </button>
            <button type="button" class="article-chat__icon-btn article-chat__icon-btn--toggle"
                    id="articleFactCheck"
                    title="<?= e(__('copilot.fact_check_title')) ?>"
                    aria-label="<?= e(__('copilot.fact_check_aria')) ?>"
                    aria-pressed="false">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"></path>
                    <path d="M9.5 12l1.8 1.8L15 10"></path>
                </svg>
            </button>
            <button type="button" class="article-chat__icon-btn article-chat__icon-btn--toggle"
                    id="articleLitReview"
                    title="<?= e(__('copilot.lit_review_title')) ?>"
                    aria-label="<?= e(__('copilot.lit_review_aria')) ?>"
                    aria-pressed="false">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polygon points="12 3 21 8 12 13 3 8 12 3"></polygon>
                    <polyline points="3 12 12 17 21 12"></polyline>
                    <polyline points="3 16 12 21 21 16"></polyline>
                </svg>
            </button>
            <button type="button" class="article-chat__icon-btn" id="articleChatClear"
                    title="<?= e(__('copilot.clear')) ?>" aria-label="<?= e(__('copilot.clear')) ?>">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                    <path d="M10 11v6"></path>
                    <path d="M14 11v6"></path>
                </svg>
            </button>
        </div>
    </header>
    <div class="article-chat__messages" id="articleChatMessages" aria-live="polite">
        <div class="article-chat__greeting" id="articleChatGreeting">
            <p><?= e(__('articles.chat_greeting')) ?></p>
        </div>
    </div>
    <form class="article-chat__form" id="articleChatForm">
        <textarea class="input article-chat__input" id="articleChatInput" rows="2"
                  placeholder="<?= e(__('articles.chat_placeholder')) ?>"
                  required></textarea>
        <button class="btn btn--primary btn--sm" type="submit"><?= e(__('copilot.send')) ?></button>
    </form>
</div>

<script>
(function () {
    'use strict';
    var root = document.getElementById('articleChat');
    if (!root) return;

    var sendUrl    = root.getAttribute('data-url');
    var historyUrl = root.getAttribute('data-history-url');
    var clearUrl   = root.getAttribute('data-clear-url');
    var csrfToken  = root.getAttribute('data-csrf');

    var msgs     = document.getElementById('articleChatMessages');
    var form     = document.getElementById('articleChatForm');
    var input    = document.getElementById('articleChatInput');
    var clearBtn = document.getElementById('articleChatClear');
    var greeting = document.getElementById('articleChatGreeting');

    var labels = {
        error:    <?= json_encode(__('copilot.error')) ?>,
        budget:   <?= json_encode(__('copilot.budget')) ?>,
        disabled: <?= json_encode(__('copilot.disabled')) ?>,
        noKey:    <?= json_encode(__('copilot.no_api_key')) ?>,
        confirm:  <?= json_encode(__('copilot.clear_confirm')) ?>,
        copy:     <?= json_encode(__('articles.copy_paragraph')) ?>,
        copied:   <?= json_encode(__('articles.copy_done')) ?>
    };

    /* Conversational mode strip — persisted per device/article. Exactly
       one (or none — 'default') is active at a time; sent to the server
       as `mode` on each turn. See ClaudeService::modeOverlay(). */
    var modeButtons = {
        devil_advocate: document.getElementById('articleDevilAdvocate'),
        socratic:       document.getElementById('articleSocratic'),
        fact_check:     document.getElementById('articleFactCheck'),
        lit_review:     document.getElementById('articleLitReview')
    };
    var modeKey = 'sysrevai.articles.mode.' + <?= json_encode($articleChatId) ?>;
    var activeMode = 'default';
    try { activeMode = localStorage.getItem(modeKey) || 'default'; } catch (e) {}
    function applyModeState() {
        Object.keys(modeButtons).forEach(function (m) {
            var btn = modeButtons[m];
            if (!btn) return;
            var on = activeMode === m;
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.classList.toggle('is-active', on);
        });
    }
    applyModeState();
    Object.keys(modeButtons).forEach(function (m) {
        var btn = modeButtons[m];
        if (!btn) return;
        btn.addEventListener('click', function () {
            activeMode = (activeMode === m) ? 'default' : m;
            try { localStorage.setItem(modeKey, activeMode); } catch (e) {}
            applyModeState();
        });
    });

    /* Cmd/Ctrl+Enter sends. */
    input.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            form.requestSubmit();
        }
    });

    var sending = false;
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sending) return;
        var text = (input.value || '').trim();
        if (!text) return;

        appendBubble('user', text);
        input.value = '';
        sending = true;
        var typing = appendTyping();

        fetch(sendUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                _csrf: csrfToken,
                message: text,
                mode: activeMode
            })
        })
        .then(function (r) { return r.json().then(function (b) { return { status: r.status, body: b }; }); })
        .then(function (res) {
            typing.remove();
            if (res.body && res.body.ok && res.body.reply) {
                appendBubble('assistant', res.body.reply, res.body.reply_html || '');
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

    clearBtn.addEventListener('click', function () {
        if (!confirm(labels.confirm)) return;
        fetch(clearUrl, {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: csrfToken })
        }).finally(function () {
            msgs.querySelectorAll('.article-chat__msg, .article-chat__typing').forEach(function (n) { n.remove(); });
            if (greeting) {
                msgs.insertBefore(greeting, msgs.firstChild);
                greeting.hidden = false;
            }
        });
    });

    /* Hydrate from server. Assistant turns arrive with pre-rendered HTML
       so bold / lists / tables show without a client-side parser. */
    var serverHistory = <?= json_encode($articleHistoryForJs, JSON_UNESCAPED_UNICODE) ?>;
    if (serverHistory && serverHistory.length) {
        if (greeting) greeting.hidden = true;
        serverHistory.forEach(function (m) { appendBubble(m.role, m.content, m.html || ''); });
    }

    function appendBubble(role, content, html) {
        if (greeting) greeting.hidden = true;
        var div = document.createElement('div');
        div.className = 'article-chat__msg article-chat__msg--' + role;
        if (role === 'assistant') {
            var body = document.createElement('div');
            body.className = 'article-chat__body';
            if (html) {
                body.innerHTML = html;
            } else {
                body.textContent = content;
            }
            div.appendChild(body);
            div.appendChild(buildCopyButton(content));
        } else {
            div.textContent = content;
        }
        msgs.appendChild(div);
        scrollToBottom();
    }

    /* One copy button per assistant response, placed at the end. Copies
       the raw markdown reply so users can paste it elsewhere. */
    function buildCopyButton(text) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'article-chat__copy';
        btn.title = labels.copy;
        btn.setAttribute('aria-label', labels.copy);
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15V5a2 2 0 0 1 2-2h10"></path></svg>'
                     + '<span class="article-chat__copy-label">' + labels.copy + '</span>';
        btn.addEventListener('click', function () {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(flash).catch(fallback);
            } else {
                fallback();
            }
            function fallback() {
                var ta = document.createElement('textarea');
                ta.value = text; ta.setAttribute('readonly', '');
                ta.style.position = 'fixed'; ta.style.left = '-9999px';
                document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                flash();
            }
            function flash() {
                btn.classList.add('is-copied');
                var labelEl = btn.querySelector('.article-chat__copy-label');
                if (labelEl) labelEl.textContent = labels.copied;
                btn.title = labels.copied;
                setTimeout(function () {
                    btn.classList.remove('is-copied');
                    if (labelEl) labelEl.textContent = labels.copy;
                    btn.title = labels.copy;
                }, 1400);
            }
        });
        return btn;
    }

    function appendTyping() {
        if (greeting) greeting.hidden = true;
        var bubble = document.createElement('div');
        bubble.className = 'article-chat__msg article-chat__msg--assistant article-chat__typing';
        var dots = document.createElement('span');
        dots.className = 'article-chat__dots';
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
