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
    public const SOURCES = ['crossref', 'openalex', 'europepmc'];
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
    public function search(string $query, int $perSource = self::DEFAULT_LIMIT_PER_SOURCE): array
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
                $hits = $src->search($query, $perSource);
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

        // Stable sort: DOI > PMID > title-based rows.
        $values = array_values($merged);
        usort($values, static function (array $a, array $b): int {
            $score = static fn (array $r): int =>
                (($r['doi']  ?? '') !== '' ? 2 : 0) +
                (($r['pmid'] ?? '') !== '' ? 1 : 0);
            return $score($b) <=> $score($a);
        });

        return ['references' => $values, 'sources' => $sources];
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
            $out[] = match ($name) {
                'crossref'  => new CrossrefBiblioSource(),
                'openalex'  => new OpenAlexBiblioSource(),
                'europepmc' => new EuropePmcBiblioSource(),
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
