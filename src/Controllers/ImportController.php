<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\ImportLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\DeduplicationService;
use SysRevAI\Services\ImportService;

final class ImportController
{
    private const MAX_BYTES = 20 * 1024 * 1024; // 20 MB for text reference files

    public function form(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        echo View::render('import/form', [
            'review'  => $review,
            'formats' => ImportService::FORMATS,
            'logs'    => ImportLog::forReview((int) $id, 10),
        ]);
    }

    public function process(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        [$content, $filename] = $this->readInput();
        if ($content === '') {
            Session::flash('error', __('import.no_input'));
            redirect('/reviews/' . $rid . '/import');
        }

        $content = $this->toUtf8($content);
        $format = (string) ($_POST['format'] ?? 'auto');
        if (!in_array($format, ImportService::FORMATS, true)) {
            $format = ImportService::detectFormat($filename, $content) ?? '';
        }
        if ($format === '') {
            Session::flash('error', __('import.unknown_format'));
            redirect('/reviews/' . $rid . '/import');
        }

        if ($format === 'freetext') {
            $ai = ClaudeService::fromSettings()->extractReferencesFromText($content, $rid);
            if (!$ai['ok']) {
                $msg = match ($ai['error'] ?? '') {
                    'no_api_key'       => __('import.ai_no_key'),
                    'feature_disabled' => __('import.ai_disabled'),
                    'budget_exceeded'  => __('import.ai_budget'),
                    default            => __('import.ai_failed', (string) ($ai['error'] ?? '')),
                };
                Session::flash('error', $msg);
                redirect('/reviews/' . $rid . '/import');
            }
            $result = ['refs' => $ai['refs'] ?? [], 'errors' => []];
        } else {
            $result = ImportService::parse($content, $format);
        }

        if (($result['refs'] ?? []) === []) {
            Session::flash('error', __('import.preview_no_refs'));
            redirect('/reviews/' . $rid . '/import');
        }

        // Stash the parsed refs and hand off to the preview step. Session
        // storage is intentional: the references never reach the database
        // until the reviewer reviews the list and clicks "Import".
        $_SESSION['import_preview'][$rid] = [
            'refs'     => $this->normaliseRefs($result['refs']),
            'filename' => $filename,
            'format'   => $format,
            'errors'   => $result['errors'] ?? [],
        ];

        redirect('/reviews/' . $rid . '/import/preview');
    }

    /**
     * Step 2 of the import flow — show the reviewer the parsed references
     * so they can untick anything they don't want before persisting. The
     * stashed preview state lives in the session under
     * `import_preview[{reviewId}]` and is dropped on confirm / discard.
     */
    public function preview(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $state = $_SESSION['import_preview'][$rid] ?? null;
        if (!is_array($state) || !is_array($state['refs'] ?? null) || $state['refs'] === []) {
            Session::flash('error', __('import.preview_no_refs'));
            redirect('/reviews/' . $rid . '/import');
        }

        echo View::render('import/preview', [
            'review'   => $review,
            'refs'     => $state['refs'],
            'filename' => (string) ($state['filename'] ?? ''),
            'format'   => (string) ($state['format'] ?? ''),
            'errors'   => (array) ($state['errors'] ?? []),
        ]);
    }

    /**
     * Step 3 — persist only the refs the reviewer ticked. Indices map
     * back into the stashed array so we never have to re-validate the
     * payload coming from the browser as a reference.
     */
    public function confirm(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $state = $_SESSION['import_preview'][$rid] ?? null;
        if (!is_array($state) || !is_array($state['refs'] ?? null)) {
            Session::flash('error', __('import.preview_expired'));
            redirect('/reviews/' . $rid . '/import');
        }

        $selected = (array) ($_POST['selected'] ?? []);
        $picked   = [];
        foreach ($selected as $idx) {
            $i = (int) $idx;
            if (isset($state['refs'][$i])) {
                $picked[] = $state['refs'][$i];
            }
        }

        if ($picked === []) {
            Session::flash('error', __('import.preview_select_at_least_one'));
            redirect('/reviews/' . $rid . '/import/preview');
        }

        $filename = (string) ($state['filename'] ?? 'imported');
        $format   = (string) ($state['format'] ?? 'unknown');

        // Bulk import: a single malformed row (oversized DOI, weird
        // encoding, mid-stream encoding fault) used to throw inside
        // Reference::create() and 500 the whole request — leaving the
        // user with "half imported" and no path to retry. We catch and
        // count per-row failures so the loop always finishes; the
        // discarded rows land in the activity log for diagnosis.
        // Long-running imports (hundreds of rows) also need more wall
        // time than the default 30s, particularly because the dedup pass
        // is O(n²) inside year buckets.
        @set_time_limit(180);
        $errorsPersist = (array) ($state['errors'] ?? []);
        $imported = 0;
        $failed   = 0;
        foreach ($picked as $i => $ref) {
            try {
                Reference::create($rid, $ref, $filename, DeduplicationService::dedupKey($ref));
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                $errorsPersist[] = [
                    'row'   => (int) $i + 1,
                    'title' => mb_substr((string) ($ref['title'] ?? ''), 0, 200),
                    'error' => $e->getMessage(),
                ];
            }
        }

        // The dedup pass is best-effort: a transient DB blip here must
        // not erase the rows we just persisted. Counts default to zero
        // so the import log and flash message still render coherently.
        $dedup = ['exact' => 0, 'fuzzy' => 0];
        if ($imported > 0) {
            try {
                $dedup = DeduplicationService::run($rid);
            } catch (\Throwable $e) {
                ActivityLog::record('references.dedup_failed', [
                    'review_id' => $rid,
                    'error'     => $e->getMessage(),
                ], $rid);
            }
        }

        ImportLog::record(
            $rid,
            (int) Auth::id(),
            $filename,
            $format,
            count($state['refs']),
            $imported,
            $dedup['exact'],
            $errorsPersist
        );
        ActivityLog::record('references.imported', [
            'format' => $format, 'imported' => $imported,
            'duplicates' => $dedup['exact'], 'failed' => $failed,
        ], $rid);

        unset($_SESSION['import_preview'][$rid]);

        if ($failed > 0) {
            Session::flash('success', __('import.result_with_failures', $imported, $dedup['exact'], $dedup['fuzzy'], $failed));
        } else {
            Session::flash('success', __('import.result', $imported, $dedup['exact'], $dedup['fuzzy']));
        }
        redirect('/reviews/' . $rid . '/references');
    }

