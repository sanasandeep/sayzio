<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed accessor for the platform's outbound mail transport, managed from
 * the admin "Email / SMTP" settings page. Mirrors the IntegrationKeySettings
 * pattern: every value lives in the `app_settings` key/value store, the SMTP
 * password is Crypt-encrypted at rest, and each getter falls back to the
 * existing env-driven config/mail.php so nothing breaks when an admin hasn't
 * configured anything yet.
 *
 * applyRuntimeConfig() pushes the effective values back into config('mail.*')
 * at boot so every outbound channel — notifications, newsletters and the
 * email OTP path — uses the admin-configured transport without a redeploy.
 */
class MailSettings
{
    // ── AppSetting keys (single "mail." namespace) ────────────────
    public const KEY_MAILER       = 'mail.mailer';
    public const KEY_HOST         = 'mail.host';
    public const KEY_PORT         = 'mail.port';
    public const KEY_ENCRYPTION   = 'mail.encryption';
    public const KEY_USERNAME     = 'mail.username';
    public const KEY_PASSWORD_ENC = 'mail.password_enc';
    public const KEY_FROM_ADDRESS = 'mail.from_address';
    public const KEY_FROM_NAME    = 'mail.from_name';

    public const ENCRYPTION_OPTIONS = ['tls', 'ssl', 'none'];

    // ─────────────────────────────────────────────────────────────
    // Effective accessors (admin value first, then config/mail.php)
    // ─────────────────────────────────────────────────────────────

    public static function mailer(): string
    {
        $admin = AppSetting::get(self::KEY_MAILER);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        return (string) config('mail.default', 'log');
    }

