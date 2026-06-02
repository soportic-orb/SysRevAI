<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Review invitations: a single-use token sent to an email, valid for 7 days.
 */
final class Invitation
{
    public static function create(int $reviewId, string $email, string $role, int $invitedBy): string
    {
        $token = bin2hex(random_bytes(32));
        $table = Database::table('invitations');
        Database::insert(
            "INSERT INTO `{$table}` (review_id, email, role, token, invited_by, expires_at)
             VALUES (?,?,?,?,?, DATE_ADD(NOW(), INTERVAL 7 DAY))",
            [$reviewId, strtolower($email), $role, $token, $invitedBy]
        );
        return $token;
    }

    public static function findByToken(string $token): ?array
    {
        $table = Database::table('invitations');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE token = ? LIMIT 1",
            [$token]
        );
    }

    public static function forReview(int $reviewId): array
    {
        $table = Database::table('invitations');
        return Database::select(
            "SELECT * FROM `{$table}` WHERE review_id = ? AND accepted_at IS NULL ORDER BY id DESC",
            [$reviewId]
        );
    }

    public static function isValid(array $invitation): bool
    {
        return $invitation['accepted_at'] === null
            && ($invitation['expires_at'] === null || strtotime((string) $invitation['expires_at']) >= time());
    }

    public static function markAccepted(int $id): void
    {
        $table = Database::table('invitations');
        Database::affecting("UPDATE `{$table}` SET accepted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function revoke(int $id, int $reviewId): void
    {
        $table = Database::table('invitations');
        Database::affecting("DELETE FROM `{$table}` WHERE id = ? AND review_id = ?", [$id, $reviewId]);
    }

    /** Absolute URL the invitee opens to accept the invitation. */
    public static function inviteUrl(string $token): string
    {
        return base_url('/invite/' . $token);
    }
}
