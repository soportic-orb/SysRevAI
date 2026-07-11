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
                (review_id, title, authors_json, year, publication_type, publication_date,
                 journal, abstract, doi, pmid, url,
                 keywords_json, source_file, dedup_key, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'imported')",
            [
                $reviewId,
                $ref['title'] ?? '',
                json_encode($ref['authors'] ?? [], JSON_UNESCAPED_UNICODE),
                $ref['year'] ?? null,
                ($ref['publication_type'] ?? '') ?: null,
                ($ref['publication_date'] ?? '') ?: null,
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
     * Paginated list for a review with optional status, search and
     * abstract-presence filters.
     *
     * @param string $abstract '' for any, 'with' for rows that carry an
     *                         abstract, 'without' for rows that don't.
     *                         The MEDIUMTEXT column itself is never
     *                         selected — we only expose a precomputed
     *                         `has_abstract` flag to the view.
     *
     * @return array{rows:array,total:int}
     */
    public static function forReview(
        int $reviewId,
        string $status = '',
        string $search = '',
        int $page = 1,
        int $perPage = 25,
        string $abstract = '',
        string $source = '',
        string $publicationType = '',
        string $dateFrom = '',
        string $dateTo = ''
    ): array {
        $table = Database::table('references');
        [$where, $params] = self::buildFilter($reviewId, $status, $search, $abstract, $source, $publicationType, $dateFrom, $dateTo);

        $countRow = Database::selectOne("SELECT COUNT(*) AS c FROM `{$table}` WHERE {$where}", $params);
        $total = (int) ($countRow['c'] ?? 0);

        // Per-page cap kept generous so the controller's whitelist
        // (50/100/200/500/1000) can actually reach the bigger sizes —
        // the previous 100 cap silently clamped 200/500/1000 down.
        $perPage = max(1, min($perPage, 1000));
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::select(
            "SELECT id, title, authors_json, year, publication_type, publication_date, journal, doi, pmid, status, source_file,
                    (abstract IS NOT NULL AND CHAR_LENGTH(abstract) > 0) AS has_abstract
             FROM `{$table}` WHERE {$where} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Distinct, non-empty source labels recorded on the review's
     * references. Used to populate the "Font" filter dropdown on the
     * references page so the user only sees options that actually
     * exist in this review.
     *
     * @return string[]
     */
    public static function distinctSources(int $reviewId): array
    {
        $table = Database::table('references');
        $rows = Database::select(
            "SELECT DISTINCT source_file
               FROM `{$table}`
              WHERE review_id = ?
                AND source_file IS NOT NULL
                AND CHAR_LENGTH(TRIM(source_file)) > 0
           ORDER BY source_file",
            [$reviewId]
        );
        return array_values(array_map(static fn ($r): string => (string) $r['source_file'], $rows));
    }

    /**
     * Distinct, non-empty publication-type labels recorded on the
     * review's references (populated at import time from the source
     * format's own record-type tag/attribute — see ImportService).
     * Used to populate the "Publication type" filter dropdown.
     *
     * @return string[]
     */
    public static function distinctPublicationTypes(int $reviewId): array
    {
        $table = Database::table('references');
        $rows = Database::select(
            "SELECT DISTINCT publication_type
               FROM `{$table}`
              WHERE review_id = ?
                AND publication_type IS NOT NULL
                AND CHAR_LENGTH(TRIM(publication_type)) > 0
           ORDER BY publication_type",
            [$reviewId]
        );
        return array_values(array_map(static fn ($r): string => (string) $r['publication_type'], $rows));
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
     * Every reference id matching the same filter as the paginated
     * index — used by the "select all across pages" bulk-delete flow.
     */
    public static function idsForReview(
        int $reviewId,
        string $status = '',
        string $search = '',
        string $abstract = '',
        string $source = '',
        string $publicationType = '',
        string $dateFrom = '',
        string $dateTo = ''
    ): array {
        $table = Database::table('references');
        [$where, $params] = self::buildFilter($reviewId, $status, $search, $abstract, $source, $publicationType, $dateFrom, $dateTo);
        $rows = Database::select("SELECT id FROM `{$table}` WHERE {$where}", $params);
        return array_map(static fn (array $r): int => (int) $r['id'], $rows);
    }

    /**
     * Common WHERE builder used by forReview() and idsForReview() so the
     * paginated table and the bulk-delete "filtered scope" share the same
     * predicate.
     *
     * $dateFrom/$dateTo are 'YYYY-MM-DD' strings (or '' for unbounded).
     * References are matched by day-precision `publication_date` when the
     * import gave one; references that only carry a plain `year` (most
     * legacy imports) fall back to a year-level match against the same
     * range so the date filter doesn't silently hide them.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function buildFilter(
        int $reviewId,
        string $status,
        string $search,
        string $abstract,
        string $source = '',
        string $publicationType = '',
        string $dateFrom = '',
        string $dateTo = ''
    ): array {
        $where  = 'review_id = ?';
        $params = [$reviewId];
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where .= ' AND (title LIKE ? OR abstract LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($abstract === 'with') {
            $where .= ' AND abstract IS NOT NULL AND CHAR_LENGTH(abstract) > 0';
        } elseif ($abstract === 'without') {
            $where .= ' AND (abstract IS NULL OR CHAR_LENGTH(abstract) = 0)';
        }
        if ($source !== '') {
            $where .= ' AND source_file = ?';
            $params[] = $source;
        }
        if ($publicationType !== '') {
            $where .= ' AND publication_type = ?';
            $params[] = $publicationType;
        }
        $dateFrom = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) ? $dateFrom : '';
        $dateTo   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) ? $dateTo : '';
        if ($dateFrom !== '' || $dateTo !== '') {
            $dateParams = [];
            $dateSql = 'publication_date IS NOT NULL';
            if ($dateFrom !== '') {
                $dateSql .= ' AND publication_date >= ?';
                $dateParams[] = $dateFrom;
            }
            if ($dateTo !== '') {
                $dateSql .= ' AND publication_date <= ?';
                $dateParams[] = $dateTo;
            }

            $yearParams = [];
            $yearSql = 'publication_date IS NULL AND year IS NOT NULL';
            if ($dateFrom !== '') {
                $yearSql .= ' AND year >= ?';
                $yearParams[] = (int) substr($dateFrom, 0, 4);
            }
            if ($dateTo !== '') {
                $yearSql .= ' AND year <= ?';
                $yearParams[] = (int) substr($dateTo, 0, 4);
            }

            $where .= " AND (({$dateSql}) OR ({$yearSql}))";
            $params = array_merge($params, $dateParams, $yearParams);
        }
        return [$where, $params];
    }

    /**
     * References currently flagged as duplicates by an earlier dedup
     * pass — listed on the Duplicates page so the user can wipe the
     * confirmed dupes in bulk.
     */
    public static function confirmedDuplicates(int $reviewId): array
    {
        $table = Database::table('references');
        return Database::select(
            "SELECT id, title, authors_json, year, journal, doi, pmid
             FROM `{$table}`
             WHERE review_id = ? AND status = 'duplicate'
             ORDER BY id DESC",
            [$reviewId]
        );
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
