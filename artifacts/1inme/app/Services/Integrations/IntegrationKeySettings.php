<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Typed accessor for the key-bearing integrations managed from the
 * admin "API Keys & Plugins" hub. Mirrors the AiEngineSettings pattern:
 * every value lives in the `app_settings` key/value store, secrets are
 * Crypt-encrypted at rest, and each getter falls back to the existing
 * env/config so nothing breaks when an admin hasn't set anything yet.
 *
 * Covered groups:
 *   WhatsApp Cloud API (OTP delivery) — admin values override
 *     config/whatsapp.php (env). Preview mode preserved when neither
 *     source has credentials.
 *   Internal alerts (Slack / Discord webhooks) — admin webhook URLs for
 *     system/team alerting. Slack falls back to the logging channel env
 *     (LOG_SLACK_WEBHOOK_URL); Discord is admin-only.
 */
class IntegrationKeySettings
{
    // ── WhatsApp Cloud API ────────────────────────────────────────
    public const KEY_WA_PHONE_NUMBER_ID = 'whatsapp.phone_number_id';
    public const KEY_WA_ACCESS_TOKEN_ENC = 'whatsapp.access_token_enc';
    public const KEY_WA_TEMPLATE_NAME    = 'whatsapp.template_name';
    public const KEY_WA_TEMPLATE_LANG    = 'whatsapp.template_language';
    public const KEY_WA_GRAPH_VERSION    = 'whatsapp.graph_version';

    // ── Internal alerts (Slack / Discord) ─────────────────────────
    public const KEY_ALERTS_ENABLED      = 'alerts.enabled';
    public const KEY_ALERTS_SLACK_ENC    = 'alerts.slack_webhook_url_enc';
    public const KEY_ALERTS_DISCORD_ENC  = 'alerts.discord_webhook_url_enc';

    // ─────────────────────────────────────────────────────────────
    // WhatsApp accessors (admin value first, then config/whatsapp.php)
    // ─────────────────────────────────────────────────────────────

