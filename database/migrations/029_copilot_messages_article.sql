-- ─────────────────────────────────────────────────────────────────────────────
-- 029_copilot_messages_article.sql — Article-scoped Copilot transcripts.
--
-- copilot_messages already carries (review_id, user_id) and (NULL, user_id)
-- threads. Adding an optional article_id lets the Article Analysis tool
-- store its own transcripts in the same table — three orthogonal scopes
-- (review / global / article) keyed by which id column is non-NULL.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}copilot_messages`
    ADD COLUMN `article_id` INT UNSIGNED NULL AFTER `review_id`;

ALTER TABLE `{prefix}copilot_messages`
    ADD KEY `idx_cm_article` (`article_id`, `user_id`, `id`);
