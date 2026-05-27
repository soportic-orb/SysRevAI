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
}
