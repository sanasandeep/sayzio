<?php

namespace App\Modules\User\Services;

use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Self-contained RFC 6238 TOTP implementation. Generates base32 secrets,
 * builds otpauth:// URIs that authenticator apps (Google Authenticator,
 * 1Password, Authy, etc.) understand, and verifies 6-digit codes with a
 * +/-1 step window so a code that just rolled over still works.
 *
 * No external TOTP package is required — this keeps the dep surface small.
 */
class TotpService
{
    public const PERIOD = 30;
    public const DIGITS = 6;
    public const ALGO   = 'sha1';
    /** Base32 alphabet (RFC 4648). */
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generate a fresh 160-bit secret encoded as base32 (no padding). */
    public function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** Build the otpauth:// URI a TOTP app scans / pastes in. */
    public function provisioningUri(string $secret, string $accountName, string $issuer): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGO),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * Render the otpauth URI as an inline SVG QR (no network roundtrip,
     * works offline, no third-party tracking pixels).
     */
    public function qrSvg(string $uri, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );
        return (new Writer($renderer))->writeString($uri);
    }

    /**
     * Verify a user-entered 6-digit code against the secret, allowing the
     * previous and next 30s window to absorb clock skew between phone and
     * server. Returns the matched step (the time-counter) on success so
     * callers can persist it and reject reuse, or null on failure.
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $now = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) return null;

        $now = $now ?? time();
        $step = intdiv($now, self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->codeAt($secret, $step + $i), $code)) {
                return $step + $i;
            }
        }
        return null;
    }

    /** Compute the TOTP code at a specific step (test/util helper). */
    public function codeAt(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        // Counter encoded as 8-byte big-endian.
        $counter = pack('N*', 0, $step);
        $hash = hash_hmac(self::ALGO, $counter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $bin = (ord($hash[$offset]) & 0x7F) << 24
             | (ord($hash[$offset + 1]) & 0xFF) << 16
             | (ord($hash[$offset + 2]) & 0xFF) << 8
             | (ord($hash[$offset + 3]) & 0xFF);
        $code = $bin % (10 ** self::DIGITS);
        return str_pad((string) $code, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Generate a batch of single-use, human-readable recovery codes. */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            // 10 hex chars, hyphenated for readability ("a1b2-c3d4-e5").
            $raw = bin2hex(random_bytes(5));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4) . '-' . substr($raw, 8, 2);
        }
        return $codes;
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= self::B32[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(rtrim($b32, '='));
        $bits = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::B32, $b32[$i]);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
