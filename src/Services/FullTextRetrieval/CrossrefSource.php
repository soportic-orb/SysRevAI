<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * CrossRef — DOI → canonical metadata + best-effort `link[]` URLs.
 *
 * Endpoint: GET https://api.crossref.org/works/{DOI}
 *
 * CrossRef's `link` array sometimes carries a direct PDF or full-text URL
 * exposed by the publisher; absent that, this source still validates the DOI
 * and provides metadata. Email goes in the polite User-Agent.
 */
final class CrossrefSource extends BaseHttpSource
{
    private const BASE = 'https://api.crossref.org/works/';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'crossref';
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
        $data = $this->getJson(self::BASE . rawurlencode($doi));
        $message = $data['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $pdfUrl = null;
        foreach ((array) ($message['link'] ?? []) as $link) {
            $contentType = (string) ($link['content-type'] ?? '');
            $url = (string) ($link['URL'] ?? '');
            if ($url === '') {
                continue;
            }
            if ($contentType === 'application/pdf' || str_ends_with(strtolower($url), '.pdf')) {
                $pdfUrl = $url;
                break;
            }
            // Fallback: any unrestricted full-text link.
            if ($pdfUrl === null && ($link['intended-application'] ?? '') === 'text-mining') {
                $pdfUrl = $url;
            }
        }
        if ($pdfUrl === null) {
            return null;
        }
        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            licenseType: $this->extractLicense($message),
            version: 'published',
            metadata: [
                'publisher' => $message['publisher'] ?? null,
                'type'      => $message['type'] ?? null,
            ],
            confidence: 0.6,
        );
    }

    public function verifyConnection(): array
    {
        $res = $this->get(self::BASE . rawurlencode(self::VERIFY_DOI));
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'CrossRef OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        // Polite pool documents ~50 req/s; we stay conservative.
        return ['requests' => 5, 'per_seconds' => 1];
    }

    private function extractLicense(array $message): string
    {
        foreach ((array) ($message['license'] ?? []) as $lic) {
            if (!empty($lic['URL'])) {
                return (string) $lic['URL'];
            }
        }
        return 'unknown';
    }
}
