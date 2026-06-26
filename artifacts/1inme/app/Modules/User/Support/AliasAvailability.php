<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic backing the live "Custom URL availability" indicator.
 *
 * Both the Laravel web Create Link page (LinkController::checkAlias) and the
 * REST API (Api\LinkController::checkAlias, used by the Expo mobile app) call
 * this so the instant feedback shown matches the exact alias rules enforced on
 * submit (alpha_dash, the owner's plan length limits, the admin banned-names
 * list and unique:links,alias). A blank alias is the auto-generate case.
 *
 * Returns the plain {status, available, message} shape; callers wrap it in
 * whatever response envelope is appropriate for their surface.
 */
class AliasAvailability
{
    /**
     * @param  int|null  $ignoreLinkId  When the indicator backs an *edit*
     *         screen, pass the link being edited so its own current alias is
     *         excluded from the uniqueness check — re-saving a link without
     *         changing its alias should read as available, not "taken".
     * @return array{status:string, available:bool|null, message:string}
     */
    public static function check(User $owner, string $alias, ?int $ignoreLinkId = null): array
    {
        $alias  = trim($alias);
        $limits = $owner->getAliasLengthLimits();

        if ($alias === '') {
            return [
                'status'    => 'empty',
                'available' => null,
                'message'   => "Leave blank and we'll generate one for you.",
            ];
        }

        // alpha_dash equivalent (Laravel: \A[\pL\pM\pN_-]+\z with /u).
        if (! preg_match('/\A[\pL\pM\pN_-]+\z/u', $alias)) {
            return [
                'status'    => 'invalid',
                'available' => false,
                'message'   => 'Only letters, numbers, dashes & underscores are allowed.',
            ];
        }

        $length = mb_strlen($alias);
        if ($length < $limits['min']) {
            return [
                'status'    => 'too_short',
                'available' => false,
                'message'   => "Too short — use at least {$limits['min']} characters.",
            ];
        }
        if ($length > $limits['max']) {
            return [
                'status'    => 'too_long',
                'available' => false,
                'message'   => "Too long — use at most {$limits['max']} characters.",
            ];
        }

        // Admin-managed banned-names list (bypassed for privileged users,
        // exactly as the NotBannedName rule does at submit time).
        $banned = false;
        (new \App\Modules\Admin\Rules\NotBannedName())
            ->validate('alias', $alias, function () use (&$banned) { $banned = true; });
        if ($banned) {
            return [
                'status'    => 'banned',
                'available' => false,
                'message'   => "This name is reserved and can't be used.",
            ];
        }

        // unique:links,alias — query the raw table so the check matches the
        // validator (which ignores model scopes/soft-deletes) exactly. On the
        // edit screen the link's own row is excluded so an unchanged alias
        // doesn't report as taken.
        $takenQuery = DB::table('links')->where('alias', $alias);
        if ($ignoreLinkId !== null) {
            $takenQuery->where('id', '!=', $ignoreLinkId);
        }
        $taken = $takenQuery->exists();
        if ($taken) {
            return [
                'status'    => 'taken',
                'available' => false,
                'message'   => 'This URL is already taken — try another.',
            ];
        }

        return [
            'status'    => 'available',
            'available' => true,
            'message'   => 'This URL is available!',
        ];
    }
}
