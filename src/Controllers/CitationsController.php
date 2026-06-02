<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Services\ClaudeService;
use SysRevAI\Services\DeduplicationService;

/**
 * Citation normaliser — paste a free-text block (or import from search /
 * a review's references), pick a citation style and the AI re-formats
 * every reference to that style. The result page offers checkboxes +
 * select-all + bulk-import into one of the user's open reviews, mirroring
 * the search-results UX.
 */
final class CitationsController
{
    public const STYLES = ['apa', 'vancouver', 'nlm', 'chicago', 'mla', 'harvard', 'ama', 'ieee'];
    private const DEFAULT_STYLE = 'apa';

    public function form(): void
    {
        // Pre-fill from a previous one-shot stash (search "Send to
        // converter" or References "Convert/normalize" flows). Both
        // entry points stash a `citations_seed` payload and bounce here.
        $seed = Session::pullFlash('citations_seed');
        $seedText  = is_array($seed) ? (string) ($seed['text']  ?? '') : '';
        $seedStyle = is_array($seed) ? (string) ($seed['style'] ?? '') : '';

        echo View::render('citations/form', [
            'styles'      => self::STYLES,
            'style'       => in_array($seedStyle, self::STYLES, true) ? $seedStyle : self::DEFAULT_STYLE,
            'text'        => $seedText,
            'openReviews' => $this->openReviewsForUser(),
            'autorun'     => is_array($seed) && (bool) ($seed['autorun'] ?? false),
        ]);
    }

    /**
     * Run the AI normalisation. Re-renders the form page with both the
     * original text (so the user can tweak and re-run) and the result
     * list, instead of redirecting — so the textarea content survives a
     * "no API key" type error.
     */
    public function convert(): void
    {
        $text  = trim((string) ($_POST['text']  ?? ''));
        $style = (string) ($_POST['style'] ?? self::DEFAULT_STYLE);
        if (!in_array($style, self::STYLES, true)) {
            $style = self::DEFAULT_STYLE;
        }
        if ($text === '') {
            Session::flash('error', __('citations.error_no_text'));
            redirect('/citations');
        }

        $res = ClaudeService::fromSettings()->normalizeCitations($text, $style);
        if (!$res['ok']) {
            $msg = match ($res['error'] ?? '') {
                'no_api_key'       => __('citations.error_no_key'),
                'feature_disabled' => __('citations.error_disabled'),
                'budget_exceeded'  => __('citations.error_budget'),
                default            => __('citations.error_failed', (string) ($res['error'] ?? '')),
            };
            Session::flash('error', $msg);
            Session::flash('citations_seed', ['text' => $text, 'style' => $style]);
            redirect('/citations');
        }

        ActivityLog::record('citations.normalised', [
            'style' => $style,
            'count' => count($res['items'] ?? []),
        ]);

        echo View::render('citations/form', [
            'styles'      => self::STYLES,
            'style'       => $style,
            'text'        => $text,
            'results'     => $res['items'] ?? [],
            'openReviews' => $this->openReviewsForUser(),
            'autorun'     => false,
        ]);
    }

