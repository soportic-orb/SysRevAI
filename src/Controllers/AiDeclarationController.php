<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Review;
use SysRevAI\Services\AiDeclarationBuilder;
use SysRevAI\Services\AiDeclarationWordWriter;

/**
 * AI-usage declaration assistant.
 *
 * Renders a per-review summary of every step where Claude has been
 * called during the research workflow — protocol extraction,
 * screening suggestions, full-text data extraction, risk-of-bias
 * judgements, copilot conversations, peer-review rubrics, etc. — so
 * the user can paste / attach the corresponding declaration in their
 * publication. Exposes a Word export for that very purpose.
 *
 * Endpoints (all gated by review membership):
 *   GET /reviews/{id}/ai-declaration         show()
 *   GET /reviews/{id}/ai-declaration/word    word()
 */
final class AiDeclarationController
{
    public function show(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $report = AiDeclarationBuilder::build($review);
        echo View::render('ai_declaration/index', [
            'review' => $review,
            'report' => $report,
        ]);
    }

    public function word(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $report = AiDeclarationBuilder::build($review);

        @set_time_limit(60);
        if (!class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            http_response_code(500);
            echo 'PhpWord is not installed.';
            return;
        }
        $bytes = AiDeclarationWordWriter::build($report);

        $slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) ($review['title'] ?? 'review')) ?: 'review';
        $slug = trim((string) $slug, '_') ?: 'review';
        $filename = $slug . '-AI-declaration.docx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store');
        echo $bytes;

        ActivityLog::record('exports.ai_declaration_downloaded', ['review_id' => (int) $review['id']], (int) $review['id']);
    }

    private function memberOrDeny(int $reviewId): array
    {
        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
