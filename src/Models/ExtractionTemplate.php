<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

final class ExtractionTemplate
{
    public const FIELD_TYPES = ['text', 'textarea', 'number', 'date', 'select', 'multi_select'];

    /** Predefined fields that cover most reviews. */
    public static function defaultFields(): array
    {
        return [
            ['key' => 'study_design',       'label' => 'Study design',        'type' => 'select',
                'options' => ['RCT', 'Cluster RCT', 'Quasi-experimental', 'Cohort', 'Case-control', 'Cross-sectional', 'Qualitative']],
            ['key' => 'country',            'label' => 'Country',             'type' => 'text'],
            ['key' => 'n_total',            'label' => 'Total N',             'type' => 'number'],
            ['key' => 'n_intervention',     'label' => 'N (intervention)',    'type' => 'number'],
            ['key' => 'n_control',          'label' => 'N (control)',         'type' => 'number'],
            ['key' => 'intervention',       'label' => 'Intervention',        'type' => 'textarea'],
            ['key' => 'comparator',         'label' => 'Comparator',          'type' => 'textarea'],
            ['key' => 'primary_outcomes',   'label' => 'Primary outcomes',    'type' => 'textarea'],
            ['key' => 'secondary_outcomes', 'label' => 'Secondary outcomes',  'type' => 'textarea'],
            ['key' => 'follow_up',          'label' => 'Follow-up duration',  'type' => 'text'],
            ['key' => 'funding',            'label' => 'Funding',             'type' => 'text'],
        ];
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('extraction_templates');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    public static function forReview(int $reviewId): array
    {
        $table = Database::table('extraction_templates');
        return Database::select(
            "SELECT * FROM `{$table}` WHERE review_id = ? ORDER BY is_default DESC, id ASC",
            [$reviewId]
        );
    }

    /** Return the default template for a review, creating one if missing. */
    public static function ensureDefault(int $reviewId): array
    {
        $table = Database::table('extraction_templates');
        $row = Database::selectOne(
            "SELECT * FROM `{$table}` WHERE review_id = ? AND is_default = 1 LIMIT 1",
            [$reviewId]
        );
        if ($row !== null) {
            return $row;
        }
        $id = Database::insert(
            "INSERT INTO `{$table}` (review_id, name, fields_json, is_default)
             VALUES (?, ?, ?, 1)",
            [$reviewId, 'Default extraction', json_encode(self::defaultFields(), JSON_UNESCAPED_UNICODE)]
        );
        return self::find($id) ?? [];
    }

    public static function update(int $id, string $name, array $fields): void
    {
        $table = Database::table('extraction_templates');
        Database::affecting(
            "UPDATE `{$table}` SET name = ?, fields_json = ? WHERE id = ?",
            [$name, json_encode(array_values($fields), JSON_UNESCAPED_UNICODE), $id]
        );
    }

    public static function decodeFields(array $template): array
    {
        $fields = json_decode((string) ($template['fields_json'] ?? ''), true);
        return is_array($fields) ? $fields : [];
    }
}
