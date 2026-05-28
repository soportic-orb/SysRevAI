<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Europe PMC — DOI/PMID → JATS XML and/or PDF.
 *
 * Search:      GET .../rest/search?query=DOI:{doi}&format=json
 * XML:         GET .../rest/{source}/{id}/fullTextXML
 * PDF:         GET .../rest/article/{source}/{id}/pdf
 */
final class EuropePmcSource extends BaseHttpSource
{
    private const BASE = 'https://www.ebi.ac.uk/europepmc/webservices/rest';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'europepmc';
    }

    public function supports(array $reference): bool
    {
        return $this->normalizeDoi($reference['doi'] ?? null) !== null
            || !empty($reference['pmid']);
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $hit = $this->lookup($reference);
        if ($hit === null) {
            return null;
        }
        $source = (string) ($hit['source'] ?? '');
        $id     = (string) ($hit['id'] ?? '');
        if ($source === '' || $id === '') {
            return null;
        }

        $pdfUrl = null;
        $xmlUrl = null;
        $isOpenAccess = ($hit['isOpenAccess'] ?? '') === 'Y';
        if ($isOpenAccess) {
            $pdfUrl = self::BASE . '/article/' . rawurlencode($source) . '/' . rawurlencode($id) . '/pdf';
            $xmlUrl = self::BASE . '/' . rawurlencode($source) . '/' . rawurlencode($id) . '/fullTextXML';
        }
        if ($pdfUrl === null && $xmlUrl === null) {
            return null;
        }

        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            xmlContent: null, // The orchestrator downloads on demand.
            licenseType: (string) ($hit['license'] ?? 'unknown'),
            version: 'published',
            metadata: [
                'source'        => $source,
                'epmc_id'       => $id,
                'pmid'          => $hit['pmid'] ?? null,
                'pmcid'         => $hit['pmcid'] ?? null,
                'xml_url'       => $xmlUrl,
                'is_open_access' => $isOpenAccess,
            ],
            confidence: 0.9,
        );
    }

    public function verifyConnection(): array
    {
        $url = self::BASE . '/search?query=DOI:' . rawurlencode(self::VERIFY_DOI) . '&format=json&resultType=lite';
        $res = $this->get($url);
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'Europe PMC OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return ['requests' => 10, 'per_seconds' => 1];
    }

    /** Find the article in Europe PMC and return its source/id row, or null. */
    private function lookup(array $reference): ?array
    {
        $doi  = $this->normalizeDoi($reference['doi'] ?? null);
        $pmid = preg_replace('/\D/', '', (string) ($reference['pmid'] ?? '')) ?? '';

        if ($doi !== null) {
            $query = 'DOI:' . $doi;
        } elseif ($pmid !== '') {
            $query = 'EXT_ID:' . $pmid . ' AND SRC:MED';
        } else {
            return null;
        }

        $url = self::BASE . '/search?query=' . rawurlencode($query) . '&format=json&resultType=lite&pageSize=1';
        $data = $this->getJson($url);
        $first = $data['resultList']['result'][0] ?? null;
        return is_array($first) ? $first : null;
    }
}
