<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * Fan a query out to every enabled bibliographic-database adapter,
 * merge the responses, and deduplicate on stable identifiers.
 *
 * The merge order prefers richer rows: when two sources return the same
 * DOI we keep the one with the longest abstract (i.e. the most
 * informative record for the screener to look at).
 */
final class BiblioSearchService
{
    public const SOURCES = ['crossref', 'openalex', 'europepmc', 'consensus'];
    private const DEFAULT_LIMIT_PER_SOURCE = 15;
    private const HARD_RESULT_CAP = 60;

    /** @var list<BiblioSearchSourceInterface> */
    private array $sources;

    public function __construct(?array $sources = null)
    {
        $this->sources = $sources ?? $this->buildEnabledSources();
    }

    /**
     * Run the query against every enabled source. Returns the merged,
     * de-duplicated list of references plus a per-source breakdown for
     * UI badges.
     *
     * @return array{
     *     references: list<array<string,mixed>>,
     *     sources:    array<string,array{count:int,error:?string}>
     * }
     */
    public function search(string $query, int $perSource = self::DEFAULT_LIMIT_PER_SOURCE, ?BiblioSearchFilters $filters = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['references' => [], 'sources' => []];
        }

        $merged = [];
        $sources = [];
        foreach ($this->sources as $src) {
            $name = $src->name();
            try {
                $hits = $src->search($query, $perSource, $filters);
            } catch (\Throwable $e) {
                $sources[$name] = ['count' => 0, 'error' => 'exception'];
                continue;
            }
            $sources[$name] = ['count' => count($hits), 'error' => null];
            foreach ($hits as $row) {
                $key = $this->dedupKey($row);
                if (isset($merged[$key])) {
                    $merged[$key] = $this->prefer($merged[$key], $this->tagSource($row, $name));
                } else {
                    $merged[$key] = $this->tagSource($row, $name);
                }
                if (count($merged) >= self::HARD_RESULT_CAP) {
                    break 2;
                }
            }
        }

        // Score every row against the query, attach the score + a 1..5
        // bucket the view renders as a dot strip, then sort by raw score
        // descending so the most relevant hits float to the top. Rows
        // that hit on identifier or several keywords beat rows that just
        // share a journal name.
        $values = array_values($merged);
        $values = array_map(function (array $row) use ($query): array {
            $score = $this->relevance($query, $row);
            $row['_relevance']       = $score;
            $row['_relevance_dots']  = self::dotsFor($score);
            return $row;
        }, $values);

        usort($values, static function (array $a, array $b): int {
            $rel = ($b['_relevance'] ?? 0) <=> ($a['_relevance'] ?? 0);
            if ($rel !== 0) {
                return $rel;
            }
            // Same relevance — break ties on identifier completeness so
            // the canonical version of a paper still ranks above a
            // title-only stub.
            $idScore = static fn (array $r): int =>
                (($r['doi']  ?? '') !== '' ? 2 : 0) +
                (($r['pmid'] ?? '') !== '' ? 1 : 0);
            return $idScore($b) <=> $idScore($a);
        });

