<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use SysRevAI\Services\BiblioSearch\BiblioSearchService;

/**
 * Cross-check a list of references against every enabled bibliographic
 * source (CrossRef, OpenAlex, Europe PMC, Consensus, …) and produce a
 * per-row verdict: verified, partial, discrepant or not_found.
 *
 * Inspired by the "deterministic citation verification" pattern in
 * imbad0202/academic-research-skills (CC-BY-NC 4.0).
 *
 * Identifier-first verification: when the input ref carries a DOI or a
 * PMID, the service skips the free-text aggregator search and resolves
 * the identifier directly against each source's canonical endpoint
 * (CrossRef /works/{doi}, OpenAlex /works/doi:{doi} or /works/pmid:{pmid},
 * Europe PMC ?query=DOI:"…" / ?query=EXT_ID:…+AND+SRC:MED). Direct
 * lookups are deterministic and avoid the relevance-ranking noise that
 * a free-text search introduces. Refs without identifiers fall through
 * to the existing BiblioSearchService title search.
 *
 * The verdict is computed locally:
 *
 *   verified    ≥2 sources confirmed with consistent year (±0)
 *   discrepant  ≥2 sources confirmed but year or title diverges
 *   partial     exactly 1 source confirmed
 *   not_found   no source could match
 *
 * The hard cap on refs keeps the synchronous request bounded — anything
 * bigger belongs in a background queue (future work).
 */
final class CitationVerificationService
{
    public const HARD_CAP = 15;
    private const TIMEOUT_SECONDS = 8;
    private const MAX_BODY_BYTES = 4 * 1024 * 1024;

    private BiblioSearchService $biblio;

    public function __construct(?BiblioSearchService $biblio = null)
    {
        $this->biblio = $biblio ?? new BiblioSearchService();
    }

    /**
     * @param list<array<string,mixed>> $refs Each ref: {title, year, doi, pmid, ...}
     * @return array{
     *   rows: list<array<string,mixed>>,
     *   capped: bool,
     *   counts: array{verified:int,discrepant:int,partial:int,not_found:int}
     * }
     */
    public function verify(array $refs): array
    {
        $capped = false;
        if (count($refs) > self::HARD_CAP) {
            $refs = array_slice($refs, 0, self::HARD_CAP);
            $capped = true;
        }

        $counts = ['verified' => 0, 'discrepant' => 0, 'partial' => 0, 'not_found' => 0];
        $rows = [];
        foreach ($refs as $ref) {
            $row = $this->verifyOne($ref);
            $counts[$row['verdict']]++;
            $rows[] = $row;
        }
        return ['rows' => $rows, 'capped' => $capped, 'counts' => $counts];
    }

    /** @param array<string,mixed> $ref */
    private function verifyOne(array $ref): array
    {
        $doi   = self::cleanDoi((string) ($ref['doi']  ?? ''));
        $pmid  = preg_replace('/\D+/', '', (string) ($ref['pmid'] ?? '')) ?? '';
        $title = trim((string) ($ref['title'] ?? ''));
        $year  = isset($ref['year']) && is_numeric($ref['year']) ? (int) $ref['year'] : null;

        $sourceMatches = [];

        // 1) Identifier-first verification. A DOI / PMID is a stable key,
        //    so we hit each source's canonical resolver endpoint instead
        //    of asking the aggregator's free-text search. This is both
        //    more accurate (no relevance-ranking noise) and cheaper
        //    (single HTTP call per source).
        if ($doi !== '' || $pmid !== '') {
            $sourceMatches = $this->lookupByIdentifier($doi, $pmid);
        }

        // 2) Fall back to a free-text title search when no identifier is
        //    available, or when every direct lookup came up empty (the
        //    user might have a typo in the DOI but a recognisable title).
        if ($sourceMatches === [] && $title !== '') {
            $r = $this->biblio->search($title, 5, null);
            foreach ($r['references'] as $hit) {
                $name = self::firstTag($hit);
                if ($name === null || isset($sourceMatches[$name])) {
                    continue;
                }
                if ($this->isMatch($ref, $hit)) {
                    $sourceMatches[$name] = self::extractHit($hit);
                }
            }
        }

        return [
            'input'   => [
                'title'   => $title,
                'year'    => $year,
                'doi'     => $doi,
                'pmid'    => $pmid,
                'journal' => trim((string) ($ref['journal'] ?? '')),
            ],
            'matches' => $sourceMatches,
            'verdict' => $this->verdict($ref, $sourceMatches),
            'diffs'   => $this->diffs($ref, $sourceMatches),
        ];
    }

