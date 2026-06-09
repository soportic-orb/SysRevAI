<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Token-based invitations to collaborate on one article. Mirrors the
 * review Invitation model but lives in its own table because the join
 * key is the article id, not the review id.
 */
final class ArticleInvitation
{
    public static function create(int $articleId, string $email, string $role, int $invitedBy): string
    {
        $token = bin2hex(random_bytes(32));
        $table = Database::table('article_invitations');
        Database::insert(
            "INSERT INTO `{$table}` (article_id, email, role, token, invited_by, expires_at)
             VALUES (?,?,?,?,?, DATE_ADD(NOW(), INTERVAL 7 DAY))",
            [$articleId, strtolower($email), $role, $token, $invitedBy]
        );
        return $token;
    }

    public static function findByToken(string $token): ?array
    {
        $table = Database::table('article_invitations');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE token = ? LIMIT 1",
            [$token]
        );
    }

    /** Pending (non-accepted) invitations for an article, newest first. */
    public static function forArticle(int $articleId): array
    {
        $table = Database::table('article_invitations');
        return Database::select(
            "SELECT * FROM `{$table}` WHERE article_id = ? AND accepted_at IS NULL ORDER BY id DESC",
            [$articleId]
        );
    }

    public static function isValid(array $invitation): bool
    {
        return $invitation['accepted_at'] === null
            && ($invitation['expires_at'] === null || strtotime((string) $invitation['expires_at']) >= time());
    }

    public static function markAccepted(int $id): void
    {
        $table = Database::table('article_invitations');
        Database::affecting("UPDATE `{$table}` SET accepted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function revoke(int $id, int $articleId): void
    {
        $table = Database::table('article_invitations');
        Database::affecting(
            "DELETE FROM `{$table}` WHERE id = ? AND article_id = ?",
            [$id, $articleId]
        );
    }

    public static function inviteUrl(string $token): string
    {
        return base_url('/articles/invite/' . $token);
    }
}
