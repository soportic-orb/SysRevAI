<?php

declare(strict_types=1);

namespace SysRevAI\Controllers;

use SysRevAI\Core\Auth;
use SysRevAI\Core\Database;
use SysRevAI\Core\Session;
use SysRevAI\Core\View;
use SysRevAI\Models\ActivityLog;
use SysRevAI\Models\Reference;
use SysRevAI\Models\Review;
use SysRevAI\Services\BiblioSearch\BiblioSearchFilters;
use SysRevAI\Services\BiblioSearch\BiblioSearchService;
use SysRevAI\Services\DeduplicationService;
use SysRevAI\Services\EvidenceHuntService;

/**
 * Cross-review reference search with three modes:
 *
 *   - "local" (default): probes the user's own references via the
 *     FULLTEXT index on title/abstract plus LIKE on the identifier
 *     columns.
 *   - "external": fans the query out to CrossRef, OpenAlex and Europe PMC
 *     via BiblioSearchService and offers per-row / bulk import into one
 *     of the user's open reviews.
 *   - "evidencehunt": AI-assisted PubMed discovery — accepts a free-text
 *     question (or generates one from a review's PICO) and shows an LLM
 *     answer together with the documents it's grounded on. Complementary
 *     to PRISMA, not a replacement.
 */
final class SearchController
{
    private const VALID_MODES = ['local', 'external', 'evidencehunt'];

    // Persist the latest EvidenceHunt output_id in the session so the
    // user can post a follow-up question against the same conversation
    // without having to copy the id around the URL.
    private const EH_OUTPUT_KEY = '_eh_output_id';

    public function index(): void
    {
        $q    = trim((string) ($_GET['q'] ?? ''));
        $mode = (string) ($_GET['mode'] ?? 'local');
        if (!in_array($mode, self::VALID_MODES, true)) {
            $mode = 'local';
        }

        $results       = [];
        $externalMeta  = [];
        $externalError = null;
        $eh = [
            'answer'            => '',
            'question'          => '',
            'output_id'         => null,
            'credits_used'      => null,
            'credits_remaining' => null,
            'error'             => null,
            'review_id'         => 0,
            'elaborate'         => false,
        ];

        // Eligibility filters for external searches. When the user
        // didn't tick any control AND they're searching in the context
        // of an accessible review (?review_id=…), we seed from the
        // protocol so the first run already reflects the eligibility
        // criteria. The form's controls win on every later refinement.
        $filters = BiblioSearchFilters::fromArray($_GET);
        if ($mode === 'external' && $filters->isEmpty()) {
            $reviewId = (int) ($_GET['review_id'] ?? 0);
            if ($reviewId > 0 && Review::userCanAccess($reviewId, (int) Auth::id())) {
                $rv = Review::find($reviewId);
                if (is_array($rv)) {
                    $filters = BiblioSearchFilters::fromProtocol($rv);
                }
            }
        }

        if ($mode === 'evidencehunt') {
            // EvidenceHunt is a separate lane: free-text question, with
            // an optional review_id that auto-generates the question from
            // the protocol's PICO. No filter form, no FULLTEXT.
            $r = $this->runEvidenceHunt($eh);
            $results = $r['docs'];
            $eh      = $r['meta'];
        } elseif ($q !== '') {
            try {
                if ($mode === 'external') {
                    $r = (new BiblioSearchService())->search($q, 15, $filters);
                    $results      = $r['references'];
                    $externalMeta = $r['sources'];
                } else {
                    $results = $this->searchLocal($q, (int) Auth::id());
                }
            } catch (\Throwable $e) {
                $results = [];
                if ($mode === 'external') {
                    $externalError = $e->getMessage();
                }
            }
        }

        $openReviews = ($mode === 'external' || $mode === 'evidencehunt')
            ? array_values(array_filter(
                Review::forUser((int) Auth::id(), 'active'),
                fn (array $rv): bool => Review::userCanAccess((int) $rv['id'], (int) Auth::id())
            ))
            : [];

        // One-shot outcomes carried from the previous import POST — keyed
        // by dedup_key so the view can paint check / X icons next to the
        // rows the user just touched. Pulled here (not from the view) so
        // each render owns its own state.
        $outcomes = Session::pullFlash('search_outcomes');
        if (!is_array($outcomes) || !is_array($outcomes['map'] ?? null)) {
            $outcomes = null;
        }

        echo View::render('search/index', [
            'q'             => $q,
            'mode'          => $mode,
            'results'       => $results,
            'externalMeta'  => $externalMeta,
            'externalError' => $externalError,
            'openReviews'   => $openReviews,
            'outcomes'      => $outcomes,
            'filters'       => $filters,
            'studyTypes'    => BiblioSearchFilters::STUDY_TYPES,
            'eh'            => $eh,
        ]);
    }

