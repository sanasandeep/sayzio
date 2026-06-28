<?php

namespace App\Services\Integrations;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Services\EmailTemplateRegistry;

/**
 * Admin-managed CC list for platform billing emails.
 *
 * Mirrors the MailSettings / EmailTemplateSettings pattern: the list lives in a
 * single `app_settings` row (`billing.cc_recipients`) so it inherits AppSetting's
 * 5-minute cache + missing-table graceful degrade, and an admin can edit it at
 * runtime with no redeploy.
 *
 * When the key has never been saved the configured DEFAULTS are returned, so the
 * finance team is CC'd out of the box and the admin form is pre-filled. Once an
 * admin saves the list (including an empty list) the stored value wins — saving
 * an empty list turns CC off entirely.
 *
 * The list is applied by the central {@see \App\Modules\Common\Services\Emailer}
 * to every send whose registry key is in the `billing` category, EXCEPT the
 * creator-economy keys in {@see self::EXCLUDED_KEYS} (a creator invoicing their
 * own client is not a platform money event and must not leak to platform finance).
 */
class BillingNotificationSettings
{
    private const KEY = 'billing.cc_recipients';

    /** Default finance/admin addresses CC'd until an admin edits the list. */
    public const DEFAULTS = [
        'sana@sayzio.app',
        'sanasandeep@gmail.com',
    ];

    /**
     * Billing-category keys that are creator-economy flows (a creator invoicing
     * their own client), NOT platform billing, so they must never be CC'd to the
     * platform finance addresses.
     */
    private const EXCLUDED_KEYS = [
        'billing.client_invoice',
        'billing.payment_reminder',
    ];

    /**
     * The effective CC recipients: the admin-saved list if present, otherwise the
     * built-in defaults. Always returns well-formed, de-duplicated emails.
     *
     * @return list<string>
     */
    public static function ccRecipients(): array
    {
        $value = AppSetting::get(self::KEY);
        $list  = is_array($value) ? $value : self::DEFAULTS;

        return self::sanitize($list);
    }

    /** True once an admin has explicitly saved a list (even an empty one). */
    public static function isConfigured(): bool
    {
        return is_array(AppSetting::get(self::KEY));
    }

    /**
     * Whether a billing email with this registry key should receive the CC list.
     */
    public static function shouldCc(string $key): bool
    {
        if (in_array($key, self::EXCLUDED_KEYS, true)) {
            return false;
        }

        return EmailTemplateRegistry::categoryFor($key) === 'billing';
    }

    /**
     * Persist the admin-edited list. Entries are trimmed, de-duplicated and
     * filtered to well-formed emails; an empty list is allowed (disables CC).
     *
     * @param  array<int,string>  $emails
     */
    public static function put(array $emails): void
    {
        AppSetting::put(self::KEY, self::sanitize($emails));
    }

    /**
     * @param  array<int,mixed>  $emails
     * @return list<string>
     */
    private static function sanitize(array $emails): array
    {
        $out  = [];
        $seen = [];
        foreach ($emails as $email) {
            $email = trim((string) $email);
            $lower = strtolower($email);
            if ($email === '' || isset($seen[$lower])) {
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $seen[$lower] = true;
            $out[] = $email;
        }

        return $out;
    }
}
