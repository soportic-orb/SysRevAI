<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class ExtractionData
{
    public const STATUSES = ['draft', 'submitted', 'approved'];

    public static function find(int $referenceId, int $reviewerId, int $templateId): ?array
    {
        $table = Database::table('extraction_data');
        return Database::selectOne(
            "SELECT * FROM `{$table}`
             WHERE reference_id = ? AND reviewer_id = ? AND template_id = ? LIMIT 1",
            [$referenceId, $reviewerId, $templateId]
        );
    }

    public static function upsert(
        int $referenceId,
        int $reviewerId,
        int $templateId,
        array $data,
        string $status = 'draft'
    ): void {
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }
        $table = Database::table('extraction_data');
        Database::affecting(
            "INSERT INTO `{$table}` (reference_id, reviewer_id, template_id, data_json, status)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                data_json = VALUES(data_json),
                status = VALUES(status)",
            [$referenceId, $reviewerId, $templateId, json_encode($data, JSON_UNESCAPED_UNICODE), $status]
        );
    }

    public static function approve(int $id, int $approverId): void
    {
        $table = Database::table('extraction_data');
        Database::affecting(
            "UPDATE `{$table}` SET status = 'approved', approved_by = ? WHERE id = ?",
            [$approverId, $id]
        );
    }

    /** Every reviewer's submission for one reference (for the compare view). */
    public static function forReference(int $referenceId): array
    {
        $ext = Database::table('extraction_data');
        $users = Database::table('users');
        return Database::select(
            "SELECT e.*, u.name AS reviewer_name
             FROM `{$ext}` e
             JOIN `{$users}` u ON u.id = e.reviewer_id
             WHERE e.reference_id = ?
             ORDER BY e.id ASC",
            [$referenceId]
        );
    }

    /** Status per (reference, reviewer) for the review's ft_included references. */
    public static function statusMap(int $reviewId): array
    {
        $ext = Database::table('extraction_data');
        $refs = Database::table('references');
        $rows = Database::select(
            "SELECT e.reference_id, e.reviewer_id, e.status, e.id
             FROM `{$ext}` e
             JOIN `{$refs}` r ON r.id = e.reference_id
             WHERE r.review_id = ?",
            [$reviewId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['reference_id']][(int) $row['reviewer_id']] = $row;
        }
        return $map;
    }

    public static function decodeData(array $row): array
    {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        return is_array($data) ? $data : [];
    }
}
