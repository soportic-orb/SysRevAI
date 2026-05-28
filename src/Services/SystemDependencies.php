<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Detect and (best-effort) install the optional system binaries that some
 * file-processing features require:
 *
 *   • Ghostscript (`gs`)       — PDF compression (admin.files.compress)
 *   • Tesseract OCR (`tesseract`) — OCR for scanned PDFs (admin.files.ocr)
 *
 * Detection just runs `command -v` plus a `--version` probe to capture the
 * version string when present. Installation tries `apt-get install -y …`
 * (with `sudo -n` so it only succeeds when a sudoers entry has been
 * pre-configured) and surfaces the full stdout/stderr to the admin so they
 * can copy-paste the command manually if it didn't work.
 */
final class SystemDependencies
{
    public const PACKAGES = [
        'ghostscript' => [
            'binary'      => 'gs',
            'apt'         => 'ghostscript',
            'manual_hint' => 'sudo apt-get install -y ghostscript',
        ],
        'tesseract' => [
            'binary'      => 'tesseract',
            'apt'         => 'tesseract-ocr',
            'manual_hint' => 'sudo apt-get install -y tesseract-ocr',
        ],
    ];

    /**
     * Snapshot of every known dependency.
     *
     * @return array<string,array{installed:bool,path:?string,version:?string,manual_hint:string}>
     */
    public static function status(): array
    {
        $out = [];
        foreach (self::PACKAGES as $key => $spec) {
            $out[$key] = self::probe($spec['binary']) + [
                'manual_hint' => $spec['manual_hint'],
            ];
        }
        return $out;
    }

    /**
     * Try to install one of the supported packages.
     *
     * @return array{ok:bool,output:string,hint:string,message:string}
     */
    public static function install(string $key): array
    {
        $spec = self::PACKAGES[$key] ?? null;
        if ($spec === null) {
            return ['ok' => false, 'output' => '', 'hint' => '', 'message' => 'unknown_package'];
        }
        // If the binary is already on PATH there's nothing to do.
        $probe = self::probe($spec['binary']);
        if ($probe['installed']) {
            return [
                'ok' => true,
                'output' => '',
                'hint' => $spec['manual_hint'],
                'message' => 'already_installed',
            ];
        }

        if (!self::which('apt-get')) {
            return [
                'ok' => false,
                'output' => '',
                'hint' => $spec['manual_hint'],
                'message' => 'no_apt',
            ];
        }

        // Use sudo -n: succeeds only if a NOPASSWD sudoers entry is
        // configured for the web user. We avoid running the command
        // interactively (which would block forever).
        $cmd = 'sudo -n DEBIAN_FRONTEND=noninteractive apt-get install -y '
            . escapeshellarg($spec['apt']) . ' 2>&1';
        @set_time_limit(300);
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        $text = trim(implode("\n", $output));

        // Re-probe regardless of exit code — the package may have installed
        // even when apt returns a non-zero (e.g. for warnings).
        $after = self::probe($spec['binary']);
        return [
            'ok'      => $after['installed'],
            'output'  => $text,
            'hint'    => $spec['manual_hint'],
            'message' => $after['installed'] ? 'installed' : ($code === 0 ? 'unknown' : 'install_failed'),
        ];
    }

    /** @return array{installed:bool,path:?string,version:?string} */
    private static function probe(string $binary): array
    {
        $path = self::which($binary);
        if ($path === null) {
            return ['installed' => false, 'path' => null, 'version' => null];
        }
        $version = self::firstLine((string) @shell_exec(escapeshellcmd($path) . ' --version 2>&1'));
        return ['installed' => true, 'path' => $path, 'version' => $version];
    }

    private static function which(string $binary): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_\-+\.]+$/', $binary)) {
            return null;
        }
        $out = trim((string) @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
        return $out === '' ? null : $out;
    }

    private static function firstLine(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        return $lines[0] ?? null;
    }
}
