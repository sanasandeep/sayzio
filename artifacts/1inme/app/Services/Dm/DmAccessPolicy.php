<?php

namespace App\Services\Dm;

use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmUserBlock;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;

/**
 * Single source of truth for "can this fan DM this creator right now?".
 *
 * Returns a structured result the controller / view can use to render
 * the right CTA: send normally, blocked banner, "subscribe to message",
 * or "pay $5 to message". Once a paid-to-message conversation has been
 * paid for (or once a subscriber stays subscribed), follow-up sends go
 * through the cheaper sub/free path.
 *
 * Reasons:
 *  - owner          : creator viewing their own profile
 *  - self           : fan messaging themselves
 *  - closed         : creator turned DMs off
 *  - account_blocked: creator banned this viewer account-wide
 *  - thread_blocked : this specific conversation is blocked
 *  - subs_required  : subs-only mode and viewer is not subscribed
 *  - paid_required  : pay-to-message and viewer hasn't paid yet
 *  - throttled      : free-tier intro cap exhausted (waiting for reply)
 *  - ok             : viewer can send normally
 */
class DmAccessPolicy
{
    public const REASON_OWNER            = 'owner';
    public const REASON_SELF             = 'self';
    public const REASON_LOGIN_REQUIRED   = 'login_required';
    public const REASON_CLOSED           = 'closed';
    public const REASON_ACCOUNT_BLOCKED  = 'account_blocked';
    public const REASON_THREAD_BLOCKED   = 'thread_blocked';
    public const REASON_SUBS_REQUIRED    = 'subs_required';
    public const REASON_PAID_REQUIRED    = 'paid_required';
    public const REASON_THROTTLED        = 'throttled';
    public const REASON_OK               = 'ok';

    /**
     * @return array{
     *     can: bool,
     *     reason: string,
     *     mode: string,
     *     price_cents?: int,
     *     currency?: string,
     *     min_tier_id?: ?int,
     *     min_tier_name?: ?string,
     *     paid?: bool,
     *     subscribed?: bool,
     * }
     */
    public function evaluate(User $creator, ?User $viewer, ?ViewerDmConversation $conv = null): array
    {
        $mode     = $creator->dm_access_mode ?: User::DM_MODE_OPEN;
        $price    = (int) ($creator->dm_pay_price_cents ?? 0);
        $currency = (string) ($creator->dm_pay_currency ?: 'USD');

        $base = [
            'mode'        => $mode,
            'price_cents' => $price,
            'currency'    => $currency,
            'min_tier_id' => $creator->dm_min_tier_id,
        ];

        if (!$viewer) {
            return $base + ['can' => false, 'reason' => self::REASON_LOGIN_REQUIRED];
        }

        if ((int) $viewer->id === (int) $creator->id) {
            return $base + ['can' => false, 'reason' => self::REASON_SELF];
        }

        if ($mode === User::DM_MODE_CLOSED) {
            return $base + ['can' => false, 'reason' => self::REASON_CLOSED];
        }

        // Account-wide ban — beats every other rule.
        $accountBlocked = ViewerDmUserBlock::where('owner_user_id', $creator->id)
            ->where('viewer_user_id', $viewer->id)->exists();
        if ($accountBlocked) {
            return $base + ['can' => false, 'reason' => self::REASON_ACCOUNT_BLOCKED];
        }

        if ($conv && $conv->isBlocked()) {
            return $base + ['can' => false, 'reason' => self::REASON_THREAD_BLOCKED];
        }

        // Resolve "is this fan a current paid subscriber?" once, since
        // it's needed by both the subs-only branch and as a free-pass
        // shortcut around pay-to-message and the intro throttle.
        $subscribed = $this->isCurrentSubscriber($creator, $viewer, $creator->dm_min_tier_id);

        $minTierName = null;
        if ($creator->dm_min_tier_id) {
            $minTierName = SubscriptionTier::query()
                ->whereKey($creator->dm_min_tier_id)
                ->value('name');
        }
        $base['min_tier_name'] = $minTierName;
        $base['subscribed']    = $subscribed;
        $base['paid']          = (bool) ($conv?->paid_to_message ?? false);

        if ($mode === User::DM_MODE_SUBS && !$subscribed) {
            return $base + ['can' => false, 'reason' => self::REASON_SUBS_REQUIRED];
        }

        if ($mode === User::DM_MODE_PAID
            && !$subscribed
            && !($conv?->paid_to_message ?? false)
            && $price > 0
        ) {
            return $base + ['can' => false, 'reason' => self::REASON_PAID_REQUIRED];
        }

        // Anti-spam intro cap. Subscribers / paid-to-message threads
        // bypass the cap — they've earned uninterrupted access.
        if ($conv && !$subscribed && $conv->viewerIsThrottled()) {
            return $base + ['can' => false, 'reason' => self::REASON_THROTTLED];
        }

        return $base + ['can' => true, 'reason' => self::REASON_OK];
    }

    protected function isCurrentSubscriber(User $creator, User $fan, ?int $minTierId): bool
    {
        $sub = CreatorSubscription::query()
            ->where('creator_user_id', $creator->id)
            ->where('fan_user_id', $fan->id)
            ->whereIn('status', [
                CreatorSubscription::STATUS_ACTIVE,
                CreatorSubscription::STATUS_TRIALING,
                CreatorSubscription::STATUS_PAST_DUE,
            ])
            ->latest('id')
            ->first();
        if (!$sub || !$sub->isCurrent()) return false;
        if (!$minTierId) return true;

        // The configured "minimum tier" treats every tier whose
        // sort_order is >= the configured tier as eligible — same
        // ladder semantics as PostAccessPolicy.
        $minTier = SubscriptionTier::find($minTierId);
        if (!$minTier) return true;
        $subTier = SubscriptionTier::find($sub->tier_id);
        if (!$subTier) return false;
        return (int) $subTier->sort_order >= (int) $minTier->sort_order;
    }
}
