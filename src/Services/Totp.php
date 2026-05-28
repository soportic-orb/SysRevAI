<?php

declare(strict_types=1);

namespace SysRevAI\Services;

/**
 * RFC 6238 TOTP (Time-based One-Time Password) — compatible with Google
 * Authenticator, Authy, 1Password, Bitwarden, etc.
 *
 * The shared secret is a 20-byte random value, base32-encoded for transport
 * (manual entry / otpauth:// URI). We always issue / verify 6-digit codes on
 * a 30-second window with SHA-1 HMAC, which is what every common
 * authenticator app defaults to.
 */
final class Totp
{
    /** Generate a 20-byte secret (base32 encoded, no padding). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /**
     * Verify a 6-digit code against the secret, with a ±1 step tolerance
     * to absorb minor clock drift.
     */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $bytes = self::base32Decode($secret);
        if ($bytes === '') {
            return false;
        }
        $step = (int) floor(time() / 30);
        for ($drift = -1; $drift <= 1; $drift++) {
            if (hash_equals(self::generate($bytes, $step + $drift), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the otpauth:// URI for QR codes / authenticator imports.
     *
     * @param string $secret Base32 secret without padding
     * @param string $account User identifier (typically the email)
     * @param string $issuer  Visible app name
     */
    public static function otpauthUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => 6,
            'period'    => 30,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** Pretty-print a secret in 4-char groups for manual entry. */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    /* ── Internals ─────────────────────────────────────────────────────── */

    private static function generate(string $key, int $step): string
    {
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = (ord($hash[$offset]) & 0x7F) << 24
                | (ord($hash[$offset + 1]) & 0xFF) << 16
                | (ord($hash[$offset + 2]) & 0xFF) << 8
                | (ord($hash[$offset + 3]) & 0xFF);
        return str_pad((string) ($binary % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }
        $bits = '';
        for ($i = 0, $n = strlen($secret); $i < $n; $i++) {
            $pos = strpos($alphabet, $secret[$i]);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }
}
