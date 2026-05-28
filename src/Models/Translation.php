<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Cached translations keyed by SHA-256(source) + (source_lang, target_lang).
 */
final class Translation
{
    public static function hash(string $source): string
    {
        return hash('sha256', $source);
    }

    public static function find(string $source, string $sourceLang, string $targetLang): ?string
    {
        $table = Database::table('translations');
        $row = Database::selectOne(
            "SELECT translated_text FROM `{$table}`
             WHERE source_hash = ? AND source_lang = ? AND target_lang = ? LIMIT 1",
            [self::hash($source), $sourceLang, $targetLang]
        );
        return $row !== null ? (string) $row['translated_text'] : null;
    }

    public static function store(string $source, string $sourceLang, string $targetLang, string $translated): void
    {
        $table = Database::table('translations');
        Database::affecting(
            "INSERT INTO `{$table}` (source_hash, source_lang, target_lang, translated_text)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)",
            [self::hash($source), $sourceLang, $targetLang, $translated]
        );
    }

    public static function purgeOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $table = Database::table('translations');
        return Database::affecting(
            "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
    }
}
