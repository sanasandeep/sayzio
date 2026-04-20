<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single source of truth for subscription state transitions.
 *
 * Statuses: active | past_due | grace | cancelled | expired.
 *
 *  - active     : current_period_end is in the future, last renewal succeeded.
 *  - past_due   : renewal attempt failed or awaiting-approval invoice not paid
 *                 by period_end. `grace_until = period_end + plan.grace_days`.
 *                 Plan features STAY ACTIVE until grace_until.
 *  - grace      : (synonym display label — same gated behavior as past_due.
 *                  Kept as a distinct status so UI can say "grace ending in
 *                  X days" vs "renewal just failed".)
 *  - cancelled  : user cancelled at period end, or superseded by an upgrade.
 *  - expired    : period_end + grace_days has passed with no successful
 *                 renewal. Plan features are revoked; user is on Free.
 */
class SubscriptionLifecycle
{
    public function markRenewalFailed(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $plan = $subscription->plan;
            $graceDays = (int) ($plan->grace_days ?? 7);
            $graceUntil = Carbon::parse($subscription->current_period_end)->addDays($graceDays);

            $subscription->forceFill([
                'status'                   => 'past_due',
                'grace_until'              => $graceUntil,
                'grace_ending_notified_at' => null,
            ])->save();

            $this->notify($subscription, 'renewal_failed', [
                'grace_days' => $graceDays,
                'grace_until' => $graceUntil->toDateString(),
            ]);
        });
    }

    public function expireIfGraceElapsed(Subscription $subscription): bool
    {
        if (!in_array($subscription->status, ['past_due', 'grace'], true)) return false;
        if (!$subscription->grace_until) return false;
        if (Carbon::parse($subscription->grace_until)->isFuture()) return false;

        DB::transaction(function () use ($subscription) {
            $subscription->forceFill(['status' => 'expired'])->save();
            $this->downgradeToFree($subscription);
            $this->notify($subscription, 'downgraded', []);
        });
        return true;
    }

    public function cancelAtPeriodEnd(Subscription $subscription): void
    {
        $subscription->forceFill([
            'cancel_at_period_end' => true,
            'cancel_at'            => $subscription->current_period_end,
        ])->save();
    }

    public function undoCancel(Subscription $subscription): void
    {
        $subscription->forceFill([
            'cancel_at_period_end' => false,
            'cancel_at'            => null,
        ])->save();
    }

    /**
     * Hard-stop a subscription (refund flow, immediate user-initiated
     * cancellation). Revokes plan features on the user row as well.
     */
    public function cancelImmediately(Subscription $subscription, string $reason = 'user_requested'): void
    {
        DB::transaction(function () use ($subscription, $reason) {
            $subscription->forceFill([
                'status'    => 'cancelled',
                'cancel_at' => now(),
            ])->save();
            $this->downgradeToFree($subscription);
            Log::info('Subscription cancelled immediately', [
                'subscription_id' => $subscription->id, 'reason' => $reason,
            ]);
        });
    }

    /** Pull the user off the paid plan. Idempotent. */
    public function downgradeToFree(Subscription $subscription): void
    {
        $user = $subscription->user;
        if (!$user) return;
        $free = Plan::where('slug', 'free')->orWhere('is_default', true)->orderByDesc('is_default')->first();
        $user->forceFill([
            'plan_id'         => $free?->id,
            'billing_cycle'   => 'monthly',
            'plan_expires_at' => null,
        ])->save();
    }

    public function notify(Subscription $subscription, string $kind, array $extra = []): void
    {
        try {
            $email = optional($subscription->user)->email;
            if (!$email) return;
            $subject = match ($kind) {
                'renewal_failed' => 'Renewal failed — we couldn\'t charge your payment method',
                'grace_ending'   => 'Your plan is about to downgrade',
                'downgraded'     => 'Your plan has been downgraded',
                default          => 'Subscription update',
            };
            $body = match ($kind) {
                'renewal_failed' => "We couldn't process your renewal for {$subscription->plan?->name}.\nYour plan features remain active until "
                    . ($extra['grace_until'] ?? 'the grace period ends') . ". Please update your payment method before then.",
                'grace_ending'   => "Your {$subscription->plan?->name} plan will downgrade in less than 24 hours unless you renew.",
                'downgraded'     => "Your {$subscription->plan?->name} plan has been downgraded to Free because the grace period ended.",
                default          => "Your subscription status has changed.",
            };
            Mail::raw($body, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Lifecycle notify failed: ' . $e->getMessage());
        }
    }
}
