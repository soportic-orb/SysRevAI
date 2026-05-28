<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Discussion threads on a review (and, once screening lands, on a reference).
 */
final class Comment
{
    public static function create(
        int $reviewId,
        int $userId,
        string $content,
        ?int $referenceId = null,
        ?int $parentId = null,
        array $mentions = []
    ): int {
        $table = Database::table('comments');
        return Database::insert(
            "INSERT INTO `{$table}` (review_id, reference_id, user_id, parent_id, content, mentions_json)
             VALUES (?,?,?,?,?,?)",
            [
                $reviewId,
                $referenceId,
                $userId,
                $parentId,
                $content,
                $mentions === [] ? null : json_encode(array_values($mentions)),
            ]
        );
    }

    /** Review-board comments (reference_id IS NULL) with author info. */
    public static function forReview(int $reviewId): array
    {
        $comments = Database::table('comments');
        $users = Database::table('users');
        return Database::select(
            "SELECT c.*, u.name AS author_name, u.email AS author_email, u.avatar_path AS author_avatar
             FROM `{$comments}` c
             JOIN `{$users}` u ON u.id = c.user_id
             WHERE c.review_id = ? AND c.reference_id IS NULL AND c.deleted_at IS NULL
             ORDER BY c.id ASC",
            [$reviewId]
        );
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('comments');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    public static function softDelete(int $id): void
    {
        $table = Database::table('comments');
        Database::affecting("UPDATE `{$table}` SET deleted_at = NOW() WHERE id = ?", [$id]);
    }

    /**
     * Extract @mentions from text and resolve them to review members' user IDs.
     * Matches @"Full Name" or @firstword against member names (case-insensitive).
     *
     * @return array{0:int[],1:string[]} [userIds, names]
     */
    public static function resolveMentions(string $content, array $members): array
    {
        if (!preg_match_all('/@([\\p{L}][\\p{L}0-9_.\\-]*)/u', $content, $m)) {
            return [[], []];
        }
        $tokens = array_map('mb_strtolower', $m[1]);
        $ids = [];
        $names = [];
        foreach ($members as $member) {
            $first = mb_strtolower((string) preg_split('/\s+/', (string) $member['name'])[0]);
            if (in_array($first, $tokens, true)) {
                $ids[] = (int) $member['id'];
                $names[] = (string) $member['name'];
            }
        }
        return [array_values(array_unique($ids)), $names];
    }
}