    /**
     * Drive EvidenceHunt. Returns the normalised docs (so the existing
     * external-row markup can paint them) and a meta block with the
     * answer, credits and follow-up output_id.
     *
     * @return array{docs:list<array<string,mixed>>, meta:array<string,mixed>}
     */
    private function runEvidenceHunt(array $meta): array
    {
        $svc = new EvidenceHuntService();
        $uid = (int) Auth::id();

        $q          = trim((string) ($_GET['q']         ?? ''));
        $followQ    = trim((string) ($_GET['follow_q']  ?? ''));
        $reviewId   = (int) ($_GET['review_id']         ?? 0);
        $elaborate  = !empty($_GET['elaborate']);

        // PICO → question with a fixed template (no Claude call). The
        // user can still override by typing a question; a typed value
        // wins over the auto-generated one.
        if ($q === '' && $reviewId > 0 && Review::userCanAccess($reviewId, $uid)) {
            $rv = Review::find($reviewId);
            if (is_array($rv)) {
                $q = EvidenceHuntService::questionFromPico($rv);
            }
        }

        // Follow-up against the previous turn — needs the stored output_id.
        $prevOutputId = (string) (Session::get(self::EH_OUTPUT_KEY) ?? '');
        $isFollowUp   = $followQ !== '' && $prevOutputId !== '';
        $effectiveQ   = $isFollowUp ? $followQ : $q;

        $meta['question']  = $effectiveQ;
        $meta['review_id'] = $reviewId;
        $meta['elaborate'] = $elaborate;
        $meta['output_id'] = $prevOutputId !== '' ? $prevOutputId : null;

        if ($effectiveQ === '') {
            return ['docs' => [], 'meta' => $meta];
        }

        $resp = $svc->query($effectiveQ, [
            'elaborate'    => $elaborate,
            'userLanguage' => current_locale(),
            'followUp'     => $isFollowUp,
            'outputId'     => $isFollowUp ? $prevOutputId : '',
        ]);

        $meta['answer']            = (string) $resp['answer'];
        $meta['error']             = $resp['error'];
        $meta['credits_used']      = $resp['credits_used'];
        $meta['credits_remaining'] = $resp['credits_remaining'];
        if (!empty($resp['output_id'])) {
            Session::set(self::EH_OUTPUT_KEY, $resp['output_id']);
            $meta['output_id'] = $resp['output_id'];
        }

        ActivityLog::record('evidencehunt.query', [
            'review_id'         => $reviewId > 0 ? $reviewId : null,
            'follow_up'         => $isFollowUp,
            'elaborate'         => $elaborate,
            'credits_used'      => $resp['credits_used'],
            'credits_remaining' => $resp['credits_remaining'],
            'ok'                => $resp['ok'],
            'error'             => $resp['error'],
            'docs_returned'     => count($resp['docs']),
        ], $reviewId > 0 ? $reviewId : null);

        return ['docs' => $resp['docs'], 'meta' => $meta];
    }

