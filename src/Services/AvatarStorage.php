<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * Profile-picture handling: validate an uploaded image, downsize it to a
 * sensible 256×256 square (when ext-gd is available) and store it under
 * public/uploads/avatars/. Returns the relative path so the caller can
 * persist it on the user row.
 *
 * GD is in the platform's "recommended" extension list but not strictly
 * required — when missing we still accept JPG/PNG/WEBP and keep the file
 * as-is, just with a sanitised filename.
 */
final class AvatarStorage
{
    public const MAX_BYTES = 4 * 1024 * 1024; // 4 MB raw upload cap
    public const TARGET_PX = 256;             // final width/height in pixels
    public const SUBDIR    = 'avatars';

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * Validate $_FILES['avatar']-style upload and store it.
     *
     * @return array{ok:bool,path?:string,error?:string}
     */
    public static function store(array $file, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'no_file'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'invalid_upload'];
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'too_large'];
        }

        $mime = self::detectMime((string) $file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'error' => 'unsupported_format'];
        }
        $ext = self::ALLOWED_MIME[$mime];

        $dir = self::absoluteDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'cannot_create_dir'];
        }

        $token = bin2hex(random_bytes(4));
        $rel   = self::SUBDIR . "/{$userId}-{$token}.{$ext}";
        $abs   = $dir . "/{$userId}-{$token}.{$ext}";

        if (function_exists('imagecreatefromstring') && in_array($ext, ['jpg', 'png', 'webp'], true)) {
            $resized = self::tryResize((string) $file['tmp_name'], $abs, $ext);
            if (!$resized) {
                if (!@move_uploaded_file((string) $file['tmp_name'], $abs)) {
                    return ['ok' => false, 'error' => 'cannot_write'];
                }
            }
        } else {
            if (!@move_uploaded_file((string) $file['tmp_name'], $abs)) {
                return ['ok' => false, 'error' => 'cannot_write'];
            }
        }
        @chmod($abs, 0644);

        return ['ok' => true, 'path' => $rel];
    }

    /** Delete an existing avatar file (relative path). Silent on missing. */
    public static function delete(?string $relPath): void
    {
        if ($relPath === null || $relPath === '') {
            return;
        }
        $abs = self::absoluteDir() . '/' . basename($relPath);
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public static function publicUrl(?string $relPath): ?string
    {
        if ($relPath === null || $relPath === '') {
            return null;
        }
        return '/uploads/' . ltrim($relPath, '/');
    }

    /* ── Internals ─────────────────────────────────────────────────────── */

    private static function absoluteDir(): string
    {
        return (string) config('paths.uploads') . '/' . self::SUBDIR;
    }

    private static function detectMime(string $path): string
    {
        if (class_exists('finfo')) {
            try {
                return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
            } catch (\Throwable) {
                // fall through
            }
        }
        return (string) (mime_content_type($path) ?: '');
    }

    /** Best-effort GD downsizing to a 256×256 square (center crop). */
    private static function tryResize(string $srcPath, string $destPath, string $ext): bool
    {
        $data = @file_get_contents($srcPath);
        if ($data === false) {
            return false;
        }
        $src = @imagecreatefromstring($data);
        if (!$src) {
            return false;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $size = min($w, $h);
        $offX = (int) (($w - $size) / 2);
        $offY = (int) (($h - $size) / 2);

        $dst = imagecreatetruecolor(self::TARGET_PX, self::TARGET_PX);
        // Preserve transparency for PNG / WEBP.
        if ($ext === 'png' || $ext === 'webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, self::TARGET_PX, self::TARGET_PX, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, $offX, $offY, self::TARGET_PX, self::TARGET_PX, $size, $size);

        $ok = match ($ext) {
            'jpg'  => @imagejpeg($dst, $destPath, 88),
            'png'  => @imagepng($dst, $destPath, 6),
            'webp' => @imagewebp($dst, $destPath, 88),
            default => false,
        };

        imagedestroy($src);
        imagedestroy($dst);
        return $ok;
    }
}
