<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Cached AI-generated critical report for one article. Stored as a single
 * JSON blob so the view layer can evolve without schema churn: the five
 * rubric axes (methodology / clarity / novelty / evidence / limitations),
 * the executive summary, the devil's-advocate counter-argument, the
 * overall verdict and the ordered section-by-section recommendations all
 * sit inside `data_json`.
 *
 * Inspired by the multi-perspective critical-review pattern from
 * imbad0202/academic-research-skills (CC-BY-NC 4.0).
 */
final class ArticleCriticalReport
{
    public const AXES = ['methodology', 'clarity', 'novelty', 'evidence', 'limitations'];

    public static function find(int $articleId): ?array
    {
        $table = Database::table('article_critical_reports');
        try {
            $row = Database::selectOne(
                "SELECT * FROM `{$table}` WHERE article_id = ? LIMIT 1",
                [$articleId]
            );
        } catch (\Throwable) {
            return null;
        }
        return $row;
    }

    /** @return array<string,mixed>|null Decoded report payload. */
    public static function decode(array $row): ?array
    {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        return is_array($data) ? $data : null;
    }

    /** Upsert: same article can be re-evaluated, latest wins. */
    public static function save(int $articleId, array $data, ?string $model, ?int $userId): void
    {
        $table = Database::table('article_critical_reports');
        $json  = json_encode($data, JSON_UNESCAPED_UNICODE);
        Database::affecting(
            "INSERT INTO `{$table}` (article_id, data_json, model, generated_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                data_json    = VALUES(data_json),
                model        = VALUES(model),
                generated_by = VALUES(generated_by)",
            [$articleId, $json, $model, $userId]
        );
    }

    public static function delete(int $articleId): void
    {
        $table = Database::table('article_critical_reports');
        try {
            Database::affecting("DELETE FROM `{$table}` WHERE article_id = ?", [$articleId]);
        } catch (\Throwable) {
            // Table missing in a partial install — nothing to cascade.
        }
    }
}
