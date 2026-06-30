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
                // Mirrors the web reminder-banner visibility rule: only true
                // when email is a usable sign-in method (not a mobile-only
                // login policy), so the mobile nudge never shows for accounts
                // that can never meaningfully verify their email.
                'email_verification_meaningful' => \App\Modules\Common\Support\AuthMethods::emailVerificationMeaningful(),
                'onboarded_at'      => optional($u->onboarded_at)->toIso8601String(),
                'created_at'        => optional($u->created_at)->toIso8601String(),
                // Admin-assigned account badges (name + color). Mirrors the web
                // dashboard/sidebar badge chips so the mobile profile screen can
                // show the same earned badges. Empty array when the user has
                // none, so the client can render nothing gracefully.
                'account_badges'    => $u->accountBadges->map(fn ($b) => [
                    'id'    => (int) $b->id,
                    'name'  => $b->name,
                    'color' => $b->color,
                ])->values()->all(),
                // Plan capabilities relevant to the browser extension's
                // smart-link rule builder. Surfaced here so the popup can
                // gate the UI without a second round-trip.
                'capabilities'      => [
                    'link_smart_rules' => (bool) $u->planFeatureEnabled('link_smart_rules'),
                    'max_smart_rules'  => self::resolveMaxSmartRules($u),
                    // Mirrors the web `analytics_export` gate (Stats CSV
                    // export). Lets the mobile Stats screen hide its
                    // "Export CSV" control + show an upgrade prompt without
                    // a second round-trip. Default-true matches the web
                    // helper's fallback for plans that don't set the key.
                    'analytics_export' => (bool) $u->getPlanFeature('analytics_export', true),
                    // Per-plan monthly Buzz (social-proof) impressions
                    // allowance + current-period usage. -1 = unlimited.
                    // Lets the mobile/extension Buzz surface show the same
                    // "views this month" gauge and paused state the web UI
                    // does without a second round-trip.
                    'buzz_popups'             => (bool) $u->getPlanFeature('buzz_popups', false),
                    'max_buzz_impressions'    => \App\Services\BuzzImpressionMeter::allowanceFor($u),
                    'buzz_impressions_used'   => \App\Services\BuzzImpressionMeter::used((int) $u->id),
                    // Followable `calendar` link type: whether the module is
                    // enabled and the per-plan caps (-1 = unlimited) so the
                    // mobile calendar surface can gate creation + show the
                    // right upgrade prompt without a second round-trip.
                    'module_calendar'         => (bool) $u->planFeatureEnabled('module_calendar'),
                    'max_calendars'           => (int) $u->getPlanFeature('max_calendars', -1),
                    'max_calendar_events'     => (int) $u->getPlanFeature('max_calendar_events', -1),
                    'calendar_sync'           => (bool) $u->getPlanFeature('calendar_sync', false),
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
