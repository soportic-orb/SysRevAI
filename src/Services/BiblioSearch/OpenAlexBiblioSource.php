<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * OpenAlex public REST API. Single entry-point: GET /works.
 *
 *   - keyword: ?search=…&per_page=N
 *   - DOI:     /works/doi:10.…
 *   - PMID:    /works/pmid:12345678
 *
 * OpenAlex returns abstracts as an inverted index (word → positions)
 * which we rebuild into running text below.
 */
final class OpenAlexBiblioSource extends BaseHttpBiblioSource
{
    private const ENDPOINT = 'https://api.openalex.org/works';

    public function name(): string
    {
        return 'openalex';
    }

    public function search(string $query, int $limit = 20): array
    {
        $email = (string) (setting('fulltext.polite_email') ?? setting('crossref.email') ?? '');
        $params = ['per_page' => max(1, min($limit, 50))];
        if ($email !== '') {
            $params['mailto'] = $email;
        }

        $isDoi  = preg_match('#^10\.\d{4,9}/\S+$#i', $query) === 1;
        $isPmid = preg_match('#^\d{1,9}$#', $query) === 1;

        if ($isDoi) {
            $url = self::ENDPOINT . '/doi:' . rawurlencode($query) . '?' . http_build_query($params);
        } elseif ($isPmid) {
            $url = self::ENDPOINT . '/pmid:' . rawurlencode($query) . '?' . http_build_query($params);
        } else {
            $params['search'] = $query;
            $url = self::ENDPOINT . '?' . http_build_query($params);
        }

        $resp = $this->get($url);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return [];
        }

        $items = ($isDoi || $isPmid) ? [$data] : ($data['results'] ?? []);
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
        $title = (string) ($r['title'] ?? $r['display_name'] ?? '');

        $authors = [];
        foreach (($r['authorships'] ?? []) as $a) {
            $name = (string) ($a['author']['display_name'] ?? '');
            if ($name !== '') {
                $authors[] = $name;
            }
        }

        $year = isset($r['publication_year']) ? (int) $r['publication_year'] : null;

        $journal = (string) (
            $r['primary_location']['source']['display_name']
            ?? $r['host_venue']['display_name']
            ?? ''
        );

        // OpenAlex returns abstract_inverted_index: { word: [positions...] }.
        // Rebuild a flat string so the reference behaves like every other one.
        $abstract = $this->rebuildAbstract($r['abstract_inverted_index'] ?? null);

        $doi  = (string) ($r['doi'] ?? '');
        $doi  = preg_replace('#^https?://(?:dx\.)?doi\.org/#i', '', $doi) ?? $doi;

        $pmid = '';
        $pmidUrl = (string) ($r['ids']['pmid'] ?? '');
        if (preg_match('#pubmed/(\d+)#', $pmidUrl, $m) === 1) {
            $pmid = $m[1];
        }

        $url = (string) ($r['primary_location']['landing_page_url'] ?? $r['id'] ?? '');

        $keywords = [];
        foreach (($r['concepts'] ?? []) as $c) {
            $name = (string) ($c['display_name'] ?? '');
            if ($name !== '') {
                $keywords[] = $name;
            }
        }

        return [
            'title'    => $title,
            'authors'  => $authors,
            'year'     => $year,
            'journal'  => $journal,
            'abstract' => $abstract,
            'doi'      => $doi,
            'pmid'     => $pmid,
            'url'      => $url,
            'keywords' => array_slice($keywords, 0, 10),
        ];
    }

    /** @param mixed $idx */
    private function rebuildAbstract(mixed $idx): string
    {
        if (!is_array($idx) || $idx === []) {
            return '';
        }
        $positions = [];
        foreach ($idx as $word => $pos) {
            if (!is_array($pos)) {
                continue;
            }
            foreach ($pos as $p) {
                $positions[(int) $p] = (string) $word;
            }
        }
        ksort($positions);
        return trim(implode(' ', $positions));
    }
}
