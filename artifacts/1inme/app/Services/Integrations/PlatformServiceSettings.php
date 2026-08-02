<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Typed accessor for the platform services that, until now, were only
 * configurable through the server environment: Google Places (reviews),
 * Trustpilot (reviews), Google Contacts OAuth, and the S3/CloudFront
 * user-content storage backend.
 *
 * Mirrors the MailSettings / IntegrationKeySettings pattern exactly:
 *   - every value lives in the `app_settings` key/value store,
 *   - secrets are Crypt-encrypted at rest and never echoed back (masked),
 *   - each getter falls back to the existing config/env so nothing breaks
 *     when an admin hasn't configured anything yet, and
 *   - applyRuntimeConfig() pushes the effective values back into
 *     config('services.*') / config('filesystems.*') at boot so the
 *     adapters and the filesystem manager pick them up without a redeploy.
 */
class PlatformServiceSettings
{
    // ── Google Places (Google Business Profile reviews) ───────────
    public const KEY_GOOGLE_PLACES_API_KEY_ENC = 'google_places.api_key_enc';

    // ── Trustpilot reviews ────────────────────────────────────────
    public const KEY_TRUSTPILOT_API_KEY_ENC = 'trustpilot.api_key_enc';

    // ── Google Contacts OAuth ─────────────────────────────────────
    public const KEY_GOOGLE_CONTACTS_CLIENT_ID      = 'google_contacts.client_id';
    public const KEY_GOOGLE_CONTACTS_CLIENT_SEC_ENC = 'google_contacts.client_secret_enc';

    // ── Google Calendar OAuth (Service Booking two-way sync) ─────
    public const KEY_GOOGLE_CALENDAR_CLIENT_ID      = 'google_calendar.client_id';
    public const KEY_GOOGLE_CALENDAR_CLIENT_SEC_ENC = 'google_calendar.client_secret_enc';

    // ── Microsoft Outlook / 365 Calendar OAuth (Graph two-way sync) ─
    public const KEY_MICROSOFT_CALENDAR_CLIENT_ID      = 'microsoft_calendar.client_id';
    public const KEY_MICROSOFT_CALENDAR_CLIENT_SEC_ENC = 'microsoft_calendar.client_secret_enc';

    // ── S3 / CloudFront user-content storage ──────────────────────
    // KEY_S3_ENABLED is intentionally retired: S3 is now mandatory for user
    // content, there is no admin-facing "disable S3" switch. The constant is
    // kept (unused for writes) only so any stale historical app_settings row
    // is harmless if still present.
    public const KEY_S3_ENABLED        = 'storage.s3_enabled';
    public const KEY_S3_KEY_ENC        = 'storage.s3_key_enc';
    public const KEY_S3_SECRET_ENC     = 'storage.s3_secret_enc';
    public const KEY_S3_REGION         = 'storage.s3_region';
    public const KEY_S3_BUCKET         = 'storage.s3_bucket';
    public const KEY_S3_URL            = 'storage.s3_url';
    public const KEY_S3_ENDPOINT       = 'storage.s3_endpoint';
    public const KEY_S3_PATH_STYLE     = 'storage.s3_use_path_style';

    public const S3_DISK_NAMES = ['public', 'user_files', 'admin_assets', 's3'];

    // ── Google Custom Search (AI builder image search) ────────────
    public const KEY_GOOGLE_CSE_API_KEY_ENC = 'google_cse.api_key_enc';
    public const KEY_GOOGLE_CSE_ENGINE_ID   = 'google_cse.engine_id';
    public const KEY_GOOGLE_CSE_USER_DAILY_CAP = 'google_cse.user_daily_cap';

    // ── GitHub personal access token ──────────────────────────────
    // Shared by the GitHub push sync (SystemUpdateService / github:check-token)
    // and the zio-browser:refresh-release GitHub API calls (raises the
    // anonymous 60 req/hr rate limit to 5,000 req/hr).
    public const KEY_GITHUB_TOKEN_ENC = 'github.token_enc';

    // ═════════════════════════════════════════════════════════════
    // Google Places
    // ═════════════════════════════════════════════════════════════

