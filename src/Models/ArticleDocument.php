<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Secondary documents attached to an article — companion papers,
 * methodology references, guidelines, etc. The Copilot reads their
 * extracted text so the user can ask cross-document questions
 * grounded in the actual content.
 *
 * The primary manuscript itself stays on the articles row; the rows
 * here are strictly the *additional* materials uploaded after the
 * article was created.
 */
final class ArticleDocument
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function forArticle(int $articleId): array
    {
        $table = Database::table('article_documents');
        try {
            return Database::select(
                "SELECT id, article_id, filename, mime, file_path, char_count, uploaded_by, created_at
                   FROM `{$table}` WHERE article_id = ?
               ORDER BY id ASC",
                [$articleId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        $table = Database::table('article_documents');
        try {
            return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function create(int $articleId, string $filename, string $mime, string $filePath, string $extractedText, ?int $userId): int
    {
        $table = Database::table('article_documents');
        return Database::insert(
            "INSERT INTO `{$table}`
                (article_id, filename, mime, file_path, extracted_text, char_count, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $articleId,
                mb_substr($filename, 0, 512),
                mb_substr($mime, 0, 80),
                mb_substr($filePath, 0, 1024),
                $extractedText,
                mb_strlen($extractedText),
                $userId,
            ]
        );
    }

    public static function delete(int $id): void
    {
        $table = Database::table('article_documents');
        Database::affecting("DELETE FROM `{$table}` WHERE id = ?", [$id]);
    }

    /**
     * Extracted-text payload for the Copilot system prompt — one entry
     * per document with the filename + a truncated body. Per-document
     * cap keeps every document represented even when one is huge.
     *
     * @return array<int,array{filename:string,text:string}>
     */
    public static function copilotPayload(int $articleId, int $perDocCap = 12000): array
    {
        $table = Database::table('article_documents');
        try {
            $rows = Database::select(
                "SELECT filename, extracted_text FROM `{$table}` WHERE article_id = ? ORDER BY id ASC",
                [$articleId]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $text = (string) ($r['extracted_text'] ?? '');
            if (trim($text) === '') {
                continue;
            }
            if (mb_strlen($text) > $perDocCap) {
                $text = mb_substr($text, 0, $perDocCap) . "\n[…truncated…]";
            }
            $out[] = [
                'filename' => (string) ($r['filename'] ?? '—'),
                'text'     => $text,
            ];
        }
        return $out;
    }
}
