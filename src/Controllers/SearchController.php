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
        $useFt = mb_strlen($q) >= 4;

        if ($useFt) {
            $score  = "MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)";
            $where  = "MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)";
            // Placeholders in source order: SELECT score, JOIN user, WHERE match, WHERE owner, WHERE member.
            $params = [$q, $userId, $q, $userId, $userId];
        } else {
            $score  = "1";
            $like   = '%' . $q . '%';
            $where  = "(r.title LIKE ? OR r.abstract LIKE ? OR r.doi LIKE ? OR r.pmid LIKE ?)";
            // JOIN user, WHERE like x4, WHERE owner, WHERE member.
            $params = [$userId, $like, $like, $like, $like, $userId, $userId];
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
}
