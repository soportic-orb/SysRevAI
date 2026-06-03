<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Platform-level user invitations. Distinct from `Invitation`, which
 * adds an existing user to a review — this one creates a brand-new
 * account when accepted, with the role the admin pre-selected. Single
 * use, valid for 7 days.
 */
final class UserInvitation
{
    public static function create(string $email, string $role, int $invitedBy): string
    {
        $token = bin2hex(random_bytes(32));
        $table = Database::table('user_invitations');
        Database::insert(
            "INSERT INTO `{$table}` (email, role, token, invited_by, expires_at)
             VALUES (?,?,?,?, DATE_ADD(NOW(), INTERVAL 7 DAY))",
            [strtolower($email), $role, $token, $invitedBy]
        );
        return $token;
    }

    public static function findByToken(string $token): ?array
    {
        $table = Database::table('user_invitations');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE token = ? LIMIT 1",
            [$token]
        );
    }

    /** Pending (non-accepted) invitations, newest first. */
    public static function pending(): array
    {
        $table = Database::table('user_invitations');
        return Database::select(
            "SELECT * FROM `{$table}` WHERE accepted_at IS NULL ORDER BY id DESC"
        );
    }

    /**
     * The first still-valid invitation that matches the given email —
     * used by the manual-registration flow to lift the admin's pre-chosen
     * role onto the brand-new account and skip the pending-approval step.
     */
    public static function findValidForEmail(string $email): ?array
    {
        $table = Database::table('user_invitations');
        return Database::selectOne(
            "SELECT * FROM `{$table}`
              WHERE email = ?
                AND accepted_at IS NULL
                AND (expires_at IS NULL OR expires_at >= NOW())
              ORDER BY id ASC LIMIT 1",
            [strtolower($email)]
        );
    }

    public static function isValid(array $invitation): bool
    {
        return $invitation['accepted_at'] === null
            && ($invitation['expires_at'] === null || strtotime((string) $invitation['expires_at']) >= time());
    }

    public static function markAccepted(int $id): void
    {
        $table = Database::table('user_invitations');
        Database::affecting("UPDATE `{$table}` SET accepted_at = NOW() WHERE id = ?", [$id]);
    }

    public static function revoke(int $id): void
    {
        $table = Database::table('user_invitations');
        Database::affecting("DELETE FROM `{$table}` WHERE id = ?", [$id]);
    }

    /** Absolute URL the invitee opens to set their password and join. */
    public static function inviteUrl(string $token): string
    {
        return base_url('/user-invite/' . $token);
    }
}
