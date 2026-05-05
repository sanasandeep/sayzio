<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\User;
use Illuminate\Http\Request;

/**
 * Read-side helpers for the visitor 18+ age gate. Used from the
 * /@handle controller to decide whether to render the interstitial.
 */
class AgeGate
{
    public const COOKIE = 'age_confirmed_18';
    public const DAYS   = 30;

    public static function passed(Request $request, ?User $viewer = null): bool
    {
        // Logged-in viewers who have confirmed 18+ on their own account
        // get a permanent pass — no need to re-prompt per device.
        if ($viewer && !empty($viewer->age_verified_at)) return true;
        return (string) $request->cookie(self::COOKIE) === '1';
    }
}
