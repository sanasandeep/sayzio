<?php

namespace App\Modules\Api\Resources;

use App\Modules\User\Models\User;

class UserResource
{
    public static function toArray(User $u, bool $self = false): array
    {
        $base = [
            'id'              => $u->id,
            'name'            => $u->name,
            'handle'          => $u->handle,
            'avatar'          => $u->avatar,
            'bio'             => $u->bio,
            'discoverable'    => (bool) $u->discoverable,
            'allow_followers' => (bool) $u->allow_followers,
            'followers_count' => (int)  $u->followers_count,
        ];
        if ($self) {
            $base = array_merge($base, [
                'email'             => $u->email,
                'phone'             => $u->phone,
                'timezone'          => $u->timezone,
                'language'          => $u->language,
                'plan_id'           => $u->plan_id,
                'billing_cycle'     => $u->billing_cycle,
                'plan_expires_at'   => optional($u->plan_expires_at)->toIso8601String(),
                'trial_ends_at'     => optional($u->trial_ends_at)->toIso8601String(),
                'role'              => $u->role,
                'status'            => $u->status,
                'email_verified_at' => optional($u->email_verified_at)->toIso8601String(),
                'onboarded_at'      => optional($u->onboarded_at)->toIso8601String(),
                'created_at'        => optional($u->created_at)->toIso8601String(),
                // Plan capabilities relevant to the browser extension's
                // smart-link rule builder. Surfaced here so the popup can
                // gate the UI without a second round-trip.
                'capabilities'      => [
                    'link_smart_rules' => (bool) $u->planFeatureEnabled('link_smart_rules'),
                    'max_smart_rules'  => self::resolveMaxSmartRules($u),
                ],
            ]);
        }
        return $base;
    }

    /**
     * Per-plan cap on the number of rules a single smart link may carry.
     * Falls back to the hard ceiling enforced by sanitizeSmartRules() (25)
     * when the plan doesn't specify one. -1 means unlimited (capped at 25
     * by the sanitizer regardless).
     */
    private static function resolveMaxSmartRules(User $u): int
    {
        $val = $u->getPlanFeature('max_smart_rules', null);
        if ($val === null) return 25;
        $n = (int) $val;
        if ($n < 0) return 25;
        return min(25, $n);
    }
}
