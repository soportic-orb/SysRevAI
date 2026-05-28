<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\ReferenceFullText;
use SysRevAI\Models\Review;
use SysRevAI\Services\FileStorage;
use SysRevAI\Services\PdfService;

final class PdfController
{
    public function upload(string $id, string $refId): void
    {
        [$review, $reference] = $this->loadOrDeny((int) $id, (int) $refId);
        $rid = (int) $id;
        $referenceId = (int) $reference['id'];

        $validation = FileStorage::validatePdfUpload($_FILES['pdf'] ?? []);
        if (!$validation['ok']) {
            Session::flash('error', __('fulltext.upload_' . $validation['error']));
            redirect('/reviews/' . $rid . '/full-text');
        }

        // Replace any existing PDF for this reference.
        $existing = ReferenceFullText::find($referenceId);
        if ($existing !== null && !empty($existing['pdf_path'])) {
            FileStorage::delete((string) $existing['pdf_path']);
        }

        $stored = FileStorage::storePdf($validation['tmp']);
        if ($stored === null) {
            Session::flash('error', __('fulltext.upload_failed'));
            redirect('/reviews/' . $rid . '/full-text');
        }

        $extracted = PdfService::extract($stored);
        ReferenceFullText::save(
            $referenceId,
            $stored,
            $validation['name'],
            $extracted['text'],
            $extracted['pages'],
            $validation['size'],
            (int) Auth::id()
        );
        ActivityLog::record('pdf.uploaded', ['reference_id' => $referenceId, 'pages' => $extracted['pages']], $rid);
        Session::flash('success', __('fulltext.upload_ok'));
        redirect('/reviews/' . $rid . '/full-text');
    }

    public function serve(string $id, string $refId): void
    {
        [, $reference] = $this->loadOrDeny((int) $id, (int) $refId);
        $ft = ReferenceFullText::find((int) $reference['id']);
        if ($ft === null || empty($ft['pdf_path']) || !FileStorage::isStoredPdf((string) $ft['pdf_path']) || !is_file($ft['pdf_path'])) {
            http_response_code(404);
            return;
        }

        $path = (string) $ft['pdf_path'];
        $name = (string) ($ft['original_filename'] ?? 'reference.pdf');
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    /** @return array{0:array,1:array} [review, reference] */
    private function loadOrDeny(int $reviewId, int $refId): array
    {
        $review = Review::find($reviewId);
        $reference = Reference::find($refId);
        if ($review === null || $reference === null
            || (int) $reference['review_id'] !== $reviewId
            || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            exit;
        }
        return [$review, $reference];
    }
}