    public static function host(): ?string
    {
        $admin = AppSetting::get(self::KEY_HOST);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) config('mail.mailers.smtp.host', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function port(): ?int
    {
        $admin = AppSetting::get(self::KEY_PORT);
        if ($admin !== null && $admin !== '' && (int) $admin > 0) return (int) $admin;
        $cfg = config('mail.mailers.smtp.port');
        return $cfg !== null ? (int) $cfg : null;
    }

    /** One of tls|ssl|none. Derived from the SMTP scheme/port when unset. */
    public static function encryption(): string
    {
        $admin = AppSetting::get(self::KEY_ENCRYPTION);
        if (is_string($admin) && in_array($admin, self::ENCRYPTION_OPTIONS, true)) {
            return $admin;
        }

        $scheme = (string) config('mail.mailers.smtp.scheme', '');
        if ($scheme === 'smtps') return 'ssl';
        if ($scheme === 'smtp')  return 'tls';

        return ((int) config('mail.mailers.smtp.port', 0) === 465) ? 'ssl' : 'tls';
    }

    public static function username(): ?string
    {
        $admin = AppSetting::get(self::KEY_USERNAME);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) config('mail.mailers.smtp.username', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function password(): ?string
    {
        $admin = self::decrypt(self::KEY_PASSWORD_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) config('mail.mailers.smtp.password', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function fromAddress(): ?string
    {
        $admin = AppSetting::get(self::KEY_FROM_ADDRESS);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) config('mail.from.address', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function fromName(): ?string
    {
        $admin = AppSetting::get(self::KEY_FROM_NAME);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) config('mail.from.name', '');
        return $cfg !== '' ? $cfg : null;
    }

    // ─────────────────────────────────────────────────────────────
    // Setters (used by the admin controller)
    // ─────────────────────────────────────────────────────────────

    public static function setMailer(?string $v): void
    {
        AppSetting::put(self::KEY_MAILER, self::cleanScalar($v));
    }

    public static function setHost(?string $v): void
    {
        AppSetting::put(self::KEY_HOST, self::cleanScalar($v));
    }

    public static function setPort(?int $v): void
    {
        AppSetting::put(self::KEY_PORT, ($v !== null && $v > 0) ? $v : null);
    }

    public static function setEncryption(?string $v): void
    {
        $v = is_string($v) ? trim($v) : null;
        AppSetting::put(self::KEY_ENCRYPTION, in_array($v, self::ENCRYPTION_OPTIONS, true) ? $v : null);
    }

    public static function setUsername(?string $v): void
    {
        AppSetting::put(self::KEY_USERNAME, self::cleanScalar($v));
    }

    public static function setPassword(?string $v): void
    {
        self::storeSecret(self::KEY_PASSWORD_ENC, $v);
    }

    public static function setFromAddress(?string $v): void
    {
        AppSetting::put(self::KEY_FROM_ADDRESS, self::cleanScalar($v));
    }

    public static function setFromName(?string $v): void
    {
        AppSetting::put(self::KEY_FROM_NAME, self::cleanScalar($v));
    }

    // ─────────────────────────────────────────────────────────────
    // UI helpers
    // ─────────────────────────────────────────────────────────────

    /** Masked SMTP password for the admin UI: ••••••••wXyz. */
    public static function maskedPassword(): ?string
    {
        $p = self::password();
        if (!$p) return null;
        return '••••••••' . substr($p, -4);
    }

    /** True when an admin has stored an SMTP password (vs. env fallback). */
    public static function hasAdminPassword(): bool
    {
        $p = self::decrypt(self::KEY_PASSWORD_ENC);
        return $p !== null && $p !== '';
    }

    /** True when an admin has stored any email setting at all. */
    public static function hasAnyAdminValue(): bool
    {
        foreach ([
            self::KEY_MAILER, self::KEY_HOST, self::KEY_PORT, self::KEY_ENCRYPTION,
            self::KEY_USERNAME, self::KEY_PASSWORD_ENC, self::KEY_FROM_ADDRESS, self::KEY_FROM_NAME,
        ] as $key) {
            $v = AppSetting::get($key);
            if ($v !== null && $v !== '') return true;
        }
        return false;
    }

    /**
     * Status descriptor for the admin badge.
     *
     * @return array{key:string,label:string,tone:string}
     */
    public static function status(): array
    {
        if (self::hasAnyAdminValue()) {
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::mailer() !== 'log' && self::host() !== null) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'log', 'label' => 'Log driver (not sending)', 'tone' => 'slate'];
    }

    /** Mailer names available from config/mail.php, keyed by name. */
    public static function availableMailers(): array
    {
        return array_keys((array) config('mail.mailers', []));
    }

    // ─────────────────────────────────────────────────────────────
    // Runtime override
    // ─────────────────────────────────────────────────────────────

    /**
     * Push the effective (admin-or-env) values into config('mail.*') so all
     * outbound mail uses the admin-configured transport. No-op when an admin
     * has never saved anything, leaving the pure env/config defaults intact.
     */
    public static function applyRuntimeConfig(): void
    {
        if (!self::hasAnyAdminValue()) {
            return;
        }

        $mailer = self::mailer();
        config(['mail.default' => $mailer]);

        // SMTP transport — only the smtp mailer reads these, but applying
        // them unconditionally is harmless for other drivers.
        $host = self::host();
        $port = self::port();
        $encryption = self::encryption();

        if ($host !== null) config(['mail.mailers.smtp.host' => $host]);
        if ($port !== null) config(['mail.mailers.smtp.port' => $port]);

        // MailManager (Laravel 13) derives TLS from the connection scheme:
        // 'smtps' = implicit TLS (port 465), 'smtp' = STARTTLS / plaintext.
        config(['mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp']);

        $username = self::username();
        $password = self::password();
        config(['mail.mailers.smtp.username' => $username]);
        config(['mail.mailers.smtp.password' => $password]);

        $fromAddress = self::fromAddress();
        $fromName    = self::fromName();
        if ($fromAddress !== null) config(['mail.from.address' => $fromAddress]);
        if ($fromName !== null)    config(['mail.from.name' => $fromName]);
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private static function cleanScalar(?string $v): ?string
    {
        if ($v === null) return null;
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private static function decrypt(string $key): ?string
    {
        $enc = AppSetting::get($key);
        if (!$enc || !is_string($enc)) return null;
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function storeSecret(string $key, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            AppSetting::put($key, null);
            return;
        }
        AppSetting::put($key, Crypt::encryptString(trim($value)));
    }
}
