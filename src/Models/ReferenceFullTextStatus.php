<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Consolidated retrieval state per reference (the latest "best" outcome).
 */
final class ReferenceFullTextStatus
{
    public static function find(int $referenceId): ?array
    {
        $table = Database::table('reference_fulltext_status');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE reference_id = ? LIMIT 1",
            [$referenceId]
        );
    }

    /** Upsert the consolidated row after a chain run. */
    public static function upsert(
        int $referenceId,
        bool $hasFulltext,
        ?string $source,
        ?string $url,
        ?string $licenseType,
        ?string $versionType,
        int $retryAfterDays,
        int $attemptDelta = 1
    ): void {
        $table = Database::table('reference_fulltext_status');
        $nextRetry = $hasFulltext ? null : sprintf('DATE_ADD(NOW(), INTERVAL %d DAY)', max(1, $retryAfterDays));

        Database::affecting(
            "INSERT INTO `{$table}`
                (reference_id, has_fulltext, fulltext_source, fulltext_url,
                 license_type, version_type, last_attempt_at, next_retry_at, attempts_count)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), " . ($nextRetry ?? 'NULL') . ", ?)
             ON DUPLICATE KEY UPDATE
                has_fulltext    = VALUES(has_fulltext),
                fulltext_source = VALUES(fulltext_source),
                fulltext_url    = VALUES(fulltext_url),
                license_type    = VALUES(license_type),
                version_type    = VALUES(version_type),
                last_attempt_at = NOW(),
                next_retry_at   = " . ($nextRetry ?? 'NULL') . ",
                attempts_count  = attempts_count + VALUES(attempts_count)",
            [
                $referenceId,
                $hasFulltext ? 1 : 0,
                $source,
                $url,
                $licenseType,
                $versionType,
                max(0, $attemptDelta),
            ]
        );
    }

    public static function markDownloaded(int $referenceId, string $pdfPath): void
    {
        $table = Database::table('reference_fulltext_status');
        Database::affecting(
            "UPDATE `{$table}` SET pdf_downloaded = 1, pdf_local_path = ? WHERE reference_id = ?",
            [$pdfPath, $referenceId]
        );
    }

    /** Update both the PDF and XML local paths after the downloader runs. */
    public static function markStored(int $referenceId, ?string $pdfPath, ?string $xmlPath): void
    {
        $table = Database::table('reference_fulltext_status');
        Database::affecting(
            "UPDATE `{$table}` SET
                pdf_downloaded = CASE WHEN ? IS NULL THEN pdf_downloaded ELSE 1 END,
                pdf_local_path = COALESCE(?, pdf_local_path),
                xml_available  = CASE WHEN ? IS NULL THEN xml_available ELSE 1 END,
                xml_local_path = COALESCE(?, xml_local_path)
             WHERE reference_id = ?",
            [$pdfPath, $pdfPath, $xmlPath, $xmlPath, $referenceId]
        );
    }

    /** Status rows for every reference in a review, keyed by reference_id. */
    public static function mapForReview(int $reviewId): array
    {
        $rfs  = Database::table('reference_fulltext_status');
        $refs = Database::table('references');
        $rows = Database::select(
            "SELECT s.* FROM `{$rfs}` s
             JOIN `{$refs}` r ON r.id = s.reference_id
             WHERE r.review_id = ?",
            [$reviewId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['reference_id']] = $row;
        }
        return $map;
    }

    /** Aggregated coverage numbers for a review (or globally if 0). */
    public static function coverage(int $reviewId = 0): array
    {
        $rfs = Database::table('reference_fulltext_status');
        $refs = Database::table('references');
        if ($reviewId > 0) {
            $row = Database::selectOne(
                "SELECT
                    (SELECT COUNT(*) FROM `{$refs}` WHERE review_id = ?) AS total,
                    COUNT(s.reference_id) AS attempted,
                    SUM(s.has_fulltext) AS with_text
                 FROM `{$rfs}` s JOIN `{$refs}` r ON r.id = s.reference_id
                 WHERE r.review_id = ?",
                [$reviewId, $reviewId]
            );
        } else {
            $row = Database::selectOne(
                "SELECT (SELECT COUNT(*) FROM `{$refs}`) AS total,
                        COUNT(*) AS attempted,
                        SUM(has_fulltext) AS with_text
                 FROM `{$rfs}`"
            );
        }
        return [
            'total'     => (int) ($row['total'] ?? 0),
            'attempted' => (int) ($row['attempted'] ?? 0),
            'with_text' => (int) ($row['with_text'] ?? 0),
        ];
    }
}
