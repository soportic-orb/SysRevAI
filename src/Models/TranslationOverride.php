<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * DB-backed string overrides for the i18n catalogue. Each row is a single
 * (locale, dot-notation key) pair that supersedes the platform default
 * read from /lang/{locale}.php. Wipe the row to restore the default.
 */
final class TranslationOverride
{
    /** @return array<string,string> key_path => value */
    public static function forLocale(string $locale): array
    {
        $table = Database::table('translation_overrides');
        try {
            $rows = Database::select(
                "SELECT key_path, value FROM `{$table}` WHERE locale = ?",
                [$locale]
            );
        } catch (\Throwable) {
            // Table missing in a partial install — treat as "no overrides".
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['key_path']] = (string) $row['value'];
        }
        return $out;
    }

    /** Distinct locale codes that currently carry at least one override. */
    public static function localesWithOverrides(): array
    {
        $table = Database::table('translation_overrides');
        try {
            $rows = Database::select(
                "SELECT DISTINCT locale FROM `{$table}` ORDER BY locale ASC"
            );
        } catch (\Throwable) {
            return [];
        }
        return array_map(static fn (array $r): string => (string) $r['locale'], $rows);
    }

    /** Insert or update one override; returns the affected row id. */
    public static function upsert(string $locale, string $keyPath, string $value, ?int $userId): void
    {
        $table = Database::table('translation_overrides');
        Database::affecting(
            "INSERT INTO `{$table}` (locale, key_path, value, updated_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)",
            [$locale, $keyPath, $value, $userId]
        );
    }

    /** Restore the platform default for one key in one locale. */
    public static function delete(string $locale, string $keyPath): int
    {
        $table = Database::table('translation_overrides');
        return Database::affecting(
            "DELETE FROM `{$table}` WHERE locale = ? AND key_path = ?",
            [$locale, $keyPath]
        );
    }

    /** Wipe every override for a locale — used when the admin removes a custom language. */
    public static function clearLocale(string $locale): int
    {
        $table = Database::table('translation_overrides');
        return Database::affecting(
            "DELETE FROM `{$table}` WHERE locale = ?",
            [$locale]
        );
    }
}
