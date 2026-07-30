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
     * @param  int|string|null  $domainId  Domain the alias would be bound to
     *         (raw request value ok). Null/'' = the default platform domain.
     *         Uniqueness is per-domain, so the same alias can be available on
     *         one domain while taken on another.
     * @return array{status:string, available:bool|null, message:string}
     */
    public static function check(User $owner, string $alias, ?int $ignoreLinkId = null, int|string|null $domainId = null): array
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

        // Matches the unified alias character rule (letters, numbers, `.`, `_`,
        // `-`) used at submit time by AliasFormat, so the live verdict can't
        // disagree with what save accepts.
        if (! preg_match(\App\Modules\User\Rules\AliasFormat::REGEX, $alias)) {
            return [
                'status'    => 'invalid',
                'available' => false,
                'message'   => \App\Modules\User\Rules\AliasFormat::MESSAGE,
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

        // Per-domain uniqueness — mirrors UniqueAliasCi at submit time (raw
        // tables, no model scopes/soft-deletes, case-insensitive, and both
        // links.alias + link_aliases.alias within the target domain's
        // namespace). On the edit screen the link's own rows are excluded so
        // an unchanged alias doesn't report as taken.
        if (\App\Modules\User\Support\AliasNamespace::isTaken($alias, $domainId, $ignoreLinkId)) {
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
