<?php

namespace App\Services\Billing;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\SubscriptionAddon;
use App\Services\Billing\ProrationCalculator;
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
        // Cancelling to Free supersedes any scheduled paid downgrade.
        $subscription->forceFill([
            'cancel_at_period_end'        => true,
            'cancel_at'                   => $subscription->current_period_end,
            'scheduled_downgrade_plan_id' => null,
        ])->save();
    }

    /**
     * Schedule a change to a chosen LOWER PAID plan, applied at the end of
     * the current cycle (NOT a revert to Free). Mutually exclusive with a
     * cancel-at-period-end. The user keeps their current (higher) plan
     * until the cycle ends.
     */
    public function scheduleDowngrade(Subscription $subscription, Plan $target): void
    {
        $subscription->forceFill([
            'scheduled_downgrade_plan_id' => $target->id,
            'cancel_at_period_end'        => false,
            'cancel_at'                   => null,
        ])->save();

        $this->notify($subscription, 'downgrade_scheduled', [
            'target_plan' => $target->name,
            'effective'   => Carbon::parse($subscription->current_period_end)->toDateString(),
        ]);
    }

    /** Cancel a pending scheduled downgrade before it applies. */
    public function cancelScheduledDowngrade(Subscription $subscription): void
    {
        $subscription->forceFill(['scheduled_downgrade_plan_id' => null])->save();
    }

    /**
     * Apply a scheduled downgrade at cycle end: move the subscription to
     * the chosen lower plan, drop add-ons that plan can't carry, and clear
     * the schedule. Returns the target Plan so the caller can re-bill it at
     * the lower plan's price (consistent with how renewals charge), or null
     * when the schedule is invalid/stale and was simply cleared.
     */
    public function applyScheduledDowngrade(Subscription $subscription): ?Plan
    {
        $targetId = $subscription->scheduled_downgrade_plan_id;
        if (!$targetId) return null;

        $target = Plan::find($targetId);
        $currency = (string) $subscription->currency;

        // Guard: the target must still be a valid, public, lower PAID plan.
        // If it's gone / archived / no longer cheaper, clear the schedule
        // and let the normal renewal path handle this subscription.
        $valid = $target
            && $target->status === 'active'
            && !$target->is_archived
            && !$target->is_default
            && $target->id !== $subscription->plan_id
            && ProrationCalculator::resolveMinor($target, $subscription->billing_cycle, null, $currency) > 0
            && ProrationCalculator::resolveMinor($target, $subscription->billing_cycle, null, $currency)
                < ProrationCalculator::resolveMinor($subscription->plan, $subscription->billing_cycle, null, $currency);

        if (!$valid) {
            Log::info('Scheduled downgrade target invalid; clearing schedule', [
                'subscription_id' => $subscription->id,
                'target_plan_id'  => $targetId,
            ]);
            $subscription->forceFill(['scheduled_downgrade_plan_id' => null])->save();
            return null;
        }

        $dropped = [];
        DB::transaction(function () use ($subscription, $target, &$dropped) {
            // Drop add-ons the lower plan is not eligible to carry.
            $eligible = $target->addons()->pluck('addons.id')->map(fn ($id) => (int) $id)->all();
            foreach ($subscription->addons()->with('addon')->get() as $sa) {
                if (!in_array((int) $sa->addon_id, $eligible, true)) {
                    $dropped[] = $sa->addon->name ?? ('Add-on #' . $sa->addon_id);
                    $sa->delete();
                }
            }

            $subscription->forceFill([
                'plan_id'                     => $target->id,
                'scheduled_downgrade_plan_id' => null,
            ])->save();
            $subscription->setRelation('plan', $target);

            // Entitlements move to the lower plan now (cycle end). The new
            // period is extended by the renewal payment that follows.
            $user = $subscription->user;
            if ($user) {
                $user->forceFill([
                    'plan_id'       => $target->id,
                    'billing_cycle' => $subscription->billing_cycle,
                ])->save();
            }
        });

        $this->notify($subscription, 'downgrade_applied', [
            'target_plan'    => $target->name,
            'dropped_addons' => $dropped,
        ]);

        return $target;
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
            $key = match ($kind) {
                'renewal_failed'      => 'billing.subscription_renewal_failed',
                'grace_ending'        => 'billing.subscription_grace_ending',
                'downgraded'          => 'billing.subscription_downgraded',
                'downgrade_scheduled' => 'billing.subscription_downgrade_scheduled',
                'downgrade_applied'   => 'billing.subscription_downgrade_applied',
                default               => 'billing.subscription_update',
            };

            $dropped = $extra['dropped_addons'] ?? [];
            $droppedSummary = !empty($dropped)
                ? 'These add-ons are no longer included: ' . implode(', ', $dropped) . '.'
                : 'No add-ons were affected by this change.';

            \App\Modules\Common\Services\Emailer::send($key, $email, [
                'plan_name'      => $subscription->plan?->name,
                'grace_until'    => $extra['grace_until'] ?? 'the grace period ends',
                'target_plan'    => $extra['target_plan'] ?? null,
                'effective'      => $extra['effective'] ?? null,
                'dropped_addons' => $droppedSummary,
            ], [
                'user'    => optional($subscription->user)->id,
                'related' => $subscription,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Lifecycle notify failed: ' . $e->getMessage());
        }
    }
}
