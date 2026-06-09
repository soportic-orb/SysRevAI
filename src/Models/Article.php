<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;
use SysRevAI\Services\FileStorage;

/**
 * Article project. One row per uploaded paper; owner has full
 * control, collaborators join via article_users.
 */
final class Article
{
    public static function find(int $id): ?array
    {
        $table = Database::table('articles');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1", [$id]);
    }

    /** Articles the user owns or collaborates on, newest first. */
    public static function forUser(int $userId): array
    {
        $articles = Database::table('articles');
        $members  = Database::table('article_users');
        return Database::select(
            "SELECT a.* FROM `{$articles}` a
             LEFT JOIN `{$members}` au ON au.article_id = a.id AND au.user_id = ? AND au.removed_at IS NULL
             WHERE a.owner_id = ? OR au.user_id = ?
             GROUP BY a.id
             ORDER BY a.updated_at DESC",
            [$userId, $userId, $userId]
        );
    }

    public static function userCanAccess(int $articleId, int $userId): bool
    {
        $row = self::find($articleId);
        if ($row === null) {
            return false;
        }
        if ((int) $row['owner_id'] === $userId) {
            return true;
        }
        return ArticleUser::isMember($articleId, $userId);
    }

    public static function isOwner(array $article, int $userId): bool
    {
        return (int) ($article['owner_id'] ?? 0) === $userId;
    }

    /** @return int Newly created article id. */
    public static function create(int $ownerId, array $data): int
    {
        $table = Database::table('articles');
        return Database::insert(
            "INSERT INTO `{$table}` (owner_id, title, source_filename, mime, file_path, extracted_text, char_count)
             VALUES (?,?,?,?,?,?,?)",
            [
                $ownerId,
                $data['title'],
                $data['source_filename'] ?? null,
                $data['mime']            ?? null,
                $data['file_path']       ?? null,
                $data['extracted_text']  ?? null,
                (int) ($data['char_count'] ?? 0),
            ]
        );
    }

    public static function updateTitle(int $id, string $title): void
    {
        $table = Database::table('articles');
        Database::affecting("UPDATE `{$table}` SET title = ? WHERE id = ?", [$title, $id]);
    }

    /**
     * Wipe the article and every byte it owns: file on disk, copilot
     * transcripts, members and invitations cascade via FK.
     */
    public static function delete(int $id): void
    {
        $row = self::find($id);
        if ($row !== null) {
            $path = (string) ($row['file_path'] ?? '');
            if ($path !== '' && FileStorage::isStoredIn($path, 'articles')) {
                @unlink($path);
            }
        }
        // Manual cleanup of copilot_messages (no FK to articles).
        $table = Database::table('copilot_messages');
        try {
            Database::affecting("DELETE FROM `{$table}` WHERE article_id = ?", [$id]);
        } catch (\Throwable) {
            // Article-scoped column missing in a partial install.
        }
        $articles = Database::table('articles');
        Database::affecting("DELETE FROM `{$articles}` WHERE id = ?", [$id]);
    }
}