    public static function googlePlacesApiKey(): ?string
    {
        $admin = self::decrypt(self::KEY_GOOGLE_PLACES_API_KEY_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (config('services.google_places.api_key') ?: env('GOOGLE_PLACES_API_KEY', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setGooglePlacesApiKey(?string $v): void
    {
        self::storeSecret(self::KEY_GOOGLE_PLACES_API_KEY_ENC, $v);
    }

    public static function googlePlacesHasAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_GOOGLE_PLACES_API_KEY_ENC);
        return $v !== null && $v !== '';
    }

    public static function maskedGooglePlacesApiKey(): ?string
    {
        return self::maskSecret(self::googlePlacesApiKey());
    }

    public static function googlePlacesStatus(): array
    {
        return self::secretStatus(self::googlePlacesHasAdminValue(), self::googlePlacesApiKey() !== null);
    }

    // ═════════════════════════════════════════════════════════════
    // Trustpilot
    // ═════════════════════════════════════════════════════════════

    public static function trustpilotApiKey(): ?string
    {
        $admin = self::decrypt(self::KEY_TRUSTPILOT_API_KEY_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (config('services.trustpilot.api_key') ?: env('TRUSTPILOT_API_KEY', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setTrustpilotApiKey(?string $v): void
    {
        self::storeSecret(self::KEY_TRUSTPILOT_API_KEY_ENC, $v);
    }

    public static function trustpilotHasAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_TRUSTPILOT_API_KEY_ENC);
        return $v !== null && $v !== '';
    }

    public static function maskedTrustpilotApiKey(): ?string
    {
        return self::maskSecret(self::trustpilotApiKey());
    }

    public static function trustpilotStatus(): array
    {
        return self::secretStatus(self::trustpilotHasAdminValue(), self::trustpilotApiKey() !== null);
    }

    // ═════════════════════════════════════════════════════════════
    // Google Contacts OAuth
    // ═════════════════════════════════════════════════════════════

    public static function googleContactsClientId(): ?string
    {
        $admin = AppSetting::get(self::KEY_GOOGLE_CONTACTS_CLIENT_ID);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) (config('services.google_contacts.client_id') ?: env('GOOGLE_CONTACTS_CLIENT_ID', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function googleContactsClientSecret(): ?string
    {
        $admin = self::decrypt(self::KEY_GOOGLE_CONTACTS_CLIENT_SEC_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (config('services.google_contacts.client_secret') ?: env('GOOGLE_CONTACTS_CLIENT_SECRET', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setGoogleContactsClientId(?string $v): void
    {
        AppSetting::put(self::KEY_GOOGLE_CONTACTS_CLIENT_ID, self::cleanScalar($v));
    }

    public static function setGoogleContactsClientSecret(?string $v): void
    {
        self::storeSecret(self::KEY_GOOGLE_CONTACTS_CLIENT_SEC_ENC, $v);
    }

    public static function maskedGoogleContactsClientSecret(): ?string
    {
        return self::maskSecret(self::googleContactsClientSecret());
    }

    public static function googleContactsHasAdminValue(): bool
    {
        $id  = AppSetting::get(self::KEY_GOOGLE_CONTACTS_CLIENT_ID);
        $sec = self::decrypt(self::KEY_GOOGLE_CONTACTS_CLIENT_SEC_ENC);
        return (is_string($id) && trim($id) !== '') || ($sec !== null && $sec !== '');
    }

    public static function googleContactsConfigured(): bool
    {
        return self::googleContactsClientId() !== null && self::googleContactsClientSecret() !== null;
    }

    public static function googleContactsStatus(): array
    {
        if (self::googleContactsHasAdminValue()) {
            // Configured here but only a half-pair? warn.
            if (!self::googleContactsConfigured()) {
                return ['key' => 'incomplete', 'label' => 'Incomplete (need both)', 'tone' => 'amber'];
            }
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::googleContactsConfigured()) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Not configured', 'tone' => 'slate'];
    }

    // ═════════════════════════════════════════════════════════════
    // Google Calendar OAuth (Service Booking two-way sync)
    // ═════════════════════════════════════════════════════════════

    public static function googleCalendarClientId(): ?string
    {
        $admin = AppSetting::get(self::KEY_GOOGLE_CALENDAR_CLIENT_ID);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) (config('services.google_calendar.client_id') ?: env('GOOGLE_CALENDAR_CLIENT_ID', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function googleCalendarClientSecret(): ?string
    {
        $admin = self::decrypt(self::KEY_GOOGLE_CALENDAR_CLIENT_SEC_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (config('services.google_calendar.client_secret') ?: env('GOOGLE_CALENDAR_CLIENT_SECRET', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setGoogleCalendarClientId(?string $v): void
    {
        AppSetting::put(self::KEY_GOOGLE_CALENDAR_CLIENT_ID, self::cleanScalar($v));
    }

    public static function setGoogleCalendarClientSecret(?string $v): void
    {
        self::storeSecret(self::KEY_GOOGLE_CALENDAR_CLIENT_SEC_ENC, $v);
    }

    public static function maskedGoogleCalendarClientSecret(): ?string
    {
        return self::maskSecret(self::googleCalendarClientSecret());
    }

    public static function googleCalendarHasAdminValue(): bool
    {
        $id  = AppSetting::get(self::KEY_GOOGLE_CALENDAR_CLIENT_ID);
        $sec = self::decrypt(self::KEY_GOOGLE_CALENDAR_CLIENT_SEC_ENC);
        return (is_string($id) && trim($id) !== '') || ($sec !== null && $sec !== '');
    }

    public static function googleCalendarConfigured(): bool
    {
        return self::googleCalendarClientId() !== null && self::googleCalendarClientSecret() !== null;
    }

    public static function googleCalendarStatus(): array
    {
        if (self::googleCalendarHasAdminValue()) {
            if (!self::googleCalendarConfigured()) {
                return ['key' => 'incomplete', 'label' => 'Incomplete (need both)', 'tone' => 'amber'];
            }
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::googleCalendarConfigured()) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Not configured', 'tone' => 'slate'];
    }

    // ═════════════════════════════════════════════════════════════
    // Microsoft Outlook / 365 Calendar OAuth (Graph two-way sync)
    // ═════════════════════════════════════════════════════════════

    public static function microsoftCalendarClientId(): ?string
    {
        $admin = AppSetting::get(self::KEY_MICROSOFT_CALENDAR_CLIENT_ID);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) (config('services.microsoft_calendar.client_id') ?: env('MICROSOFT_CALENDAR_CLIENT_ID', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function microsoftCalendarClientSecret(): ?string
    {
        $admin = self::decrypt(self::KEY_MICROSOFT_CALENDAR_CLIENT_SEC_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (config('services.microsoft_calendar.client_secret') ?: env('MICROSOFT_CALENDAR_CLIENT_SECRET', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setMicrosoftCalendarClientId(?string $v): void
    {
        AppSetting::put(self::KEY_MICROSOFT_CALENDAR_CLIENT_ID, self::cleanScalar($v));
    }

    public static function setMicrosoftCalendarClientSecret(?string $v): void
    {
        self::storeSecret(self::KEY_MICROSOFT_CALENDAR_CLIENT_SEC_ENC, $v);
    }

    public static function maskedMicrosoftCalendarClientSecret(): ?string
    {
        return self::maskSecret(self::microsoftCalendarClientSecret());
    }

    public static function microsoftCalendarHasAdminValue(): bool
    {
        $id  = AppSetting::get(self::KEY_MICROSOFT_CALENDAR_CLIENT_ID);
        $sec = self::decrypt(self::KEY_MICROSOFT_CALENDAR_CLIENT_SEC_ENC);
        return (is_string($id) && trim($id) !== '') || ($sec !== null && $sec !== '');
    }

    public static function microsoftCalendarConfigured(): bool
    {
        return self::microsoftCalendarClientId() !== null && self::microsoftCalendarClientSecret() !== null;
    }

    public static function microsoftCalendarStatus(): array
    {
        if (self::microsoftCalendarHasAdminValue()) {
            if (!self::microsoftCalendarConfigured()) {
                return ['key' => 'incomplete', 'label' => 'Incomplete (need both)', 'tone' => 'amber'];
            }
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::microsoftCalendarConfigured()) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Not configured', 'tone' => 'slate'];
    }

    // ═════════════════════════════════════════════════════════════
    // S3 / CloudFront user-content storage
    // ═════════════════════════════════════════════════════════════

    /**
     * User-content disks are always S3-backed — there is no local-disk mode
     * to opt out of. Kept as a method (rather than removed outright) because
     * a few call sites still ask "is S3 on" for status/UI purposes; it is
     * hardcoded true and can no longer be turned off from the admin UI.
     */
    public static function s3Enabled(): bool
    {
        return true;
    }

    public static function s3HasAdminValue(): bool
    {
        foreach ([
            self::KEY_S3_KEY_ENC, self::KEY_S3_SECRET_ENC,
            self::KEY_S3_REGION, self::KEY_S3_BUCKET, self::KEY_S3_URL,
            self::KEY_S3_ENDPOINT, self::KEY_S3_PATH_STYLE,
        ] as $key) {
            $v = AppSetting::get($key);
            if ($v !== null && $v !== '') return true;
        }
        return false;
    }

    public static function s3Key(): ?string
    {
        $admin = self::decrypt(self::KEY_S3_KEY_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) env('AWS_ACCESS_KEY_ID', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function s3Secret(): ?string
    {
        $admin = self::decrypt(self::KEY_S3_SECRET_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) env('AWS_SECRET_ACCESS_KEY', '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function s3Region(): ?string
    {
        return self::scalarWithEnv(self::KEY_S3_REGION, 'AWS_DEFAULT_REGION');
    }

    public static function s3Bucket(): ?string
    {
        return self::scalarWithEnv(self::KEY_S3_BUCKET, 'AWS_BUCKET');
    }

    public static function s3Url(): ?string
    {
        return self::scalarWithEnv(self::KEY_S3_URL, 'AWS_URL');
    }

    public static function s3Endpoint(): ?string
    {
        return self::scalarWithEnv(self::KEY_S3_ENDPOINT, 'AWS_ENDPOINT');
    }

    public static function s3UsePathStyle(): bool
    {
        $admin = AppSetting::get(self::KEY_S3_PATH_STYLE);
        if ($admin !== null) return (bool) $admin;
        return (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false);
    }

    public static function maskedS3Key(): ?string
    {
        return self::maskSecret(self::s3Key());
    }

    public static function maskedS3Secret(): ?string
    {
        return self::maskSecret(self::s3Secret());
    }

    public static function s3HasKeyAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_S3_KEY_ENC);
        return $v !== null && $v !== '';
    }

    public static function s3HasSecretAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_S3_SECRET_ENC);
        return $v !== null && $v !== '';
    }

    public static function setS3Key(?string $v): void
    {
        self::storeSecret(self::KEY_S3_KEY_ENC, $v);
    }

    public static function setS3Secret(?string $v): void
    {
        self::storeSecret(self::KEY_S3_SECRET_ENC, $v);
    }

    public static function setS3Region(?string $v): void
    {
        AppSetting::put(self::KEY_S3_REGION, self::cleanScalar($v));
    }

    public static function setS3Bucket(?string $v): void
    {
        AppSetting::put(self::KEY_S3_BUCKET, self::cleanScalar($v));
    }

    public static function setS3Url(?string $v): void
    {
        AppSetting::put(self::KEY_S3_URL, self::cleanScalar($v));
    }

    public static function setS3Endpoint(?string $v): void
    {
        AppSetting::put(self::KEY_S3_ENDPOINT, self::cleanScalar($v));
    }

    public static function setS3UsePathStyle(bool $on): void
    {
        AppSetting::put(self::KEY_S3_PATH_STYLE, $on);
    }

    /**
     * Whether the effective S3 config is complete enough to drive the
     * user-content disks (credentials + bucket + region present).
     */
    public static function s3Configured(): bool
    {
        return self::s3Key() !== null
            && self::s3Secret() !== null
            && self::s3Bucket() !== null
            && self::s3Region() !== null;
    }

    /**
     * Human-readable names of the required S3 pieces that are missing from
     * the effective (admin-or-env) config. Empty array ⇒ fully configured.
     *
     * @return array<int,string>
     */
    public static function s3MissingPieces(): array
    {
        $missing = [];
        if (self::s3Key() === null)    $missing[] = 'access key';
        if (self::s3Secret() === null) $missing[] = 'secret key';
        if (self::s3Bucket() === null) $missing[] = 'bucket';
        if (self::s3Region() === null) $missing[] = 'region';
        return $missing;
    }

    public static function s3Status(): array
    {
        if (!self::s3Configured()) {
            return ['key' => 'incomplete', 'label' => 'Missing credentials — uploads will fail', 'tone' => 'red'];
        }
        if (self::s3HasAdminValue()) {
            return ['key' => 'configured', 'label' => 'S3 (configured)', 'tone' => 'green'];
        }
        return ['key' => 'env', 'label' => 'S3 (env fallback)', 'tone' => 'amber'];
    }

    /**
     * Build the effective S3 disk array (mirrors config/filesystems.php).
     * No `visibility` key — the bucket has ACLs disabled.
     *
     * @return array<string,mixed>
     */
    public static function s3DiskArray(): array
    {
        return [
            'driver'                  => 's3',
            'key'                     => self::s3Key(),
            'secret'                  => self::s3Secret(),
            'region'                  => self::s3Region(),
            'bucket'                  => self::s3Bucket(),
            'url'                     => self::s3Url(),
            'endpoint'                => self::s3Endpoint(),
            'use_path_style_endpoint' => self::s3UsePathStyle(),
            'throw'                   => false,
            'report'                  => false,
        ];
    }

    /**
     * Write + read back + delete a tiny probe object to verify the
     * configured S3 credentials actually work. Applies the runtime config
     * first so the probe targets the admin-configured bucket.
     *
     * @return array{ok:bool,error:?string}
     */
    public static function verifyS3(): array
    {
        if (!self::s3Configured()) {
            return ['ok' => false, 'error' => 'S3 is not fully configured (need key, secret, bucket and region).'];
        }

        self::applyRuntimeConfig();

        $probe = 'health/integration-probe-' . Str::random(12) . '.txt';
        try {
            Storage::forgetDisk('s3');
            $disk = Storage::disk('s3');
            $disk->put($probe, 'ok ' . now()->toIso8601String());
            $back = $disk->get($probe);
            $disk->delete($probe);
            if ($back === null) {
                return ['ok' => false, 'error' => 'Wrote a probe object but could not read it back.'];
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        return ['ok' => true, 'error' => null];
    }

    // ═════════════════════════════════════════════════════════════
    // Connected Apps — per-CRM OAuth client credentials + GA toggle
    // ═════════════════════════════════════════════════════════════
    //
    // CRMs (Salesforce / HubSpot / Zoho) authenticate creators via OAuth using
    // a platform-level client id + secret the admin provides here; absent ⇒
    // the provider shows as "coming soon" in the creator UI. Google Analytics
    // needs no platform OAuth client (creators bring their own Measurement ID +
    // API secret), so it is gated by a simple admin enable flag instead.

    public const KEY_CONNECTED_APP_CLIENT_ID  = 'connected_apps.%s.client_id';
    public const KEY_CONNECTED_APP_SECRET_ENC = 'connected_apps.%s.client_secret_enc';
    public const KEY_GOOGLE_ANALYTICS_ENABLED = 'connected_apps.google_analytics.enabled';

    private static function connectedAppIdKey(string $provider): string
    {
        return sprintf(self::KEY_CONNECTED_APP_CLIENT_ID, $provider);
    }

    private static function connectedAppSecretKey(string $provider): string
    {
        return sprintf(self::KEY_CONNECTED_APP_SECRET_ENC, $provider);
    }

    public static function connectedAppClientId(string $provider): ?string
    {
        $v = AppSetting::get(self::connectedAppIdKey($provider));
        if (is_string($v) && trim($v) !== '') return trim($v);
        // env fallback: e.g. SALESFORCE_CLIENT_ID
        $env = (string) env(strtoupper($provider) . '_CLIENT_ID', '');
        return $env !== '' ? $env : null;
    }

    public static function connectedAppClientSecret(string $provider): ?string
    {
        $v = self::decrypt(self::connectedAppSecretKey($provider));
        if ($v !== null && $v !== '') return $v;
        $env = (string) env(strtoupper($provider) . '_CLIENT_SECRET', '');
        return $env !== '' ? $env : null;
    }

    public static function setConnectedAppClientId(string $provider, ?string $v): void
    {
        AppSetting::put(self::connectedAppIdKey($provider), self::cleanScalar($v));
    }

    public static function setConnectedAppClientSecret(string $provider, ?string $v): void
    {
        self::storeSecret(self::connectedAppSecretKey($provider), $v);
    }

    public static function maskedConnectedAppClientSecret(string $provider): ?string
    {
        return self::maskSecret(self::connectedAppClientSecret($provider));
    }

    public static function connectedAppHasAdminValue(string $provider): bool
    {
        $id  = AppSetting::get(self::connectedAppIdKey($provider));
        $sec = self::decrypt(self::connectedAppSecretKey($provider));
        return (is_string($id) && trim($id) !== '') || ($sec !== null && $sec !== '');
    }

    public static function connectedAppConfigured(string $provider): bool
    {
        return self::connectedAppClientId($provider) !== null
            && self::connectedAppClientSecret($provider) !== null;
    }

    public static function connectedAppStatus(string $provider): array
    {
        if (self::connectedAppHasAdminValue($provider)) {
            if (!self::connectedAppConfigured($provider)) {
                return ['key' => 'incomplete', 'label' => 'Incomplete (need both)', 'tone' => 'amber'];
            }
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::connectedAppConfigured($provider)) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Not configured', 'tone' => 'slate'];
    }

    public static function googleAnalyticsEnabled(): bool
    {
        $v = AppSetting::get(self::KEY_GOOGLE_ANALYTICS_ENABLED);
        if ($v !== null) return (bool) $v;
        return (bool) env('CONNECTED_APPS_GA_ENABLED', false);
    }

    public static function setGoogleAnalyticsEnabled(bool $on): void
    {
        AppSetting::put(self::KEY_GOOGLE_ANALYTICS_ENABLED, $on);
    }

    public static function googleAnalyticsStatus(): array
    {
        return self::googleAnalyticsEnabled()
            ? ['key' => 'configured', 'label' => 'Enabled', 'tone' => 'green']
            : ['key' => 'preview', 'label' => 'Disabled', 'tone' => 'slate'];
    }

    // ═════════════════════════════════════════════════════════════
    // Social OAuth (login + follow) — per-provider client credentials
    // ═════════════════════════════════════════════════════════════
    //
    // The "Continue with Google / LinkedIn / …" sign-in buttons and the
    // creator "Connect with …" follower-sync flow both authenticate through
    // SocialOAuthService, which historically read GOOGLE_CLIENT_ID /
    // *_CLIENT_SECRET (etc.) straight from the environment. These accessors
    // let an admin set the same client id + secret from the Integrations UI
    // instead, falling back to the provider's env var when nothing is saved —
    // so existing env-configured installs keep working untouched.
    //
    // The env var *names* differ per provider (GOOGLE_CLIENT_ID,
    // TIKTOK_CLIENT_KEY, …), so callers pass the provider's env key from
    // SocialOAuthService::PROVIDERS rather than deriving it.

    public const KEY_SOCIAL_OAUTH_CLIENT_ID  = 'social_oauth.%s.client_id';
    public const KEY_SOCIAL_OAUTH_SECRET_ENC = 'social_oauth.%s.client_secret_enc';

    private static function socialOAuthIdKey(string $provider): string
    {
        return sprintf(self::KEY_SOCIAL_OAUTH_CLIENT_ID, $provider);
    }

    private static function socialOAuthSecretKey(string $provider): string
    {
        return sprintf(self::KEY_SOCIAL_OAUTH_SECRET_ENC, $provider);
    }

    public static function socialOAuthClientId(string $provider, string $envKey): ?string
    {
        $v = AppSetting::get(self::socialOAuthIdKey($provider));
        if (is_string($v) && trim($v) !== '') return trim($v);
        $env = (string) env($envKey, '');
        return $env !== '' ? $env : null;
    }

    public static function socialOAuthClientSecret(string $provider, string $envKey): ?string
    {
        $v = self::decrypt(self::socialOAuthSecretKey($provider));
        if ($v !== null && $v !== '') return $v;
        $env = (string) env($envKey, '');
        return $env !== '' ? $env : null;
    }

    public static function setSocialOAuthClientId(string $provider, ?string $v): void
    {
        AppSetting::put(self::socialOAuthIdKey($provider), self::cleanScalar($v));
    }

    public static function setSocialOAuthClientSecret(string $provider, ?string $v): void
    {
        self::storeSecret(self::socialOAuthSecretKey($provider), $v);
    }

    public static function socialOAuthAdminClientId(string $provider): ?string
    {
        $v = AppSetting::get(self::socialOAuthIdKey($provider));
        return (is_string($v) && trim($v) !== '') ? trim($v) : null;
    }

    public static function maskedSocialOAuthClientSecret(string $provider, string $envKey): ?string
    {
        return self::maskSecret(self::socialOAuthClientSecret($provider, $envKey));
    }

    public static function socialOAuthHasAdminValue(string $provider): bool
    {
        $id  = AppSetting::get(self::socialOAuthIdKey($provider));
        $sec = self::decrypt(self::socialOAuthSecretKey($provider));
        return (is_string($id) && trim($id) !== '') || ($sec !== null && $sec !== '');
    }

    public static function socialOAuthHasSecretAdminValue(string $provider): bool
    {
        $v = self::decrypt(self::socialOAuthSecretKey($provider));
        return $v !== null && $v !== '';
    }

    // ═════════════════════════════════════════════════════════════
    // Google Custom Search (Programmable Search Engine) — image search
    // for the AI biolink builder. API key is a secret; the engine ID
    // (cx) is plain configuration.
    // ═════════════════════════════════════════════════════════════

    public static function googleCseApiKey(): ?string
    {
        $admin = self::decrypt(self::KEY_GOOGLE_CSE_API_KEY_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (config('services.google_cse.api_key') ?: env('GOOGLE_CSE_API_KEY', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function googleCseEngineId(): ?string
    {
        $admin = AppSetting::get(self::KEY_GOOGLE_CSE_ENGINE_ID);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) (config('services.google_cse.engine_id') ?: env('GOOGLE_CSE_ENGINE_ID', ''));
        return $cfg !== '' ? $cfg : null;
    }

    public static function setGoogleCseApiKey(?string $v): void
    {
        self::storeSecret(self::KEY_GOOGLE_CSE_API_KEY_ENC, $v);
    }

    public static function setGoogleCseEngineId(?string $v): void
    {
        AppSetting::put(self::KEY_GOOGLE_CSE_ENGINE_ID, self::cleanScalar($v));
    }

    public static function maskedGoogleCseApiKey(): ?string
    {
        return self::maskSecret(self::googleCseApiKey());
    }

    public static function googleCseHasAdminValue(): bool
    {
        $id  = AppSetting::get(self::KEY_GOOGLE_CSE_ENGINE_ID);
        $sec = self::decrypt(self::KEY_GOOGLE_CSE_API_KEY_ENC);
        return (is_string($id) && trim($id) !== '') || ($sec !== null && $sec !== '');
    }

    public static function googleCseHasKeyAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_GOOGLE_CSE_API_KEY_ENC);
        return $v !== null && $v !== '';
    }

    /** Both the API key and the engine ID resolve ⇒ image search is live. */
    public static function googleCseConfigured(): bool
    {
        return self::googleCseApiKey() !== null && self::googleCseEngineId() !== null;
    }

    /** Per-user daily image-search cap. 0 = unlimited. */
    public static function googleCseUserDailyCap(): int
    {
        $v = AppSetting::get(self::KEY_GOOGLE_CSE_USER_DAILY_CAP);
        return is_numeric($v) ? max(0, (int) $v) : 0;
    }

    public static function setGoogleCseUserDailyCap(?int $v): void
    {
        AppSetting::put(self::KEY_GOOGLE_CSE_USER_DAILY_CAP, $v !== null ? max(0, $v) : null);
    }

    public static function googleCseStatus(): array
    {
        if (self::googleCseHasAdminValue()) {
            if (!self::googleCseConfigured()) {
                return ['key' => 'incomplete', 'label' => 'Incomplete (need both)', 'tone' => 'amber'];
            }
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::googleCseConfigured()) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Preview mode', 'tone' => 'slate'];
    }

    // ═════════════════════════════════════════════════════════════
    // GitHub personal access token
    // ═════════════════════════════════════════════════════════════

    public static function githubToken(): ?string
    {
        $admin = self::decrypt(self::KEY_GITHUB_TOKEN_ENC);
        if ($admin !== null && $admin !== '') return $admin;
        $cfg = (string) (env('GITHUB_TOKEN', '') ?: '');
        return $cfg !== '' ? $cfg : null;
    }

    public static function setGithubToken(?string $v): void
    {
        self::storeSecret(self::KEY_GITHUB_TOKEN_ENC, $v);
    }

    public static function githubHasAdminValue(): bool
    {
        $v = self::decrypt(self::KEY_GITHUB_TOKEN_ENC);
        return $v !== null && $v !== '';
    }

    public static function maskedGithubToken(): ?string
    {
        return self::maskSecret(self::githubToken());
    }

    public static function githubStatus(): array
    {
        if (self::githubHasAdminValue()) {
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if (self::githubToken() !== null) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Not configured (rate-limited)', 'tone' => 'slate'];
    }

    // ═════════════════════════════════════════════════════════════
    // Runtime override
    // ═════════════════════════════════════════════════════════════

    /**
     * Push the effective (admin-or-env) values into the live config so the
     * review adapters, Google Contacts provider and filesystem manager all
     * pick up admin-configured values without a redeploy. Each section is a
     * no-op when an admin has never saved anything for it, leaving the pure
     * env/config defaults intact.
     */
    public static function applyRuntimeConfig(): void
    {
        // ── Reviews / Contacts API keys ──────────────────────────
        if (self::googlePlacesHasAdminValue()) {
            config(['services.google_places.api_key' => self::googlePlacesApiKey()]);
        }
        if (self::trustpilotHasAdminValue()) {
            config(['services.trustpilot.api_key' => self::trustpilotApiKey()]);
        }
        if (self::googleContactsHasAdminValue()) {
            config([
                'services.google_contacts.client_id'     => self::googleContactsClientId(),
                'services.google_contacts.client_secret' => self::googleContactsClientSecret(),
            ]);
        }
        if (self::googleCalendarHasAdminValue()) {
            config([
                'services.google_calendar.client_id'     => self::googleCalendarClientId(),
                'services.google_calendar.client_secret' => self::googleCalendarClientSecret(),
            ]);
        }
        if (self::microsoftCalendarHasAdminValue()) {
            config([
                'services.microsoft_calendar.client_id'     => self::microsoftCalendarClientId(),
                'services.microsoft_calendar.client_secret' => self::microsoftCalendarClientSecret(),
            ]);
        }

        // ── Google Custom Search (AI builder image search) ───────
        if (self::googleCseHasAdminValue()) {
            config([
                'services.google_cse.api_key'   => self::googleCseApiKey(),
                'services.google_cse.engine_id' => self::googleCseEngineId(),
            ]);
        }

        // ── GitHub personal access token ─────────────────────────
        // Shared by the GitHub push sync and the zio-browser release
        // refresh (raises the GitHub API rate limit). Only override when
        // an admin has actually saved a token, so the GITHUB_TOKEN env
        // secret keeps working untouched otherwise.
        if (self::githubHasAdminValue()) {
            config(['services.github.token' => self::githubToken()]);
        }

        // ── S3 user-content storage ──────────────────────────────
        // User-content disks are always S3 (config/filesystems.php has no
        // local fallback). Only override the env-driven disk arrays when an
        // admin has saved storage settings AND the effective config resolves
        // to a usable S3 disk; otherwise leave the env-driven arrangement
        // untouched (it is already S3-shaped).
        if (self::s3HasAdminValue() && self::s3Configured()) {
            $s3 = self::s3DiskArray();
            foreach (self::S3_DISK_NAMES as $name) {
                config(["filesystems.disks.{$name}" => $s3]);
                // Drop any disk the FilesystemManager may have already
                // resolved from the env config so the new array takes effect.
                try {
                    Storage::forgetDisk($name);
                } catch (\Throwable $e) {
                    // best-effort
                }
            }
        }

        if (!self::s3Configured()) {
            \Illuminate\Support\Facades\Log::warning(
                'S3 user-content storage is not fully configured (missing key/secret/bucket/region). '
                . 'User file uploads will fail loudly until an admin fixes this in Integrations > Storage.'
            );

            // Proactively alert ops admins (in-app + email, cooldown-guarded
            // so it isn't spammy). Best-effort and web-boot-only — console
            // boots are covered by the hourly storage:check-s3-config
            // command, which also sends the recovery all-clear.
            StorageHealthAlerts::alertFromBoot();
        }
    }

    // ═════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════

    private static function scalarWithEnv(string $key, string $envName): ?string
    {
        $admin = AppSetting::get($key);
        if (is_string($admin) && trim($admin) !== '') return trim($admin);
        $cfg = (string) env($envName, '');
        return $cfg !== '' ? $cfg : null;
    }

    private static function secretStatus(bool $hasAdminValue, bool $hasAnyValue): array
    {
        if ($hasAdminValue) {
            return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
        }
        if ($hasAnyValue) {
            return ['key' => 'env', 'label' => 'Using env fallback', 'tone' => 'amber'];
        }
        return ['key' => 'preview', 'label' => 'Preview mode', 'tone' => 'slate'];
    }

    private static function maskSecret(?string $secret): ?string
    {
        if (!$secret) return null;
        return '••••••••' . substr($secret, -4);
    }

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
