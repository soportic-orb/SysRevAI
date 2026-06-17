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
        // Allow the Tools hub (and any deep link) to pre-select the kind
        // via ?kind=scoping so users entering through the Scoping tile
        // land on a form already configured with PCC labels.
        $kind = in_array(($_GET['kind'] ?? ''), Review::KINDS, true)
            ? (string) $_GET['kind']
            : 'systematic';
        echo View::render('reviews/form', [
            'review'    => ['kind' => $kind],
            'pico'      => Review::pico([]),
            'reasons'   => $this->defaultReasons(),
            'formAction' => '/reviews',
            'isNew'     => true,
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

        // Promote a protocol-document draft, if the new-review form
        // included one. The token was minted by runProtocolExtraction()
        // and stashed under storage/protocol_drafts/{uid}_{uuid}.{ext}
        // — we re-store the bytes under storage/protocols/ so the
        // review's protocol_path matches the regular upload format,
        // and clean up the draft. The {uid} prefix check stops a
        // malicious POST from promoting another user's draft.
        $this->promoteProtocolDraft($id, $uid);

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
        // Persisted separately so the main update SQL stays untouched
        // and a pre-migration install still saves the other fields.
        Review::saveScreeningGuide((int) $id, trim((string) ($_POST['screening_guide'] ?? '')));
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

        // Keep the original document on disk so the user can download it
        // Keep the original document on disk so the user can download it
        // back from the "Editar protocol" page. Two code paths:
        //
        //  • Existing review (reviewId !== null): store directly under
        //    storage/protocols/ and record the path on the review row.
        //  • New-review draft (reviewId === null): the review row doesn't
        //    exist yet, so we stash the bytes under storage/protocol_
        //    drafts/ with a {userId}_{uuid}.{ext} basename and return
        //    the basename as `protocol_draft_token`. The new-review form
        //    submits that token along with the row; store() promotes
        //    the file to storage/protocols/ once the review is created.
        $extra = [];
        if ($reviewId !== null) {
            try {
                $bytes = (string) file_get_contents((string) $file['tmp_name']);
                $path  = \SysRevAI\Services\FileStorage::storeBytes($bytes, $ext, 'protocols');
                if ($path !== null) {
                    // Delete the previous protocol file so we don't accumulate
                    // orphan uploads on every re-extraction.
                    $existing = Review::find($reviewId);
                    if ($existing !== null && !empty($existing['protocol_path'])) {
                        \SysRevAI\Services\FileStorage::delete((string) $existing['protocol_path']);
                    }
                    Review::saveProtocolFile($reviewId, $path, $name, $mime ?: '');
                }
            } catch (\Throwable) {
                // Disk full / perms — extraction still succeeded so the
                // user keeps the AI draft; the download button just
                // won't appear until they re-upload.
            }
        } else {
            try {
                $uid   = (int) Auth::id();
                $bytes = (string) file_get_contents((string) $file['tmp_name']);
                $path  = \SysRevAI\Services\FileStorage::storeBytes($bytes, $ext, 'protocol_drafts');
                if ($path !== null && $uid > 0) {
                    // Namespace draft files by user id so a malicious
                    // client can't promote another user's draft into
                    // its own review. The basename then carries the
                    // uploader's id, which store() checks.
                    $prefixed = dirname($path) . '/' . $uid . '_' . basename($path);
                    if (@rename($path, $prefixed)) {
                        $extra = [
                            'protocol_draft_token'    => basename($prefixed),
                            'protocol_draft_filename' => $name,
                            'protocol_draft_mime'     => $mime ?: '',
                        ];
                    } else {
                        @unlink($path);
                    }
                }
            } catch (\Throwable) {
                // Same fallback as above — the AI draft still lands,
                // we just can't offer a download later.
            }
        }

        echo json_encode(['ok' => true, 'data' => $result['data']] + $extra, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Serve back the protocol document the user (or someone with write
     * access) uploaded. Members can download; non-members get 403.
     */
    public function downloadProtocol(string $id): void
    {
        $review = $this->loadOrDeny((int) $id);
        $path = (string) ($review['protocol_path'] ?? '');
        if ($path === '' || !\SysRevAI\Services\FileStorage::isStoredIn($path, 'protocols') || !is_file($path)) {
            http_response_code(404);
            echo View::render('errors/404', [], 'layouts/auth');
            return;
        }
        $filename = (string) ($review['protocol_filename'] ?? '') !== ''
            ? (string) $review['protocol_filename']
            : ('protocol-' . (int) $review['id'] . '.' . pathinfo($path, PATHINFO_EXTENSION));
        $mime = (string) ($review['protocol_mime'] ?? '') !== ''
            ? (string) $review['protocol_mime']
            : 'application/octet-stream';

        // Strip any path component the original filename might have
        // carried (Windows users sometimes upload "C:\fakepath\foo.pdf")
        // and quote-escape to keep Content-Disposition well-formed.
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = str_replace(['"', "\r", "\n"], ['', '', ''], $filename);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0');
        readfile($path);
        ActivityLog::record('review.protocol_downloaded', ['review_id' => (int) $review['id']], (int) $review['id']);
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
        $mode = ($body['mode'] ?? '') === 'devil_advocate' ? 'devil_advocate' : 'default';

        $result = ClaudeService::fromSettings()->copilotChat(
            $review,
            Review::pico($review),
            Review::metrics($rid),
            $history,
            $message,
            $rid,
            $pageContext,
            $mode,
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

    /**
     * Move a protocol-document draft (created by the new-review form's
     * AI extractor) into the canonical storage/protocols/ location and
     * link it on the just-created review. No-op when the token is
     * missing or doesn't pass the user-id / shape check.
     */
    private function promoteProtocolDraft(int $reviewId, int $userId): void
    {
        $token    = (string) ($_POST['protocol_draft_token']    ?? '');
        $filename = (string) ($_POST['protocol_draft_filename'] ?? '');
        $mime     = (string) ($_POST['protocol_draft_mime']     ?? '');
        if ($token === '') {
            return;
        }
        // Strict format check + per-user prefix so a forged token can't
        // promote another user's draft into the new review.
        $expectedPrefix = $userId . '_';
        if (
            preg_match('/^[a-zA-Z0-9_-]+\.[a-z0-9]{1,5}$/i', $token) !== 1
            || !str_starts_with($token, $expectedPrefix)
        ) {
            return;
        }
        $draftDir = (string) config('paths.storage') . '/protocol_drafts/';
        $draftPath = $draftDir . $token;
        if (!is_file($draftPath) || !\SysRevAI\Services\FileStorage::isStoredIn($draftPath, 'protocol_drafts')) {
            return;
        }
        try {
            $bytes = (string) file_get_contents($draftPath);
            $ext = strtolower((string) pathinfo($token, PATHINFO_EXTENSION));
            $newPath = \SysRevAI\Services\FileStorage::storeBytes($bytes, $ext, 'protocols');
            if ($newPath !== null) {
                Review::saveProtocolFile($reviewId, $newPath, $filename, $mime);
                @unlink($draftPath);
            }
        } catch (\Throwable) {
            // Same fallback as the upload path — the review still gets
            // created with the AI-filled fields; the download just
            // won't appear until the user re-uploads.
        }
    }

    private function readInput(): array
    {
        $mode = in_array(($_POST['screening_mode'] ?? ''), Review::SCREENING_MODES, true)
            ? $_POST['screening_mode'] : 'double_blind';
        $kind = in_array(($_POST['kind'] ?? ''), Review::KINDS, true)
            ? (string) $_POST['kind']
            : 'systematic';

        // We store every framework's keys in the same pico_json so the
        // user can switch kind later without losing data they already
        // typed under the other framework's labels.
        return [
            'title'              => trim((string) ($_POST['title'] ?? '')),
            'question'           => trim((string) ($_POST['question'] ?? '')),
            'pico'               => [
                'population'    => trim((string) ($_POST['population'] ?? '')),
                'intervention'  => trim((string) ($_POST['intervention'] ?? '')),
                'comparison'    => trim((string) ($_POST['comparison'] ?? '')),
                'outcome'       => trim((string) ($_POST['outcome'] ?? '')),
                'study_design'  => trim((string) ($_POST['study_design'] ?? '')),
                'concept'       => trim((string) ($_POST['concept'] ?? '')),
                'context'       => trim((string) ($_POST['context'] ?? '')),
            ],
            'inclusion_criteria' => trim((string) ($_POST['inclusion_criteria'] ?? '')),
            'exclusion_criteria' => trim((string) ($_POST['exclusion_criteria'] ?? '')),
            'screening_mode'     => $mode,
            'pilot_count'        => max(1, (int) ($_POST['pilot_count'] ?? 50)),
            'reviewers_required' => max(1, min(5, (int) ($_POST['reviewers_required'] ?? 2))),
            'kind'               => $kind,
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
