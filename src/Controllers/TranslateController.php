<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Models\Review;
use SysRevAI\Services\TranslateService;

/**
 * Generic translation endpoint scoped to a review (member access). Accepts
 * arbitrary text from the UI (abstracts, AI summaries, snippets) and returns
 * the translated string as JSON. Results are cached in the translations table.
 */
final class TranslateController
{
    public function translate(string $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $review = Review::find((int) $id);
        if ($review === null || !Review::userCanAccess((int) $id, (int) Auth::id())) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            return;
        }

        $text = (string) ($_POST['text'] ?? '');
        $target = (string) ($_POST['target_lang'] ?? '');
        $source = (string) ($_POST['source_lang'] ?? 'auto');

        if (!preg_match('/^[a-z]{2,3}(-[A-Z]{2})?$/', $target)) {
            echo json_encode(['ok' => false, 'error' => 'invalid_target']);
            return;
        }
        if (mb_strlen($text) === 0) {
            echo json_encode(['ok' => true, 'translated' => '']);
            return;
        }
        if (mb_strlen($text) > 100_000) {
            echo json_encode(['ok' => false, 'error' => 'too_long']);
            return;
        }

        $result = TranslateService::translate($text, $target, $source);
        if (!($result['ok'] ?? false)) {
            echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'translate_failed')]);
            return;
        }
        echo json_encode(['ok' => true, 'translated' => (string) $result['text']], JSON_UNESCAPED_UNICODE);
    }
}
