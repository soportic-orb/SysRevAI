<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\CopilotMessage;
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
        // AJAX detection — the secondary-study cards on the new-review form
        // post here with X-Requested-With:fetch so the user can spawn extra
        // sibling reviews without losing the main draft they're still
        // editing. Falls through to the normal redirect for plain submits.
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

        $data = $this->readInput();
        if ($data['title'] === '') {
            if ($isAjax) {
                http_response_code(422);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => __('reviews.title_required')]);
                return;
            }
            Session::flash('error', __('reviews.title_required'));
            redirect('/reviews/new');
        }

        $uid = (int) Auth::id();
        $id = Review::create($uid, $data);
        ReviewUser::add($id, $uid, 'owner', false, true);

        // Persist exclusion reasons. When the caller didn't send any (the
        // AJAX-submitted sub-study cards only carry the 9 protocol fields,
        // and the AI-extract flow doesn't seed the exclusion-reasons
        // textarea), fall back to the platform defaults so every new
        // review starts with a usable PRISMA-friendly set.
        $reasons = $this->splitLines((string) ($_POST['exclusion_reasons'] ?? ''));
        if ($reasons === []) {
            $reasons = array_values(array_filter(
                array_map('strval', $this->defaultReasons()),
                static fn (string $s): bool => trim($s) !== ''
            ));
        }
        foreach ($reasons as $i => $label) {
            ExclusionReason::add($id, $label, 'both', $i);
        }

        ActivityLog::record('review.created', ['review_id' => $id], $id);

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => true,
                'id'    => $id,
                'title' => $data['title'],
                'url'   => '/reviews/' . $id,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

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
        $this->loadOrDeny((int) $id, true);
        $this->runProtocolExtraction((int) $id);
    }

    /**
     * Same as extractProtocol() but for the "Create a new review" form,
     * where there's no review row yet. Any logged-in user can use it —
     * the result is just JSON sent back to populate the form, no DB
     * writes happen until the user actually submits the new-review form.
     */
    public function extractProtocolDraft(): void
    {
        $this->runProtocolExtraction(null);
    }

    /**
     * Shared file-handling + Claude-call body for both extractProtocol()
     * (existing review) and extractProtocolDraft() (new-review form).
     * Emits the JSON response directly; the caller's job is just to
     * gate access correctly.
     */
    private function runProtocolExtraction(?int $reviewId): void
    {
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

        $result = ClaudeService::fromSettings()->extractProtocolFromText($text, $reviewId);
        if (!$result['ok']) {
            ActivityLog::record('review.protocol_extract.failed', ['error' => $result['error'] ?? 'unknown'], $reviewId);
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'ai_failed']);
            return;
        }
        ActivityLog::record('review.protocol_extract.ok', [], $reviewId);
        echo json_encode(['ok' => true, 'data' => $result['data']], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Scientific Copilot endpoint. Any member of the review can chat.
     * History is persisted server-side in copilot_messages so the bot
     * remembers prior turns across devices and sessions — the JS only
     * sends the new user message; the controller pulls history from
     * the database and writes both turns back when Claude replies.
     */
    public function copilot(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        header('Content-Type: application/json; charset=utf-8');

        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = $_POST;
        }
        $message = trim((string) ($body['message'] ?? ''));

        if ($message === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'empty_message']);
            return;
        }

        $rid = (int) $id;
        $uid = (int) Auth::id();
        $history = CopilotMessage::history($rid, $uid, 200);

        // Persist the user turn BEFORE calling the AI so even an AI failure
        // leaves a trace of what was asked.
        CopilotMessage::add($rid, $uid, 'user', $message);

        // Page context (current screen + reference being reviewed) is
        // supplied by the front end. Sanitised here so a malicious client
        // can't sneak in fields from other reviews.
        $pageContext = $this->sanitisePageContext($body['page_context'] ?? null, $rid);

        $result = ClaudeService::fromSettings()->copilotChat(
            $review,
            Review::pico($review),
            Review::metrics($rid),
            $history,
            $message,
            $rid,
            $pageContext,
        );

        if (!$result['ok']) {
            ActivityLog::record('copilot.failed', ['error' => $result['error'] ?? 'unknown'], $rid);
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'failed']);
            return;
        }
        CopilotMessage::add($rid, $uid, 'assistant', $result['reply']);
        ActivityLog::record('copilot.message', [], $rid);
        echo json_encode(['ok' => true, 'reply' => $result['reply']], JSON_UNESCAPED_UNICODE);
    }

    /** Return the persisted Copilot transcript so the widget can hydrate. */
    public function copilotHistory(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        header('Content-Type: application/json; charset=utf-8');
        $messages = CopilotMessage::history((int) $id, (int) Auth::id(), 200);
        echo json_encode(['ok' => true, 'messages' => $messages], JSON_UNESCAPED_UNICODE);
    }

    /** Wipe the researcher's own thread for this review (used by /clear). */
    public function copilotClear(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        header('Content-Type: application/json; charset=utf-8');
        CopilotMessage::clear((int) $id, (int) Auth::id());
        ActivityLog::record('copilot.cleared', [], (int) $id);
        echo json_encode(['ok' => true]);
    }

    /**
     * Validate the client-supplied page context. Returns null when nothing
     * meaningful is provided. The reference_id is always re-loaded from the
     * database and must belong to this review — that way a tampered POST
     * cannot leak references from other reviews.
     *
     * @return array<string,mixed>|null
     */
    private function sanitisePageContext(mixed $raw, int $reviewId): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $allowedPages = ['titleabstract-screening', 'fulltext-screening', 'extraction', 'rob'];
        $page = (string) ($raw['page'] ?? '');
        if (!in_array($page, $allowedPages, true)) {
            return null;
        }
        $out = ['page' => $page];

        $refId = isset($raw['reference_id']) ? (int) $raw['reference_id'] : 0;
        if ($refId > 0) {
            $ref = \SysRevAI\Models\Reference::find($refId);
            if ($ref !== null && (int) $ref['review_id'] === $reviewId) {
                $out['reference_id']    = $refId;
                $out['reference_title'] = (string) ($ref['title'] ?? '');
                $out['reference_year']  = (int) ($ref['year'] ?? 0);
                $out['reference_journal'] = (string) ($ref['journal'] ?? '');
                $out['reference_doi']   = (string) ($ref['doi'] ?? '');
                $out['reference_pmid']  = (string) ($ref['pmid'] ?? '');
                $out['reference_abstract'] = (string) ($ref['abstract'] ?? '');
                // Pull a slice of the article body when available so the
                // Copilot can answer content questions during full-text
                // screening — only on the FT page, to keep prompts small.
                if ($page === 'fulltext-screening') {
                    try {
                        $ft = \SysRevAI\Models\ReferenceFullText::find($refId);
                        if (is_array($ft) && !empty($ft['extracted_text'])) {
                            $out['reference_full_text_excerpt'] = mb_substr((string) $ft['extracted_text'], 0, 20000);
                        }
                    } catch (\Throwable) {
                        // ReferenceFullText optional — fall through silently.
                    }
                }
            }
        }
        return $out;
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

    /**
     * Permanently delete an archived review and all of its data.
     * Restricted to the review owner and platform admins/owners.
     * Refuses on non-archived reviews — archiving is the required
     * cool-off step so accidental clicks don't destroy live work.
     */
    public function destroy(string $id): void
    {
        $rid = (int) $id;
        $review = Review::find($rid);
        $uid    = (int) Auth::id();
        $isReviewOwner   = $review !== null && (int) $review['owner_id'] === $uid;
        $isPlatformAdmin = Auth::hasRole('owner', 'admin');
        if ($review === null || (!$isReviewOwner && !$isPlatformAdmin)) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        if ($review['status'] !== 'archived') {
            Session::flash('error', __('reviews.delete_requires_archived'));
            redirect('/reviews/' . $rid);
        }

        $title = (string) ($review['title'] ?? '');
        Review::delete($rid);
        // Tombstone written AFTER the wipe so it isn't itself swept by
        // the activity-log cleanup inside Review::delete(). review_id is
        // left null because the row it would point to no longer exists.
        ActivityLog::record('review.deleted', [
            'review_id' => $rid,
            'title'     => $title,
        ]);
        Session::flash('success', __('reviews.deleted_ok'));
        redirect('/reviews');
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
