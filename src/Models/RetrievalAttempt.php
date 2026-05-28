<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * One row per source consultation for a reference. Used for auditing and the
 * admin coverage report.
 */
final class RetrievalAttempt
{
    public static function record(int $referenceId, array $attempt): void
    {
        if ($referenceId <= 0) {
            return;
        }
        $table = Database::table('retrieval_attempts');
        Database::insert(
            "INSERT INTO `{$table}`
                (reference_id, source, status, http_status, response_time_ms,
                 pdf_found, pdf_url, license_type, version_type,
                 error_message, raw_response_excerpt)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $referenceId,
                (string) $attempt['source'],
                (string) $attempt['status'],
                $attempt['http_status'] ?? null,
                $attempt['response_time_ms'] ?? null,
                !empty($attempt['pdf_found']) ? 1 : 0,
                $attempt['pdf_url'] ?? null,
                $attempt['license_type'] ?? null,
                $attempt['version_type'] ?? null,
                $attempt['error_message'] ?? null,
                isset($attempt['raw_response_excerpt'])
                    ? mb_substr((string) $attempt['raw_response_excerpt'], 0, 500)
                    : null,
            ]
        );
    }

    public static function forReference(int $referenceId, int $limit = 50): array
    {
        $table = Database::table('retrieval_attempts');
        $limit = max(1, min($limit, 200));
        return Database::select(
            "SELECT * FROM `{$table}` WHERE reference_id = ? ORDER BY id DESC LIMIT {$limit}",
            [$referenceId]
        );
    }
}
