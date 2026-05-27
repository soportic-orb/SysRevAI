<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Google Cloud Translation v3 client.
 *
 * Phase 2 ships only a configuration check for the admin panel (presence and
 * validity of the service-account JSON + project ID). Actual translation,
 * chunking and the SHA-256 cache table arrive in Phase 11.
 */
final class TranslateService
{
    /** @return array{ok:bool,message:string} */
    public static function verifyConfig(): array
    {
        $projectId = (string) (setting('google.project_id') ?? '');
        $credPath  = (string) (setting('google.credentials_path') ?? '');

        if ($projectId === '') {
            return ['ok' => false, 'message' => 'Project ID is not set.'];
        }
        if ($credPath === '' || !is_file($credPath)) {
            return ['ok' => false, 'message' => 'Service-account JSON is not uploaded.'];
        }

        $decoded = json_decode((string) file_get_contents($credPath), true);
        if (!is_array($decoded) || ($decoded['type'] ?? '') !== 'service_account') {
            return ['ok' => false, 'message' => 'Service-account JSON is invalid.'];
        }

        // Live API verification (a "Hello" round-trip) lands with the
        // translation feature; configuration looks valid for now.
        return ['ok' => true, 'message' => 'Configuration looks valid (project: ' . $projectId . ').'];
    }
}
