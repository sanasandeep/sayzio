<?php

namespace App\Modules\Admin\Rules;

use App\Modules\Common\Support\RetiredAdminEmails;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule rejecting the now-retired privileged admin email addresses
 * (matched case-insensitively) from admin-editable recipient settings — the
 * billing CC list, the contact-notification recipient, and the mail "From"
 * address. Prevents an admin from silently re-introducing the addresses a
 * migration deliberately scrubbed, which would recreate the exact identity
 * confusion the cleanup removed.
 *
 * @see RetiredAdminEmails for the canonical/retired address list.
 */
class NotRetiredAdminEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || trim($value) === '') {
            return;
        }

        if (RetiredAdminEmails::isRetired($value)) {
            $fail(
                'This address (' . trim($value) . ') has been retired. Use '
                . RetiredAdminEmails::CANONICAL . ' instead.'
            );
        }
    }
}
