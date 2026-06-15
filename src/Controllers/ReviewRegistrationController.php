<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewRegistration;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\RegistrationExportService;
use SysRevAI\Services\RegistrationFields;

/**
 * PROSPERO / OSF registration assistant on the review export tab.
 *
 * One page renders a form whose schema is picked from
 * RegistrationFields::schemaFor(Review::kind($review)) — systematic
 * reviews get the PROSPERO field set, scoping reviews get the OSF
 * one. The form persists to review_registrations, can be auto-filled
 * by Claude from the existing protocol, and exports to Word + PDF.
 *
 * Endpoints (all gated by review membership):
 *   GET  /reviews/{id}/exports/registration            show()
 *   POST /reviews/{id}/exports/registration/save       save()
 *   POST /reviews/{id}/exports/registration/ai-fill    aiFill()
 *   GET  /reviews/{id}/exports/registration/word       word()
 *   GET  /reviews/{id}/exports/registration/pdf        pdf()
 */
final class ReviewRegistrationController
{
    public function show(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $kind = $this->kindFor($review);
        $row  = ReviewRegistration::find((int) $review['id']);
        $data = ReviewRegistration::decode($row);
        // First time on the page → seed the title field with the review
        // title so the user has something to look at before AI-filling.
        if ($data === [] && isset($review['title']) && (string) $review['title'] !== '') {
            $data['title'] = (string) $review['title'];
        }
        echo View::render('exports/registration', [
            'review'     => $review,
            'kind'       => $kind,
            'schema'     => RegistrationFields::schemaFor($kind),
            'data'       => $data,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
    }

    public function save(string $id): void
    {
        header('Content-Type: application/json');
        $review = $this->memberOrDeny((int) $id);
        $kind = $this->kindFor($review);
        $payload = $this->jsonBody();
        $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $clean = RegistrationFields::sanitise($fields, $kind);
        ReviewRegistration::save((int) $review['id'], $kind, $clean, (int) Auth::id() ?: null);
        ActivityLog::record('exports.registration_saved', ['review_id' => (int) $review['id'], 'kind' => $kind], (int) $review['id']);
        echo json_encode(['ok' => true, 'saved_at' => gmdate('c')]);
    }

    public function aiFill(string $id): void
    {
        header('Content-Type: application/json');
        $review = $this->memberOrDeny((int) $id);
        $kind   = $this->kindFor($review);
        $schema = RegistrationFields::schemaFor($kind);
        $pico   = Review::pico($review);
        $locale = (string) (Auth::user()['locale'] ?? current_locale());

        @set_time_limit(240);
        $result = ClaudeService::fromSettings()->fillRegistrationFields($review, $pico, $schema, $kind, $locale);
        if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
            ActivityLog::record('exports.registration_ai_failed', [
                'review_id' => (int) $review['id'],
                'kind'      => $kind,
                'error'     => (string) ($result['error'] ?? 'unknown'),
            ], (int) $review['id']);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'unknown']);
            return;
        }

        // Merge with the saved row so AI never wipes a field the user
        // has already polished — only blank fields receive a draft.
        $existing = ReviewRegistration::decode(ReviewRegistration::find((int) $review['id']));
        $merged = $existing;
        foreach ($schema as $f) {
            $current = trim((string) ($existing[$f['id']] ?? ''));
            $proposed = trim((string) ($result['data'][$f['id']] ?? ''));
            if ($current === '' && $proposed !== '') {
                $merged[$f['id']] = $proposed;
            }
        }
        // Persist so a refresh keeps the AI draft.
        ReviewRegistration::save((int) $review['id'], $kind, $merged, (int) Auth::id() ?: null);
        ActivityLog::record('exports.registration_ai_filled', [
            'review_id' => (int) $review['id'],
            'kind'      => $kind,
            'filled'    => count(array_filter($merged, static fn (string $v) => $v !== '')),
        ], (int) $review['id']);
        echo json_encode(['ok' => true, 'data' => $merged]);
    }

    public function word(string $id): void
    {
        $this->exportFile((int) $id, 'word');
    }

    public function pdf(string $id): void
    {
        $this->exportFile((int) $id, 'pdf');
    }

    private function exportFile(int $id, string $format): void
    {
        $review = $this->memberOrDeny($id);
        $kind   = $this->kindFor($review);
        $schema = RegistrationFields::schemaFor($kind);
        $data   = ReviewRegistration::decode(ReviewRegistration::find($id));
        $title  = (string) ($review['title'] ?? 'Review');

        @set_time_limit(120);
        if ($format === 'word') {
            $bytes = RegistrationExportService::word($title, $kind, $schema, $data);
            $this->sendFile($review, $kind, 'docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', $bytes);
        } else {
            $bytes = RegistrationExportService::pdf($title, $kind, $schema, $data);
            $this->sendFile($review, $kind, 'pdf', 'application/pdf', $bytes);
        }
        ActivityLog::record('exports.registration_downloaded', ['review_id' => $id, 'kind' => $kind, 'format' => $format], $id);
    }

    private function sendFile(array $review, string $kind, string $ext, string $mime, string $bytes): void
    {
        $slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string) $review['title']) ?: 'review';
        $slug = trim((string) $slug, '_') ?: 'review';
        $filename = $slug . '-' . strtoupper($kind) . '-registration.' . $ext;
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store');
        echo $bytes;
    }

    private function kindFor(array $review): string
    {
        return Review::isScoping($review) ? RegistrationFields::KIND_OSF : RegistrationFields::KIND_PROSPERO;
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
