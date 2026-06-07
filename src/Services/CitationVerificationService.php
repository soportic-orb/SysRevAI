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
 * We reuse BiblioSearchService for the network legwork — each ref is
 * searched against the aggregator with the DOI / PMID / title as the
 * query, and the first row that strongly matches the input is recorded
 * as that source's confirmation. The verdict is computed locally:
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

        // Query string priority: DOI is unique and short, PMID next,
        // free-text title last. If we have none of those there's
        // nothing to verify.
        $query = $doi !== '' ? $doi : ($pmid !== '' ? $pmid : $title);
        $sourceMatches = [];
        if ($query !== '') {
            $r = $this->biblio->search($query, 5, null);
            foreach ($r['references'] as $hit) {
                $name = self::firstTag($hit);
                if ($name === null || isset($sourceMatches[$name])) {
                    continue;
                }
                if ($this->isMatch($ref, $hit)) {
                    $sourceMatches[$name] = [
                        'title'   => trim((string) ($hit['title']   ?? '')),
                        'year'    => isset($hit['year']) && is_numeric($hit['year']) ? (int) $hit['year'] : null,
                        'journal' => trim((string) ($hit['journal'] ?? '')),
                        'doi'     => self::cleanDoi((string) ($hit['doi'] ?? '')),
                    ];
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
