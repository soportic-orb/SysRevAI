<?php

declare(strict_types=1);

namespace SysRevAI\Models;

use SysRevAI\Core\Database;

/**
 * Named snapshot of the collaborative editor's HTML for an article.
 *
 * The current draft is autosaved on `articles.editor_html`; rows here
 * only exist when the user explicitly pressed "Desar versió" in the
 * editor toolbar. The dropdown next to the button lists every snapshot
 * (most-recent first) and the user can pick any of them to restore.
 */
final class ArticleEditorVersion
{
    /**
     * Persist a new snapshot. Returns the new row id.
     */
    public static function create(int $articleId, string $html, ?string $label, ?int $userId): int
    {
        $table = Database::table('article_editor_versions');
        return Database::insert(
            "INSERT INTO `{$table}` (article_id, label, html, saved_by) VALUES (?, ?, ?, ?)",
            [$articleId, $label !== null ? mb_substr($label, 0, 140) : null, $html, $userId]
        );
    }

    /**
     * Snapshots for the article, newest first. The HTML column is
     * intentionally OMITTED here so a long list doesn't pull every
     * MEDIUMTEXT body into memory; the editor fetches the body lazily
     * via find() when the user picks a row from the dropdown.
     *
     * @return array<int,array{id:int,label:?string,created_at:string,saved_by:?int,saved_by_name:?string}>
     */
    public static function listForArticle(int $articleId, int $limit = 50): array
    {
        $table = Database::table('article_editor_versions');
        $users = Database::table('users');
        $rows = Database::select(
            "SELECT v.id, v.label, v.created_at, v.saved_by, u.name AS saved_by_name
               FROM `{$table}` v
          LEFT JOIN `{$users}` u ON u.id = v.saved_by
              WHERE v.article_id = ?
           ORDER BY v.id DESC
              LIMIT {$limit}",
            [$articleId]
        );
        return array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'label'         => $r['label'] !== null ? (string) $r['label'] : null,
            'created_at'    => (string) $r['created_at'],
            'saved_by'      => $r['saved_by'] !== null ? (int) $r['saved_by'] : null,
            'saved_by_name' => $r['saved_by_name'] !== null ? (string) $r['saved_by_name'] : null,
        ], $rows);
    }

    /** Fetch a single snapshot including its HTML body. */
    public static function find(int $id): ?array
    {
        $table = Database::table('article_editor_versions');
        return Database::selectOne("SELECT * FROM `{$table}` WHERE id = ?", [$id]);
    }
}
