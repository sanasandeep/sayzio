<?php

namespace App\Modules\Common\Support;

/**
 * Single source of truth for the now-retired privileged admin email addresses
 * and the canonical address that replaced them.
 *
 * Background
 * ----------
 * `2027_07_17_000001_consolidate_admin_email_to_sayzioapp` consolidated three
 * legacy privileged identities under the canonical `sayzioapp@gmail.com`, and
 * `2028_01_24_000001_scrub_retired_admin_emails_from_app_settings` scrubbed any
 * lingering references to them out of admin-editable recipient settings. To keep
 * those retired addresses from silently creeping back in, admin-facing recipient
 * settings (billing CC list, contact-notification recipient, mail "From"
 * address) validate against this list server-side.
 *
 * Both the scrub migration and the validators/services reference these constants
 * so the two can never drift apart.
 */
class RetiredAdminEmails
{
    /** The canonical address every retired identity was consolidated under. */
    public const CANONICAL = 'sayzioapp@gmail.com';

    /**
     * The retired admin addresses, matched case-insensitively. Do not add live
     * addresses here — entries here are actively rejected/normalized away.
     *
     * @var list<string>
     */
    public const RETIRED = [
        'sanasandeep@gmail.com',
        'official1inme@gmail.com',
        'admin@1inme.com',
    ];

    /** Whether the given value is one of the retired admin addresses. */
    public static function isRetired(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        $lower = strtolower(trim($email));
        if ($lower === '') {
            return false;
        }

        foreach (self::RETIRED as $legacy) {
            if ($lower === strtolower($legacy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return the canonical address when the input is a retired one, otherwise
     * the value unchanged (trimmed only when it was retired). Used as a
     * defensive normalization on the service layer so a non-validated caller
     * can never persist a retired address.
     */
    public static function normalize(?string $email): ?string
    {
        return self::isRetired($email) ? self::CANONICAL : $email;
    }
}
