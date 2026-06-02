<?php

declare(strict_types=1);

namespace SysRevAI\Services\BiblioSearch;

/**
 * Europe PMC public REST API.
 *
 *   - keyword: ?query=…
 *   - DOI:     ?query=DOI:10.…
 *   - PMID:    ?query=EXT_ID:12345678 AND SRC:MED
 *
 * Europe PMC is particularly useful for biomedical hits: it carries
 * PMIDs alongside DOIs and exposes the abstract in plain text.
 */
final class EuropePmcBiblioSource extends BaseHttpBiblioSource
{
    private const ENDPOINT = 'https://www.ebi.ac.uk/europepmc/webservices/rest/search';

    public function name(): string
    {
        return 'europepmc';
    }

    public function search(string $query, int $limit = 20, ?BiblioSearchFilters $filters = null): array
    {
        $q = $query;
        if (preg_match('#^10\.\d{4,9}/\S+$#i', $query) === 1) {
            $q = 'DOI:' . $query;
        } elseif (preg_match('#^\d{1,9}$#', $query) === 1) {
            $q = 'EXT_ID:' . $query . ' AND SRC:MED';
        }

        // Europe PMC has no separate filter param — narrowing happens by
        // appending Lucene-style clauses to the query string. Only the
        // year range is portable enough to be worth doing here.
        if ($filters !== null && ($filters->yearMin !== null || $filters->yearMax !== null)) {
            $min = $filters->yearMin ?? 1800;
            $max = $filters->yearMax ?? ((int) date('Y') + 1);
            $q .= ' AND (PUB_YEAR:[' . $min . ' TO ' . $max . '])';
        }

        $url = self::ENDPOINT . '?' . http_build_query([
            'query'    => $q,
            'format'   => 'json',
            'pageSize' => max(1, min($limit, 50)),
            'resultType' => 'core',
        ]);

        $resp = $this->get($url);
        if ($resp['status'] !== 200 || $resp['body'] === '') {
            return [];
        }
        $data = json_decode($resp['body'], true);
        if (!is_array($data)) {
            return [];
        }
        $items = $data['resultList']['result'] ?? [];
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
        $authors = [];
        if (isset($r['authorList']['author']) && is_array($r['authorList']['author'])) {
            foreach ($r['authorList']['author'] as $a) {
                $name = trim((string) ($a['fullName'] ?? ''));
                if ($name !== '') {
                    $authors[] = $name;
                }
            }
        } elseif (isset($r['authorString'])) {
            // Fallback: pre-formatted "Smith J, Doe A, …" string.
            foreach (explode(',', (string) $r['authorString']) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $authors[] = $name;
                }
            }
        }

        $keywords = [];
        if (isset($r['keywordList']['keyword']) && is_array($r['keywordList']['keyword'])) {
            foreach ($r['keywordList']['keyword'] as $k) {
                $kw = trim((string) $k);
                if ($kw !== '') {
                    $keywords[] = $kw;
                }
            }
        }

        $year = null;
        if (!empty($r['pubYear']) && is_numeric($r['pubYear'])) {
            $year = (int) $r['pubYear'];
        }

        $pmid = (string) ($r['pmid'] ?? '');
        $doi  = (string) ($r['doi'] ?? '');

        $url = '';
        if ($pmid !== '') {
            $url = 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid . '/';
        } elseif ($doi !== '') {
            $url = 'https://doi.org/' . $doi;
        }

        return [
            'title'    => trim((string) ($r['title'] ?? '')),
            'authors'  => $authors,
            'year'     => $year,
            'journal'  => trim((string) ($r['journalTitle'] ?? '')),
            'abstract' => trim((string) ($r['abstractText'] ?? '')),
            'doi'      => $doi,
            'pmid'     => $pmid,
            'url'      => $url,
            'keywords' => array_slice($keywords, 0, 10),
        ];
    }
}
