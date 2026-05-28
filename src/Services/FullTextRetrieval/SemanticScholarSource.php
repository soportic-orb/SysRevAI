<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Semantic Scholar — DOI/PMID → openAccessPdf.url and citation context.
 *
 * Endpoint: GET https://api.semanticscholar.org/graph/v1/paper/DOI:{doi}
 *           GET https://api.semanticscholar.org/graph/v1/paper/PMID:{pmid}
 *
 * Key is optional. Without it: 100 req / 5 min. With it: 1 req/s (paradox).
 */
final class SemanticScholarSource extends BaseHttpSource
{
    private const BASE = 'https://api.semanticscholar.org/graph/v1/paper/';
    private const FIELDS = 'openAccessPdf,tldr,citationCount,year,journal,externalIds';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'semantic_scholar';
    }

    public function supports(array $reference): bool
    {
        return $this->normalizeDoi($reference['doi'] ?? null) !== null
            || !empty($reference['pmid']);
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $identifier = $this->paperId($reference);
        if ($identifier === null) {
            return null;
        }
        $url = self::BASE . rawurlencode($identifier) . '?fields=' . self::FIELDS;
        $data = $this->getJson($url, $this->headers());
        if ($data === null) {
            return null;
        }
        $oaPdf = $data['openAccessPdf'] ?? null;
        $pdfUrl = is_array($oaPdf) ? ($oaPdf['url'] ?? null) : null;
        if (!is_string($pdfUrl) || $pdfUrl === '') {
            return null;
        }
        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            licenseType: (string) ($oaPdf['license'] ?? 'unknown'),
            version: 'published',
            metadata: [
                'citation_count' => $data['citationCount'] ?? null,
                'tldr'           => $data['tldr']['text'] ?? null,
                'paper_id'       => $data['paperId'] ?? null,
            ],
            confidence: 0.85,
        );
    }

    public function verifyConnection(): array
    {
        $url = self::BASE . 'DOI:' . rawurlencode(self::VERIFY_DOI) . '?fields=paperId';
        $res = $this->get($url, $this->headers());
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'Semantic Scholar OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return $this->apiKey() !== ''
            ? ['requests' => 1, 'per_seconds' => 1]
            : ['requests' => 100, 'per_seconds' => 300];
    }

    private function paperId(array $reference): ?string
    {
        $doi = $this->normalizeDoi($reference['doi'] ?? null);
        if ($doi !== null) {
            return 'DOI:' . $doi;
        }
        $pmid = preg_replace('/\D/', '', (string) ($reference['pmid'] ?? '')) ?? '';
        return $pmid !== '' ? 'PMID:' . $pmid : null;
    }

    private function apiKey(): string
    {
        return (string) (setting('fulltext.semantic_scholar.api_key')
            ?? setting('semantic_scholar.api_key') ?? '');
    }

    private function headers(): array
    {
        $key = $this->apiKey();
        return $key !== '' ? ['x-api-key: ' . $key] : [];
    }
}
