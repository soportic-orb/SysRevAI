<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\View;
use SysRevAI\Models\Duplicate;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;

final class ReferencesController
{
    public function index(string $id): void
    {
        $review = $this->memberOrDeny((int) $id);
        $rid = (int) $id;

        $status = (string) ($_GET['status'] ?? '');
        $search = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = Reference::forReview($rid, $status, $search, $page);

        echo View::render('references/index', [
            'review'        => $review,
            'rows'          => $result['rows'],
            'total'         => $result['total'],
            'page'          => $page,
            'perPage'       => 25,
            'status'        => $status,
            'search'        => $search,
            'statuses'      => Reference::STATUSES,
            'metrics'       => Review::metrics($rid),
            'pendingDups'   => Duplicate::pendingCount($rid),
            'ftStatus'      => \SysRevAI\Models\ReferenceFullTextStatus::mapForReview($rid),
            'ftInFlight'    => \SysRevAI\Models\RetrievalQueue::inFlightForReview($rid),
            'ftEnabled'     => (bool) (setting('fulltext.enabled') ?? false),
        ]);
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
