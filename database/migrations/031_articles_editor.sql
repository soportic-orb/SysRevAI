-- ─────────────────────────────────────────────────────────────────────────────
-- 031_articles_editor.sql — Collaborative editor state for an article.
--
-- The "Edició col·laborativa" workspace stores its rich-text content as
-- HTML on the article row itself: there's only one current draft per
-- article and TinyMCE already round-trips HTML losslessly through
-- save/load. editor_updated_at lets the UI tell the user when it last
-- autosaved.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}articles`
    ADD COLUMN `editor_html`        MEDIUMTEXT NULL AFTER `extracted_text`,
    ADD COLUMN `editor_updated_at`  TIMESTAMP  NULL DEFAULT NULL AFTER `editor_html`;
