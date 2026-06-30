<?php

namespace App\Modules\User\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use App\Services\Billing\CreditNoteService;
use App\Services\Billing\GatewayManager;
use App\Services\Billing\NotImplementedException;
use App\Services\Billing\ProrationCalculator;
use App\Services\Billing\RefundService;
use App\Services\Billing\SubscriptionLifecycle;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The user-facing billing dashboard.
 *
 *   /user/billing           — overview (current plan, next renewal,
 *                             grace banner, invoices + credit notes).
 *   /user/billing/upgrade   — pick a higher plan.
 *   /user/billing/upgrade/confirm   — preview full-price upgrade amount.
 *   /user/billing/upgrade/handoff   — create upgrade invoice + gateway.
 *   /user/billing/downgrade — pick a lower paid plan to schedule.
 *   /user/billing/downgrade/schedule — schedule the downgrade at cycle end.
 *   /user/billing/downgrade/cancel   — cancel a pending scheduled downgrade.
 *   /user/billing/cancel    — cancel at period end.
 *   /user/billing/resume    — undo cancel.
 *   /user/billing/invoices/{id}/refund  — self-serve refund within window.
 *   /user/billing/credit-notes/{id}.pdf — credit-note PDF.
 *
 * Plan changes do NOT use proration. An UPGRADE charges full price for a
 * fresh full cycle starting now; the leftover days/add-on time on the old
 * plan are flagged for optional admin credit (subscription_credit_reviews),
 * never auto-credited. A DOWNGRADE is scheduled to a chosen lower PAID plan
 * and applied at cycle end (it never silently reverts to Free).
 */
