<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Services\BiblioSearch\BiblioSearchService;
use SysRevAI\Services\DeduplicationService;

/**
 * Cross-review reference search with two modes:
 *
 *   - "local" (default): probes the user's own references via the
 *     FULLTEXT index on title/abstract plus LIKE on the identifier
 *     columns.
 *   - "external": fans the query out to CrossRef, OpenAlex and Europe PMC
 *     via BiblioSearchService and offers per-row / bulk import into one
 *     of the user's open reviews.
 */
final class SearchController
{
    private const VALID_MODES = ['local', 'external'];

    public function index(): void
    {
        $q    = trim((string) ($_GET['q'] ?? ''));
        $mode = (string) ($_GET['mode'] ?? 'local');
        if (!in_array($mode, self::VALID_MODES, true)) {
            $mode = 'local';
        }

        $results       = [];
        $externalMeta  = [];
        $externalError = null;

        if ($q !== '') {
            try {
                if ($mode === 'external') {
                    $r = (new BiblioSearchService())->search($q);
                    $results      = $r['references'];
                    $externalMeta = $r['sources'];
                } else {
                    $results = $this->searchLocal($q, (int) Auth::id());
                }
            } catch (\Throwable $e) {
                $results = [];
                if ($mode === 'external') {
                    $externalError = $e->getMessage();
                }
            }
        }

        $openReviews = $mode === 'external'
            ? array_values(array_filter(
                Review::forUser((int) Auth::id(), 'active'),
                fn (array $rv): bool => Review::userCanAccess((int) $rv['id'], (int) Auth::id())
            ))
            : [];

        echo View::render('search/index', [
            'q'             => $q,
            'mode'          => $mode,
            'results'       => $results,
            'externalMeta'  => $externalMeta,
            'externalError' => $externalError,
            'openReviews'   => $openReviews,
        ]);
    }

    /**
     * POST /search/import — accept either one (`single`) reference or a
     * list of selected ones (`selected[]`), validate the destination
     * review, then persist via the same path as the file-based importer
     * so dedup keys and activity logs stay consistent.
     */
    public function importToReview(): void
    {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId <= 0) {
            Session::flash('error', __('search.import_no_review'));
            redirect($this->backHref());
        }

        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            return;
        }

        $payload = [];
        if (isset($_POST['single']) && is_string($_POST['single']) && $_POST['single'] !== '') {
            $payload = [$_POST['single']];
        } elseif (isset($_POST['selected']) && is_array($_POST['selected'])) {
            $payload = array_values(array_filter($_POST['selected'], 'is_string'));
        }

        if ($payload === []) {
            Session::flash('error', __('search.import_no_selection'));
            redirect($this->backHref());
        }

        $imported = 0;
        $skipped  = 0;
        foreach ($payload as $json) {
            $ref = json_decode($json, true);
            if (!is_array($ref) || trim((string) ($ref['title'] ?? '')) === '') {
                $skipped++;
                continue;
            }
            $ref = $this->sanitise($ref);
            try {
                Reference::create($reviewId, $ref, 'biblio-search', DeduplicationService::dedupKey($ref));
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        if ($imported > 0) {
            $dedup = DeduplicationService::run($reviewId);
            ActivityLog::record('references.imported', [
                'format'   => 'biblio_search',
                'imported' => $imported,
                'skipped'  => $skipped,
                'duplicates' => $dedup['exact'] ?? 0,
            ], $reviewId);
            Session::flash('success', __('search.import_done', $imported, $skipped));
        } else {
            Session::flash('error', __('search.import_failed'));
        }

        redirect($this->backHref());
    }

    /* ─── Helpers ──────────────────────────────────────────────────── */

    private function backHref(): string
    {
        $q    = trim((string) ($_POST['back_q'] ?? ''));
        $mode = (string) ($_POST['back_mode'] ?? 'external');
        $qs   = http_build_query(['q' => $q, 'mode' => $mode]);
        return '/search?' . $qs;
    }

    /**
     * Whitelist the fields we accept from a posted reference. The form
     * payload comes from the browser, so we treat it as untrusted: cap
     * sizes, normalise arrays, and silently drop everything else.
     *
     * @param array<string,mixed> $ref
     * @return array<string,mixed>
     */
    private function sanitise(array $ref): array
    {
        $clip = static fn (string $s, int $max): string =>
            mb_substr(trim($s), 0, $max);

        $authors = [];
        foreach ((array) ($ref['authors'] ?? []) as $a) {
            $a = $clip((string) $a, 255);
            if ($a !== '') {
                $authors[] = $a;
            }
            if (count($authors) >= 50) {
                break;
            }
        }
        $keywords = [];
        foreach ((array) ($ref['keywords'] ?? []) as $k) {
            $k = $clip((string) $k, 100);
            if ($k !== '') {
                $keywords[] = $k;
            }
            if (count($keywords) >= 30) {
                break;
            }
        }

        return [
            'title'    => $clip((string) ($ref['title']    ?? ''), 1024),
            'authors'  => $authors,
            'year'     => isset($ref['year']) && is_numeric($ref['year']) ? (int) $ref['year'] : null,
            'journal'  => $clip((string) ($ref['journal']  ?? ''), 255),
            'abstract' => $clip((string) ($ref['abstract'] ?? ''), 32_000),
            'doi'      => $clip((string) ($ref['doi']      ?? ''), 255),
            'pmid'     => $clip((string) ($ref['pmid']     ?? ''), 32),
            'url'      => $clip((string) ($ref['url']      ?? ''), 1024),
            'keywords' => $keywords,
        ];
    }

    /** @return array<int,array> */
    private function searchLocal(string $q, int $userId): array
    {
        $refs    = Database::table('references');
        $reviews = Database::table('reviews');
        $members = Database::table('review_users');

        $idQuery = $this->stripDoiPrefix($q);

        $useFt  = mb_strlen($q) >= 4;
        $like   = '%' . $q . '%';
        $idLike = '%' . $idQuery . '%';

        if ($useFt) {
            $score  = 'MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $where  = '( MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)
                         OR r.title LIKE ? OR r.abstract LIKE ?
                         OR r.doi LIKE ? OR r.pmid LIKE ? )';
            $params = [$q, $userId, $q, $like, $like, $idLike, $like, $userId, $userId];
        } else {
            $score  = '1';
            $where  = '(r.title LIKE ? OR r.abstract LIKE ? OR r.doi LIKE ? OR r.pmid LIKE ?)';
            $params = [$userId, $like, $like, $idLike, $like, $userId, $userId];
        }

        $sql = "SELECT r.id, r.review_id, r.title, r.year, r.journal, r.status,
                       r.authors_json, rv.title AS review_title, ({$score}) AS score
                FROM `{$refs}` r
                JOIN `{$reviews}` rv ON rv.id = r.review_id
                LEFT JOIN `{$members}` ru ON ru.review_id = rv.id AND ru.user_id = ? AND ru.removed_at IS NULL
                WHERE {$where} AND (rv.owner_id = ? OR ru.user_id = ?)
                GROUP BY r.id
                ORDER BY score DESC, r.id DESC
                LIMIT 50";

        return Database::select($sql, $params);
    }

    private function stripDoiPrefix(string $q): string
    {
        $q = trim($q);
        $patterns = [
            '#^https?://(?:dx\.)?doi\.org/#i',
            '#^doi\s*:\s*#i',
        ];
        foreach ($patterns as $p) {
            $q = (string) preg_replace($p, '', $q, 1);
        }
        return trim($q);
    }
}
