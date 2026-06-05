-- ─────────────────────────────────────────────────────────────────────────────
-- 024_copilot_messages_global.sql — Allow Copilot conversations outside a review.
--
-- Until now copilot_messages.review_id was NOT NULL because the Copilot only
-- lived on /reviews/{id}/* pages. The widget is now floating globally —
-- platform help and methodology questions don't require a review context —
-- so the column becomes NULL-able. NULL means "global thread for this user".
--
-- A composite index on (user_id, id) helps the cheap "give me my global
-- transcript" query without disrupting the existing per-review index.
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `{prefix}copilot_messages`
    MODIFY `review_id` INT UNSIGNED NULL;

ALTER TABLE `{prefix}copilot_messages`
    ADD KEY `idx_cm_user_thread` (`user_id`, `id`);
