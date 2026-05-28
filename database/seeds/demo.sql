-- ─────────────────────────────────────────────────────────────────────────────
-- demo.sql — Sample review with a handful of references to try the platform.
--
-- Load AFTER installation has created your admin user (id = 1) and AFTER all
-- migrations have been applied:
--
--   mysql -u <user> -p <database> < database/seeds/demo.sql
--
-- The `{prefix}` token must be replaced with your configured table prefix
-- (default `sra_`) before loading, e.g.:
--
--   sed 's/{prefix}/sra_/g' database/seeds/demo.sql | mysql -u <user> -p <database>
--
-- All inserts are idempotent on review title so you can safely re-run.
-- ─────────────────────────────────────────────────────────────────────────────

SET @owner_id := 1;

INSERT INTO `{prefix}reviews`
    (owner_id, title, question, pico_json, inclusion_criteria, exclusion_criteria,
     screening_mode, pilot_count, reviewers_required, status)
SELECT
    @owner_id,
    'Exercise programs to prevent falls in older adults',
    'Are exercise programs effective in preventing falls in community-dwelling adults aged 65+ compared with usual care?',
    JSON_OBJECT(
        'population',   'Community-dwelling adults aged 65 or older',
        'intervention', 'Structured exercise programs (balance, strength, multimodal)',
        'comparison',   'Usual care, no intervention or placebo',
        'outcome',      'Rate of falls and fall-related injuries',
        'study_design', 'Randomized controlled trials'
    ),
    'Adults aged 65 or older living in the community; randomized controlled trials; reporting falls outcomes; published in English, Spanish or Catalan.',
    'Hospital inpatients or institutionalized residents; non-randomized designs; abstracts without full data.',
    'double_blind', 50, 2, 'active'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `{prefix}reviews`
    WHERE title = 'Exercise programs to prevent falls in older adults'
);

SET @review_id := (
    SELECT id FROM `{prefix}reviews`
    WHERE title = 'Exercise programs to prevent falls in older adults'
    LIMIT 1
);

-- Add the owner as a member (idempotent: row exists, role stays).
INSERT INTO `{prefix}review_users` (review_id, user_id, role, is_blinded, can_resolve_conflicts, joined_at)
VALUES (@review_id, @owner_id, 'owner', 0, 1, NOW())
ON DUPLICATE KEY UPDATE removed_at = NULL;

-- Default exclusion reasons (idempotent: skip if a row already exists).
INSERT INTO `{prefix}exclusion_reasons` (review_id, label, stage, sort_order)
SELECT @review_id, label, 'both', sort_order
FROM (
    SELECT 'Wrong population'   AS label, 0 AS sort_order UNION ALL
    SELECT 'Wrong intervention',           1               UNION ALL
    SELECT 'Wrong comparator',             2               UNION ALL
    SELECT 'Wrong outcome',                3               UNION ALL
    SELECT 'Wrong study design',           4               UNION ALL
    SELECT 'Duplicate',                    5
) seed
WHERE NOT EXISTS (
    SELECT 1 FROM `{prefix}exclusion_reasons`
    WHERE review_id = @review_id AND label = seed.label
);

-- Sample references with realistic abstracts. Status starts at 'imported'; load
-- the screening view to promote them and try the workflow.
INSERT INTO `{prefix}references`
    (review_id, title, authors_json, year, journal, abstract, doi, pmid, dedup_key, status)
SELECT @review_id, t.title, t.authors, t.year, t.journal, t.abstract, t.doi, t.pmid, t.dedup_key, 'imported'
FROM (
    SELECT
        'Effect of multimodal exercise on falls in older adults: a randomized trial' AS title,
        JSON_ARRAY('Garcia, M.', 'Lopez, A.', 'Tanaka, R.') AS authors,
        2023 AS year,
        'BMC Geriatrics' AS journal,
        'Background: Falls are a major cause of injury in older adults. Methods: We randomized 320 community-dwelling adults aged 70+ to a 12-week multimodal exercise program or usual care. Results: The intervention reduced fall rate by 38% (RR 0.62, 95% CI 0.47-0.81). Conclusions: A structured multimodal exercise program reduces falls in older adults.' AS abstract,
        '10.1186/s12877-023-04567' AS doi,
        '37123456' AS pmid,
        'effect of multimodal exercise on falls in older adults a randomized trial|garcia|2023' AS dedup_key
    UNION ALL SELECT
        'Tai Chi for fall prevention in community-dwelling seniors',
        JSON_ARRAY('Smith, J.', 'Brown, K.'),
        2022,
        'JAMA Internal Medicine',
        'We evaluated whether a 6-month Tai Chi program reduced falls. In 670 participants aged 65-89, Tai Chi reduced injurious falls by 31% compared with stretching exercises.',
        '10.1001/jamainternmed.2022.5012',
        '36876543',
        'tai chi for fall prevention in community dwelling seniors|smith|2022'
    UNION ALL SELECT
        'Strength training and balance for fall prevention in older women',
        JSON_ARRAY('Nguyen, T.', 'Patel, S.', 'Hernandez, P.'),
        2021,
        'Age and Ageing',
        'A randomized trial of progressive resistance training plus balance exercises in 412 women aged 70+. Falls decreased significantly in the intervention arm over 18 months of follow-up.',
        '10.1093/ageing/afab123',
        '34567890',
        'strength training and balance for fall prevention in older women|nguyen|2021'
    UNION ALL SELECT
        'Pharmacological vs exercise interventions: a network meta-analysis',
        JSON_ARRAY('Anders, L.', 'Petrov, D.'),
        2024,
        'The Lancet Healthy Longevity',
        'Network meta-analysis of 92 trials. Exercise interventions consistently outperformed pharmacological options for reducing falls in older adults.',
        '10.1016/s2666-7568(24)00012-7',
        '38123456',
        'pharmacological vs exercise interventions a network meta analysis|anders|2024'
    UNION ALL SELECT
        'Home-based exercise to prevent falls: usability study',
        JSON_ARRAY('Wong, H.', 'Rossi, F.'),
        2020,
        'JMIR Aging',
        'Mixed-methods usability study of a home-based exercise app in 38 older adults. High acceptability but no efficacy outcomes reported.',
        '10.2196/19876',
        '32123456',
        'home based exercise to prevent falls usability study|wong|2020'
) t
WHERE NOT EXISTS (
    SELECT 1 FROM `{prefix}references` WHERE review_id = @review_id AND doi = t.doi
);
