<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Per-reference full-text PDF metadata and extracted text.
 */
final class ReferenceFullText
{
    public static function find(int $referenceId): ?array
    {
        $table = Database::table('reference_full_text');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE reference_id = ? LIMIT 1", [$referenceId]);
    }

    public static function save(
        int $referenceId,
        string $pdfPath,
        string $originalFilename,
        string $extractedText,
        int $pageCount,
        int $sizeBytes,
        int $uploadedBy
    ): void {
        $table = Database::table('reference_full_text');
        Database::affecting(
            "INSERT INTO `{$table}`
                (reference_id, pdf_path, original_filename, extracted_text, page_count, size_bytes, uploaded_by)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                pdf_path = VALUES(pdf_path), original_filename = VALUES(original_filename),
                extracted_text = VALUES(extracted_text), page_count = VALUES(page_count),
                size_bytes = VALUES(size_bytes), uploaded_by = VALUES(uploaded_by),
                uploaded_at = NOW()",
            [$referenceId, $pdfPath, $originalFilename, $extractedText, $pageCount, $sizeBytes, $uploadedBy]
        );
    }

    public static function deleteFor(int $referenceId): void
    {
        $table = Database::table('reference_full_text');
        Database::affecting("DELETE FROM `{$table}` WHERE reference_id = ?", [$referenceId]);
    }
}
