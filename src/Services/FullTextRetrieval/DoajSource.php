<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * DOAJ — DOI → Open Access journal article URL (fulltext link or landing page).
 *
 * Endpoint: GET https://doaj.org/api/v3/search/articles/doi:{doi}
 *
 * DOAJ is primarily a curated index of OA journals; when an article matches,
 * the response carries one or more "link" entries; we keep the first PDF URL.
 */
final class DoajSource extends BaseHttpSource
{
    private const SEARCH = 'https://doaj.org/api/v3/search/articles/';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'doaj';
    }

    public function supports(array $reference): bool
    {
        return $this->normalizeDoi($reference['doi'] ?? null) !== null;
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $doi = $this->normalizeDoi($reference['doi'] ?? null);
        if ($doi === null) {
            return null;
        }
        $url = self::SEARCH . rawurlencode('doi:' . $doi) . '?pageSize=1';
        $data = $this->getJson($url);
        $hit = $data['results'][0] ?? null;
        if (!is_array($hit)) {
            return null;
        }

        $bibjson = $hit['bibjson'] ?? [];
        $pdfUrl = null;
        $fallbackUrl = null;
        foreach ((array) ($bibjson['link'] ?? []) as $link) {
            $type = (string) ($link['type'] ?? '');
            $href = (string) ($link['url'] ?? '');
            if ($href === '') {
                continue;
            }
            if ($type === 'fulltext' && str_ends_with(strtolower($href), '.pdf')) {
                $pdfUrl = $href;
                break;
            }
            if ($fallbackUrl === null && $type === 'fulltext') {
                $fallbackUrl = $href;
            }
        }
        $pdfUrl = $pdfUrl ?? $fallbackUrl;
        if ($pdfUrl === null) {
            return null;
        }

        $license = 'unknown';
        foreach ((array) ($bibjson['journal']['license'] ?? []) as $lic) {
            if (!empty($lic['type'])) {
                $license = (string) $lic['type'];
                break;
            }
        }

        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            licenseType: $license,
            version: 'published',
            metadata: [
                'journal' => $bibjson['journal']['title'] ?? null,
                'doaj_id' => $hit['id'] ?? null,
            ],
            confidence: 0.7,
        );
    }

    public function verifyConnection(): array
    {
        $url = self::SEARCH . rawurlencode('doi:' . self::VERIFY_DOI) . '?pageSize=1';
        $res = $this->get($url);
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'DOAJ OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return ['requests' => 2, 'per_seconds' => 1];
    }
}
