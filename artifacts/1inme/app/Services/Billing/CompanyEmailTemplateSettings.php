<?php

namespace App\Services\Billing;

use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Modules\User\Models\CompanyEmailTemplate;

/**
 * Storage for a creator's per-billing-company overrides of their client-facing
 * accounting email templates. Mirrors the admin
 * {@see \App\Services\Integrations\EmailTemplateSettings} pattern but persists
 * to the company_email_templates table (one row per company + template key)
 * instead of the global app_settings store.
 *
 * Resolution precedence in the central pipeline is:
 *   company override (here) → admin/global override → registry default.
 *
 * Only the EXISTING client-facing accounting templates are editable
 * ({@see KEYS}); platform/system emails and admin/global templates are out of
 * scope and untouched.
 */
class CompanyEmailTemplateSettings
{
    /**
     * The client-facing accounting templates a creator may customise per
     * company. These are the registry keys delivered to a company's clients:
     * the invoice email (also reused for recurring auto-send), the payment
     * receipt, and the payment reminder nudging clients about unpaid/overdue
     * invoices.
     */
    public const KEYS = [
        'billing.client_invoice',
        'billing.receipt',
        'billing.payment_reminder',
    ];

    public static function isEditable(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /** Registry metadata for every editable key (for the editor UI). */
    public static function editableEntries(): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $entry = EmailTemplateRegistry::get($key);
            if ($entry !== null) {
                $out[$key] = $entry;
            }
        }
        return $out;
    }

    /**
     * Return the stored override for a company + key, or null if none exists.
     *
     * @return array{subject:?string, body:?string, format:?string, updated_at:?string, updated_by:?int}|null
     */
    public static function get(int $companyId, string $key): ?array
    {
        if (!self::isEditable($key)) {
            return null;
        }

        $row = CompanyEmailTemplate::where('billing_company_id', $companyId)
            ->where('template_key', $key)
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'subject'    => $row->subject,
            'body'       => $row->body,
            'format'     => $row->format,
            'updated_at' => optional($row->updated_at)->toIso8601String(),
            'updated_by' => $row->updated_by,
        ];
    }

    public static function hasOverride(int $companyId, string $key): bool
    {
        return self::get($companyId, $key) !== null;
    }

    /** Save (or update) a company's override for a template key. */
    public static function put(int $companyId, string $key, string $subject, string $body, string $format, ?int $updatedBy = null): void
    {
        if (!self::isEditable($key)) {
            return;
        }

        CompanyEmailTemplate::updateOrCreate(
            ['billing_company_id' => $companyId, 'template_key' => $key],
            [
                'subject'    => $subject,
                'body'       => $body,
                'format'     => in_array($format, ['html', 'text'], true) ? $format : 'html',
                'updated_by' => $updatedBy,
            ],
        );
    }

    /** Remove a company's override (reset the template to the inherited content). */
    public static function forget(int $companyId, string $key): void
    {
        CompanyEmailTemplate::where('billing_company_id', $companyId)
            ->where('template_key', $key)
            ->delete();
    }
}
