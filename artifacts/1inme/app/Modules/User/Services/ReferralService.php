<?php

namespace App\Modules\User\Services;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Referral;
use App\Modules\User\Models\ReferralReward;
use App\Modules\User\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    public const COOKIE_NAME = 'ref_code';
    public const COOKIE_MINUTES = 60 * 24 * 30; // 30 days

    public const RESERVED = [
        'admin', 'administrator', 'api', 'app', 'auth', 'dashboard', 'help',
        'home', 'login', 'logout', 'me', 'new', 'null', 'profile', 'r',
        'register', 'root', 'settings', 'signin', 'signup', 'support',
        'system', 'terms', 'privacy', 'user', 'users', 'referral', 'referrals',
        'plans', 'plan', 'free', 'pro', 'premium', 'about',
    ];

    /** Global enable/disable. */
    public function isEnabled(): bool
    {
        return (bool) AppSetting::get('referrals.enabled', true);
    }

    public function setEnabled(bool $on): void
    {
        AppSetting::put('referrals.enabled', $on);
    }

    public function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtolower(Str::random($length));
        } while (User::where('referral_code', $code)->exists());
        return $code;
    }

    /**
     * Validate a desired code. Returns null if OK, otherwise an error message.
     */
    public function validateCode(string $code, ?int $excludeUserId = null): ?string
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-z0-9_-]{3,32}$/', $code)) {
            return 'Use 3-32 letters, numbers, dashes or underscores.';
        }
        if (in_array($code, self::RESERVED, true)) {
            return 'That code is reserved. Please pick another.';
        }
        $exists = User::where('referral_code', $code)
            ->when($excludeUserId, fn($q) => $q->where('id', '!=', $excludeUserId))
            ->exists();
        if ($exists) {
            return 'That code is already taken.';
        }
        return null;
    }

    public function findReferrerByCode(?string $code): ?User
    {
        if (!$code) return null;
        return User::where('referral_code', strtolower(trim($code)))->first();
    }

    /**
     * Called from registration. Attaches referrer (resolved from $code or
     * fallback cookie code) and creates the referrals row.
     */
    public function attributeSignup(User $newUser, ?string $submittedCode, ?string $cookieCode, ?string $ip, ?string $userAgent): ?Referral
    {
        if (!$this->isEnabled()) return null;

        $code = $submittedCode ?: $cookieCode;
        $referrer = $this->findReferrerByCode($code);
        if (!$referrer || $referrer->id === $newUser->id) return null;

        $newUser->update([
            'referrer_id' => $referrer->id,
            'referral_code_used' => strtolower(trim($code)),
        ]);

        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_user_id' => $newUser->id,
            'code_used' => strtolower(trim($code)),
            'status' => 'signed_up',
            'signed_up_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
        ]);

        // Optional signup-only bonus, based on referrer's current plan.
        $signupDays = (int) $this->planFeature($referrer->plan, 'signup_bonus_days', 0);
        if ($signupDays > 0) {
            $this->grantReward($referrer, $referral, 'signup', $signupDays, $referrer->plan_id);
        }

        return $referral;
    }

    /**
     * Called when a referred user activates a paid plan for the first time.
     * Idempotent per (referral, type) thanks to the unique constraint.
     */
    public function handlePlanActivation(User $user, Plan $activatedPlan): void
    {
        if (!$this->isEnabled()) return;
        if (!$user->referrer_id) return;
        if (!$activatedPlan || strtolower((string) $activatedPlan->slug) === 'free') return;

        $referral = Referral::where('referred_user_id', $user->id)->first();
        if (!$referral) return;

        // Already converted? Don't double-grant referrer/referred rewards.
        $already = ReferralReward::where('referral_id', $referral->id)
            ->whereIn('type', ['referrer', 'referred'])->exists();
        if ($already) return;

        $referrer = User::find($referral->referrer_id);
        if (!$referrer) return;

        $referrerDays = (int) $this->planFeature($referrer->plan, 'referrer_free_days', 0);
        $referredDays = (int) $this->planFeature($activatedPlan, 'referred_free_days', 0);

        if ($referrerDays > 0) {
            $this->grantReward($referrer, $referral, 'referrer', $referrerDays, $referrer->plan_id);
        }
        if ($referredDays > 0) {
            $this->grantReward($user, $referral, 'referred', $referredDays, $activatedPlan->id);
        }

        $referral->update([
            'status' => 'rewarded',
            'converted_at' => now(),
        ]);

        // Award fan-leaderboard points for the referral on every link the
        // referrer owns that has the leaderboard enabled. The engine
        // silently no-ops on disabled links, so this stays cheap and
        // honors per-link opt-in. The referred user's identity is used as
        // the "voter" so the points are attributed to a real fan.
        try {
            /** @var \App\Modules\User\Services\FanPointsEngine $engine */
            $engine = app(\App\Modules\User\Services\FanPointsEngine::class);
            $links = \App\Modules\User\Models\Link::query()
                ->withoutGlobalScope('workspace')
                ->where('user_id', $referrer->id)
                ->get();
            foreach ($links as $link) {
                // Attribute the points to the REFERRER (the existing fan
                // who brought the new user in), not to the referred user.
                // The leaderboard ranks fans of the creator, so the
                // referrer is the one whose rank should rise.
                $engine->award(
                    $link,
                    'referral',
                    $referral,
                    $referrer->id,
                    'referrer:' . $referrer->id,
                    $referrer->name,
                    ['referred_user_id' => $user->id]
                );
            }
        } catch (\Throwable $e) {
            // Don't let leaderboard accounting block the referral flow.
            \Log::warning('FanPointsEngine referral award failed: ' . $e->getMessage());
        }
    }

    /** Grants free days to a user and writes the ledger row. Idempotent. */
    protected function grantReward(User $user, Referral $referral, string $type, int $days, ?int $planIdBasis): ?ReferralReward
    {
        if ($days <= 0) return null;

        return DB::transaction(function () use ($user, $referral, $type, $days, $planIdBasis) {
            // Idempotency guard.
            $existing = ReferralReward::where('referral_id', $referral->id)
                ->where('type', $type)->first();
            if ($existing) return $existing;

            // Extend the user's plan_expires_at (or trial_ends_at if still on trial).
            $user->refresh();
            if ($user->trial_ends_at && $user->trial_ends_at->isFuture()) {
                $user->trial_ends_at = $user->trial_ends_at->copy()->addDays($days);
            } else {
                $base = $user->plan_expires_at && $user->plan_expires_at->isFuture()
                    ? $user->plan_expires_at->copy()
                    : Carbon::now();
                $user->plan_expires_at = $base->addDays($days);
            }
            $user->save();

            return ReferralReward::create([
                'user_id' => $user->id,
                'referral_id' => $referral->id,
                'type' => $type,
                'days_granted' => $days,
                'plan_id_basis' => $planIdBasis,
                'granted_at' => now(),
            ]);
        });
    }

    protected function planFeature(?Plan $plan, string $key, $default = 0)
    {
        if (!$plan || !is_array($plan->features)) return $default;
        return $plan->features[$key] ?? $default;
    }

    public function buildReferralUrl(string $code): string
    {
        return url('/r/' . $code);
    }
}