        return ['references' => $values, 'sources' => $sources];
    }

    /**
     * Heuristic 0..N relevance against the user query. Title matches
     * outweigh abstract matches; an exact identifier paste beats
     * everything; rows returned by more than one source get a small
     * confidence boost.
     *
     * @param array<string,mixed> $row
     */
    private function relevance(string $query, array $row): int
    {
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return 0;
        }

        // Exact identifier paste — the user typed a DOI / PMID and the
        // row matches it. Nothing else can beat this signal.
        $doi  = mb_strtolower(trim((string) ($row['doi']  ?? '')));
        $pmid = trim((string) ($row['pmid'] ?? ''));
        if ($doi !== '' && $q === $doi) {
            return 100;
        }
        if ($pmid !== '' && $q === $pmid) {
            return 100;
        }

        $tokens = preg_split('/\W+/u', $q) ?: [];
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            static fn (string $t): bool => mb_strlen($t) >= 3
        )));
        if ($tokens === []) {
            // Short / pure-punctuation query — fall back to a baseline
            // so the sort still groups identifier-rich rows up top.
            return 0;
        }

        $title    = mb_strtolower((string) ($row['title']    ?? ''));
        $abstract = mb_strtolower((string) ($row['abstract'] ?? ''));
        $journal  = mb_strtolower((string) ($row['journal']  ?? ''));

        $score = 0;
        foreach ($tokens as $t) {
            if ($title !== '' && str_contains($title, $t)) {
                $score += 3;
            }
            if ($abstract !== '' && str_contains($abstract, $t)) {
                $score += 1;
            }
            if ($journal !== '' && str_contains($journal, $t)) {
                $score += 1;
            }
        }

        // Multi-source bonus: 2-3 databases returning the same row is a
        // strong signal of canonical relevance.
        $sources = (array) ($row['_sources'] ?? []);
        if (count($sources) > 1) {
            $score += count($sources) - 1;
        }

        return $score;
    }

    /** Map a raw score to the 1..5 bucket the view renders as dots. */
    private static function dotsFor(int $score): int
    {
        return match (true) {
            $score >= 100 => 5,
            $score >= 12  => 5,
            $score >= 8   => 4,
            $score >= 5   => 3,
            $score >= 2   => 2,
            default       => 1,
        };
    }

    /** @return list<BiblioSearchSourceInterface> */
    private function buildEnabledSources(): array
    {
        $out = [];
        foreach (self::SOURCES as $name) {
            // The toggle key mirrors the FullTextRetrieval convention so
            // that a single setting can govern both subsystems if needed.
            $enabled = (bool) (setting('biblio_search.' . $name . '.enabled') ?? true);
            if (!$enabled) {
                continue;
            }
            // Consensus is gated additionally on a configured API key —
            // skipping it silently when missing keeps the other three
            // databases serving the search.
            if ($name === 'consensus' && trim((string) (setting('consensus.api_key') ?? '')) === '') {
                continue;
            }
            $out[] = match ($name) {
                'crossref'  => new CrossrefBiblioSource(),
                'openalex'  => new OpenAlexBiblioSource(),
                'europepmc' => new EuropePmcBiblioSource(),
                'consensus' => new ConsensusBiblioSource(),
            };
        }
        return $out;
    }

    /** @param array<string,mixed> $row */
    private function dedupKey(array $row): string
    {
        $doi = strtolower(trim((string) ($row['doi'] ?? '')));
        if ($doi !== '') {
            return 'doi:' . $doi;
        }
        $pmid = trim((string) ($row['pmid'] ?? ''));
        if ($pmid !== '') {
            return 'pmid:' . $pmid;
        }
        // Fall back to a title hash so two title-only rows from different
        // sources still collapse into one entry instead of duplicating.
        $title = preg_replace('/\s+/u', ' ', strtolower(trim((string) ($row['title'] ?? '')))) ?? '';
        return 'title:' . md5($title);
    }

    /**
     * Pick the more informative of two duplicate rows. The merge isn't
     * deep: we prefer the one with the longer abstract, but inherit
     * missing identifiers from the other side so the result carries
     * both a DOI and a PMID whenever any source provided either.
     *
     * @param array<string,mixed> $existing
     * @param array<string,mixed> $incoming
     * @return array<string,mixed>
     */
    private function prefer(array $existing, array $incoming): array
    {
        $base = strlen((string) ($incoming['abstract'] ?? '')) > strlen((string) ($existing['abstract'] ?? ''))
            ? $incoming
            : $existing;

        foreach (['doi', 'pmid', 'url', 'journal'] as $field) {
            if (trim((string) ($base[$field] ?? '')) === '') {
                $base[$field] = $existing[$field] ?? $incoming[$field] ?? '';
            }
        }

        // Union the source attribution so the UI can show which DBs hit.
        $sources = array_values(array_unique(array_merge(
            (array) ($existing['_sources'] ?? []),
            (array) ($incoming['_sources'] ?? []),
        )));
        $base['_sources'] = $sources;

        return $base;
    }

    /** @param array<string,mixed> $row */
    private function tagSource(array $row, string $name): array
    {
        $row['_sources'] = [$name];
        return $row;
    }
}
