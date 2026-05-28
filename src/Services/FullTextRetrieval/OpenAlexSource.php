<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * OpenAlex — DOI/PMID → best OA PDF URL.
 *
 * Endpoint: GET https://api.openalex.org/works/doi:{DOI}
 *           GET https://api.openalex.org/works?filter=ids.pmid:{pmid}
 *
 * Email (mailto=…) is optional but puts requests in the "polite pool" and
 * yields better rate limits.
 */
final class OpenAlexSource extends BaseHttpSource
{
    private const BASE = 'https://api.openalex.org/works';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'openalex';
    }

    public function supports(array $reference): bool
    {
        return $this->normalizeDoi($reference['doi'] ?? null) !== null
            || !empty($reference['pmid']);
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $url = $this->buildUrl($reference);
        if ($url === null) {
            return null;
        }
        $data = $this->getJson($url);
        if ($data === null) {
            return null;
        }
        // /works?filter=… wraps the work in a results array.
        if (isset($data['results']) && is_array($data['results'])) {
            $data = $data['results'][0] ?? null;
        }
        if (!is_array($data)) {
            return null;
        }

        $best = $data['best_oa_location'] ?? null;
        $pdfUrl = is_array($best) ? ($best['pdf_url'] ?? null) : null;
        if (!is_string($pdfUrl) || $pdfUrl === '') {
            $oa = $data['open_access'] ?? null;
            $pdfUrl = is_array($oa) ? ($oa['oa_url'] ?? null) : null;
        }
        if (!is_string($pdfUrl) || $pdfUrl === '') {
            return null;
        }

        $license = is_array($best) ? (string) ($best['license'] ?? 'unknown') : 'unknown';
        $version = is_array($best) ? (string) ($best['version'] ?? 'unknown') : 'unknown';

        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            licenseType: $license,
            version: $version,
            metadata: [
                'oa_status'       => $data['open_access']['oa_status'] ?? null,
                'is_oa'           => $data['open_access']['is_oa'] ?? null,
                'cited_by_count'  => $data['cited_by_count'] ?? null,
            ],
            confidence: 0.9,
        );
    }

    public function verifyConnection(): array
    {
        $url = self::BASE . '/doi:' . rawurlencode(self::VERIFY_DOI);
        $email = $this->politeEmail();
        if ($email !== '') {
            $url .= '?mailto=' . rawurlencode($email);
        }
        $res = $this->get($url);
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'OpenAlex OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return ['requests' => 10, 'per_seconds' => 1];
    }

    private function buildUrl(array $reference): ?string
    {
        $doi = $this->normalizeDoi($reference['doi'] ?? null);
        $email = $this->politeEmail();
        $params = $email !== '' ? ('?mailto=' . rawurlencode($email)) : '';
        if ($doi !== null) {
            return self::BASE . '/doi:' . rawurlencode($doi) . $params;
        }
        $pmid = preg_replace('/\D/', '', (string) ($reference['pmid'] ?? '')) ?? '';
        if ($pmid !== '') {
            $glue = $params === '' ? '?' : '&';
            return self::BASE . '?filter=ids.pmid:' . rawurlencode($pmid) . $glue . 'per_page=1';
        }
        return null;
    }
}
