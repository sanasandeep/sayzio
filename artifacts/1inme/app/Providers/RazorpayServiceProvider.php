<?php

namespace App\Providers;

use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Subscription;
use App\Services\PricingResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wires Razorpay-specific post-activation hooks WITHOUT touching the
 * activation pipeline, webhook router, or gateway contract (all of
 * which are locked down per the task spec).
 *
 * Strategy: Invoice::saved fires after ActivateSubscription commits
 * the invoice → paid transition and the subscription_id link. We
 * detect razorpay invoices and run two gateway-specific steps:
 *
 *   1. First-cycle subscription activation: stamp the new internal
 *      Subscription's `gateway_subscription_id` from the Razorpay
 *      subscription_id recorded on the initiated PaymentAttempt, so
 *      future `subscription.charged` webhooks can resolve the row.
 *
 *   2. Mid-cycle upgrade: the prorated one-time Order has just been
 *      captured. Cancel the old Razorpay subscription via API and
 *      create a new Razorpay subscription on the upgraded plan,
 *      binding its id to the freshly-created internal subscription.
 *      Errors here are logged but NEVER thrown — activation must
 *      remain atomic; operators can retry via a future reconcile
 *      command or manual Razorpay dashboard action.
 */
class RazorpayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Invoice::saved(function (Invoice $invoice) {
            if ($invoice->gateway !== 'razorpay') return;
            if ($invoice->status !== 'paid') return;
            if (!$invoice->subscription_id) return;
            if (!$invoice->wasChanged('status')) return; // only on the paid transition

            $sub = Subscription::find($invoice->subscription_id);
            if (!$sub) return;

            $this->stampGatewaySubscriptionId($invoice, $sub);
            $this->handleUpgradeGatewaySwap($invoice, $sub);
        });
    }

    /**
     * For first-cycle subscription checkouts, the Razorpay subscription
     * id is on the PaymentAttempt row we wrote at handoff time
     * (raw_response.kind=subscription, raw_response.ref_id=sub_XYZ).
     */
    protected function stampGatewaySubscriptionId(Invoice $invoice, Subscription $sub): void
    {
        if ($sub->gateway_subscription_id) return;

        $attempt = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'razorpay')
            ->whereIn('status', ['initiated', 'succeeded'])
            ->orderBy('id')
            ->first();
        if (!$attempt) return;

        $raw    = (array) $attempt->raw_response;
        $kind   = $raw['kind']    ?? null;
        $refId  = $raw['ref_id']  ?? null;
        if ($kind === 'subscription' && is_string($refId) && $refId !== '') {
            $sub->forceFill(['gateway_subscription_id' => $refId])->save();
        }
    }

    /**
     * Mid-cycle upgrade: we charged a prorated one-time Order, and
     * ActivateSubscription cancelled the old Subscription row + created
     * a new one. We still need to:
     *   (a) cancel the old Razorpay subscription so it doesn't auto-
     *       charge the old price next cycle,
     *   (b) create a new Razorpay subscription on the new plan and
     *       link its id so renewals continue.
     */
    protected function handleUpgradeGatewaySwap(Invoice $invoice, Subscription $sub): void
    {
        $items = is_array($invoice->line_items) ? $invoice->line_items : [];
        $isUpgrade = false; $oldSubId = null;
        foreach ($items as $li) {
            $meta = $li['meta'] ?? [];
            if (($meta['kind'] ?? null) === 'plan_upgrade') {
                $isUpgrade = true;
                $oldSubId  = (int) ($meta['upgrade_from_subscription_id'] ?? 0);
                break;
            }
        }
        if (!$isUpgrade) return;

        $setting = GatewaySetting::where('gateway_slug', 'razorpay')->first();
        $keyId     = (string) ($setting?->credential('key_id', '') ?? '');
        $keySecret = (string) ($setting?->credential('key_secret', '') ?? '');
        if ($keyId === '' || $keySecret === '') return;

        // (a) Cancel the old Razorpay subscription.
        if ($oldSubId) {
            $old = Subscription::find($oldSubId);
            $rzpOldId = $old?->gateway_subscription_id;
            if ($rzpOldId) {
                try {
                    Http::withBasicAuth($keyId, $keySecret)
                        ->asJson()
                        ->post('https://api.razorpay.com/v1/subscriptions/' . $rzpOldId . '/cancel', [
                            'cancel_at_cycle_end' => 0,
                        ]);
                } catch (\Throwable $e) {
                    Log::warning('Razorpay old-subscription cancel failed', [
                        'rzp_sub_id' => $rzpOldId, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // (b) Create a new Razorpay subscription on the upgraded plan
        //     so auto-renewal continues. Skip if already stamped.
        if ($sub->gateway_subscription_id) return;
        $plan = $sub->plan ?: Plan::find($sub->plan_id);
        if (!$plan) return;

        try {
            $priced = PricingResolver::priceForCurrency(
                $plan, (string) $sub->currency, (string) $sub->billing_cycle
            );
            $amountMinor = (int) $priced['amount_minor'];

            // Reuse the cache via the adapter's plan-ensure flow, but we
            // duplicate minimal logic here to avoid coupling back into
            // the adapter's stateful settings object from an event.
            $period = $sub->billing_cycle === 'annual' ? 'yearly' : 'monthly';
            $planRes = Http::withBasicAuth($keyId, $keySecret)
                ->asJson()
                ->post('https://api.razorpay.com/v1/plans', [
                    'period'   => $period,
                    'interval' => 1,
                    'item'     => [
                        'name'     => $plan->name . ' (' . $sub->billing_cycle . ')',
                        'amount'   => $amountMinor,
                        'currency' => (string) $sub->currency,
                    ],
                    'notes' => [
                        'internal_plan_id' => (string) $plan->id,
                        'upgraded'         => '1',
                    ],
                ]);
            if (!$planRes->successful()) {
                Log::warning('Razorpay upgrade plan-create failed', ['body' => $planRes->body()]);
                return;
            }
            $rzpPlanId = (string) $planRes->json('id');

            $subRes = Http::withBasicAuth($keyId, $keySecret)
                ->asJson()
                ->post('https://api.razorpay.com/v1/subscriptions', [
                    'plan_id'     => $rzpPlanId,
                    'total_count' => $sub->billing_cycle === 'annual' ? 10 : 120,
                    'customer_notify' => 1,
                    'start_at'    => $sub->current_period_end?->timestamp,
                    'notes'       => [
                        'internal_subscription_id' => (string) $sub->id,
                        'user_id'                  => (string) $sub->user_id,
                        'intent'                   => 'upgrade_new_sub',
                    ],
                ]);
            if (!$subRes->successful()) {
                Log::warning('Razorpay upgrade subscription-create failed', ['body' => $subRes->body()]);
                return;
            }
            $sub->forceFill([
                'gateway'                 => 'razorpay',
                'gateway_subscription_id' => (string) $subRes->json('id'),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Razorpay upgrade swap failed', [
                'subscription_id' => $sub->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