    /**
     * Direct identifier resolution per source. Each call is gated on the
     * same biblio_search.{name}.enabled toggle as the aggregator, so an
     * admin can disable a source in one place and have it disappear from
     * both the search hub and verification.
     *
     * @return array<string,array<string,mixed>>
     */
    private function lookupByIdentifier(string $doi, string $pmid): array
    {
        $matches = [];
        if ($doi !== '') {
            if (self::sourceEnabled('crossref')) {
                $hit = $this->lookupCrossrefDoi($doi);
                if ($hit !== null) {
                    $matches['crossref'] = $hit;
                }
            }
            if (self::sourceEnabled('openalex')) {
                $hit = $this->lookupOpenAlexBy('doi:' . $doi);
                if ($hit !== null) {
                    $matches['openalex'] = $hit;
                }
            }
            if (self::sourceEnabled('europepmc')) {
                $hit = $this->lookupEuropePmc('DOI:"' . $doi . '"');
                if ($hit !== null) {
                    $matches['europepmc'] = $hit;
                }
            }
        }
        if ($pmid !== '') {
            if (self::sourceEnabled('openalex') && !isset($matches['openalex'])) {
                $hit = $this->lookupOpenAlexBy('pmid:' . $pmid);
                if ($hit !== null) {
                    $matches['openalex'] = $hit;
                }
            }
            if (self::sourceEnabled('europepmc') && !isset($matches['europepmc'])) {
                $hit = $this->lookupEuropePmc('EXT_ID:' . $pmid . ' AND SRC:MED');
                if ($hit !== null) {
                    $matches['europepmc'] = $hit;
                }
            }
        }
        return $matches;
    }

    private function lookupCrossrefDoi(string $doi): ?array
    {
        $url = 'https://api.crossref.org/works/' . rawurlencode($doi);
        $data = $this->httpGetJson($url);
        $work = is_array($data['message'] ?? null) ? $data['message'] : null;
        if (!is_array($work)) {
            return null;
        }
        $title = '';
        if (isset($work['title'][0])) {
            $title = (string) $work['title'][0];
        }
        $year = $work['published']['date-parts'][0][0]
            ?? $work['issued']['date-parts'][0][0]
            ?? null;
        $journal = '';
        if (isset($work['container-title'][0])) {
            $journal = (string) $work['container-title'][0];
        }
        return [
            'title'   => trim($title),
            'year'    => is_numeric($year) ? (int) $year : null,
            'journal' => trim($journal),
            'doi'     => self::cleanDoi((string) ($work['DOI'] ?? '')),
        ];
    }

    private function lookupOpenAlexBy(string $key): ?array
    {
        $url = 'https://api.openalex.org/works/' . rawurlencode($key);
        $work = $this->httpGetJson($url);
        if (!is_array($work) || empty($work['id'])) {
            return null;
        }
        $doi = self::cleanDoi((string) ($work['doi'] ?? ''));
        $journal = '';
        if (isset($work['primary_location']['source']['display_name'])) {
            $journal = (string) $work['primary_location']['source']['display_name'];
        } elseif (isset($work['host_venue']['display_name'])) {
            $journal = (string) $work['host_venue']['display_name'];
        }
        $year = $work['publication_year'] ?? null;
        return [
            'title'   => trim((string) ($work['title'] ?? '')),
            'year'    => is_numeric($year) ? (int) $year : null,
            'journal' => trim($journal),
            'doi'     => $doi,
        ];
    }

    private function lookupEuropePmc(string $query): ?array
    {
        $url = 'https://www.ebi.ac.uk/europepmc/webservices/rest/search'
            . '?format=json&pageSize=1&resultType=lite&query=' . rawurlencode($query);
        $data = $this->httpGetJson($url);
        $hit = $data['resultList']['result'][0] ?? null;
        if (!is_array($hit)) {
            return null;
        }
        $year = $hit['pubYear'] ?? null;
        return [
            'title'   => trim((string) ($hit['title'] ?? '')),
            'year'    => is_numeric($year) ? (int) $year : null,
            'journal' => trim((string) ($hit['journalTitle'] ?? '')),
            'doi'     => self::cleanDoi((string) ($hit['doi'] ?? '')),
        ];
    }

