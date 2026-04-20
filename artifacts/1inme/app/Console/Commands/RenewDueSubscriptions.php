<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use App\Services\Billing\SubscriptionLifecycle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * subscriptions:renew-due
 *
 * Runs hourly. For every active subscription whose current_period_end
 * is within the next 24h:
 *
 *   - If cancel_at_period_end is true → leave it alone; it'll expire
 *     naturally when the period ends (handled by the same run on a
 *     later tick via expireDueSubscriptions()).
 *   - Otherwise call adapter.chargeRecurring() on the subscription's
 *     gateway. The adapter is responsible for issuing & paying the
 *     renewal invoice (or creating one awaiting approval, for offline).
 *     On NotImplementedException, mark the subscription past_due.
 *
 * Also processes expirations for subscriptions whose grace window has
 * elapsed (→ status=expired, user downgraded to Free).
 *
 * Idempotent: the adapter's chargeRecurring() creates one invoice per
 * call — but we skip any subscription that ALREADY has an unpaid or
 * paid invoice covering the upcoming period. So a second invocation
 * within the same hour is a no-op for subs that already got a renewal
 * invoice in this run.
 */
class RenewDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:renew-due';
    protected $description = 'Charge gateways for subscriptions whose next renewal falls within 24h, and expire grace-ended ones.';

    public function handle(GatewayManager $gateways, SubscriptionLifecycle $lifecycle): int
    {
        $this->processScheduledCancellations($lifecycle);
        $this->transitionUnpaidPastPeriod($lifecycle);
        $this->renewUpcoming($gateways, $lifecycle);
        $this->expireGraceEnded($lifecycle);
        $this->notifyGraceEnding($lifecycle);
        return self::SUCCESS;
    }

    /**
     * Offline renewals stay in `awaiting_admin_approval` until the user
     * pays manually. If `current_period_end` passes without that invoice
     * being paid, the subscription needs to move to `past_due` with a
     * grace window — otherwise `hasRenewalInvoice()` keeps returning
     * true on every cron tick and the subscription is frozen in `active`
     * with expired access. This step closes that gap.
     */
    protected function transitionUnpaidPastPeriod(SubscriptionLifecycle $lifecycle): void
    {
        $subs = Subscription::where('status', 'active')
            ->where('cancel_at_period_end', false)
            ->where('current_period_end', '<=', now())
            ->get();
        foreach ($subs as $sub) {
            if ($this->hasPaidRenewal($sub)) continue;
            $lifecycle->markRenewalFailed($sub);
        }
    }

    protected function hasPaidRenewal(Subscription $sub): bool
    {
        return Invoice::where('user_id', $sub->user_id)
            ->where('status', 'paid')
            ->where('issued_at', '>=', Carbon::parse($sub->current_period_start))
            ->get()
            ->contains(function (Invoice $inv) use ($sub) {
                foreach ((array) $inv->line_items as $li) {
                    if (($li['meta']['kind'] ?? null) === 'plan_renewal'
                        && (int) ($li['meta']['renew_subscription_id'] ?? 0) === $sub->id) {
                        return true;
                    }
                }
                return false;
            });
    }

    /**
     * When cancel_at_period_end is set and we're past current_period_end,
     * flip the subscription to cancelled and downgrade the user. Without
     * this step the flag is purely cosmetic — plan gating keys off the
     * user row, not the subscription row.
     */
    protected function processScheduledCancellations(SubscriptionLifecycle $lifecycle): void
    {
        Subscription::where('status', 'active')
            ->where('cancel_at_period_end', true)
            ->where('current_period_end', '<=', now())
            ->get()
            ->each(fn ($s) => $lifecycle->cancelImmediately($s, 'cancel_at_period_end'));
    }

    protected function renewUpcoming(GatewayManager $gateways, SubscriptionLifecycle $lifecycle): void
    {
        $deadline = now()->addDay();
        $subs = Subscription::where('status', 'active')
            ->where('cancel_at_period_end', false)
            ->where('current_period_end', '<=', $deadline)
            ->where('current_period_end', '>', now()->subDays(7)) // don't revive truly ancient rows
            ->get();
        foreach ($subs as $sub) {
            if ($this->hasRenewalInvoice($sub)) continue;
            try {
                $adapter = $gateways->for($sub->gateway ?? 'offline');
                $adapter->chargeRecurring($sub);
            } catch (NotImplementedException $e) {
                $lifecycle->markRenewalFailed($sub);
            } catch (\Throwable $e) {
                Log::warning('chargeRecurring failed', ['sub' => $sub->id, 'err' => $e->getMessage()]);
                $lifecycle->markRenewalFailed($sub);
            }
        }
    }

    protected function hasRenewalInvoice(Subscription $sub): bool
    {
        // A renewal invoice covers the NEXT period. We recognize it by
        // a line-item meta.kind='plan_renewal' with renew_subscription_id
        // matching this sub, issued after current_period_start.
        $cursor = Carbon::parse($sub->current_period_start);
        return Invoice::where('user_id', $sub->user_id)
            ->where('issued_at', '>=', $cursor)
            ->whereIn('status', ['pending', 'awaiting_admin_approval', 'paid'])
            ->get()
            ->contains(function (Invoice $inv) use ($sub) {
                foreach ((array) $inv->line_items as $li) {
                    if (($li['meta']['kind'] ?? null) === 'plan_renewal'
                        && (int) ($li['meta']['renew_subscription_id'] ?? 0) === $sub->id) {
                        return true;
                    }
                }
                return false;
            });
    }

    protected function expireGraceEnded(SubscriptionLifecycle $lifecycle): void
    {
        Subscription::whereIn('status', ['past_due', 'grace'])
            ->whereNotNull('grace_until')
            ->where('grace_until', '<=', now())
            ->get()
            ->each(fn ($s) => $lifecycle->expireIfGraceElapsed($s));

        // Also expire subscriptions whose period has ended with no grace
        // window configured (grace_days=0) and no successful renewal.
        Subscription::where('status', 'past_due')
            ->whereNull('grace_until')
            ->where('current_period_end', '<=', now())
            ->get()
            ->each(function ($s) use ($lifecycle) {
                $s->forceFill(['grace_until' => now()->subSecond()])->save();
                $lifecycle->expireIfGraceElapsed($s);
            });
    }

    protected function notifyGraceEnding(SubscriptionLifecycle $lifecycle): void
    {
        // One-shot: only notify if we haven't already for this grace window.
        Subscription::whereIn('status', ['past_due', 'grace'])
            ->whereNotNull('grace_until')
            ->whereBetween('grace_until', [now(), now()->addDay()])
            ->whereNull('grace_ending_notified_at')
            ->get()
            ->each(function ($s) use ($lifecycle) {
                $lifecycle->notify($s, 'grace_ending');
                $s->forceFill(['grace_ending_notified_at' => now()])->save();
            });
    }
}
