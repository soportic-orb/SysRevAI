<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\AiChatMessage;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;

/**
 * Per-article chat with Claude. History is private per (user, reference).
 */
final class ChatController
{
    public function send(string $id, string $refId): void
    {
        $review = Review::find((int) $id);
        $reference = Reference::find((int) $refId);
        if ($review === null || $reference === null
            || (int) $reference['review_id'] !== (int) $id
            || !Review::userCanAccess((int) $id, (int) Auth::id())) {
            http_response_code(403);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '' || mb_strlen($message) > 4000) {
            echo json_encode(['ok' => false, 'error' => 'invalid_message']);
            return;
        }

        $ft = ReferenceFullText::find((int) $reference['id']);
        $articleText = (string) ($ft['extracted_text'] ?? ($reference['abstract'] ?? ''));
        if (trim($articleText) === '') {
            echo json_encode(['ok' => false, 'error' => 'no_text']);
            return;
        }

        $userId = (int) Auth::id();
        $history = AiChatMessage::history((int) $reference['id'], $userId, 20);

        $result = ClaudeService::fromSettings()
            ->chatWithArticle($articleText, $history, $message, (int) $id);

        if (!($result['ok'] ?? false)) {
            echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'ai_error')]);
            return;
        }

        $reply = (string) $result['data'];
        AiChatMessage::append((int) $reference['id'], $userId, 'user', $message);
        AiChatMessage::append((int) $reference['id'], $userId, 'assistant', $reply);

        echo json_encode(['ok' => true, 'reply' => $reply], JSON_UNESCAPED_UNICODE);
    }
}
