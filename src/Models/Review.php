<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;
use SysRevAI\Services\FileStorage;

/**
 * Systematic review records and their protocol.
 */
final class Review
{
    public const SCREENING_MODES = ['double_blind', 'double_blind_third', 'single', 'pilot'];
    public const STATUSES = ['active', 'archived', 'completed'];
    public const KINDS = ['systematic', 'scoping'];

    /**
     * Field labels each protocol framework asks for. Systematic reviews
     * use PICO (Population, Intervention, Comparison, Outcome, Study
     * design); scoping reviews use PCC (Population, Concept, Context).
     * Both kinds reuse the same `pico_json` column — only the field
     * keys we expose to the UI differ — so toggling kind never
     * destroys what the user already typed.
     */
    public const FRAMEWORK_FIELDS = [
        'systematic' => ['population', 'intervention', 'comparison', 'outcome', 'study_design'],
        'scoping'    => ['population', 'concept', 'context'],
    ];

    public static function kind(array $review): string
    {
        $k = (string) ($review['kind'] ?? 'systematic');
        return in_array($k, self::KINDS, true) ? $k : 'systematic';
    }

    public static function isScoping(array $review): bool
    {
        return self::kind($review) === 'scoping';
    }

    public static function frameworkFields(string $kind): array
    {
        return self::FRAMEWORK_FIELDS[$kind] ?? self::FRAMEWORK_FIELDS['systematic'];
    }

