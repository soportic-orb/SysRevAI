<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * CrossRef public REST API.
 *
 *   - keyword:  /works?query.bibliographic=…&rows=N
 *   - DOI:      /works/{doi}
 *
 * No API key required; the polite User-Agent (with mailto) gets us
 * routed to the higher-rate-limit pool.
 */
final class CrossrefBiblioSource extends BaseHttpBiblioSource
{
    private const ENDPOINT = 'https://api.crossref.org/works';

    public function name(): string
    {
        return 'crossref';
    }

    public function search(string $query, int $limit = 20, ?BiblioSearchFilters $filters = null): array
    {
        $isDoi = preg_match('#^10\.\d{4,9}/\S+$#i', $query) === 1;
        if ($isDoi) {
            $url = self::ENDPOINT . '/' . rawurlencode($query);
        } else {
            $params = [
                'query.bibliographic' => $query,
                'rows'                => max(1, min($limit, 50)),
                'select'              => 'DOI,title,author,issued,container-title,abstract,URL,subject,type',
            ];
            // CrossRef expresses the year range via two filter clauses
            // joined by commas. We can't honour study type, sample size
            // or SJR rank here — those degrade to post-filtering on the
            // merged result set.
            if ($filters !== null) {
                [$from, $to] = $filters->yearRangeIso();
                $clauses = [];
                if ($from !== null) {
                    $clauses[] = 'from-pub-date:' . $from;
                }
                if ($to !== null) {
                    $clauses[] = 'until-pub-date:' . $to;
                }
                if ($clauses !== []) {
                    $params['filter'] = implode(',', $clauses);
                }
            }
            $url = self::ENDPOINT . '?' . http_build_query($params);
        }

        $resp = $this->get($url);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        $data = json_decode($resp['body'], true);
        if (!is_array($data) || !isset($data['message'])) {
            return [];
        }

        $items = $isDoi ? [$data['message']] : ($data['message']['items'] ?? []);
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $out[] = $this->normalise($it);
        }
        return $out;
    }

    /** @param array<string,mixed> $r */
    private function normalise(array $r): array
    {
        $title = '';
        if (isset($r['title']) && is_array($r['title']) && $r['title'] !== []) {
            $title = trim((string) reset($r['title']));
        }

        $authors = [];
        foreach (($r['author'] ?? []) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $name = trim(((string) ($a['given'] ?? '')) . ' ' . ((string) ($a['family'] ?? '')));
            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $year = null;
        $issued = $r['issued']['date-parts'][0][0] ?? null;
        if (is_numeric($issued)) {
            $year = (int) $issued;
        }

        $journal = '';
        if (isset($r['container-title']) && is_array($r['container-title']) && $r['container-title'] !== []) {
            $journal = trim((string) reset($r['container-title']));
        }

        // CrossRef abstracts arrive as JATS XML. Strip tags for a plain-text
        // version that fits the rest of the import pipeline.
        $abstract = isset($r['abstract']) ? trim(strip_tags((string) $r['abstract'])) : '';

        return [
            'title'    => $title,
            'authors'  => $authors,
            'year'     => $year,
            'journal'  => $journal,
            'abstract' => $abstract,
            'doi'      => (string) ($r['DOI'] ?? ''),
            'pmid'     => '',
            'url'      => (string) ($r['URL'] ?? ''),
            'keywords' => isset($r['subject']) && is_array($r['subject']) ? array_values($r['subject']) : [],
        ];
    }
}
