<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Admin-managed SendGrid credentials for the Zio Digest email channel
 * (Task #5620). Follows the IntegrationKeySettings pattern: values live in
 * app_settings, the API key is Crypt-encrypted at rest, and each getter
 * falls back to env-backed config so an env-only deployment keeps working
 * with no admin action.
 */
class SendGridSettings
{
    private const KEY_API_KEY_ENC = 'sendgrid.api_key_enc';
    private const KEY_FROM_EMAIL  = 'sendgrid.from_email';
    private const KEY_FROM_NAME   = 'sendgrid.from_name';

    public static function apiKey(): ?string
    {
        $admin = self::readSecret(self::KEY_API_KEY_ENC);
        if ($admin !== null && $admin !== '') {
            return $admin;
        }
        $env = config('services.sendgrid.api_key');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public static function setApiKey(?string $v): void
    {
        self::storeSecret(self::KEY_API_KEY_ENC, $v);
    }

    public static function maskedApiKey(): ?string
    {
        $key = self::apiKey();
        if ($key === null) {
            return null;
        }

        return strlen($key) > 6 ? substr($key, 0, 3) . str_repeat('•', 8) . substr($key, -4) : '••••••';
    }

    public static function fromEmail(): string
    {
        $admin = AppSetting::get(self::KEY_FROM_EMAIL);
        if (is_string($admin) && trim($admin) !== '') {
            return trim($admin);
        }

        return (string) (config('mail.from.address') ?: 'no-reply@sayzio.com');
    }

    public static function setFromEmail(?string $v): void
    {
        AppSetting::set(self::KEY_FROM_EMAIL, $v !== null ? trim($v) : null);
    }

    public static function fromName(): string
    {
        $admin = AppSetting::get(self::KEY_FROM_NAME);
        if (is_string($admin) && trim($admin) !== '') {
            return trim($admin);
        }

        return (string) (config('mail.from.name') ?: config('app.name'));
    }

    public static function setFromName(?string $v): void
    {
        AppSetting::set(self::KEY_FROM_NAME, $v !== null ? trim($v) : null);
    }

    public static function configured(): bool
    {
        return self::apiKey() !== null;
    }

    public static function hasAdminValue(): bool
    {
        return self::readSecret(self::KEY_API_KEY_ENC) !== null;
    }

    /** @return array{state:string,label:string,detail:string} */
    public static function status(): array
    {
        if (self::hasAdminValue()) {
            return ['state' => 'connected', 'label' => 'Connected', 'detail' => 'API key stored in admin settings (encrypted).'];
        }
        if (self::configured()) {
            return ['state' => 'connected', 'label' => 'Connected (env)', 'detail' => 'API key supplied via server environment.'];
        }

        return ['state' => 'missing', 'label' => 'Not configured', 'detail' => 'Zio Digest email sends will fail until a SendGrid API key is added.'];
    }

    private static function storeSecret(string $key, ?string $value): void
    {
        $value = $value !== null ? trim($value) : null;
        AppSetting::set($key, ($value === null || $value === '') ? null : Crypt::encryptString($value));
    }

    private static function readSecret(string $key): ?string
    {
        $enc = AppSetting::get($key);
        if (!is_string($enc) || $enc === '') {
            return null;
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            Log::warning("SendGridSettings: failed to decrypt {$key}: " . $e->getMessage());

            return null;
        }
    }
}
