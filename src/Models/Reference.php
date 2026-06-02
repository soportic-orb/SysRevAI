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

    /**
     * Already in this review? Matches by stable dedup_key first, falling
     * back to DOI / PMID so cleaned-up titles still detect prior imports.
     */
    public static function existsInReview(int $reviewId, string $dedupKey, string $doi = '', string $pmid = ''): bool
    {
        $table = Database::table('references');
        if ($dedupKey !== '') {
            $row = Database::selectOne(
                "SELECT id FROM `{$table}` WHERE review_id = ? AND dedup_key = ? LIMIT 1",
                [$reviewId, $dedupKey]
            );
            if ($row !== null) {
                return true;
            }
        }
        if ($doi !== '') {
            $row = Database::selectOne(
                "SELECT id FROM `{$table}` WHERE review_id = ? AND doi = ? LIMIT 1",
                [$reviewId, $doi]
            );
            if ($row !== null) {
                return true;
            }
        }
        if ($pmid !== '') {
            $row = Database::selectOne(
                "SELECT id FROM `{$table}` WHERE review_id = ? AND pmid = ? LIMIT 1",
                [$reviewId, $pmid]
            );
            if ($row !== null) {
                return true;
            }
        }
        return false;
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $table = Database::table('references');
        Database::affecting("UPDATE `{$table}` SET status = ? WHERE id = ?", [$status, $id]);
    }

    /**
     * Remove a reference. Foreign keys cascade across the schema
     * (screening_decisions, full_text, ai_chat_history, summaries,
     * risk_of_bias, retrieval_queue, duplicates, …), so deleting the
     * row here cleans everything up. The on-disk PDF, if any, is the
     * one exception — the caller is responsible for unlinking it.
     */
    public static function delete(int $id): void
    {
        $table = Database::table('references');
        Database::affecting("DELETE FROM `{$table}` WHERE id = ?", [$id]);
    }
}
