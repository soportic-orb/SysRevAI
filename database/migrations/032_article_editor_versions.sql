-- ─────────────────────────────────────────────────────────────────────────────
-- 032_article_editor_versions.sql — Named snapshots of the collaborative
-- editor's HTML for an article. Each press of the "Desar versió" button
-- in the editor toolbar appends a row here, so the user can scroll the
-- dropdown of past versions and restore any of them back into TinyMCE.
-- The current draft still lives on articles.editor_html (autosaved);
-- this table only holds explicit snapshots the user wanted to keep.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE `{prefix}article_editor_versions` (
    `id`         INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `article_id` INT UNSIGNED   NOT NULL,
    `label`      VARCHAR(140)   NULL,
    `html`       MEDIUMTEXT     NOT NULL,
    `saved_by`   INT UNSIGNED   NULL,
    `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `aev_article_idx` (`article_id`, `created_at`),
    CONSTRAINT `aev_article_fk` FOREIGN KEY (`article_id`)
        REFERENCES `{prefix}articles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `aev_user_fk` FOREIGN KEY (`saved_by`)
        REFERENCES `{prefix}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
