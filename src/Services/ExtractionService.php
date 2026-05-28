<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Helpers around an extraction template (field validation + AI payload shape).
 */
final class ExtractionService
{
    /** Cast/clean submitted values per field type; drop unknown keys. */
    public static function sanitize(array $fields, array $posted): array
    {
        $clean = [];
        foreach ($fields as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $value = $posted[$key] ?? null;
            $clean[$key] = self::castField($field, $value);
        }
        return $clean;
    }

    private static function castField(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');
        switch ($type) {
            case 'number':
                if ($value === null || $value === '') {
                    return null;
                }
                return is_numeric($value) ? (float) $value : null;

            case 'date':
                if (!is_string($value) || $value === '') {
                    return null;
                }
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;

            case 'select':
                $options = (array) ($field['options'] ?? []);
                $value = is_string($value) ? trim($value) : '';
                return in_array($value, $options, true) ? $value : null;

            case 'multi_select':
                $options = (array) ($field['options'] ?? []);
                if (!is_array($value)) {
                    return [];
                }
                return array_values(array_intersect($options, array_map('strval', $value)));

            case 'textarea':
            case 'text':
            default:
                if (!is_string($value)) {
                    return null;
                }
                $value = trim($value);
                return $value === '' ? null : mb_substr($value, 0, 5000);
        }
    }

    /** Compact JSON description of the template for the AI prompt. */
    public static function templateForAi(array $fields): array
    {
        $shape = [];
        foreach ($fields as $f) {
            $key = (string) ($f['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $shape[$key] = [
                'label' => (string) ($f['label'] ?? $key),
                'type'  => (string) ($f['type'] ?? 'text'),
            ];
            if (!empty($f['options']) && is_array($f['options'])) {
                $shape[$key]['options'] = array_values($f['options']);
            }
        }
        return $shape;
    }
}