class BillingController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $subscription = Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due', 'grace'])
            ->latest('id')->first();
        $invoices = Invoice::where('user_id', $user->id)
            ->orderByDesc('id')->limit(50)->get();
        $creditNotes = CreditNote::where('user_id', $user->id)
            ->orderByDesc('id')->limit(50)->get();

        $graceDaysRemaining = null;
        if ($subscription && $subscription->grace_until) {
            $graceDaysRemaining = max(0, (int) now()->diffInDays(Carbon::parse($subscription->grace_until), false));
        }

        // Refund eligibility must be computed per-invoice using THAT
        // invoice's subscription/plan window — not the user's current
        // plan window. Historical invoices on a prior plan keep their
        // own refund window.
        $refundableInvoices = $invoices->filter(function (Invoice $inv) {
            if ($inv->status !== 'paid' || !$inv->paid_at) return false;
            $invSub  = $inv->subscription_id ? Subscription::find($inv->subscription_id) : null;
            $invPlan = $invSub?->plan;
            $window  = (int) ($invPlan?->refund_window_days ?? 7);
            return Carbon::parse($inv->paid_at)->addDays($window)->isFuture()
                && (int) Refund::where('invoice_id', $inv->id)->where('status', 'succeeded')->sum('amount_minor') < (int) $inv->grand_total_minor;
        })->pluck('id')->all();

        $addons = $subscription
            ? $subscription->addons()->with('addon')->get()
            : collect();

        $scheduledDowngradePlan = $subscription && $subscription->scheduled_downgrade_plan_id
            ? Plan::find($subscription->scheduled_downgrade_plan_id)
            : null;

        return view('user.billing.show', compact(
            'subscription', 'invoices', 'creditNotes',
            'graceDaysRemaining', 'refundableInvoices', 'addons',
            'scheduledDowngradePlan'
        ));
    }

    public function upgrade(Request $request)
    {
        $user = $request->user();
        $current = $this->activeSubscription($user);
        if (!$current) {
            return redirect()->route('user.upgrade');
        }
        // Currency is locked on the subscription — list upgrade options
        // by comparing prices in the SUBSCRIPTION's currency, never the
        // user's current country/session.
        $subCurrency = (string) $current->currency;
        $currentMinor = ProrationCalculator::resolveMinor($current->plan, $current->billing_cycle, null, $subCurrency);
        $plans = Plan::active()->public()->ordered()->get()->filter(function (Plan $p) use ($current, $subCurrency, $currentMinor) {
            return $p->id !== $current->plan_id
                && ProrationCalculator::resolveMinor($p, $current->billing_cycle, null, $subCurrency) > $currentMinor;
        });
        return view('user.billing.upgrade', compact('current', 'plans'));
    }

    public function upgradeConfirm(Request $request)
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
        ]);
        $user = $request->user();
        $current = $this->activeSubscription($user);
        abort_unless($current, 404);
        $target = Plan::active()->public()->findOrFail($data['plan_id']);

        // No proration: an upgrade is full price for a fresh full cycle.
        // We still require the target to actually be a higher-priced plan.
        $currency     = (string) $current->currency;
        $currentMinor = ProrationCalculator::resolveMinor($current->plan, $current->billing_cycle, null, $currency);
        $amountMinor  = ProrationCalculator::resolveMinor($target, $current->billing_cycle, null, $currency);
        abort_unless($amountMinor > $currentMinor, 422, 'Choose a higher plan to upgrade. To move to a lower plan, use the downgrade option.');

        return view('user.billing.upgrade_confirm', [
            'current'      => $current,
            'target'       => $target,
            'amount_minor' => $amountMinor,
            'currency'     => $currency,
            'cycle'        => $current->billing_cycle,
            'gateways'     => app(GatewayManager::class)->enabledAdapters(),
        ]);
    }

    public function upgradeHandoff(Request $request, GatewayManager $gm)
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
            'gateway' => 'required|string|in:razorpay,stripe,paypal,cashfree,payumoney,offline',
        ]);
        $user = $request->user();
        $current = $this->activeSubscription($user);
        abort_unless($current, 404);
        $target = Plan::active()->public()->findOrFail($data['plan_id']);

        $enabledSlugs = array_map(fn($a) => $a->slug(), $gm->enabledAdapters());
        if (!in_array($data['gateway'], $enabledSlugs, true)) {
            return back()->with('error', 'That payment method is not available right now.');
        }

        // No proration: charge the FULL price of the target plan for a
        // fresh full cycle. Leftover time on the old plan is flagged for
        // optional admin credit by ActivateSubscription, not netted off.
        $currency     = (string) $current->currency;
        $currentMinor = ProrationCalculator::resolveMinor($current->plan, $current->billing_cycle, null, $currency);
        $amountMinor  = ProrationCalculator::resolveMinor($target, $current->billing_cycle, null, $currency);
        if ($amountMinor <= $currentMinor || $amountMinor <= 0) {
            return back()->with('error', 'Choose a higher plan to upgrade. To move to a lower plan, use the downgrade option.');
        }

        $cycleLabel = $current->billing_cycle === 'annual' ? 'annual' : 'monthly';
        $items = [[
            'label'        => 'Upgrade to ' . $target->name . ' (full ' . $cycleLabel . ' term)',
            'amount_minor' => (int) $amountMinor,
            'quantity'     => 1,
            'meta'         => [
                'kind'    => 'plan_upgrade',
                'plan_id' => $target->id,
                'cycle'   => $current->billing_cycle,
                'upgrade_from_subscription_id' => $current->id,
            ],
        ]];
        $invoice = ActivateSubscription::issuePendingInvoice($user, $items, $current->currency);

        try {
            $adapter = $gm->for($data['gateway']);
            $result  = $adapter->createCheckout($invoice);
        } catch (NotImplementedException $e) {
            $invoice->forceFill(['status' => 'cancelled'])->save();
            return redirect()->route('user.billing.show')
                ->with('error', 'That gateway is not available yet.');
        }
        if (($result['kind'] ?? null) === 'redirect') {
            WorkspaceActivityRecorder::record(null, 'billing.upgrade', 'billing', $invoice->id, 'Upgrade to ' . $target->name, route('user.billing.show'), [
                'target_plan_id' => $target->id, 'gateway' => $data['gateway'], 'invoice_id' => $invoice->id,
            ]);
            return redirect()->away((string) $result['url']);
        }
        if (($result['kind'] ?? null) === 'view') {
            WorkspaceActivityRecorder::record(null, 'billing.upgrade', 'billing', $invoice->id, 'Upgrade to ' . $target->name, route('user.billing.show'), [
                'target_plan_id' => $target->id, 'gateway' => $data['gateway'], 'invoice_id' => $invoice->id,
            ]);
            return view($result['view'], $result['data']);
        }
        return redirect()->route('user.billing.show');
    }

    public function cancel(Request $request, SubscriptionLifecycle $lc)
    {
        $sub = $this->activeSubscription($request->user());
        abort_unless($sub, 404);
        $lc->cancelAtPeriodEnd($sub);
        WorkspaceActivityRecorder::record(null, 'billing.cancel', 'billing', $sub->id, 'Cancel subscription #' . $sub->id, route('user.billing.show'));
        return back()->with('status', 'Your plan will stop renewing at the end of the current billing period.');
    }

    public function resume(Request $request, SubscriptionLifecycle $lc)
    {
        $sub = $this->activeSubscription($request->user());
        abort_unless($sub, 404);
        $lc->undoCancel($sub);
        WorkspaceActivityRecorder::record(null, 'billing.resume', 'billing', $sub->id, 'Resume subscription #' . $sub->id, route('user.billing.show'));
        return back()->with('status', 'Your plan will continue renewing.');
    }

    /**
     * Pick a LOWER PAID plan to switch to at the end of the current cycle.
     * Free is never an option here — moving to Free is "Cancel at period
     * end". For each candidate we surface which of the user's current
     * add-ons that plan can't carry, so the choice is informed.
     */
    public function downgrade(Request $request)
    {
        $user = $request->user();
        $current = $this->activeSubscription($user);
        if (!$current) {
            return redirect()->route('user.billing.show');
        }
        $subCurrency  = (string) $current->currency;
        $currentMinor = ProrationCalculator::resolveMinor($current->plan, $current->billing_cycle, null, $subCurrency);

        $currentAddonIds = $current->addons()->pluck('addon_id')->map(fn ($id) => (int) $id)->all();
        $currentAddonNames = $current->addons()->with('addon')->get()
            ->mapWithKeys(fn ($sa) => [(int) $sa->addon_id => ($sa->addon->name ?? 'Add-on #' . $sa->addon_id)])
            ->all();

        $plans = Plan::active()->public()->ordered()->get()
            ->filter(function (Plan $p) use ($current, $subCurrency, $currentMinor) {
                if ($p->id === $current->plan_id) return false;
                if ($p->is_default) return false; // Free is "cancel", not "downgrade"
                $minor = ProrationCalculator::resolveMinor($p, $current->billing_cycle, null, $subCurrency);
                return $minor > 0 && $minor < $currentMinor;
            })
            ->map(function (Plan $p) use ($currentAddonIds, $currentAddonNames, $current, $subCurrency) {
                $eligible = $p->addons()->pluck('addons.id')->map(fn ($id) => (int) $id)->all();
                $lost = [];
                foreach ($currentAddonIds as $aid) {
                    if (!in_array($aid, $eligible, true)) {
                        $lost[] = $currentAddonNames[$aid] ?? ('Add-on #' . $aid);
                    }
                }
                $p->setAttribute('downgrade_price_minor', ProrationCalculator::resolveMinor($p, $current->billing_cycle, null, $subCurrency));
                $p->setAttribute('downgrade_lost_addons', $lost);
                return $p;
            })
            ->values();

        $scheduledDowngradePlan = $current->scheduled_downgrade_plan_id
            ? Plan::find($current->scheduled_downgrade_plan_id)
            : null;

        return view('user.billing.downgrade', compact('current', 'plans', 'scheduledDowngradePlan'));
    }

    public function scheduleDowngrade(Request $request, SubscriptionLifecycle $lc)
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
        ]);
        $user = $request->user();
        $current = $this->activeSubscription($user);
        abort_unless($current, 404);

        $target = Plan::active()->public()->find($data['plan_id']);
        if (!$target || $target->is_default || $target->id === $current->plan_id) {
            return back()->with('error', 'That plan is not a valid downgrade option.');
        }

        $subCurrency  = (string) $current->currency;
        $currentMinor = ProrationCalculator::resolveMinor($current->plan, $current->billing_cycle, null, $subCurrency);
        $targetMinor  = ProrationCalculator::resolveMinor($target, $current->billing_cycle, null, $subCurrency);
        if ($targetMinor <= 0 || $targetMinor >= $currentMinor) {
            return back()->with('error', 'Pick a lower-priced paid plan to downgrade. To upgrade, use the upgrade option.');
        }

        $lc->scheduleDowngrade($current, $target);
        WorkspaceActivityRecorder::record(null, 'billing.downgrade_scheduled', 'billing', $current->id, 'Schedule downgrade to ' . $target->name, route('user.billing.show'), [
            'target_plan_id' => $target->id,
        ]);

        $when = Carbon::parse($current->current_period_end)->toFormattedDateString();
        return redirect()->route('user.billing.show')
            ->with('status', 'Your plan will change to ' . $target->name . ' on ' . $when . '. You can cancel this anytime before then.');
    }

    public function cancelDowngrade(Request $request, SubscriptionLifecycle $lc)
    {
        $sub = $this->activeSubscription($request->user());
        abort_unless($sub, 404);
        if (!$sub->scheduled_downgrade_plan_id) {
            return back()->with('status', 'There is no scheduled downgrade to cancel.');
        }
        // cancelScheduledDowngrade re-checks under a row lock: if the renewal
        // cron applied the downgrade in the same moment, it returns false and
        // we must NOT claim it was cancelled.
        if (!$lc->cancelScheduledDowngrade($sub)) {
            return back()->with('status', 'Your scheduled plan change has already taken effect, so there was nothing left to cancel.');
        }
        WorkspaceActivityRecorder::record(null, 'billing.downgrade_cancelled', 'billing', $sub->id, 'Cancel scheduled downgrade #' . $sub->id, route('user.billing.show'));
        return back()->with('status', 'Your scheduled downgrade has been cancelled. You will stay on your current plan.');
    }

    public function refundInvoice(Request $request, Invoice $invoice, RefundService $refunds)
    {
        $user = $request->user();
        abort_unless($invoice->user_id === $user->id, 403);
        abort_unless($invoice->status === 'paid' && $invoice->paid_at, 422, 'Invoice not refundable.');
        $sub  = Subscription::find($invoice->subscription_id);
        $plan = $sub?->plan;
        $window = (int) ($plan?->refund_window_days ?? 7);
        if (Carbon::parse($invoice->paid_at)->addDays($window)->isPast()) {
            return back()->with('error', 'The refund window for this invoice has closed. Please contact support.');
        }
        try {
            $refund = $refunds->issue($invoice, (int) $invoice->grand_total_minor, [
                'reason'         => 'Self-serve refund within policy window',
                'user_initiated' => true,
                'downgrade_on_success' => true,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Refund could not be issued: ' . $e->getMessage());
        }
        WorkspaceActivityRecorder::record(null, 'billing.refund', 'billing', $invoice->id, 'Refund invoice #' . $invoice->id, route('user.billing.show'), [
            'amount_minor' => (int) $invoice->grand_total_minor, 'currency' => $invoice->currency ?? null,
        ]);
        // Distinguish the offline "pending admin confirmation" case
        // from a gateway-completed refund — the user must not be told
        // they're back on Free when the refund hasn't actually been
        // paid out yet.
        $status = is_object($refund) ? (string) ($refund->status ?? 'pending') : 'pending';
        $msg = $status === 'succeeded'
            ? 'Refund issued. You are back on the Free plan.'
            : 'Refund request received. We\'ll confirm the payout and then downgrade your plan — you\'ll get an email once it\'s complete.';
        return redirect()->route('user.billing.show')->with('status', $msg);
    }

    public function creditNotePdf(Request $request, CreditNote $creditNote)
    {
        abort_unless($creditNote->user_id === $request->user()->id, 403);
        $pdf = CreditNoteService::renderPdf($creditNote);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . str_replace('/', '_', $creditNote->number) . '.pdf"',
        ]);
    }

    protected function activeSubscription($user): ?Subscription
    {
        return Subscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'past_due', 'grace'])
            ->latest('id')->first();
    }
}
