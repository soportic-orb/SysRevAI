<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Imported references. The physical table is `{prefix}references`.
 */
final class Reference
{
    public const STATUSES = [
        'imported', 'duplicate', 'ta_screening', 'ta_included', 'ta_excluded',
        'ft_screening', 'ft_included', 'ft_excluded', 'extracted',
    ];

    public static function create(int $reviewId, array $ref, ?string $sourceFile, string $dedupKey): int
    {
        $table = Database::table('references');
        return Database::insert(
            "INSERT INTO `{$table}`
                (review_id, title, authors_json, year, journal, abstract, doi, pmid, url,
                 keywords_json, source_file, dedup_key, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'imported')",
            [
                $reviewId,
                $ref['title'] ?? '',
                json_encode($ref['authors'] ?? [], JSON_UNESCAPED_UNICODE),
                $ref['year'] ?? null,
                $ref['journal'] ?? '',
                $ref['abstract'] ?? '',
                ($ref['doi'] ?? '') ?: null,
                ($ref['pmid'] ?? '') ?: null,
                ($ref['url'] ?? '') ?: null,
                json_encode($ref['keywords'] ?? [], JSON_UNESCAPED_UNICODE),
                $sourceFile,
                $dedupKey ?: null,
            ]
        );
    }

    /** Minimal fields for the dedup pass (non-duplicates only). */
    public static function forDedup(int $reviewId): array
    {
        $table = Database::table('references');
        return Database::select(
            "SELECT id, title, doi, pmid, dedup_key, year
             FROM `{$table}` WHERE review_id = ? AND status <> 'duplicate' ORDER BY id ASC",
            [$reviewId]
        );
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('references');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    /**
     * Paginated list for a review with optional status filter and search.
     * @return array{rows:array,total:int}
     */
    public static function forReview(int $reviewId, string $status = '', string $search = '', int $page = 1, int $perPage = 25): array
    {
        $table = Database::table('references');
        $where = "review_id = ?";
        $params = [$reviewId];
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $where .= " AND (title LIKE ? OR abstract LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $countRow = Database::selectOne("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where}", $params);
        $total = (int) ($countRow['c'] ?? 0);

        $perPage = max(1, min($perPage, 100));
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::select(
            "SELECT id, title, authors_json, year, journal, doi, pmid, status
             FROM `{$table}` WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $table = Database::table('references');
        Database::affecting("UPDATE `{$table}` SET status = ? WHERE id = ?", [$status, $id]);
    }
}
