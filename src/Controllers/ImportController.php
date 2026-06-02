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
        $imported = 0;
        foreach ($result['refs'] as $ref) {
            Reference::create($rid, $ref, $filename, DeduplicationService::dedupKey($ref));
            $imported++;
        }

        $dedup = $imported > 0 ? DeduplicationService::run($rid) : ['exact' => 0, 'fuzzy' => 0];

        ImportLog::record(
            $rid,
            (int) Auth::id(),
            $filename,
            $format,
            count($result['refs']),
            $imported,
            $dedup['exact'],
            $result['errors']
        );
        ActivityLog::record('references.imported', [
            'format' => $format, 'imported' => $imported, 'duplicates' => $dedup['exact'],
        ], $rid);

        Session::flash('success', __('import.result', $imported, $dedup['exact'], $dedup['fuzzy']));
        redirect('/reviews/' . $rid . '/references');
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
