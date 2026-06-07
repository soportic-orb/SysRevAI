<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Services\CitationVerificationService;

/**
 * Citation verification module — exposed in the Tools hub. Lets the
 * researcher paste a list of references (one per line, free-text DOI
 * / PMID / title) or pick an active review, and runs a per-row check
 * against every enabled bibliographic database. Each row gets a
 * verdict (verified / partial / discrepant / not_found) computed
 * locally; the actual network legwork lives in
 * CitationVerificationService.
 */
final class VerificationController
{
    public function index(): void
    {
        $uid = (int) Auth::id();
        try {
            $openReviews = Review::forUser($uid, 'active');
        } catch (\Throwable) {
            $openReviews = [];
        }
        echo View::render('verification/index', [
            'openReviews' => $openReviews,
            'cap'         => CitationVerificationService::HARD_CAP,
        ]);
    }

    public function run(): void
    {
        $uid    = (int) Auth::id();
        $source = (string) ($_POST['source'] ?? 'paste');

        $refs = [];
        if ($source === 'review') {
            $rid = (int) ($_POST['review_id'] ?? 0);
            if ($rid <= 0 || !Review::userCanAccess($rid, $uid)) {
                Session::flash('error', __('verification.no_review'));
                redirect('/tools/verify-citations');
            }
            // Pull the most recent N references; the service hard-caps
            // again so we don't trust any client-side count.
            $rows = Reference::forReview($rid, '', '', 1, CitationVerificationService::HARD_CAP);
            foreach ($rows['rows'] as $r) {
                $authors = json_decode((string) ($r['authors_json'] ?? ''), true) ?: [];
                $refs[] = [
                    'title'   => (string) ($r['title']   ?? ''),
                    'year'    => isset($r['year']) ? (int) $r['year'] : null,
                    'journal' => (string) ($r['journal'] ?? ''),
                    'doi'     => (string) ($r['doi']     ?? ''),
                    'pmid'    => (string) ($r['pmid']    ?? ''),
                    'authors' => $authors,
                ];
            }
        } else {
            $paste = (string) ($_POST['paste'] ?? '');
            $refs  = $this->parsePastedLines($paste);
        }

        if ($refs === []) {
            Session::flash('error', __('verification.no_input'));
            redirect('/tools/verify-citations');
        }

        @set_time_limit(300);
        $report = (new CitationVerificationService())->verify($refs);

        ActivityLog::record('citations.verify_run', [
            'source'  => $source,
            'rows'    => count($refs),
            'counts'  => $report['counts'],
        ]);

        echo View::render('verification/report', [
            'report' => $report,
            'cap'    => CitationVerificationService::HARD_CAP,
        ]);
    }

    /**
     * One ref per line. Each line is either:
     *   - a bare DOI (10.xxx/yyy)
     *   - a bare PMID (6-9 digits)
     *   - a free-text line whose DOI / PMID we extract via regex
     *
     * @return list<array<string,mixed>>
     */
    private function parsePastedLines(string $raw): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $doi = '';
            if (preg_match('#(10\.\S+?/[^\s,;]+)#', $line, $m)) {
                $doi = rtrim($m[1], '.,;:');
            }
            $pmid = '';
            if (preg_match('/\bPMID[:\s]*(\d{6,9})\b/i', $line, $m)) {
                $pmid = $m[1];
            } elseif ($doi === '' && preg_match('/^\d{6,9}$/', $line)) {
                $pmid = $line;
            }
            // Title falls out as "whatever's left", which keeps free-text
            // pastes usable even when the user provided a citation string
            // with no identifier (verification will fall back to the
            // Jaro-Winkler match in the service).
            $title = $line;
            if ($doi !== '')  { $title = trim(str_replace($doi, '', $title), " ,.;:\t"); }
            if ($pmid !== '') { $title = (string) preg_replace('/\bPMID[:\s]*' . $pmid . '\b/i', '', $title); }
            $out[] = [
                'title' => trim($title),
                'doi'   => $doi,
                'pmid'  => $pmid,
                'year'  => null,
            ];
        }
        return $out;
    }
}