    /**
     * POST /search/import — accept either one (`single`) reference or a
     * list of selected ones (`selected[]`), validate the destination
     * review, then persist via the same path as the file-based importer
     * so dedup keys and activity logs stay consistent.
     *
     * Records a per-reference outcome (imported / duplicate / error)
     * keyed by dedup_key so the search page can paint a green check on
     * the rows that landed and a red X on the ones that didn't.
     */
    public function importToReview(): void
    {
        $reviewId = (int) ($_POST['review_id'] ?? 0);
        if ($reviewId <= 0) {
            Session::flash('error', __('search.import_no_review'));
            redirect($this->backHref());
        }

        $review = Review::find($reviewId);
        if ($review === null || !Review::userCanAccess($reviewId, (int) Auth::id())) {
            http_response_code(403);
            echo View::render('errors/403', [], 'layouts/auth');
            return;
        }

        $isSingle = false;
        $payload  = [];
        if (isset($_POST['single']) && is_string($_POST['single']) && $_POST['single'] !== '') {
            $payload  = [$_POST['single']];
            $isSingle = true;
        } elseif (isset($_POST['selected']) && is_array($_POST['selected'])) {
            $payload = array_values(array_filter($_POST['selected'], 'is_string'));
        }

        if ($payload === []) {
            Session::flash('error', __('search.import_no_selection'));
            redirect($this->backHref());
        }

        // Origin hint on the form lets us label rows from EvidenceHunt
        // distinctly in references.source_file — the dedup / activity-log
        // flow is identical to the regular external import.
        $origin = (string) ($_POST['origin'] ?? 'biblio-search');
        $sourceTag = $origin === 'evidencehunt' ? 'evidencehunt' : 'biblio-search';

        /** @var array<string,string> $outcomes  dedup_key => 'imported'|'duplicate'|'error' */
        $outcomes  = [];
        $imported  = 0;
        $duplicate = 0;
        $errored   = 0;

        foreach ($payload as $json) {
            $ref = json_decode($json, true);
            if (!is_array($ref) || trim((string) ($ref['title'] ?? '')) === '') {
                $errored++;
                continue;
            }
            $ref      = $this->sanitise($ref);
            $dedupKey = DeduplicationService::dedupKey($ref);
            $marker   = $dedupKey !== '' ? $dedupKey : 'doi:' . (string) ($ref['doi'] ?? '') . '|pmid:' . (string) ($ref['pmid'] ?? '');

            if (Reference::existsInReview($reviewId, $dedupKey, (string) $ref['doi'], (string) $ref['pmid'])) {
                $outcomes[$marker] = 'duplicate';
                $duplicate++;
                continue;
            }

            try {
                Reference::create($reviewId, $ref, $sourceTag, $dedupKey);
                $outcomes[$marker] = 'imported';
                $imported++;
            } catch (\Throwable $e) {
                $outcomes[$marker] = 'error';
                $errored++;
            }
        }

        if ($imported > 0) {
            $dedup = DeduplicationService::run($reviewId);
            ActivityLog::record('references.imported', [
                'format'     => $origin === 'evidencehunt' ? 'evidencehunt' : 'biblio_search',
                'imported'   => $imported,
                'duplicates' => $duplicate,
                'errors'     => $errored,
                'fuzzy_dups' => $dedup['fuzzy'] ?? 0,
            ], $reviewId);
        }

        // Stash per-row outcomes so the GET re-render can paint check / X
        // icons next to the matching reference rows.
        Session::flash('search_outcomes', [
            'review_id' => $reviewId,
            'map'       => $outcomes,
        ]);

        // Single-import vs. bulk get distinct flash phrasing — the user
        // who clicked the per-row button only ever cares about the one
        // reference they touched.
        if ($isSingle) {
            $only = $outcomes[array_key_first($outcomes)] ?? 'error';
            match ($only) {
                'imported'  => Session::flash('success', __('search.import_one_done')),
                'duplicate' => Session::flash('error',   __('search.import_one_duplicate')),
                default     => Session::flash('error',   __('search.import_failed')),
            };
        } elseif ($imported === 0 && $duplicate === 0) {
            Session::flash('error', __('search.import_failed'));
        } else {
            Session::flash('success', __('search.import_done_breakdown', $imported, $duplicate, $errored));
        }

        redirect($this->backHref());
    }

    /* ─── Helpers ──────────────────────────────────────────────────── */

