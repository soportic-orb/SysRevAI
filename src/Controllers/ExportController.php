<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\Review;
use SysRevAI\Services\ExportService;

final class ExportController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $counts = ExportService::prismaCounts((int) $id);
        echo View::render('exports/index', [
            'review' => $review,
            'counts' => $counts,
            'svg'    => ExportService::prismaSvg($counts, (string) $review['title']),
        ]);
    }

    public function prisma(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $svg = ExportService::prismaSvg(ExportService::prismaCounts((int) $id), (string) $review['title']);
        $this->sendFile('image/svg+xml', $this->filename($review, 'prisma', 'svg'), $svg);
    }

    public function csv(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $name = $this->filename($review, 'references', 'csv');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        // BOM helps Excel detect UTF-8.
        echo "\xEF\xBB\xBF";
        ExportService::csv((int) $id);
    }

    public function excel(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $result = ExportService::excel((int) $id);
        if ($result['bytes'] === null) {
            Session::flash('admin_error', __('exports.' . $result['error']));
            redirect('/reviews/' . (int) $id . '/exports');
        }
        $this->sendFile(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $this->filename($review, 'references', 'xlsx'),
            (string) $result['bytes']
        );
    }

    public function word(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $result = ExportService::word((int) $id);
        if ($result['bytes'] === null) {
            Session::flash('admin_error', __('exports.' . $result['error']));
            redirect('/reviews/' . (int) $id . '/exports');
        }
        $this->sendFile(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $this->filename($review, 'review', 'docx'),
            (string) $result['bytes']
        );
    }

    public function revman(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $xml = ExportService::revman((int) $id);
        $this->sendFile('application/xml', $this->filename($review, 'revman', 'rm5'), $xml);
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

    private function filename(array $review, string $kind, string $ext): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $review['title']) ?? 'review';
        $slug = trim($slug, '_') ?: 'review';
        return $slug . '-' . $kind . '-' . date('Ymd') . '.' . $ext;
    }

    private function sendFile(string $mime, string $filename, string $bytes): void
    {
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($bytes));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
    }
}
