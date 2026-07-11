<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Helpers\Markdown;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
use SysRevAI\Models\ArticleCriticalReport;
use SysRevAI\Models\CopilotMessage;
use SysRevAI\Services\ClaudeService;

/**
 * Article-scoped Copilot endpoints. Mirror the global / review-scoped
 * Copilot pair, with the article as the context anchor.
 */
final class ArticleChatController
{
    public function send(string $id): void
    {
        header('Content-Type: application/json');
        $uid = (int) Auth::id();
        $articleId = (int) $id;
        $article = Article::find($articleId);
        if ($article === null || !Article::userCanAccess($articleId, $uid)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            return;
        }

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
        $mode = in_array(($payload['mode'] ?? ''), ClaudeService::CHAT_MODES, true) ? (string) $payload['mode'] : 'default';

        $history = CopilotMessage::history(null, $uid, 200, $articleId);
        CopilotMessage::add(null, $uid, 'user', $message, $articleId);

        // If a critical report exists for this article, hand it to
        // ClaudeService so Copilot can ground its suggestions in the
        // structured rubric and recommendations the user already saw.
        $reportRow = ArticleCriticalReport::find($articleId);
        $report = $reportRow !== null ? ArticleCriticalReport::decode($reportRow) : null;

        // Secondary documents the user has attached — fed as supporting
        // material so Copilot can answer cross-document questions
        // (methodology comparisons, citation lookups, …).
        $secondary = \SysRevAI\Models\ArticleDocument::copilotPayload($articleId, 12000);

        $result = ClaudeService::fromSettings()->articleChat($article, $history, $message, $mode, $report, $secondary);

        if (!$result['ok']) {
            ActivityLog::record('articles.chat_failed', ['article_id' => $articleId, 'error' => $result['error'] ?? 'unknown']);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
            return;
        }

        $reply = (string) $result['reply'];
        CopilotMessage::add(null, $uid, 'assistant', $reply, $articleId);
        ActivityLog::record('articles.chat_message', ['article_id' => $articleId]);
        echo json_encode([
            'ok'        => true,
            'reply'     => $reply,
            'reply_html' => Markdown::render($reply),
        ]);
    }

    public function history(string $id): void
    {
        header('Content-Type: application/json');
        $uid = (int) Auth::id();
        $articleId = (int) $id;
        if (!Article::userCanAccess($articleId, $uid)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        echo json_encode([
            'ok'       => true,
            'messages' => CopilotMessage::history(null, $uid, 200, $articleId),
        ]);
    }

    public function clear(string $id): void
    {
        header('Content-Type: application/json');
        $uid = (int) Auth::id();
        $articleId = (int) $id;
        if (!Article::userCanAccess($articleId, $uid)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            return;
        }
        CopilotMessage::clear(null, $uid, $articleId);
        ActivityLog::record('articles.chat_cleared', ['article_id' => $articleId]);
        echo json_encode(['ok' => true]);
    }
}
