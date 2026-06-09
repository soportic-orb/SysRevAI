<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Article membership (many-to-many). Mirrors ReviewUser.
 */
final class ArticleUser
{
    public static function add(int $articleId, int $userId, string $role = 'collaborator'): void
    {
        $table = Database::table('article_users');
        Database::affecting(
            "INSERT INTO `{$table}` (article_id, user_id, role)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE role = VALUES(role), removed_at = NULL",
            [$articleId, $userId, $role]
        );
    }

    public static function isMember(int $articleId, int $userId): bool
    {
        $table = Database::table('article_users');
        $row = Database::selectOne(
            "SELECT 1 FROM `{$table}` WHERE article_id = ? AND user_id = ? AND removed_at IS NULL LIMIT 1",
            [$articleId, $userId]
        );
        return $row !== null;
    }

    /** @return list<array<string,mixed>> Members + user details. */
    public static function forArticle(int $articleId): array
    {
        $au    = Database::table('article_users');
        $users = Database::table('users');
        return Database::select(
            "SELECT au.role, au.joined_at, u.id, u.name, u.email
             FROM `{$au}` au
             JOIN `{$users}` u ON u.id = au.user_id
             WHERE au.article_id = ? AND au.removed_at IS NULL
             ORDER BY au.joined_at ASC",
            [$articleId]
        );
    }

    public static function remove(int $articleId, int $userId): void
    {
        $table = Database::table('article_users');
        Database::affecting(
            "UPDATE `{$table}` SET removed_at = NOW() WHERE article_id = ? AND user_id = ?",
            [$articleId, $userId]
        );
    }
}
