<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * Consensus quick-search API. Authenticated with a single account-level
 * API key, sent ONLY as the `x-api-key` header — never in the query
 * string, never logged. The key is loaded from setting('consensus.api_key')
 * (stored encrypted in the integrations group); when missing, this
 * source silently returns no hits so an unconfigured install keeps
 * working with the other three databases.
 *
 *   GET https://api.consensus.app/v1/quick_search
 *     query=…           (free text)
 *     year_min, year_max
 *     human, sample_size_min, sjr_max  (sjr_max is 1..4, 1 = best)
 *     exclude_preprints
 *     study_types=…&study_types=…       (repeated, NOT bracketed)
 *
 * Consensus expresses every filter in BiblioSearchFilters natively, so
 * this adapter is the highest-fidelity source. study_type / takeaway
 * fields in the response depend on the user's plan and may be absent —
 * the normaliser degrades gracefully.
 */
final class ConsensusBiblioSource extends BaseHttpBiblioSource
{
    private const ENDPOINT = 'https://api.consensus.app/v1/quick_search';

    public function name(): string
    {
        return 'consensus';
    }

    public function search(string $query, int $limit = 20, ?BiblioSearchFilters $filters = null): array
    {
        $key = (string) (setting('consensus.api_key') ?? '');
        if ($key === '') {
            return [];
        }
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $url = self::ENDPOINT . '?' . $this->buildQuery($query, $filters);

        $resp = $this->get($url, ['x-api-key: ' . $key]);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return [];
        }
        $items = $data['results'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $out[] = $this->normalise($it);
            // No page-size parameter upstream — the plan caps the count
            // (~20). We still cap on the client so the aggregator never
            // sees more than the caller asked for.
            if (count($out) >= max(1, $limit)) {
                break;
            }
        }
        return $out;
    }

    /**
     * Build the query string. study_types must be REPEATED
     * (study_types=rct&study_types=…), which http_build_query() can only
     * express with bracketed indices — so we append it manually.
     */
    private function buildQuery(string $query, ?BiblioSearchFilters $filters): string
    {
        $pairs = ['query' => $query];

        if ($filters !== null) {
            if ($filters->yearMin !== null) {
                $pairs['year_min'] = (string) $filters->yearMin;
            }
            if ($filters->yearMax !== null) {
                $pairs['year_max'] = (string) $filters->yearMax;
            }
            if ($filters->human !== null) {
                $pairs['human'] = $filters->human ? 'true' : 'false';
            }
            if ($filters->sampleSizeMin !== null) {
                $pairs['sample_size_min'] = (string) $filters->sampleSizeMin;
            }
            if ($filters->sjrMax !== null) {
                $pairs['sjr_max'] = (string) $filters->sjrMax;
            }
            if ($filters->excludePreprints !== null) {
                $pairs['exclude_preprints'] = $filters->excludePreprints ? 'true' : 'false';
            }
        }

        $qs = http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);

        if ($filters !== null && $filters->studyTypes !== []) {
            $extras = [];
            foreach ($filters->studyTypes as $t) {
                $extras[] = 'study_types=' . rawurlencode($t);
            }
            if ($extras !== []) {
                $qs .= ($qs === '' ? '' : '&') . implode('&', $extras);
            }
        }
        return $qs;
    }

    /** @param array<string,mixed> $r */
    private function normalise(array $r): array
    {
        $authors = [];
        foreach ((array) ($r['authors'] ?? []) as $a) {
            $name = trim((string) $a);
            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $year = null;
        if (isset($r['publish_year']) && is_numeric($r['publish_year'])) {
            $year = (int) $r['publish_year'];
        }

        $doi = (string) ($r['doi'] ?? '');
        // Some payloads ship the resolver URL; reduce it to the bare DOI
        // so dedup keys agree with the other sources.
        $doi = (string) preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $doi);

        $url = trim((string) ($r['url'] ?? ''));
        if ($url === '' && $doi !== '') {
            $url = 'https://doi.org/' . $doi;
        }

        return [
            'title'    => trim((string) ($r['title'] ?? '')),
            'authors'  => $authors,
            'year'     => $year,
            'journal'  => trim((string) ($r['journal_name'] ?? '')),
            'abstract' => trim((string) ($r['abstract'] ?? '')),
            'doi'      => $doi,
            'pmid'     => '',
            'url'      => $url,
            'keywords' => [],
        ];
    }
}