    /** Reviews the user owns or collaborates on. */
    public static function forUser(int $userId, ?string $status = 'active'): array
    {
        $reviews = Database::table('reviews');
        $members = Database::table('review_users');

        $sql = "SELECT r.* FROM `{$reviews}` r
                LEFT JOIN `{$members}` ru ON ru.review_id = r.id AND ru.user_id = ? AND ru.removed_at IS NULL
                WHERE (r.owner_id = ? OR ru.user_id = ?)";
        $params = [$userId, $userId, $userId];

        if ($status !== null) {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        $sql .= " GROUP BY r.id ORDER BY r.updated_at DESC";

        return Database::select($sql, $params);
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('reviews');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    public static function userCanAccess(int $reviewId, int $userId): bool
    {
        $review = self::find($reviewId);
        if ($review === null) {
            return false;
        }
        if ((int) $review['owner_id'] === $userId) {
            return true;
        }
        return ReviewUser::isMember($reviewId, $userId);
    }

    public static function create(int $ownerId, array $data): int
    {
        $table = Database::table('reviews');
        $kind = in_array(($data['kind'] ?? 'systematic'), self::KINDS, true)
            ? $data['kind'] : 'systematic';
        return Database::insert(
            "INSERT INTO `{$table}`
                (owner_id, title, question, pico_json, inclusion_criteria, exclusion_criteria,
                 screening_mode, pilot_count, reviewers_required, status, kind)
             VALUES (?,?,?,?,?,?,?,?,?, 'active', ?)",
            [
                $ownerId,
                $data['title'],
                $data['question'] ?? null,
                json_encode($data['pico'] ?? [], JSON_UNESCAPED_UNICODE),
                $data['inclusion_criteria'] ?? null,
                $data['exclusion_criteria'] ?? null,
                $data['screening_mode'] ?? 'double_blind',
                (int) ($data['pilot_count'] ?? 50),
                (int) ($data['reviewers_required'] ?? 2),
                $kind,
            ]
        );
    }

    /**
     * Persist (or replace) the protocol document attached to the
     * review. Tolerant of pre-migration installs — silent no-op if the
     * columns don't exist yet so a working review never fails on
     * write just because 035 hasn't been applied.
     */
    public static function saveProtocolFile(int $id, string $path, string $filename, string $mime): void
    {
        $table = Database::table('reviews');
        try {
            Database::affecting(
                "UPDATE `{$table}`
                    SET protocol_path = ?, protocol_filename = ?, protocol_mime = ?,
                        protocol_uploaded_at = CURRENT_TIMESTAMP
                  WHERE id = ?",
                [$path, mb_substr($filename, 0, 512), mb_substr($mime, 0, 80), $id]
            );
        } catch (\Throwable) {
            // pre-migration install — degrade silently.
        }
    }

    public static function update(int $id, array $data): void
    {
        $table = Database::table('reviews');
        // kind is set on create and only changed if the user explicitly
        // requested it (e.g. they ticked the "switch to scoping" toggle
        // on the protocol editor). Missing means "leave it alone".
        $kindIsSet = isset($data['kind']) && in_array($data['kind'], self::KINDS, true);
        if ($kindIsSet) {
            Database::affecting(
                "UPDATE `{$table}` SET
                    title = ?, question = ?, pico_json = ?, inclusion_criteria = ?,
                    exclusion_criteria = ?, screening_mode = ?, pilot_count = ?, reviewers_required = ?,
                    kind = ?
                 WHERE id = ?",
                [
                    $data['title'],
                    $data['question'] ?? null,
                    json_encode($data['pico'] ?? [], JSON_UNESCAPED_UNICODE),
                    $data['inclusion_criteria'] ?? null,
                    $data['exclusion_criteria'] ?? null,
                    $data['screening_mode'] ?? 'double_blind',
                    (int) ($data['pilot_count'] ?? 50),
                    (int) ($data['reviewers_required'] ?? 2),
                    $data['kind'],
                    $id,
                ]
            );
            return;
        }
        Database::affecting(
            "UPDATE `{$table}` SET
                title = ?, question = ?, pico_json = ?, inclusion_criteria = ?,
                exclusion_criteria = ?, screening_mode = ?, pilot_count = ?, reviewers_required = ?
             WHERE id = ?",
            [
                $data['title'],
                $data['question'] ?? null,
                json_encode($data['pico'] ?? [], JSON_UNESCAPED_UNICODE),
                $data['inclusion_criteria'] ?? null,
                $data['exclusion_criteria'] ?? null,
                $data['screening_mode'] ?? 'double_blind',
                (int) ($data['pilot_count'] ?? 50),
                (int) ($data['reviewers_required'] ?? 2),
                $id,
            ]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            return;
        }
        $table = Database::table('reviews');
        Database::affecting("UPDATE `{$table}` SET status = ? WHERE id = ?", [$status, $id]);
    }

    /**
     * Permanently delete a review and every byte of data it owns:
     * cascading FKs sweep references, screening decisions, full-text
     * records, extractions, RoB, comments, invitations and team rows; we
     * additionally drop activity-log entries (no FK), copilot messages
     * (no FK) and AI usage rows (FK is ON DELETE SET NULL by design),
     * and unlink any PDF / XML blobs on disk so storage doesn't leak.
     */
    public static function delete(int $id): void
    {
        // Best-effort filesystem cleanup before the DB rows vanish.
        // The DB-level CASCADE will remove these rows regardless, but
        // once they're gone we can no longer locate the files.
        $refs       = Database::table('references');
        $refFull    = Database::table('reference_full_text');
        $refStatus  = Database::table('reference_fulltext_status');
        try {
            $pdfs = Database::select(
                "SELECT rft.pdf_path
                   FROM `{$refFull}` rft
                   JOIN `{$refs}` r ON r.id = rft.reference_id
                  WHERE r.review_id = ?",
                [$id]
            );
            foreach ($pdfs as $row) {
                $path = (string) ($row['pdf_path'] ?? '');
                if ($path !== '') {
                    FileStorage::delete($path);
                }
            }
        } catch (\Throwable) {
            // Table missing in a partial install — skip.
        }
        try {
            $blobs = Database::select(
                "SELECT rfs.pdf_local_path, rfs.xml_local_path
                   FROM `{$refStatus}` rfs
                   JOIN `{$refs}` r ON r.id = rfs.reference_id
                  WHERE r.review_id = ?",
                [$id]
            );
            foreach ($blobs as $row) {
                foreach (['pdf_local_path' => 'pdfs', 'xml_local_path' => 'fulltext'] as $col => $subdir) {
                    $path = (string) ($row[$col] ?? '');
                    if ($path !== '' && FileStorage::isStoredIn($path, $subdir)) {
                        @unlink($path);
                    }
                }
            }
        } catch (\Throwable) {
            // Table missing — skip.
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Tables without an ON DELETE CASCADE to reviews.id.
            foreach (['ai_usage', 'activity_log', 'copilot_messages'] as $name) {
                $t = Database::table($name);
                try {
                    Database::affecting("DELETE FROM `{$t}` WHERE review_id = ?", [$id]);
                } catch (\Throwable) {
                    // Table optional in some installs.
                }
            }
            // Cascades handle references → reference_full_text /
            // screening_decisions / risk_of_bias / extractions /
            // retrieval_attempts / retrieval_queue / reference_fulltext_status,
            // plus review_users, invitations, comments, exclusion_reasons.
            $reviews = Database::table('reviews');
            Database::affecting("DELETE FROM `{$reviews}` WHERE id = ?", [$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Decode the PICO JSON to an array with the expected keys. */
    public static function pico(array $review): array
    {
        $pico = json_decode((string) ($review['pico_json'] ?? ''), true);
        $pico = is_array($pico) ? $pico : [];
        // Include every framework's keys so the form can flip kind
        // without losing data the user already filled in for the other
        // framework's fields. Missing values default to empty strings.
        return array_merge(
            [
                'population'   => '',
                'intervention' => '',
                'comparison'   => '',
                'outcome'      => '',
                'study_design' => '',
                'concept'      => '',
                'context'      => '',
            ],
            $pico
        );
    }

    /**
     * Reference counts by workflow status. Returns zeros until the references
     * feature (Phase 5) creates the table; degrades gracefully meanwhile.
     */
    public static function metrics(int $reviewId): array
    {
        $base = [
            'total' => 0, 'imported' => 0, 'duplicate' => 0,
            'ta_screening' => 0, 'ta_included' => 0, 'ta_excluded' => 0,
            'ft_screening' => 0, 'ft_included' => 0, 'ft_excluded' => 0,
            'extracted' => 0,
        ];
        try {
            $table = Database::table('references');
            $rows = Database::select(
                "SELECT status, COUNT(*) AS c FROM `{$table}` WHERE review_id = ? GROUP BY status",
                [$reviewId]
            );
            foreach ($rows as $row) {
                $base[$row['status']] = (int) $row['c'];
                $base['total'] += (int) $row['c'];
            }
        } catch (\Throwable) {
            // references table not present yet.
        }
        return $base;
    }
}
