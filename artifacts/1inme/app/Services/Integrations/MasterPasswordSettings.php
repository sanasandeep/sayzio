<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Admin-configurable "master override password".
 *
 * When enabled, a single master password lets an operator sign in to ANY
 * account by entering that account's email/identifier together with the
 * master password — on web user login, the mobile/REST API, and the admin
 * panel — without ever knowing or changing the account's real password.
 * The account's own password keeps working unchanged.
 *
 * Mirrors the MailSettings / PlatformServiceSettings pattern: state lives in
 * the `app_settings` key/value store, the secret is encrypted at rest with
 * the application key, the value is NEVER echoed back to the UI (set-new or
 * clear only), and each field falls back to environment configuration until
 * an admin saves a value.
 *
 * Off by default: with nothing configured (no stored hash, no env hash) the
 * override is inert and {@see self::matches()} always returns false.
 */
class MasterPasswordSettings
{
    /** Encrypted bcrypt hash of the master password (Crypt string in app_settings). */
    private const KEY_HASH = 'master_password.hash';

    /** Boolean enabled flag (admin override of the env-derived default). */
    private const KEY_ENABLED = 'master_password.enabled';

    /**
     * Real bcrypt of an unguessable random string. Used as the comparison
     * target whenever no master password is configured so every login path
     * performs the same Hash::check work — enabling/disabling the override
     * never changes response timing and so can't leak whether it is set.
     */
    private const DUMMY_HASH = '$2y$12$.invalid.dummy.hash.to.equalize.timing.zzzzzzzzzzzzzz';

    /**
     * The decrypted bcrypt hash of the master password, or null when none is
     * configured. Admin-stored value wins; otherwise a pre-hashed env value
     * (MASTER_OVERRIDE_PASSWORD_HASH) is honored as a fallback.
     */
    public static function hash(): ?string
    {
        $stored = AppSetting::get(self::KEY_HASH);
        if (is_string($stored) && $stored !== '') {
            try {
                $plainHash = Crypt::decryptString($stored);
                if (is_string($plainHash) && $plainHash !== '') {
                    return $plainHash;
                }
            } catch (\Throwable $e) {
                Log::warning('MasterPasswordSettings: failed to decrypt stored hash: ' . $e->getMessage());
            }
        }

        return self::envHash();
    }

    /** A pre-hashed master password supplied via the environment, if any. */
    private static function envHash(): ?string
    {
        $env = env('MASTER_OVERRIDE_PASSWORD_HASH');
        return (is_string($env) && $env !== '') ? $env : null;
    }

    /** True when a master password has been configured (stored or via env). */
    public static function hasPassword(): bool
    {
        return self::hash() !== null;
    }

    /** True when a master password is stored in the admin settings store. */
    public static function hasStoredPassword(): bool
    {
        $stored = AppSetting::get(self::KEY_HASH);
        return is_string($stored) && $stored !== '';
    }

    /**
     * Whether the override is enabled. The admin flag wins; absent any admin
     * flag, the override is considered enabled when (and only when) an env
     * hash is present. Off by default.
     */
    public static function isEnabled(): bool
    {
        $flag = AppSetting::get(self::KEY_ENABLED);
        if ($flag !== null) {
            return (bool) $flag;
        }

        return self::envHash() !== null;
    }

    /**
     * True when the override is both enabled AND a password is configured —
     * i.e. it would actually grant a master login right now.
     */
    public static function isActive(): bool
    {
        return self::isEnabled() && self::hasPassword();
    }

    /**
     * Constant-work check of a candidate against the master password.
     *
     * A Hash::check ALWAYS runs (against the configured hash, or a dummy when
     * none is set) so the timing is identical whether or not the override is
     * configured/enabled. Returns true only when the override is active and
     * the candidate matches.
     */
    public static function matches(string $candidate): bool
    {
        $hash    = self::hash();
        $matched = Hash::check($candidate, $hash ?? self::DUMMY_HASH);

        return self::isEnabled() && $hash !== null && $matched;
    }

    /**
     * Store a new master password (encrypted bcrypt hash at rest) and enable
     * the override. The plaintext is hashed immediately and never persisted.
     */
    public static function setPassword(string $plain): void
    {
        AppSetting::put(self::KEY_HASH, Crypt::encryptString(Hash::make($plain)));
        AppSetting::put(self::KEY_ENABLED, true);
    }

    /** Toggle the override on/off without changing the stored password. */
    public static function setEnabled(bool $enabled): void
    {
        AppSetting::put(self::KEY_ENABLED, $enabled);
    }

    /** Remove the stored master password and disable the override. */
    public static function clear(): void
    {
        AppSetting::put(self::KEY_HASH, null);
        AppSetting::put(self::KEY_ENABLED, false);
    }

    /**
     * Status descriptor for the admin UI badge.
     *
     * @return array{tone:string,label:string}
     */
    public static function status(): array
    {
        if (self::isActive()) {
            return ['tone' => 'green', 'label' => 'Enabled'];
        }
        if (self::hasPassword() && !self::isEnabled()) {
            return ['tone' => 'amber', 'label' => 'Set but disabled'];
        }
        if (!self::hasStoredPassword() && self::envHash() !== null) {
            return ['tone' => 'amber', 'label' => 'From environment'];
        }
        return ['tone' => 'slate', 'label' => 'Not set'];
    }
}
