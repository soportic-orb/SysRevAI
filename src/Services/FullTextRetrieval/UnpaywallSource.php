<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * Unpaywall — DOI → best open-access PDF URL.
 *
 * Endpoint: GET https://api.unpaywall.org/v2/{DOI}?email={email}
 * Coverage: ~30M articles with a DOI. Email is required (no API key).
 */
final class UnpaywallSource extends BaseHttpSource
{
    private const ENDPOINT = 'https://api.unpaywall.org/v2/';
    private const VERIFY_DOI = '10.1371/journal.pone.0173152';

    public function name(): string
    {
        return 'unpaywall';
    }

    public function isEnabled(): bool
    {
        return parent::isEnabled() && $this->politeEmail() !== '';
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
        $url = self::ENDPOINT . rawurlencode($doi) . '?email=' . rawurlencode($this->politeEmail());
        $data = $this->getJson($url);
        if ($data === null) {
            return null;
        }

        $best = $data['best_oa_location'] ?? null;
        $pdfUrl = is_array($best) ? ($best['url_for_pdf'] ?? null) : null;
        if (!is_string($pdfUrl) || $pdfUrl === '') {
            return null;
        }

        $license = (string) ($best['license'] ?? 'unknown');
        $version = (string) ($best['version'] ?? 'unknown');
        $confidence = ($data['oa_status'] ?? '') === 'closed' ? 0.0 : 0.95;

        return new FullTextResult(
            source: $this->name(),
            pdfUrl: $pdfUrl,
            licenseType: $license,
            version: $version,
            metadata: [
                'oa_status' => $data['oa_status'] ?? null,
                'host_type' => $best['host_type'] ?? null,
            ],
            confidence: $confidence,
        );
    }

    public function verifyConnection(): array
    {
        if ($this->politeEmail() === '') {
            return ['ok' => false, 'message' => 'Email not configured.'];
        }
        $url = self::ENDPOINT . rawurlencode(self::VERIFY_DOI) . '?email=' . rawurlencode($this->politeEmail());
        $res = $this->get($url);
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'Unpaywall OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return ['requests' => 100000, 'per_seconds' => 86400];
    }
}
