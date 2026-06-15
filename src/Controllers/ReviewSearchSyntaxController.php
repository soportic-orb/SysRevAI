<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewSearchSyntax;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\SearchSyntaxDatabases;

/**
 * "Sintaxis de recerca" page on the review sub-nav.
 *
 * Endpoints (all gated by review membership):
 *   GET  /reviews/{id}/search-syntaxes           index()
 *   POST /reviews/{id}/search-syntaxes           save()         bulk replace
 *   POST /reviews/{id}/search-syntaxes/ai-import aiImport()     pull from protocol
 */
final class ReviewSearchSyntaxController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        echo View::render('search_syntaxes/index', [
            'review'    => $review,
            'rows'      => ReviewSearchSyntax::listForReview((int) $review['id']),
            'databases' => SearchSyntaxDatabases::all(),
        ]);
    }

    public function save(string $id): void
    {
        header('Content-Type: application/json');
        $review = $this->memberOrDeny((int) $id);
        $payload = $this->jsonBody();
        $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
        $clean = $this->sanitiseRows($rows);
        ReviewSearchSyntax::replaceAll((int) $review['id'], $clean, (int) Auth::id() ?: null);
        ActivityLog::record('reviews.search_syntaxes_saved', [
            'review_id' => (int) $review['id'],
            'count'     => count($clean),
        ], (int) $review['id']);
        echo json_encode(['ok' => true, 'saved_at' => gmdate('c')]);
    }

    public function aiImport(string $id): void
    {
        header('Content-Type: application/json');
        $review = $this->memberOrDeny((int) $id);
        $databases = SearchSyntaxDatabases::all();
        $protocolText = $this->buildProtocolText($review);

        if (trim($protocolText) === '') {
            echo json_encode(['ok' => true, 'data' => [], 'note' => 'empty_protocol']);
            return;
        }

        @set_time_limit(240);
        $result = ClaudeService::fromSettings()->extractSearchSyntaxes($review, $protocolText, $databases);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            ActivityLog::record('reviews.search_syntaxes_ai_failed', [
                'review_id' => (int) $review['id'],
                'error'     => (string) ($result['error'] ?? 'unknown'),
            ], (int) $review['id']);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
            return;
        }

        // Drop empty / whitespace-only matches. The UI then merges the
        // non-empty ones into the saved list without disturbing rows
        // the user already typed.
        $clean = [];
        foreach ($result['data'] as $key => $syntax) {
            $syntax = trim((string) $syntax);
            if ($syntax !== '') {
                $clean[(string) $key] = $syntax;
            }
        }

        ActivityLog::record('reviews.search_syntaxes_ai_imported', [
            'review_id' => (int) $review['id'],
            'found'     => count($clean),
        ], (int) $review['id']);
        echo json_encode(['ok' => true, 'data' => $clean]);
    }

    /**
     * @param  array<int,array<string,mixed>> $input
     * @return array<int,array{database_key:string,syntax:string}>
     */
    private function sanitiseRows(array $input): array
    {
        $allowed = array_flip(SearchSyntaxDatabases::keys());
        $out = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['database_key'] ?? '');
            $syntax = trim((string) ($row['syntax'] ?? ''));
            if (!isset($allowed[$key]) || $syntax === '') {
                continue;
            }
            $out[] = ['database_key' => $key, 'syntax' => $syntax];
        }
        return $out;
    }

    /**
     * Assemble whatever protocol-shaped text we have on the review so
     * the AI has something to read. Empty fields are skipped so the
     * model isn't biased by null markers.
     */
    private function buildProtocolText(array $review): string
    {
        $pieces = [];
        $title = trim((string) ($review['title'] ?? ''));
        if ($title !== '')                                                $pieces[] = 'TITLE: '              . $title;
        $question = trim((string) ($review['question'] ?? ''));
        if ($question !== '')                                             $pieces[] = 'QUESTION: '           . $question;
        $inc = trim((string) ($review['inclusion_criteria'] ?? ''));
        if ($inc !== '')                                                  $pieces[] = 'INCLUSION CRITERIA:'  . "\n" . $inc;
        $exc = trim((string) ($review['exclusion_criteria'] ?? ''));
        if ($exc !== '')                                                  $pieces[] = 'EXCLUSION CRITERIA:'  . "\n" . $exc;

        $pico = Review::pico($review);
        $picoLines = [];
        foreach ($pico as $k => $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                $picoLines[] = strtoupper($k) . ': ' . $v;
            }
        }
        if ($picoLines !== []) {
            $pieces[] = "PICO / PCC:\n" . implode("\n", $picoLines);
        }
        return implode("\n\n", $pieces);
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        return is_array($body) ? $body : [];
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
