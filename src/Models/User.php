<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * User data access (PDO, prepared statements).
 */
final class User
{
    public static function findByEmail(string $email): ?array
    {
        $table = Database::table('users');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE email = ? LIMIT 1",
            [$email]
        );
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('users');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE id = ? LIMIT 1",
            [$id]
        );
    }

    public static function touchLogin(int $id): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET last_login_at = NOW(), failed_login_attempts = 0 WHERE id = ?",
            [$id]
        );
    }

    public static function registerFailedAttempt(int $id): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?",
            [$id]
        );
    }

    public static function count(): int
    {
        $table = Database::table('users');
        $row = Database::selectOne("SELECT COUNT(*) AS c FROM `{$table}`");
        return (int) ($row['c'] ?? 0);
    }

    /** @return array<int,array> */
    public static function all(string $search = ''): array
    {
        $table = Database::table('users');
        if ($search !== '') {
            $like = '%' . $search . '%';
            return Database::select(
                "SELECT id,name,email,role,status,is_active,last_login_at,created_at
                 FROM `{$table}` WHERE name LIKE ? OR email LIKE ? ORDER BY id DESC",
                [$like, $like]
            );
        }
        return Database::select(
            "SELECT id,name,email,role,status,is_active,last_login_at,created_at
             FROM `{$table}` ORDER BY id DESC"
        );
    }

    public static function create(array $data): int
    {
        $table = Database::table('users');
        // legal_accepted_at: when the caller passes true we stamp the row
        // with NOW(); when missing we leave it NULL so the caller can decide
        // (some internal flows, e.g. coordinator-created reviewers, may not
        // have an explicit consent step yet).
        $stampLegal = !empty($data['legal_accepted']);
        return Database::insert(
            "INSERT INTO `{$table}`
                (name,email,password_hash,role,status,locale,is_active,email_verified_at,legal_accepted_at)
             VALUES (?,?,?,?,?,?,?, NOW(), " . ($stampLegal ? 'NOW()' : 'NULL') . ")",
            [
                $data['name'],
                $data['email'],
                $data['password_hash'],
                $data['role'] ?? 'reviewer',
                $data['status'] ?? 'active',
                $data['locale'] ?? 'ca',
                (int) ($data['is_active'] ?? 1),
            ]
        );
    }

    public static function updateAccount(int $id, string $role, string $status, bool $isActive): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET role = ?, status = ?, is_active = ? WHERE id = ?",
            [$role, $status, $isActive ? 1 : 0, $id]
        );
    }

    public static function delete(int $id): void
    {
        $table = Database::table('users');
        Database::affecting("DELETE FROM `{$table}` WHERE id = ?", [$id]);
    }

    public static function updateNotificationPreferences(int $id, array $prefs): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET notification_preferences = ? WHERE id = ?",
            [json_encode($prefs, JSON_UNESCAPED_UNICODE), $id]
        );
    }

    /** Update the editable profile fields (name, email, locale). */
    public static function updateProfile(int $id, string $name, string $email, string $locale): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET name = ?, email = ?, locale = ? WHERE id = ?",
            [$name, $email, $locale, $id]
        );
    }

    public static function updatePassword(int $id, string $newHash): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET password_hash = ? WHERE id = ?",
            [$newHash, $id]
        );
    }

    /** Persist the user's avatar path (relative to public/uploads). */
    public static function setAvatarPath(int $id, ?string $relPath): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET avatar_path = ? WHERE id = ?",
            [$relPath, $id]
        );
    }

    /** Store an encrypted TOTP secret (already encrypted by the caller). */
    public static function setTwoFactorSecret(int $id, ?string $encryptedSecret): void
    {
        $table = Database::table('users');
        Database::affecting(
            "UPDATE `{$table}` SET two_factor_secret = ? WHERE id = ?",
            [$encryptedSecret, $id]
        );
    }

    /**
     * Email addresses of every active owner / admin. Used by
     * MailService::notifyAdmins() to fan operational notifications out
     * (new user registered, pending validation, …).
     *
     * @return list<string>
     */
    public static function adminEmails(): array
    {
        $table = Database::table('users');
        $rows = Database::select(
            "SELECT email FROM `{$table}`
              WHERE role IN ('owner', 'admin')
                AND is_active = 1
                AND status = 'active'
                AND email <> ''
              ORDER BY role = 'owner' DESC, id ASC"
        );
        $out = [];
        foreach ($rows as $r) {
            $email = trim((string) ($r['email'] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }
        return array_values(array_unique($out));
    }

    public static function countOwners(): int
    {
        $table = Database::table('users');
        $row = Database::selectOne("SELECT COUNT(*) AS c FROM `{$table}` WHERE role = 'owner'");
        return (int) ($row['c'] ?? 0);
    }
}