    public static function whatsappPhoneNumberId(): ?string
    {
        $admin = AppSetting::get(self::KEY_WA_PHONE_NUMBER_ID);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) config('whatsapp.phone_number_id', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function whatsappAccessToken(): ?string
    {
        $admin = self::decrypt(self::KEY_WA_ACCESS_TOKEN_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) config('whatsapp.access_token', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function whatsappTemplateName(): string
    {
        $admin = AppSetting::get(self::KEY_WA_TEMPLATE_NAME);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        return (string) config('whatsapp.template_name', 'otp_code');
    }

    public static function whatsappTemplateLanguage(): string
    {
        $admin = AppSetting::get(self::KEY_WA_TEMPLATE_LANG);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        return (string) config('whatsapp.template_language', 'en_US');
    }

    public static function whatsappGraphVersion(): string
    {
        $admin = AppSetting::get(self::KEY_WA_GRAPH_VERSION);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        return (string) config('whatsapp.graph_version', 'v21.0');
    }

    public static function setWhatsappPhoneNumberId(?string $v): void
    {
        AppSetting::put(self::KEY_WA_PHONE_NUMBER_ID, self::cleanScalar($v));
    }

    public static function setWhatsappAccessToken(?string $v): void
    {
        self::storeSecret(self::KEY_WA_ACCESS_TOKEN_ENC, $v);
    }

    public static function setWhatsappTemplateName(?string $v): void
    {
        AppSetting::put(self::KEY_WA_TEMPLATE_NAME, self::cleanScalar($v));
    }

    public static function setWhatsappTemplateLanguage(?string $v): void
    {
        AppSetting::put(self::KEY_WA_TEMPLATE_LANG, self::cleanScalar($v));
    }

    public static function setWhatsappGraphVersion(?string $v): void
    {
        AppSetting::put(self::KEY_WA_GRAPH_VERSION, self::cleanScalar($v));
    }

    /** Masked access token for the admin UI: ••••••••AbCd. */
    public static function maskedWhatsappAccessToken(): ?string
    {
        $t = self::whatsappAccessToken();
        if (!$t) return null;
        return '••••••••' . substr($t, -4);
    }

    /** True when an admin has stored a WhatsApp token (vs. env fallback). */
    public static function whatsappHasAdminValues(): bool
    {
        $phone = AppSetting::get(self::KEY_WA_PHONE_NUMBER_ID);
        $token = self::decrypt(self::KEY_WA_ACCESS_TOKEN_ENC);
        return (is_string($phone) && trim($phone) !== '') && ($token !== null && $token !== '');
    }

    /** True when delivery is possible (live or env) — i.e. not preview. */
    public static function whatsappConfigured(): bool
    {
        return self::whatsappPhoneNumberId() !== null && self::whatsappAccessToken() !== null;
    }

    /**
     * Status descriptor for the admin badge.
     *
     * @return array{key:string,label:string,tone:string}
     */
    public static function whatsappStatus(): array
    {
        if (self::whatsappHasAdminValues()) {
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::whatsappConfigured()) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Preview mode', 'tone' => 'slate'];
    }

    // ─────────────────────────────────────────────────────────────
    // Internal alert accessors
    // ─────────────────────────────────────────────────────────────

    public static function alertsEnabled(): bool
    {
        return (bool) AppSetting::get(self::KEY_ALERTS_ENABLED, false);
    }

    public static function setAlertsEnabled(bool $on): void
    {
        AppSetting::put(self::KEY_ALERTS_ENABLED, $on);
    }

    /** Slack incoming-webhook URL (admin first, then logging channel env). */
    public static function slackWebhookUrl(): ?string
    {
        $admin = self::decrypt(self::KEY_ALERTS_SLACK_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $env = (string) config('logging.channels.slack.url', '');
        return $env !== '' ? $env : null;
    }

    public static function setSlackWebhookUrl(?string $v): void
    {
        self::storeSecret(self::KEY_ALERTS_SLACK_ENC, $v);
    }

    public static function maskedSlackWebhookUrl(): ?string
    {
        return self::maskUrl(self::slackWebhookUrl());
    }

    /** True when the Slack hook came from an admin value (vs. env). */
    public static function slackHasAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_ALERTS_SLACK_ENC);
        return $v !== null && $v !== '';
    }

    /** Discord webhook URL — admin only, no env fallback. */
    public static function discordWebhookUrl(): ?string
    {
        return self::decrypt(self::KEY_ALERTS_DISCORD_ENC);
    }

    public static function setDiscordWebhookUrl(?string $v): void
    {
        self::storeSecret(self::KEY_ALERTS_DISCORD_ENC, $v);
    }

    public static function maskedDiscordWebhookUrl(): ?string
    {
        return self::maskUrl(self::discordWebhookUrl());
    }

    /** Any webhook URL available from any source. */
    public static function alertsHaveAnyWebhook(): bool
    {
        return self::slackWebhookUrl() !== null || self::discordWebhookUrl() !== null;
    }

    /**
     * Status descriptor for the admin badge.
     *
     * @return array{key:string,label:string,tone:string}
     */
    public static function alertsStatus(): array
    {
        $hasWebhook = self::alertsHaveAnyWebhook();
        if (!$hasWebhook) {
            return ['key' => 'preview', 'label' => 'Not configured', 'tone' => 'slate'];
        }
        if (!self::alertsEnabled()) {
            return ['key' => 'disabled', 'label' => 'Disabled', 'tone' => 'slate'];
        }
        if (self::slackHasAdminValue() || self::discordWebhookUrl() !== null) {
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
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

    private static function maskUrl(?string $url): ?string
    {
        if (!$url) return null;
        $tail = substr($url, -6);
        return '••••••••' . $tail;
    }
}
