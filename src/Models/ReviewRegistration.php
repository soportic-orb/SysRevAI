<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Draft registration record for a review — a single row per review
 * holding the field map the user is preparing for PROSPERO (systematic
 * reviews) or OSF (scoping reviews). The kind column determines which
 * field schema applies; data is a JSON object keyed by RegistrationFields ids.
 */
final class ReviewRegistration
{
    public static function find(int $reviewId): ?array
    {
        $table = Database::table('review_registrations');
        try {
            return Database::selectOne("SELECT * FROM `{$table}` WHERE review_id = ?", [$reviewId]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Decode the JSON `data` column into a string-keyed map. Anything
     * non-string is dropped silently — the form only renders text /
     * textarea fields so anything else is a malformed payload.
     *
     * @return array<string,string>
     */
    public static function decode(?array $row): array
    {
        if ($row === null) {
            return [];
        }
        $raw = $row['data'] ?? '';
        $decoded = is_string($raw) ? json_decode($raw, true) : (is_array($raw) ? $raw : []);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Upsert the registration record. The `data` blob is replaced
     * wholesale on every save — the form submits all fields at once.
     *
     * @param array<string,string> $data
     */
    public static function save(int $reviewId, string $kind, array $data, ?int $userId): void
    {
        $table = Database::table('review_registrations');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        Database::affecting(
            "INSERT INTO `{$table}` (review_id, kind, data, updated_by)
                  VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                  kind = VALUES(kind),
                  data = VALUES(data),
                  updated_by = VALUES(updated_by),
                  updated_at = CURRENT_TIMESTAMP",
            [$reviewId, $kind, $json, $userId]
        );
    }
}
