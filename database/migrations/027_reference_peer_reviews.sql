-- ─────────────────────────────────────────────────────────────────────────────
-- 027_reference_peer_reviews.sql — Cached AI peer-review rubrics.
--
-- One row per reference. The rubric is a JSON blob with five 0-100 scores
-- (methodology, clarity, novelty, evidence, limitations), a one-paragraph
-- summary, a devil's-advocate counter-argument and an overall verdict —
-- structured by ClaudeService::peerReviewRubric and rendered by the
-- peer_review/show view.
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `{prefix}reference_peer_reviews` (
    `reference_id` BIGINT UNSIGNED NOT NULL,
    `data_json`    MEDIUMTEXT      NOT NULL,
    `model`        VARCHAR(80)     NULL,
    `generated_by` BIGINT UNSIGNED NULL,
    `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`reference_id`),
    CONSTRAINT `fk_peerreview_ref`
        FOREIGN KEY (`reference_id`) REFERENCES `{prefix}references` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
