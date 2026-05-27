<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Review membership (many-to-many). Full collaboration features (invitations,
 * assignment, blinding workflow) build on this in Phase 4.
 */
final class ReviewUser
{
    public static function add(
        int $reviewId,
        int $userId,
        string $role = 'reviewer',
        bool $isBlinded = true,
        bool $canResolve = false
    ): void {
        $table = Database::table('review_users');
        Database::affecting(
            "INSERT INTO `{$table}` (review_id, user_id, role, is_blinded, can_resolve_conflicts, joined_at)
             VALUES (?,?,?,?,?, NOW())
             ON DUPLICATE KEY UPDATE role = VALUES(role), removed_at = NULL",
            [$reviewId, $userId, $role, $isBlinded ? 1 : 0, $canResolve ? 1 : 0]
        );
    }

    public static function isMember(int $reviewId, int $userId): bool
    {
        $table = Database::table('review_users');
        $row = Database::selectOne(
            "SELECT 1 FROM `{$table}` WHERE review_id = ? AND user_id = ? AND removed_at IS NULL LIMIT 1",
            [$reviewId, $userId]
        );
        return $row !== null;
    }

    /** Members with their user details. */
    public static function forReview(int $reviewId): array
    {
        $ru = Database::table('review_users');
        $users = Database::table('users');
        return Database::select(
            "SELECT ru.role, ru.is_blinded, ru.can_resolve_conflicts, ru.joined_at,
                    u.id, u.name, u.email
             FROM `{$ru}` ru
             JOIN `{$users}` u ON u.id = ru.user_id
             WHERE ru.review_id = ? AND ru.removed_at IS NULL
             ORDER BY ru.joined_at ASC",
            [$reviewId]
        );
    }
}
