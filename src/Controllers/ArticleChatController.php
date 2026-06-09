<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Article;
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
        $mode = ($payload['mode'] ?? '') === 'devil_advocate' ? 'devil_advocate' : 'default';

        $history = CopilotMessage::history(null, $uid, 200, $articleId);
        CopilotMessage::add(null, $uid, 'user', $message, $articleId);

        $result = ClaudeService::fromSettings()->articleChat($article, $history, $message, $mode);

        if (!$result['ok']) {
            ActivityLog::record('articles.chat_failed', ['article_id' => $articleId, 'error' => $result['error'] ?? 'unknown']);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
            return;
        }

        CopilotMessage::add(null, $uid, 'assistant', (string) $result['reply'], $articleId);
        ActivityLog::record('articles.chat_message', ['article_id' => $articleId]);
        echo json_encode(['ok' => true, 'reply' => (string) $result['reply']]);
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
