<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;
use SysRevAI\Core\View;

/**
 * Cross-review reference search, scoped to the user's accessible reviews.
 * Uses the FULLTEXT index on references(title, abstract) (and on the
 * extracted text when available) with a LIKE fallback for short queries.
 */
final class SearchController
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $results = [];
        if ($q !== '') {
            try {
                $results = $this->search($q, (int) Auth::id());
            } catch (\Throwable $e) {
                $results = [];
            }
        }
        echo View::render('search/index', [
            'q'       => $q,
            'results' => $results,
        ]);
    }

    /** @return array<int,array> */
    private function search(string $q, int $userId): array
    {
        $refs    = Database::table('references');
        $reviews = Database::table('reviews');
        $members = Database::table('review_users');

        // DOIs are often pasted with a `https://doi.org/` or `doi:` prefix —
        // strip them so the LIKE match hits the stored identifier directly.
        $idQuery = $this->stripDoiPrefix($q);

        // Free-text queries get FULLTEXT relevance ranking against title /
        // abstract; identifiers and short queries fall through to LIKE.
        // The FT index only covers (title, abstract), so we ALWAYS OR in a
        // LIKE probe against doi/pmid even on long queries — otherwise a
        // pasted DOI like "10.1186/s12879-021-06525-6" (which never appears
        // in the abstract) would silently return no results.
        $useFt = mb_strlen($q) >= 4;
        $like  = '%' . $q . '%';
        $idLike = '%' . $idQuery . '%';

        if ($useFt) {
            $score  = 'MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $where  = '( MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)
                         OR r.title LIKE ? OR r.abstract LIKE ?
                         OR r.doi LIKE ? OR r.pmid LIKE ? )';
            // Order: SELECT score, JOIN user, WHERE match, WHERE title, WHERE abstract,
            //        WHERE doi, WHERE pmid, WHERE owner, WHERE member.
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

    /**
     * Normalise pasted DOI input — users often include a resolver prefix
     * (`https://doi.org/`, `http://dx.doi.org/`, `doi:`) which won't appear
     * in the stored identifier.
     */
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