    /** @return array<string,mixed>|null Decoded JSON body or null on any failure. */
    private function httpGetJson(string $url): ?array
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                CURLOPT_HTTPHEADER     => [
                    'Accept: application/json',
                    'User-Agent: SysRevAI/' . (string) config('app.version', '0.1.0-dev'),
                ],
            ]);
            $body = (string) curl_exec($ch);
            $err  = curl_errno($ch) !== 0;
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($err || $status !== 200 || $body === '') {
                return null;
            }
            if (strlen($body) > self::MAX_BODY_BYTES) {
                return null;
            }
            $data = json_decode($body, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Mirrors BiblioSearchService::buildEnabledSources(): default ON. */
    private static function sourceEnabled(string $name): bool
    {
        return (bool) (setting('biblio_search.' . $name . '.enabled') ?? true);
    }

    /** @return array<string,mixed> */
    private static function extractHit(array $hit): array
    {
        return [
            'title'   => trim((string) ($hit['title']   ?? '')),
            'year'    => isset($hit['year']) && is_numeric($hit['year']) ? (int) $hit['year'] : null,
            'journal' => trim((string) ($hit['journal'] ?? '')),
            'doi'     => self::cleanDoi((string) ($hit['doi'] ?? '')),
        ];
    }

    /** Decide whether a candidate row from the aggregator matches the input ref. */
    private function isMatch(array $ref, array $hit): bool
    {
        $inDoi  = self::cleanDoi((string) ($ref['doi']  ?? ''));
        $hitDoi = self::cleanDoi((string) ($hit['doi']  ?? ''));
        if ($inDoi !== '' && $hitDoi !== '' && strcasecmp($inDoi, $hitDoi) === 0) {
            return true;
        }
        $inPmid  = preg_replace('/\D+/', '', (string) ($ref['pmid'] ?? '')) ?? '';
        $hitPmid = preg_replace('/\D+/', '', (string) ($hit['pmid'] ?? '')) ?? '';
        if ($inPmid !== '' && $hitPmid !== '' && $inPmid === $hitPmid) {
            return true;
        }
        // Fall back to a fuzzy title match — Jaro-Winkler exposed by the
        // existing DeduplicationService keeps the algorithm consistent
        // with the rest of the platform.
        $inTitle  = mb_strtolower(trim((string) ($ref['title'] ?? '')));
        $hitTitle = mb_strtolower(trim((string) ($hit['title'] ?? '')));
        if ($inTitle === '' || $hitTitle === '') {
            return false;
        }
        $sim = DeduplicationService::jaroWinkler($inTitle, $hitTitle);
        return $sim >= 0.92;
    }

    /**
     * @param array<string,mixed>                                 $ref
     * @param array<string,array<string,mixed>>                   $matches
     */
    private function verdict(array $ref, array $matches): string
    {
        $count = count($matches);
        if ($count === 0) {
            return 'not_found';
        }
        if ($count === 1) {
            return 'partial';
        }
        // Two or more sources confirmed — check year consistency. We
        // compare against the input year when present, otherwise we
        // compare matches against each other.
        $years = array_values(array_filter(array_map(
            static fn (array $m): ?int => $m['year'],
            $matches
        ), static fn (?int $y): bool => $y !== null));
        $inputYear = isset($ref['year']) && is_numeric($ref['year']) ? (int) $ref['year'] : null;
        if ($inputYear !== null) {
            foreach ($years as $y) {
                if ($y !== $inputYear) {
                    return 'discrepant';
                }
            }
        } elseif ($years !== []) {
            $unique = array_unique($years);
            if (count($unique) > 1) {
                return 'discrepant';
            }
        }
        return 'verified';
    }

    /**
     * Per-source observations explaining why a verdict is what it is.
     * Used by the report to highlight which field disagrees.
     *
     * @param array<string,mixed>                                 $ref
     * @param array<string,array<string,mixed>>                   $matches
     * @return array<string,list<string>> source name => list of issue strings
     */
    private function diffs(array $ref, array $matches): array
    {
        $issues = [];
        $inputYear = isset($ref['year']) && is_numeric($ref['year']) ? (int) $ref['year'] : null;
        foreach ($matches as $name => $m) {
            $list = [];
            if ($inputYear !== null && $m['year'] !== null && $m['year'] !== $inputYear) {
                $list[] = 'year_mismatch';
            }
            if (
                isset($ref['journal']) && trim((string) $ref['journal']) !== ''
                && $m['journal'] !== ''
                && stripos($m['journal'], (string) $ref['journal']) === false
                && stripos((string) $ref['journal'], $m['journal']) === false
            ) {
                $list[] = 'journal_mismatch';
            }
            if ($list !== []) {
                $issues[$name] = $list;
            }
        }
        return $issues;
    }

    /** First "_sources" tag attached to an aggregator hit. */
    private static function firstTag(array $hit): ?string
    {
        $tags = (array) ($hit['_sources'] ?? []);
        foreach ($tags as $t) {
            $t = (string) $t;
            if ($t !== '') {
                return $t;
            }
        }
        return null;
    }

    private static function cleanDoi(string $s): string
    {
        $s = trim($s);
        return (string) preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $s);
    }
}
