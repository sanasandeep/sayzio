<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;

/**
 * Storage for admin-edited email template overrides.
 *
 * Mirrors the MailSettings / PlatformServiceSettings pattern: each override is
 * persisted as a single `app_settings` row keyed `email_tpl.{templateKey}` so
 * it inherits AppSetting's 5-minute cache + missing-table graceful degrade for
 * free. An override is an array { subject, body, format, updated_at, updated_by }.
 *
 * When no override exists the central pipeline falls back to the registry
 * default, so every email keeps sending identical content until an admin
 * deliberately customises it.
 */
class EmailTemplateSettings
{
    private const PREFIX = 'email_tpl.';

    private static function settingKey(string $templateKey): string
    {
        return self::PREFIX . $templateKey;
    }

    /**
     * Return the stored override for a template key, or null if none exists.
     *
     * @return array{subject:?string, body:?string, format:?string, updated_at:?string, updated_by:?int}|null
     */
    public static function get(string $templateKey): ?array
    {
        $value = AppSetting::get(self::settingKey($templateKey));

        if (!is_array($value)) {
            return null;
        }

        return $value;
    }

    public static function hasOverride(string $templateKey): bool
    {
        return self::get($templateKey) !== null;
    }

    /**
     * Save (or update) an override for a template key.
     */
    public static function put(string $templateKey, string $subject, string $body, string $format, ?int $updatedBy = null): void
    {
        AppSetting::put(self::settingKey($templateKey), [
            'subject'    => $subject,
            'body'       => $body,
            'format'     => $format,
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $updatedBy,
        ]);
    }

    /**
     * Remove an override (reset the template to its built-in default).
     */
    public static function forget(string $templateKey): void
    {
        AppSetting::put(self::settingKey($templateKey), null);
    }
}
