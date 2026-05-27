<?php

declare(strict_types=1);

namespace SysRevAI\Core;

use SysRevAI\Models\User;

/**
 * Authentication: credential checking and the current-user session.
 */
final class Auth
{
    private const KEY = '_auth_user_id';
    private static ?array $cached = null;

    /**
     * Verify credentials and, on success, establish the session.
     * Returns true on success. Does not reveal whether the email exists.
     */
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if ($user === null) {
            // Equalise timing against the no-user case.
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$' . str_repeat('a', 22) . '$' . str_repeat('a', 43));
            return false;
        }

        if (($user['status'] ?? 'active') !== 'active' || (int) ($user['is_active'] ?? 1) !== 1) {
            return false;
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            User::registerFailedAttempt((int) $user['id']);
            return false;
        }

        // Transparently upgrade legacy hashes.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            $table = Database::table('users');
            Database::affecting(
                "UPDATE `{$table}` SET password_hash = ? WHERE id = ?",
                [password_hash($password, PASSWORD_ARGON2ID), (int) $user['id']]
            );
        }

        self::login($user);
        User::touchLogin((int) $user['id']);
        return true;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION[self::KEY] = (int) $user['id'];
        self::$cached = $user;
    }

    public static function logout(): void
    {
        self::$cached = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::KEY]);
    }

    public static function id(): ?int
    {
        return isset($_SESSION[self::KEY]) ? (int) $_SESSION[self::KEY] : null;
    }

    public static function user(): ?array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        return self::$cached = User::find($id);
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['role'] ?? '', $roles, true);
    }
}
