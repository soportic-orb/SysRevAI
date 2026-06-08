<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Cached AI-generated peer-review rubric for one reference. Stored as a
 * single JSON blob so the view layer can evolve without schema churn:
 * the five rubric axes (methodology / clarity / novelty / evidence /
 * limitations), the summary, the devil's-advocate counter-argument and
 * the overall verdict all sit inside `data_json`.
 */
final class ReferencePeerReview
{
    public const AXES = ['methodology', 'clarity', 'novelty', 'evidence', 'limitations'];

    public static function find(int $referenceId): ?array
    {
        $table = Database::table('reference_peer_reviews');
        try {
            $row = Database::selectOne(
                "SELECT * FROM `{$table}` WHERE reference_id = ? LIMIT 1",
                [$referenceId]
            );
        } catch (\Throwable) {
            return null;
        }
        return $row;
    }

    /** @return array<string,mixed>|null Decoded rubric payload. */
    public static function decode(array $row): ?array
    {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        return is_array($data) ? $data : null;
    }

    /** Upsert: same reference can be re-evaluated, latest wins. */
    public static function save(int $referenceId, array $data, ?string $model, ?int $userId): void
    {
        $table = Database::table('reference_peer_reviews');
        $json  = json_encode($data, JSON_UNESCAPED_UNICODE);
        Database::affecting(
            "INSERT INTO `{$table}` (reference_id, data_json, model, generated_by)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE
                data_json    = VALUES(data_json),
                model        = VALUES(model),
                generated_by = VALUES(generated_by)",
            [$referenceId, $json, $model, $userId]
        );
    }

    public static function delete(int $referenceId): void
    {
        $table = Database::table('reference_peer_reviews');
        Database::affecting(
            "DELETE FROM `{$table}` WHERE reference_id = ?",
            [$referenceId]
        );
    }
}
