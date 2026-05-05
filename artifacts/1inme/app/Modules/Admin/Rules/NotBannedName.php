<?php

namespace App\Modules\Admin\Rules;

use App\Modules\Admin\Services\BannedNameChecker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

/**
 * Validation rule rejecting values that match an entry in the admin-
 * managed banned-names list (case-insensitive). Holders of the
 * `user.banned_names.bypass` permission can still claim any name
 * regardless of the list, mirroring how the plan-limit and storage
 * caps are bypassed for them.
 */
class NotBannedName implements ValidationRule
{
    public function __construct(private bool $allowBypass = true)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) return;
        $str = (string) $value;
        if (trim($str) === '') return;

        if ($this->allowBypass) {
            $user = Auth::user();
            if ($user && method_exists($user, 'hasPermission') && $user->hasPermission('user.banned_names.bypass')) {
                return;
            }
        }

        if (BannedNameChecker::isBanned($str)) {
            $fail("This name is reserved and can't be used.");
        }
    }
}
