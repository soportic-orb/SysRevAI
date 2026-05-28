<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\ExclusionReason;
use SysRevAI\Models\Review;
use SysRevAI\Models\ReviewUser;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\DocumentTextExtractor;

final class ReviewsController
{
    public function index(): void
    {
        $uid = (int) Auth::id();
        echo View::render('reviews/index', [
            'active'   => Review::forUser($uid, 'active'),
            'archived' => Review::forUser($uid, 'archived'),
        ]);
    }

    public function newForm(): void
    {
        echo View::render('reviews/form', [
            'review'    => null,
            'pico'      => Review::pico([]),
            'reasons'   => $this->defaultReasons(),
            'formAction' => '/reviews',
        ]);
    }

    public function store(): void
    {
        $data = $this->readInput();
        if ($data['title'] === '') {
            Session::flash('error', __('reviews.title_required'));
            redirect('/reviews/new');
        }

        $uid = (int) Auth::id();
        $id = Review::create($uid, $data);
        ReviewUser::add($id, $uid, 'owner', false, true);

        foreach ($this->splitLines((string) ($_POST['exclusion_reasons'] ?? '')) as $i => $label) {
            ExclusionReason::add($id, $label, 'both', $i);
        }

        ActivityLog::record('review.created', ['review_id' => $id], $id);
        Session::flash('success', __('reviews.created'));
        redirect('/reviews/' . $id);
    }

    public function show(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        echo View::render('reviews/show', [
            'review'   => $review,
            'pico'     => Review::pico($review),
            'metrics'  => Review::metrics((int) $id),
            'members'  => ReviewUser::forReview((int) $id),
            'reasons'  => ExclusionReason::forReview((int) $id),
            'comments' => \SysRevAI\Models\Comment::forReview((int) $id),
        ]);
    }

    public function editProtocol(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        $reasons = array_map(static fn ($r) => $r['label'], ExclusionReason::forReview((int) $id));
        echo View::render('reviews/form', [
            'review'     => $review,
            'pico'       => Review::pico($review),
            'reasons'    => $reasons,
            'formAction' => '/reviews/' . (int) $id . '/protocol',
        ]);
    }

    public function updateProtocol(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        $data = $this->readInput();
        if ($data['title'] === '') {
            Session::flash('error', __('reviews.title_required'));
            redirect('/reviews/' . (int) $id . '/protocol');
        }

        Review::update((int) $id, $data);
        ExclusionReason::replaceForReview((int) $id, $this->splitLines((string) ($_POST['exclusion_reasons'] ?? '')));
        ActivityLog::record('review.updated', ['review_id' => (int) $id], (int) $id);
        Session::flash('success', __('reviews.saved'));
        redirect('/reviews/' . (int) $id);
    }

    /**
     * Owner-only AJAX endpoint. Accepts a PDF/DOCX upload, extracts plain
     * text, asks Claude to fill the 8 protocol fields, and returns them
     * as JSON for the form to pre-fill. Does NOT touch the database — the
     * user still has to validate & submit the form to save.
     */
    public function extractProtocol(string $id): void
    {
        $review = $this->loadOrDeny((int) $id, true);
        header('Content-Type: application/json; charset=utf-8');

        $file = $_FILES['document'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'no_file']);
            return;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid_upload']);
            return;
        }
        $maxMb = (int) (setting('files.max_pdf_mb') ?? 50);
        if ((int) ($file['size'] ?? 0) > $maxMb * 1024 * 1024) {
            http_response_code(413);
            echo json_encode(['ok' => false, 'error' => 'too_large']);
            return;
        }
        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            http_response_code(415);
            echo json_encode(['ok' => false, 'error' => 'unsupported_format']);
            return;
        }

        $mime = '';
        if (class_exists('finfo')) {
            try {
                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
            } catch (\Throwable) {
                $mime = '';
            }
        }
        $text = DocumentTextExtractor::extract((string) $file['tmp_name'], $mime);
        if (trim($text) === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'empty_or_unreadable']);
            return;
        }

        $result = ClaudeService::fromSettings()->extractProtocolFromText($text, (int) $id);
        if (!$result['ok']) {
            ActivityLog::record('review.protocol_extract.failed', ['error' => $result['error'] ?? 'unknown'], (int) $id);
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'ai_failed']);
            return;
        }
        ActivityLog::record('review.protocol_extract.ok', [], (int) $id);
        echo json_encode(['ok' => true, 'data' => $result['data']], JSON_UNESCAPED_UNICODE);
    }

    public function archive(string $id): void
    {
        $review = $this->loadOrDeny((int) $id, true);
        $new = $review['status'] === 'archived' ? 'active' : 'archived';
        Review::setStatus((int) $id, $new);
        ActivityLog::record('review.' . ($new === 'archived' ? 'archived' : 'unarchived'), [], (int) $id);
        Session::flash('success', __('reviews.' . ($new === 'archived' ? 'archived_ok' : 'unarchived_ok')));
        redirect($new === 'archived' ? '/reviews' : '/reviews/' . (int) $id);
    }

    /* ── Helpers ───────────────────────────────────────────────────────── */

    private function readInput(): array
    {
        $mode = in_array(($_POST['screening_mode'] ?? ''), Review::SCREENING_MODES, true)
            ? $_POST['screening_mode'] : 'double_blind';

        return [
            'title'              => trim((string) ($_POST['title'] ?? '')),
            'question'           => trim((string) ($_POST['question'] ?? '')),
            'pico'               => [
                'population'    => trim((string) ($_POST['population'] ?? '')),
                'intervention'  => trim((string) ($_POST['intervention'] ?? '')),
                'comparison'    => trim((string) ($_POST['comparison'] ?? '')),
                'outcome'       => trim((string) ($_POST['outcome'] ?? '')),
                'study_design'  => trim((string) ($_POST['study_design'] ?? '')),
            ],
            'inclusion_criteria' => trim((string) ($_POST['inclusion_criteria'] ?? '')),
            'exclusion_criteria' => trim((string) ($_POST['exclusion_criteria'] ?? '')),
            'screening_mode'     => $mode,
            'pilot_count'        => max(1, (int) ($_POST['pilot_count'] ?? 50)),
            'reviewers_required' => max(1, min(5, (int) ($_POST['reviewers_required'] ?? 2))),
        ];
    }

    private function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
    }

    private function defaultReasons(): array
    {
        return (array) (setting('reviews.default_exclusion_reasons') ?? [
            'Wrong population', 'Wrong intervention', 'Wrong comparator',
            'Wrong outcome', 'Wrong study design', 'Duplicate',
        ]);
    }

    /** Load a review the current user may access, or stop with 403/redirect. */
    private function loadOrDeny(int $id, bool $ownerOnly = false): array
    {
        $review = Review::find($id);
        $uid = (int) Auth::id();
        if ($review === null || !Review::userCanAccess($id, $uid)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        if ($ownerOnly && (int) $review['owner_id'] !== $uid) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return $review;
    }
}
