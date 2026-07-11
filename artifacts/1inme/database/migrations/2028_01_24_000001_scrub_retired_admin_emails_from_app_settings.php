<?php

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

/**
 * Scrub the now-retired admin email addresses out of admin-managed
 * `app_settings` records that may still reference them.
 *
 * Background
 * ----------
 * `2027_07_17_000001_consolidate_admin_email_to_sayzioapp` consolidated the
 * three retired privileged identities under the canonical
 * `sayzioapp@gmail.com`, but it only reconciled the `admins` and
 * `protected_accounts` tables. Notification/recipient overrides that an admin
 * may have saved BEFORE that migration live in `app_settings` JSON and can
 * still name a retired address — harmless functionally (the migration didn't
 * change deliverability), but confusing when an admin looks at those records.
 *
 * What this migration DOES update (admin-editable recipient state):
 *   - `billing.cc_recipients`   — JSON list of platform-billing CC addresses.
 *                                 Any retired entry is replaced with the
 *                                 canonical address; the list is de-duplicated
 *                                 and its order preserved.
 *   - `contact_recipient_email` — single admin-notification recipient for
 *                                 inbound leads/contact-form submissions.
 *   - `mail.from_address`       — platform outbound "From" identity, ONLY when
 *                                 it exactly matches a retired address (a real
 *                                 configured sending domain is left untouched).
 *
 * What this migration deliberately does NOT touch:
 *   - `mail.username` / `mail.password_enc` — SMTP auth credentials; rewriting
 *     the auth username would break authentication even if it looks like one of
 *     the retired addresses.
 *   - Historical / compliance audit tables. These record what actually happened
 *     at the time and must remain a faithful log, so a retired address that was
 *     genuinely the actor/recipient back then stays as-is:
 *       * `email_logs`            — activity log of every outbound email sent
 *                                    (recipient/from/cc snapshot for Resend).
 *       * `admin_action_audits`, `master_password_logins`, `login_events`,
 *         `user_role_audits`, `workspace_audit_events`, `schema_repair_audits`,
 *         `vault_audit`, etc.    — immutable security/compliance trails.
 *
 * Idempotent: only rewrites a key when a retired address is actually present,
 * so re-running is a no-op. Shared-DB / un-migrated safe: reads/writes go
 * through {@see AppSetting}, which degrades gracefully when the table is absent
 * and invalidates the per-key cache on write.
 */
return new class extends Migration
{
    private string $canonical = 'sayzioapp@gmail.com';

    /** @var list<string> retired addresses, matched case-insensitively */
    private array $retired = [
        'sanasandeep@gmail.com',
        'official1inme@gmail.com',
        'admin@1inme.com',
    ];

    public function up(): void
    {
        $this->scrubList('billing.cc_recipients');
        $this->scrubScalar('contact_recipient_email');
        $this->scrubScalar('mail.from_address');
    }

    /**
     * Rewrite a JSON list setting, replacing any retired address with the
     * canonical one, de-duplicating (case-insensitively) while preserving the
     * first-seen order. Only persists when the list actually changed.
     */
    private function scrubList(string $key): void
    {
        $value = AppSetting::get($key);
        if (!is_array($value)) {
            return; // never saved, or not a list — nothing to scrub.
        }

        $out  = [];
        $seen = [];
        $changed = false;

        foreach ($value as $entry) {
            $email = trim((string) $entry);
            if ($email === '') {
                $changed = true; // dropping a blank entry is a change.
                continue;
            }

            if ($this->isRetired($email)) {
                $email   = $this->canonical;
                $changed = true;
            }

            $lower = strtolower($email);
            if (isset($seen[$lower])) {
                $changed = true; // collapsed a duplicate.
                continue;
            }

            $seen[$lower] = true;
            $out[] = $email;
        }

        if ($changed) {
            AppSetting::put($key, $out);
        }
    }

    /**
     * Rewrite a single-string setting when (and only when) it exactly holds a
     * retired address.
     */
    private function scrubScalar(string $key): void
    {
        $value = AppSetting::get($key);
        if (!is_string($value)) {
            return;
        }

        if ($this->isRetired(trim($value))) {
            AppSetting::put($key, $this->canonical);
        }
    }

    private function isRetired(string $email): bool
    {
        $lower = strtolower(trim($email));
        foreach ($this->retired as $legacy) {
            if ($lower === strtolower($legacy)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Best-effort revert is impossible — we don't know which retired address a
     * canonical entry originally replaced (or whether it was always canonical).
     * No-op by design, consistent with the consolidation migration.
     */
    public function down(): void
    {
        // No-op by design.
    }
};
