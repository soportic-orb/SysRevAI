<?php

declare(strict_types=1);

namespace SysRevAI\Controllers\Admin;

use SysRevAI\Core\Database;
use SysRevAI\Core\View;
use SysRevAI\Models\ReferenceFullTextStatus;

/**
 * Admin-wide reports. For now: full-text retrieval coverage.
 */
final class ReportsController
{
    public function fulltextCoverage(): void
    {
        echo View::render('admin/reports/fulltext_coverage', [
            'activeSection' => 'reports',
            'global'        => ReferenceFullTextStatus::coverage(0),
            'bySource'      => $this->topSources(),
            'perReview'     => $this->perReviewCoverage(),
            'noTextRefs'    => $this->referencesWithoutText(50),
        ], 'layouts/admin');
    }

    /** Stream a CSV with the references that still have no full text. */
    public function fulltextCoverageCsv(): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="fulltext-coverage-' . date('Ymd') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel detects the encoding

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['reference_id', 'review_id', 'review_title', 'reference_title', 'doi', 'pmid', 'attempts', 'last_attempt'], ',', '"', '');
        foreach ($this->referencesWithoutText(2000) as $row) {
            fputcsv($out, [
                (int) $row['reference_id'],
                (int) $row['review_id'],
                (string) ($row['review_title'] ?? ''),
                (string) ($row['ref_title'] ?? ''),
                (string) ($row['doi'] ?? ''),
                (string) ($row['pmid'] ?? ''),
                (int) ($row['attempts_count'] ?? 0),
                (string) ($row['last_attempt_at'] ?? ''),
            ], ',', '"', '');
        }
        fclose($out);
    }

    /* ── Internals ─────────────────────────────────────────────────────── */

    /** Top sources by successful hits across every review. */
    private function topSources(): array
    {
        try {
            $attempts = Database::table('retrieval_attempts');
            return Database::select(
                "SELECT source, COUNT(*) AS hits FROM `{$attempts}`
                 WHERE status = 'success' GROUP BY source ORDER BY hits DESC"
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Coverage per review (sorted by review id desc, capped to 100). */
    private function perReviewCoverage(): array
    {
        try {
            $refs    = Database::table('references');
            $reviews = Database::table('reviews');
            $rfs     = Database::table('reference_fulltext_status');
            return Database::select(
                "SELECT v.id AS review_id, v.title AS review_title,
                        COUNT(r.id) AS total,
                        SUM(CASE WHEN s.reference_id IS NOT NULL THEN 1 ELSE 0 END) AS attempted,
                        SUM(CASE WHEN s.has_fulltext = 1 THEN 1 ELSE 0 END) AS with_text
                 FROM `{$reviews}` v
                 LEFT JOIN `{$refs}` r ON r.review_id = v.id AND r.status <> 'duplicate'
                 LEFT JOIN `{$rfs}` s ON s.reference_id = r.id
                 GROUP BY v.id, v.title
                 ORDER BY v.id DESC
                 LIMIT 100"
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * References (across reviews) that still lack a full-text retrieval.
     * Includes ones that have never been attempted as well as those whose
     * latest attempt did not yield content.
     */
    private function referencesWithoutText(int $limit): array
    {
        $limit = max(1, min($limit, 5000));
        try {
            $refs    = Database::table('references');
            $reviews = Database::table('reviews');
            $rfs     = Database::table('reference_fulltext_status');
            return Database::select(
                "SELECT r.id AS reference_id, r.review_id, r.title AS ref_title,
                        r.doi, r.pmid,
                        v.title AS review_title,
                        COALESCE(s.attempts_count, 0) AS attempts_count,
                        s.last_attempt_at
                 FROM `{$refs}` r
                 JOIN `{$reviews}` v ON v.id = r.review_id
                 LEFT JOIN `{$rfs}` s ON s.reference_id = r.id
                 WHERE r.status <> 'duplicate'
                   AND (s.reference_id IS NULL OR s.has_fulltext = 0)
                 ORDER BY r.id DESC
                 LIMIT {$limit}"
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
