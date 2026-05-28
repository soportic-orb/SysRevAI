<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Secure storage for uploaded reference PDFs.
 *
 * Files live OUTSIDE the document root (storage/pdfs) under random UUID-style
 * names; the original filename is stored separately. Real MIME is validated
 * with finfo — extension is never trusted. Served via PHP with auth checks.
 */
final class FileStorage
{
    public const PDF_DIR = 'pdfs';

    /** Resolve the on-disk directory, creating it 0700 on first use. */
    public static function pdfDir(): string
    {
        $dir = (string) config('paths.storage') . '/' . self::PDF_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /**
     * Validate a $_FILES['key'] entry.
     * @return array{ok:bool,error?:string,tmp?:string,name?:string,size?:int}
     */
    public static function validatePdfUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'no_file'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'not_uploaded'];
        }

        $maxMb = (int) (setting('files.max_pdf_mb') ?? 50);
        $maxBytes = $maxMb * 1024 * 1024;
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'error' => 'too_large'];
        }

        // Real MIME via finfo — never trust the extension or the browser claim.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if ($mime !== 'application/pdf') {
            return ['ok' => false, 'error' => 'not_pdf'];
        }

        return [
            'ok'   => true,
            'tmp'  => (string) $file['tmp_name'],
            'name' => basename((string) ($file['name'] ?? 'upload.pdf')),
            'size' => $size,
        ];
    }

    /**
     * Move a validated upload into storage with a random UUID-style filename.
     * Returns the absolute path or null on failure.
     */
    public static function storePdf(string $tmpPath): ?string
    {
        $name = self::uuid() . '.pdf';
        $dest = self::pdfDir() . '/' . $name;
        if (!@move_uploaded_file($tmpPath, $dest)) {
            return null;
        }
        @chmod($dest, 0600);
        return $dest;
    }

    /** True if the path lives under the PDF directory (no path traversal). */
    public static function isStoredPdf(string $absolutePath): bool
    {
        $real = realpath($absolutePath);
        $dir = realpath(self::pdfDir());
        return $real !== false && $dir !== false && str_starts_with($real, $dir . DIRECTORY_SEPARATOR);
    }

    public static function delete(string $absolutePath): bool
    {
        if (!self::isStoredPdf($absolutePath)) {
            return false;
        }
        return @unlink($absolutePath);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