    private function backHref(): string
    {
        $q    = trim((string) ($_POST['back_q'] ?? ''));
        $mode = (string) ($_POST['back_mode'] ?? 'external');
        if (!in_array($mode, self::VALID_MODES, true)) {
            $mode = 'external';
        }
        // EvidenceHunt costs credits per call: returning to /search with
        // the question would silently re-fire the query. Drop it on the
        // import round-trip; the user lands on the EH tab with an empty
        // form and the import flash visible.
        $params = ['mode' => $mode];
        if ($mode !== 'evidencehunt') {
            $params['q'] = $q;
        }
        return '/search?' . http_build_query($params);
    }

    /**
     * Whitelist the fields we accept from a posted reference. The form
     * payload comes from the browser, so we treat it as untrusted: cap
     * sizes, normalise arrays, and silently drop everything else.
     *
     * @param array<string,mixed> $ref
     * @return array<string,mixed>
     */
    private function sanitise(array $ref): array
    {
        $clip = static fn (string $s, int $max): string =>
            mb_substr(trim($s), 0, $max);

        $authors = [];
        foreach ((array) ($ref['authors'] ?? []) as $a) {
            $a = $clip((string) $a, 255);
            if ($a !== '') {
                $authors[] = $a;
            }
            if (count($authors) >= 50) {
                break;
            }
        }
        $keywords = [];
        foreach ((array) ($ref['keywords'] ?? []) as $k) {
            $k = $clip((string) $k, 100);
            if ($k !== '') {
                $keywords[] = $k;
            }
            if (count($keywords) >= 30) {
                break;
            }
        }

        return [
            'title'    => $clip((string) ($ref['title']    ?? ''), 1024),
            'authors'  => $authors,
            'year'     => isset($ref['year']) && is_numeric($ref['year']) ? (int) $ref['year'] : null,
            'journal'  => $clip((string) ($ref['journal']  ?? ''), 255),
            'abstract' => $clip((string) ($ref['abstract'] ?? ''), 32_000),
            'doi'      => $clip((string) ($ref['doi']      ?? ''), 255),
            'pmid'     => $clip((string) ($ref['pmid']     ?? ''), 32),
            'url'      => $clip((string) ($ref['url']      ?? ''), 1024),
            'keywords' => $keywords,
        ];
    }

    /** @return array<int,array> */
    private function searchLocal(string $q, int $userId): array
    {
        $refs    = Database::table('references');
        $reviews = Database::table('reviews');
        $members = Database::table('review_users');

        $idQuery = $this->stripDoiPrefix($q);

        $useFt  = mb_strlen($q) >= 4;
        $like   = '%' . $q . '%';
        $idLike = '%' . $idQuery . '%';

        if ($useFt) {
            $score  = 'MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)';
            $where  = '( MATCH(r.title, r.abstract) AGAINST (? IN NATURAL LANGUAGE MODE)
                         OR r.title LIKE ? OR r.abstract LIKE ?
                         OR r.doi LIKE ? OR r.pmid LIKE ? )';
            $params = [$q, $userId, $q, $like, $like, $idLike, $like, $userId, $userId];
        } else {
            $score  = '1';
            $where  = '(r.title LIKE ? OR r.abstract LIKE ? OR r.doi LIKE ? OR r.pmid LIKE ?)';
            $params = [$userId, $like, $like, $idLike, $like, $userId, $userId];
        }

        $sql = "SELECT r.id, r.review_id, r.title, r.year, r.journal, r.status,
                       r.authors_json, rv.title AS review_title, ({$score}) AS score
                FROM `{$refs}` r
                JOIN `{$reviews}` rv ON rv.id = r.review_id
                LEFT JOIN `{$members}` ru ON ru.review_id = rv.id AND ru.user_id = ? AND ru.removed_at IS NULL
                WHERE {$where} AND (rv.owner_id = ? OR ru.user_id = ?)
                GROUP BY r.id
                ORDER BY score DESC, r.id DESC
                LIMIT 50";

        return Database::select($sql, $params);
    }

    private function stripDoiPrefix(string $q): string
    {
        $q = trim($q);
        $patterns = [
            '#^https?://(?:dx\.)?doi\.org/#i',
            '#^doi\s*:\s*#i',
        ];
        foreach ($patterns as $p) {
            $q = (string) preg_replace($p, '', $q, 1);
        }
        return trim($q);
    }
}