    /** Drop the stashed preview without importing anything. */
    public function discardPreview(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;
        unset($_SESSION['import_preview'][$rid]);
        redirect('/reviews/' . $rid . '/import');
    }

    /**
     * Wipe the audit list for this review. Owner / admin only — non-owners
     * never see the button, but we re-check here so a forged POST can't
     * delete history out from under the owner.
     */
    public function clearLogs(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        if ((int) $review['owner_id'] !== (int) Auth::id()
            && !Auth::hasRole('owner', 'admin')) {
            Session::flash('error', __('import.clear_forbidden'));
            redirect('/reviews/' . $rid . '/import');
        }

        $deleted = ImportLog::clearForReview($rid);
        ActivityLog::record('imports.logs_cleared', ['deleted' => $deleted], $rid);
        Session::flash('success', __('import.clear_done', $deleted));
        redirect('/reviews/' . $rid . '/import');
    }

    /**
     * Coerce every parsed reference into the shape the rest of the
     * pipeline expects. Trims strings, drops empty author / keyword
     * entries, and caps the total so a hostile or accidental 10k-row
     * paste can't blow up the session blob.
     *
     * @param  array<int,mixed> $refs
     * @return array<int,array<string,mixed>>
     */
    private function normaliseRefs(array $refs): array
    {
        $out = [];
        foreach ($refs as $r) {
            if (!is_array($r)) {
                continue;
            }
            $title = trim((string) ($r['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $authors = [];
            foreach ((array) ($r['authors'] ?? []) as $a) {
                $a = trim((string) $a);
                if ($a !== '') {
                    $authors[] = $a;
                }
            }
            $keywords = [];
            foreach ((array) ($r['keywords'] ?? []) as $k) {
                $k = trim((string) $k);
                if ($k !== '') {
                    $keywords[] = $k;
                }
            }
            $year = $r['year'] ?? null;
            if (is_numeric($year)) {
                $year = (int) $year;
            } elseif (!is_int($year)) {
                $year = null;
            }
            $out[] = [
                'title'    => $title,
                'authors'  => $authors,
                'year'     => $year,
                'journal'  => trim((string) ($r['journal']  ?? '')),
                'abstract' => trim((string) ($r['abstract'] ?? '')),
                'doi'      => trim((string) ($r['doi']      ?? '')),
                'pmid'     => trim((string) ($r['pmid']     ?? '')),
                'url'      => trim((string) ($r['url']      ?? '')),
                'keywords' => $keywords,
            ];
            if (count($out) >= 5000) {
                break;
            }
        }
        return $out;
    }

    /** @return array{0:string,1:string} [content, filename] */
    private function readInput(): array
    {
        if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
            if ((int) ($_FILES['file']['size'] ?? 0) > self::MAX_BYTES) {
                Session::flash('error', __('import.too_large'));
                redirect('/reviews/' . (int) ($_POST['_rid'] ?? 0) . '/import');
            }
            $content = (string) file_get_contents($_FILES['file']['tmp_name']);
            return [$content, basename((string) $_FILES['file']['name'])];
        }
        $paste = trim((string) ($_POST['paste'] ?? ''));
        return [$paste, 'pasted-' . date('Ymd-His')];
    }

    private function toUtf8(string $content): string
    {
        $enc = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc !== false && $enc !== 'UTF-8') {
            $content = (string) mb_convert_encoding($content, 'UTF-8', $enc);
        }
        // Strip a UTF-8 BOM if present.
        return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
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
