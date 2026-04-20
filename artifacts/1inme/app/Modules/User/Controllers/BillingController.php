<?php

namespace App\Modules\User\Controllers;

use App\Actions\Billing\ActivateSubscription;
use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CreditNote;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
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
 *   /user/billing/upgrade/confirm   — preview prorated amount.
 *   /user/billing/upgrade/handoff   — create upgrade invoice + gateway.
 *   /user/billing/cancel    — cancel at period end.
 *   /user/billing/resume    — undo cancel.
 *   /user/billing/invoices/{id}/refund  — self-serve refund within window.
 *   /user/billing/credit-notes/{id}.pdf — credit-note PDF.
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

        $refundableInvoices = $invoices->filter(function (Invoice $inv) use ($subscription) {
            if ($inv->status !== 'paid' || !$inv->paid_at) return false;
            $plan = $subscription?->plan;
            $window = (int) ($plan?->refund_window_days ?? 7);
            return Carbon::parse($inv->paid_at)->addDays($window)->isFuture()
                && (int) Refund::where('invoice_id', $inv->id)->where('status', 'succeeded')->sum('amount_minor') < (int) $inv->grand_total_minor;
        })->pluck('id')->all();

        $addons = $subscription
            ? $subscription->addons()->with('addon')->get()
            : collect();

        return view('user.billing.show', compact(
            'subscription', 'invoices', 'creditNotes',
            'graceDaysRemaining', 'refundableInvoices', 'addons'
        ));
    }

    public function upgrade(Request $request)
    {
        $user = $request->user();
        $current = $this->activeSubscription($user);
        if (!$current) {
            return redirect()->route('user.upgrade');
        }
        $plans = Plan::active()->ordered()->get()->filter(function (Plan $p) use ($current, $user) {
            return $p->id !== $current->plan_id
                && ProrationCalculator::resolveMinor($p, $current->billing_cycle, $user)
                    > ProrationCalculator::resolveMinor($current->plan, $current->billing_cycle, $user);
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
        $target = Plan::active()->findOrFail($data['plan_id']);
        $calc = ProrationCalculator::prorate(
            $current->plan, $target,
            $current->billing_cycle, now(),
            Carbon::parse($current->current_period_end),
            $user,
        );
        abort_unless($calc['is_upgrade'], 422, 'Downgrades apply at the end of the current cycle only.');
        return view('user.billing.upgrade_confirm', [
            'current' => $current, 'target' => $target, 'calc' => $calc,
            'gateways' => app(GatewayManager::class)->enabledAdapters(),
        ]);
    }

    public function upgradeHandoff(Request $request, GatewayManager $gm)
    {
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
            'gateway' => 'required|string|in:razorpay,stripe,paypal,cashfree,offline',
        ]);
        $user = $request->user();
        $current = $this->activeSubscription($user);
        abort_unless($current, 404);
        $target = Plan::active()->findOrFail($data['plan_id']);

        $enabledSlugs = array_map(fn($a) => $a->slug(), $gm->enabledAdapters());
        if (!in_array($data['gateway'], $enabledSlugs, true)) {
            return back()->with('error', 'That payment method is not available right now.');
        }

        $calc = ProrationCalculator::prorate(
            $current->plan, $target,
            $current->billing_cycle, now(),
            Carbon::parse($current->current_period_end),
            $user,
        );
        if (!$calc['is_upgrade'] || $calc['amount_minor'] <= 0) {
            return back()->with('error', 'Nothing to charge for this change.');
        }

        $items = [[
            'label'        => 'Upgrade to ' . $target->name . ' (' . $calc['days_left'] . ' of ' . $calc['days_in_cycle'] . ' days)',
            'amount_minor' => (int) $calc['amount_minor'],
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
        if (($result['kind'] ?? null) === 'redirect') return redirect()->away((string) $result['url']);
        if (($result['kind'] ?? null) === 'view') return view($result['view'], $result['data']);
        return redirect()->route('user.billing.show');
    }

    public function cancel(Request $request, SubscriptionLifecycle $lc)
    {
        $sub = $this->activeSubscription($request->user());
        abort_unless($sub, 404);
        $lc->cancelAtPeriodEnd($sub);
        return back()->with('status', 'Your plan will stop renewing at the end of the current billing period.');
    }

    public function resume(Request $request, SubscriptionLifecycle $lc)
    {
        $sub = $this->activeSubscription($request->user());
        abort_unless($sub, 404);
        $lc->undoCancel($sub);
        return back()->with('status', 'Your plan will continue renewing.');
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
            $refunds->issue($invoice, (int) $invoice->grand_total_minor, [
                'reason'         => 'Self-serve refund within policy window',
                'user_initiated' => true,
                'downgrade_on_success' => true,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Refund could not be issued: ' . $e->getMessage());
        }
        return redirect()->route('user.billing.show')->with('status', 'Refund issued. You are back on the Free plan.');
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
