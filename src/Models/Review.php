<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Systematic review records and their protocol.
 */
final class Review
{
    public const SCREENING_MODES = ['double_blind', 'double_blind_third', 'single', 'pilot'];
    public const STATUSES = ['active', 'archived', 'completed'];

    /** Reviews the user owns or collaborates on. */
    public static function forUser(int $userId, ?string $status = 'active'): array
    {
        $reviews = Database::table('reviews');
        $members = Database::table('review_users');

        $sql = "SELECT r.* FROM `{$reviews}` r
                LEFT JOIN `{$members}` ru ON ru.review_id = r.id AND ru.user_id = ? AND ru.removed_at IS NULL
                WHERE (r.owner_id = ? OR ru.user_id = ?)";
        $params = [$userId, $userId, $userId];

        if ($status !== null) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        $sql .= " GROUP BY r.id ORDER BY r.updated_at DESC";

        return Database::select($sql, $params);
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('reviews');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    public static function userCanAccess(int $reviewId, int $userId): bool
    {
        $review = self::find($reviewId);
        if ($review === null) {
            return false;
        }
        if ((int) $review['owner_id'] === $userId) {
            return true;
        }
        return ReviewUser::isMember($reviewId, $userId);
    }

    public static function create(int $ownerId, array $data): int
    {
        $table = Database::table('reviews');
        return Database::insert(
            "INSERT INTO `{$table}`
                (owner_id, title, question, pico_json, inclusion_criteria, exclusion_criteria,
                 screening_mode, pilot_count, reviewers_required, status)
             VALUES (?,?,?,?,?,?,?,?,?, 'active')",
            [
                $ownerId,
                $data['title'],
                $data['question'] ?? null,
                json_encode($data['pico'] ?? [], JSON_UNESCAPED_UNICODE),
                $data['inclusion_criteria'] ?? null,
                $data['exclusion_criteria'] ?? null,
                $data['screening_mode'] ?? 'double_blind',
                (int) ($data['pilot_count'] ?? 50),
                (int) ($data['reviewers_required'] ?? 2),
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        $table = Database::table('reviews');
        Database::affecting(
            "UPDATE `{$table}` SET
                title = ?, question = ?, pico_json = ?, inclusion_criteria = ?,
                exclusion_criteria = ?, screening_mode = ?, pilot_count = ?, reviewers_required = ?
             WHERE id = ?",
            [
                $data['title'],
                $data['question'] ?? null,
                json_encode($data['pico'] ?? [], JSON_UNESCAPED_UNICODE),
                $data['inclusion_criteria'] ?? null,
                $data['exclusion_criteria'] ?? null,
                $data['screening_mode'] ?? 'double_blind',
                (int) ($data['pilot_count'] ?? 50),
                (int) ($data['reviewers_required'] ?? 2),
                $id,
            ]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $table = Database::table('reviews');
        Database::affecting("UPDATE `{$table}` SET status = ? WHERE id = ?", [$status, $id]);
    }

    /** Decode the PICO JSON to an array with the expected keys. */
    public static function pico(array $review): array
    {
        $pico = json_decode((string) ($review['pico_json'] ?? ''), true);
        $pico = is_array($pico) ? $pico : [];
        return array_merge(
            ['population' => '', 'intervention' => '', 'comparison' => '', 'outcome' => '', 'study_design' => ''],
            $pico
        );
    }

    /**
     * Reference counts by workflow status. Returns zeros until the references
     * feature (Phase 5) creates the table; degrades gracefully meanwhile.
     */
    public static function metrics(int $reviewId): array
    {
        $base = [
            'total' => 0, 'imported' => 0, 'duplicate' => 0,
            'ta_screening' => 0, 'ta_included' => 0, 'ta_excluded' => 0,
            'ft_screening' => 0, 'ft_included' => 0, 'ft_excluded' => 0,
            'extracted' => 0,
        ];
        try {
            $table = Database::table('references');
            $rows = Database::select(
                "SELECT status, COUNT(*) AS c FROM `{$table}` WHERE review_id = ? GROUP BY status",
                [$reviewId]
            );
            foreach ($rows as $row) {
                $base[$row['status']] = (int) $row['c'];
                $base['total'] += (int) $row['c'];
            }
        } catch (\Throwable) {
            // references table not present yet.
        }
        return $base;
    }
}
