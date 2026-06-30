<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\CreatorSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Daily job that reminds a fan, a few days before their fan→creator
 * subscription auto-renews, that a charge is coming (Task #3011).
 *
 * The reminder names the creator, the amount, the billing cycle
 * (monthly/yearly) and the exact renewal date, plus a link to the
 * manage/cancel page. It is a HEADS-UP ONLY — it does not change how or
 * when renewals are actually charged (the gateway owns that).
 *
 * Eligibility (all must hold), so we never nag the wrong subscriptions:
 *   - status = active (this alone excludes trialing/past_due/canceled/paused),
 *   - cancel_at_period_end = false (a sub already set to lapse won't be charged),
 *   - price_cents > 0 (free / zero-price subs trigger no charge),
 *   - current_period_end is within the lead window (--lead-days, default 3).
 *
 * Once per billing period: a per-row `renewal_reminder_sent_at` stamp is
 * only (re)sent when it is null or predates current_period_start, so each
 * billing period gets at most one reminder and the next period re-arms
 * automatically when the period rolls forward.
 *
 * Honours the per-user `billing.creator_sub_renewal_reminder` email/in-app
 * preferences. Runs daily; needs no visitor traffic.
 */
class SendCreatorSubscriptionRenewalReminders extends Command
{
    protected $signature = 'creator-subscriptions:send-renewal-reminders
        {--sub= : Optional creator_subscription id to remind (default: all eligible)}
        {--lead-days=3 : Send when the renewal is within this many days}
        {--force : Ignore the lead-time / once-per-period guards}';

    protected $description = 'Remind fans before a creator subscription they pay for auto-renews (email + in-app), naming the creator, amount, cycle, exact date and a manage/cancel link.';

    public function handle(NotificationService $prefs): int
    {
        $force    = (bool) $this->option('force');
        $leadDays = max(0, (int) $this->option('lead-days'));
        $subId    = $this->option('sub');
        $now      = now();

        $query = CreatorSubscription::query()
            ->with(['fan', 'creator', 'tier'])
            ->where('status', CreatorSubscription::STATUS_ACTIVE)
            ->where('cancel_at_period_end', false)
            ->where('price_cents', '>', 0)
            ->whereNotNull('current_period_end');

        if ($subId) {
            $query->where('id', $subId);
        }

        if (!$force) {
            // Only those whose renewal is within the lead window (and not
            // already in the past — a never-reconciled lapsed period
            // shouldn't generate a "renews soon" nudge).
            $query->where('current_period_end', '>=', $now)
                  ->where('current_period_end', '<=', $now->copy()->addDays($leadDays));
        }

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($subs) use (&$sent, &$skipped, $prefs, $force) {
            foreach ($subs as $sub) {
                // Once per billing period: skip if we already reminded for the
                // current period (stamp at/after the period start). When the
                // period rolls forward, current_period_start advances past the
                // old stamp and the next reminder re-arms.
                if (!$force
                    && $sub->renewal_reminder_sent_at
                    && $sub->current_period_start
                    && $sub->renewal_reminder_sent_at->greaterThanOrEqualTo($sub->current_period_start)) {
                    $skipped++;
                    continue;
                }

                if ($this->remind($sub, $prefs)) {
                    $sub->forceFill(['renewal_reminder_sent_at' => now()])->save();
                    $sent++;
                } else {
                    $skipped++;
                }
            }
        });

        $this->info("Creator subscription renewal reminder run complete. Sent: {$sent}, skipped: {$skipped}.");
        return self::SUCCESS;
    }

    /**
     * Drop the in-app notification (honoring the in_app preference) and email
     * the fan a renewal heads-up. Returns true if at least one channel was
     * attempted so the once-per-period stamp is recorded — otherwise a fully
     * muted fan, or a transient email failure, would suppress later retries
     * within the same period.
     */
    private function remind(CreatorSubscription $sub, NotificationService $prefs): bool
    {
        $fan     = $sub->fan;
        $creator = $sub->creator;
        if (!$fan || !$creator) {
            return false;
        }

        $renewalDate = $sub->current_period_end instanceof Carbon
            ? $sub->current_period_end
            : Carbon::parse($sub->current_period_end);

        $creatorName = trim((string) ($creator->name ?: $creator->handle ?: 'this creator'));
        $cycle       = $sub->billing_cycle === CreatorSubscription::CYCLE_YEARLY ? 'yearly' : 'monthly';
        $amount      = number_format($sub->price_cents / 100, 2);
        $currency    = strtoupper((string) ($sub->currency ?: 'USD'));
        $renewsIn    = $this->relativeRenewal($renewalDate);
        $dateLong    = $renewalDate->format('F j, Y');

        // Manage / cancel page — the canonical fan-facing surface; the fan is
        // asked to sign in there via the existing viewer-OTP flow if needed.
        $manageUrl = $creator->handle
            ? route('creator-profile.subscription.manage', ['handle' => $creator->handle])
            : url('/');

        $delivered = false;

        $message = "Your {$cycle} subscription to {$creatorName} renews {$renewsIn} ({$dateLong}) — you'll be charged {$amount} {$currency}.";

        if ($prefs->notify($fan, 'billing.creator_sub_renewal_reminder', [
            'title'        => "Subscription to {$creatorName} renews soon",
            'message'      => $message,
            'url'          => $manageUrl,
            'creator'      => $creatorName,
            'amount'       => $amount,
            'currency'     => $currency,
            'cycle'        => $cycle,
            'renewal_date' => $renewalDate->toIso8601String(),
        ]) !== null) {
            $delivered = true;
        }

        if ($fan->email && $prefs->prefersChannel($fan->id, 'billing.creator_sub_renewal_reminder', 'email')) {
            try {
                \App\Modules\Common\Services\Emailer::send('billing.creator_sub_renewal_reminder', $fan->email, [
                    'creator_name' => $creatorName,
                    'amount'       => $amount,
                    'currency'     => $currency,
                    'cycle'        => $cycle,
                    'renews_in'    => $renewsIn,
                    'renewal_date' => $dateLong,
                    'manage_url'   => $manageUrl,
                ], [
                    'user' => $fan->id,
                ]);
                $delivered = true;
            } catch (\Throwable $e) {
                Log::warning('Creator subscription renewal reminder email failed for sub ' . $sub->id . ': ' . $e->getMessage());
            }
        }

        return $delivered;
    }

    /** Human relative phrasing for the renewal date ("today" / "tomorrow" / "in N days"). */
    private function relativeRenewal(Carbon $renewalDate): string
    {
        $days = now()->startOfDay()->diffInDays($renewalDate->copy()->startOfDay(), false);
        if ($days <= 0) return 'today';
        if ($days === 1) return 'tomorrow';
        return "in {$days} days";
    }
}