    /**
     * Persist the ticked normalised citations as fresh references in
     * the chosen review. Each selected entry comes back as the literal
     * normalised string — we run it back through the freetext extractor
     * so the structured fields (authors, year, journal, DOI) are pulled
     * out before insert.
     */
    public function import(): void
    {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId <= 0) {
            Session::flash('error', __('search.import_no_review'));
            redirect('/citations');
        }
        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            return;
        }

        $selected = (array) ($_POST['selected'] ?? []);
        $selected = array_values(array_filter($selected, 'is_string'));
        if ($selected === []) {
            Session::flash('error', __('search.import_no_selection'));
            redirect('/citations');
        }

        // Stitch the picked citations back into a freetext blob and let
        // the existing AI extractor pull the structured fields out — same
        // pipeline as the regular /import flow, so dedup and the audit
        // log behave identically.
        $blob = implode("\n\n", $selected);
        $ai = ClaudeService::fromSettings()->extractReferencesFromText($blob, $reviewId);
        if (!$ai['ok']) {
            Session::flash('error', __('import.ai_failed', (string) ($ai['error'] ?? '')));
            redirect('/citations');
        }

        $imported = 0;
        $skipped  = 0;
        foreach ($ai['refs'] ?? [] as $ref) {
            try {
                Reference::create($reviewId, $ref, 'citations-normaliser', DeduplicationService::dedupKey($ref));
                $imported++;
            } catch (\Throwable) {
                $skipped++;
            }
        }
        if ($imported > 0) {
            DeduplicationService::run($reviewId);
            ActivityLog::record('references.imported', [
                'format'   => 'citations_normaliser',
                'imported' => $imported,
                'skipped'  => $skipped,
            ], $reviewId);
            Session::flash('success', __('search.import_done', $imported, $skipped));
            redirect('/reviews/' . $reviewId . '/references');
        }
        Session::flash('error', __('search.import_failed'));
        redirect('/citations');
    }

    /**
     * Entry point from /search → "Send to converter". The search page
     * posts the same selected[] JSON blobs the regular import uses; we
     * format each one as a single-line citation, stash the result in the
     * session and bounce the user to the form so they can pick a style
     * and run.
     */
    public function fromSearch(): void
    {
        $payload = (array) ($_POST['selected'] ?? []);
        $lines = [];
        foreach ($payload as $json) {
            if (!is_string($json)) {
                continue;
            }
            $ref = json_decode($json, true);
            if (!is_array($ref)) {
                continue;
            }
            $line = $this->formatRefAsText($ref);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if ($lines === []) {
            Session::flash('error', __('citations.error_no_source_selection'));
            redirect('/search?mode=external');
        }
        Session::flash('citations_seed', ['text' => implode("\n", $lines), 'style' => self::DEFAULT_STYLE]);
        redirect('/citations');
    }

    /**
     * Entry point from /reviews/{id}/references → "Convert/normalize".
     * The bulk action's modal posts the picked reference IDs + the
     * chosen style. We pull the rows from the database, format each one
     * as a citation line, run the AI immediately and render the result
     * page — the user wants the conversion right away in this flow.
     */
    public function fromReview(string $id): void
    {
        $rid    = (int) $id;
        $review = Review::find($rid);
        if ($review === null || !Review::userCanAccess($rid, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            return;
        }

        $style = (string) ($_POST['style'] ?? self::DEFAULT_STYLE);
        if (!in_array($style, self::STYLES, true)) {
            $style = self::DEFAULT_STYLE;
        }
        $ids = array_values(array_filter(
            array_map('intval', (array) ($_POST['reference_ids'] ?? [])),
            static fn (int $i): bool => $i > 0
        ));
        if ($ids === []) {
            Session::flash('error', __('citations.error_no_source_selection'));
            redirect('/reviews/' . $rid . '/references');
        }

        $lines = [];
        foreach ($ids as $refId) {
            $row = Reference::find($refId);
            if ($row === null || (int) $row['review_id'] !== $rid) {
                continue;
            }
            $ref = [
                'title'    => (string) ($row['title'] ?? ''),
                'authors'  => json_decode((string) ($row['authors_json'] ?? ''), true) ?: [],
                'year'     => $row['year']    ?? null,
                'journal'  => (string) ($row['journal']  ?? ''),
                'doi'      => (string) ($row['doi']      ?? ''),
                'pmid'     => (string) ($row['pmid']     ?? ''),
                'url'      => (string) ($row['url']      ?? ''),
                'abstract' => (string) ($row['abstract'] ?? ''),
            ];
            $line = $this->formatRefAsText($ref);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if ($lines === []) {
            Session::flash('error', __('citations.error_no_source_selection'));
            redirect('/reviews/' . $rid . '/references');
        }

        Session::flash('citations_seed', [
            'text'    => implode("\n", $lines),
            'style'   => $style,
            'autorun' => true,
        ]);
        redirect('/citations');
    }

    /* ─── Helpers ─────────────────────────────────────────────────────── */

    /** @return list<array<string,mixed>> */
    private function openReviewsForUser(): array
    {
        return array_values(Review::forUser((int) Auth::id(), 'active'));
    }

    /**
     * Render a structured reference as a one-line plain-text citation —
     * enough for the AI normaliser to identify each reference and apply
     * the requested style.
     *
     * @param array<string,mixed> $ref
     */
    private function formatRefAsText(array $ref): string
    {
        $authors = (array) ($ref['authors'] ?? []);
        $bits = [];

        if ($authors !== []) {
            $bits[] = implode(', ', array_map('strval', array_slice($authors, 0, 12)))
                . (count($authors) > 12 ? ', et al' : '');
            $bits[] = '.';
        }
        $title = trim((string) ($ref['title'] ?? ''));
        if ($title !== '') {
            $bits[] = ' ' . $title;
            if (!str_ends_with($title, '.') && !str_ends_with($title, '?') && !str_ends_with($title, '!')) {
                $bits[] = '.';
            }
        }
        $journal = trim((string) ($ref['journal'] ?? ''));
        if ($journal !== '') {
            $bits[] = ' ' . $journal . '.';
        }
        if (!empty($ref['year'])) {
            $bits[] = ' ' . (int) $ref['year'] . '.';
        }
        $doi  = trim((string) ($ref['doi']  ?? ''));
        $pmid = trim((string) ($ref['pmid'] ?? ''));
        if ($doi !== '') {
            $bits[] = ' doi:' . $doi . '.';
        }
        if ($pmid !== '') {
            $bits[] = ' PMID: ' . $pmid . '.';
        }
        return trim(implode('', $bits));
    }
}
