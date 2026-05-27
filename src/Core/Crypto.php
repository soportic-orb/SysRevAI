<?php

declare(strict_types=1);

namespace SysRevAI\Core;

/**
 * Authenticated symmetric encryption (AES-256-GCM) for sensitive settings.
 *
 * The key comes from APP_KEY in .env (a "base64:"-prefixed 32-byte key generated
 * by the installer). Ciphertext is stored as base64( iv | tag | ciphertext ).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    private static ?string $key = null;

    /** Override the key (tests / rotation). */
    public static function useKey(?string $rawKey): void
    {
        self::$key = $rawKey;
    }

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        $configured = (string) config('app.key', '');
        if (str_starts_with($configured, 'base64:')) {
            $decoded = base64_decode(substr($configured, 7), true);
            if ($decoded !== false) {
                return self::$key = $decoded;
            }
        }
        if ($configured !== '') {
            return self::$key = $configured;
        }
        throw new \RuntimeException('APP_KEY is not set; cannot encrypt/decrypt settings.');
    }

    public static function encrypt(string $plaintext): string
    {
        $iv  = random_bytes(self::IV_LEN);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LEN
        );
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= self::IV_LEN + self::TAG_LEN) {
            return null;
        }
        $iv         = substr($raw, 0, self::IV_LEN);
        $tag        = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        return $plaintext === false ? null : $plaintext;
    }
}
