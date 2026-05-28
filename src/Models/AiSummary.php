<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class AiSummary
{
    public const SECTIONS = ['background', 'methods', 'results', 'conclusions', 'relevance'];

    public static function find(int $referenceId, string $language): ?array
    {
        $table = Database::table('ai_summaries');
        return Database::selectOne(
            "SELECT * FROM `{$table}` WHERE reference_id = ? AND language = ? LIMIT 1",
            [$referenceId, $language]
        );
    }

    public static function save(int $referenceId, string $language, array $summary, string $model): void
    {
        $table = Database::table('ai_summaries');
        Database::affecting(
            "INSERT INTO `{$table}` (reference_id, language, summary_json, model_used)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                summary_json = VALUES(summary_json),
                model_used = VALUES(model_used),
                created_at = NOW()",
            [$referenceId, $language, json_encode($summary, JSON_UNESCAPED_UNICODE), $model]
        );
    }

    public static function decode(array $row): array
    {
        $data = json_decode((string) ($row['summary_json'] ?? ''), true);
        return is_array($data) ? $data : [];
    }

    /** Languages for which a summary exists for this reference. */
    public static function languages(int $referenceId): array
    {
        $table = Database::table('ai_summaries');
        return array_map(
            static fn ($r) => (string) $r['language'],
            Database::select("SELECT language FROM `{$table}` WHERE reference_id = ? ORDER BY language", [$referenceId])
        );
    }
}
