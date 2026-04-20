<?php

namespace App\Modules\Admin\Rules;

use App\Modules\Admin\Services\BannedNameChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Validation rule rejecting values that match an entry in the admin-
 * managed banned-names list (case-insensitive). Mirrors the existing
 * super-admin bypass pattern: a signed-in super admin can still claim
 * any name regardless of the list, consistent with how plan limits
 * and storage caps are bypassed for them today.
 */
class NotBannedName implements ValidationRule
{
    public function __construct(private bool $bypassForSuperAdmin = true)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) return;
        $str = (string) $value;
        if (trim($str) === '') return;

        if ($this->bypassForSuperAdmin) {
            $user = Auth::user();
            if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return;
            }
        }

        if (BannedNameChecker::isBanned($str)) {
            $fail("This name is reserved and can't be used.");
        }
    }
}
