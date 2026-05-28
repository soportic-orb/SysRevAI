<?php

declare(strict_types=1);

namespace SysRevAI\Services\FullTextRetrieval;

/**
 * bioRxiv / medRxiv preprints. Activated for DOIs minted by Cold Spring Harbor
 * (prefix 10.1101). Tries the biorxiv server first, then medrxiv.
 *
 * Endpoint: GET https://api.biorxiv.org/details/{server}/{doi}
 * The JSON response carries the PDF URL in the `pdf_link` field.
 */
final class BiorxivSource extends BaseHttpSource
{
    private const BASE = 'https://api.biorxiv.org/details/';

    public function name(): string
    {
        return 'biorxiv';
    }

    public function supports(array $reference): bool
    {
        $doi = $this->normalizeDoi($reference['doi'] ?? null);
        return $doi !== null && str_starts_with($doi, '10.1101/');
    }

    public function retrieve(array $reference): ?FullTextResult
    {
        $doi = $this->normalizeDoi($reference['doi'] ?? null);
        if ($doi === null) {
            return null;
        }
        foreach (['biorxiv', 'medrxiv'] as $server) {
            $data = $this->getJson(self::BASE . $server . '/' . rawurlencode($doi));
            $best = $this->bestVersion($data);
            if ($best === null) {
                continue;
            }
            $pdfUrl = (string) ($best['pdf_link'] ?? '');
            if ($pdfUrl === '') {
                // Compose the canonical PDF URL when the API omits it.
                $pdfUrl = 'https://www.' . $server . '.org/content/10.1101/'
                    . rawurlencode((string) ($best['doi'] ?? $doi)) . 'v'
                    . rawurlencode((string) ($best['version'] ?? '1')) . '.full.pdf';
            }
            return new FullTextResult(
                source: $this->name(),
                pdfUrl: $pdfUrl,
                licenseType: (string) ($best['license'] ?? 'unknown'),
                version: 'preprint',
                metadata: [
                    'server'  => $server,
                    'version' => $best['version'] ?? null,
                ],
                confidence: 0.9,
            );
        }
        return null;
    }

    public function verifyConnection(): array
    {
        $res = $this->get(self::BASE . 'biorxiv/10.1101/2024.01.01.000001');
        if ($res['error'] !== null) {
            return ['ok' => false, 'message' => $res['error']];
        }
        // Even an unknown DOI returns 200 with an empty collection — that
        // proves the endpoint is reachable.
        return $res['status'] === 200
            ? ['ok' => true, 'message' => 'bioRxiv / medRxiv OK.']
            : ['ok' => false, 'message' => 'HTTP ' . $res['status']];
    }

    public function rateLimit(): array
    {
        return ['requests' => 2, 'per_seconds' => 1];
    }

    private function bestVersion(?array $data): ?array
    {
        if (!is_array($data) || empty($data['collection']) || !is_array($data['collection'])) {
            return null;
        }
        $best = null;
        foreach ($data['collection'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($best === null || (int) ($row['version'] ?? 0) > (int) ($best['version'] ?? 0)) {
                $best = $row;
            }
        }
        return $best;
    }
}
