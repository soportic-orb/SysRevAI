<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Models\CopilotMessage;
use SysRevAI\Services\ClaudeService;

/**
 * Global Scientific Copilot — the floating chat widget when the user is
 * NOT inside a review. Backed by the same copilot_messages table with a
 * NULL review_id; the per-review version (ReviewsController::copilot)
 * still owns the review-scoped flow because it needs the protocol
 * context and the optional page snapshot from the screening UI.
 */
final class CopilotController
{
    /** POST /copilot — send a message to the global thread. */
    public function send(): void
    {
        header('Content-Type: application/json');
        $uid = (int) Auth::id();

        $raw = (string) file_get_contents('php://input');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $message = trim((string) ($payload['message'] ?? ''));
        if ($message === '') {
            echo json_encode(['ok' => false, 'error' => 'empty_message']);
            return;
        }
        $mode = ($payload['mode'] ?? '') === 'devil_advocate' ? 'devil_advocate' : 'default';

        // Page context lets the global Copilot answer questions about the
        // work the user is doing right now. Today only the collaborative
        // article editor opts in — see views/articles/edit.php for the
        // emitter. We sanitise / access-check here so a malicious client
        // can't sneak in an article they don't own.
        $pageContext = $this->sanitisePageContext($payload['page_context'] ?? null, $uid);

        $history = CopilotMessage::history(null, $uid, 200);

        // Persist the user turn first so a transport failure still leaves
        // a record of what the user asked, mirroring the review-scoped
        // version's behaviour.
        CopilotMessage::add(null, $uid, 'user', $message);

        $result = ClaudeService::fromSettings()->assistantChat($history, $message, $mode, $pageContext);

        if (!$result['ok']) {
            ActivityLog::record('copilot.global_failed', ['error' => $result['error'] ?? 'unknown']);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
            return;
        }

        CopilotMessage::add(null, $uid, 'assistant', (string) $result['reply']);
        ActivityLog::record('copilot.global_message', []);
        echo json_encode(['ok' => true, 'reply' => (string) $result['reply']]);
    }

    /** GET /copilot/history — hydrate the widget. */
    public function history(): void
    {
        header('Content-Type: application/json');
        $messages = CopilotMessage::history(null, (int) Auth::id(), 200);
        echo json_encode(['ok' => true, 'messages' => $messages]);
    }

    /** POST /copilot/clear — wipe this user's global transcript. */
    public function clear(): void
    {
        header('Content-Type: application/json');
        CopilotMessage::clear(null, (int) Auth::id());
        ActivityLog::record('copilot.global_cleared', []);
        echo json_encode(['ok' => true]);
    }

    /**
     * Sanitise the page_context payload supplied by the front end.
     * Only the article-collab-editor shape is understood today; an
     * unknown `page` or a missing / inaccessible article id makes us
     * fall back to the regular context-free flow so the user still
     * gets an answer.
     *
     * @param  mixed $raw
     * @return array<string,mixed>|null
     */
    private function sanitisePageContext(mixed $raw, int $userId): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $page = (string) ($raw['page'] ?? '');
        if ($page !== 'article-collab-editor') {
            return null;
        }
        $articleId = (int) ($raw['article_id'] ?? 0);
        if ($articleId <= 0 || !Article::userCanAccess($articleId, $userId)) {
            return null;
        }
        $article = Article::find($articleId);
        if ($article === null) {
            return null;
        }
        $reportRow = ArticleCriticalReport::find($articleId);
        return [
            'page'           => 'article-collab-editor',
            'article'        => $article,
            'editor_html'    => substr((string) ($raw['editor_html']    ?? ''), 0, 60000),
            'selection_html' => substr((string) ($raw['selection_html'] ?? ''), 0, 8000),
            'selection_text' => substr((string) ($raw['selection_text'] ?? ''), 0, 4000),
            'report'         => $reportRow !== null ? ArticleCriticalReport::decode($reportRow) : null,
        ];
    }
}
