<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Review;
use SysRevAI\Services\ExportService;

/**
 * Manual editor for the PRISMA flow-diagram cells. Lives under the
 * Exports tab — users open it from a dedicated button next to the
 * existing PRISMA download.
 *
 * Each cell may be left blank to fall back to the platform's
 * computed value (sourced from references + import_logs + the
 * persistent duplicates_removed counter). A pinned cell is stored
 * in reviews.prisma_overrides (JSON map, mig. 039) and the export
 * layer overlays it on top of the computed figure.
 */
final class PrismaEditorController
{
    public function show(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $review['id'];
        echo View::render('exports/prisma_edit', [
            'review'    => $review,
            'computed'  => ExportService::prismaCounts($rid),
            'overrides' => Review::prismaOverrides($rid),
            'keys'      => Review::PRISMA_OVERRIDE_KEYS,
        ]);
    }

    public function save(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $review['id'];

        $overrides = [];
        foreach (Review::PRISMA_OVERRIDE_KEYS as $key) {
            // Blank input → clear the override (computed value shows
            // through). Negative or non-numeric input → ignored,
            // sanitised down at the model.
            $raw = $_POST[$key] ?? '';
            if ($raw === '' || $raw === null) {
                continue;
            }
            $overrides[$key] = $raw;
        }
        Review::savePrismaOverrides($rid, $overrides);
        ActivityLog::record('exports.prisma_overrides_saved', [
            'review_id' => $rid,
            'pinned'    => array_keys($overrides),
        ], $rid);
        Session::flash('success', __('exports.prisma_saved'));
        redirect('/reviews/' . $rid . '/exports/prisma/edit');
    }

    /** POST /…/prisma/reset — wipe every override so the diagram is fully computed again. */
    public function reset(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $review['id'];
        Review::savePrismaOverrides($rid, []);
        ActivityLog::record('exports.prisma_overrides_reset', ['review_id' => $rid], $rid);
        Session::flash('success', __('exports.prisma_reset'));
        redirect('/reviews/' . $rid . '/exports/prisma/edit');
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
