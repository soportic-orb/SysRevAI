<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Models\ActivityLog;
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

        $history = CopilotMessage::history(null, $uid, 200);

        // Persist the user turn first so a transport failure still leaves
        // a record of what the user asked, mirroring the review-scoped
        // version's behaviour.
        CopilotMessage::add(null, $uid, 'user', $message);

        $result = ClaudeService::fromSettings()->assistantChat($history, $message);

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
}
