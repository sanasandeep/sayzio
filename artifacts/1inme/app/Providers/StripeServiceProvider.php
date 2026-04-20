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
 * Stripe-specific post-activation hooks. Mirrors RazorpayServiceProvider
 * in purpose: adapts the locked activation pipeline + webhook router
 * for gateway-specific side-effects without editing them.
 *
 *   1. First-cycle subscription activation: after the Checkout Session
 *      completes and the invoice flips to paid, stamp the internal
 *      Subscription's `gateway_subscription_id` from the Stripe
 *      subscription id recorded on the webhook PaymentAttempt, so
 *      future invoice.paid deliveries can resolve the row.
 *
 *   2. Mid-cycle upgrade: the one-time Checkout Session captured the
 *      prorated charge; ActivateSubscription cancelled the old Sub
 *      and created the new one. We still need to cancel the old
 *      Stripe subscription (so it doesn't renew at the old price)
 *      and create a new Stripe subscription on the upgraded plan.
 *      Errors are logged but never thrown — activation must remain
 *      atomic.
 */
class StripeServiceProvider extends ServiceProvider
{
    protected const API = 'https://api.stripe.com/v1';

    public function boot(): void
    {
        Invoice::saved(function (Invoice $invoice) {
            if ($invoice->gateway !== 'stripe') return;
            if ($invoice->status !== 'paid') return;
            if (!$invoice->subscription_id) return;
            if (!$invoice->wasChanged('status')) return;

            $sub = Subscription::find($invoice->subscription_id);
            if (!$sub) return;

            $this->stampGatewaySubscriptionId($invoice, $sub);
            $this->handleUpgradeGatewaySwap($invoice, $sub);
        });
    }

    /**
     * First-cycle: Stripe's subscription id lands on the webhook
     * PaymentAttempt (raw_response.stripe_subscription_id), written
     * by WebhookController from our parseEvent() extras.
     */
    protected function stampGatewaySubscriptionId(Invoice $invoice, Subscription $sub): void
    {
        if ($sub->gateway_subscription_id) return;

        $attempts = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'stripe')
            ->whereIn('status', ['succeeded', 'initiated'])
            ->orderByDesc('id')
            ->get();
        foreach ($attempts as $attempt) {
            $raw   = (array) $attempt->raw_response;
            $stSub = $raw['stripe_subscription_id']
                ?? ($raw['data']['object']['subscription'] ?? null);
            if (is_string($stSub) && $stSub !== '') {
                $sub->forceFill(['gateway_subscription_id' => $stSub])->save();
                return;
            }
        }
    }

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

        $setting   = GatewaySetting::where('gateway_slug', 'stripe')->first();
        $secretKey = (string) ($setting?->credential('secret_key', '') ?? '');
        if ($secretKey === '') return;

        // (a) Cancel the old Stripe subscription.
        if ($oldSubId) {
            $old = Subscription::find($oldSubId);
            $stOldId = $old?->gateway_subscription_id;
            if ($stOldId) {
                try {
                    $res = Http::withToken($secretKey)->asForm()
                        ->delete(self::API . '/subscriptions/' . $stOldId);
                    if (!$res->successful()) {
                        Log::error('Stripe old-subscription cancel returned non-success', [
                            'stripe_sub_id' => $stOldId,
                            'status'        => $res->status(),
                            'body'          => $res->json() ?: $res->body(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Stripe old-subscription cancel threw', [
                        'stripe_sub_id' => $stOldId, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // (b) Create a new Stripe subscription on the upgraded plan.
        //     Requires a Stripe Customer id — take it from the most
        //     recent succeeded PaymentAttempt for this user's latest
        //     stripe invoice (the Checkout Session creates the
        //     customer server-side and we recorded it).
        if ($sub->gateway_subscription_id) return;
        $plan = $sub->plan ?: Plan::find($sub->plan_id);
        if (!$plan) return;

        $customerId = $this->findRecentStripeCustomerId($sub->user_id);
        if (!$customerId) {
            Log::warning('Stripe upgrade swap: no customer id found, skipping new-sub create', [
                'subscription_id' => $sub->id,
            ]);
            return;
        }

        try {
            $priced = PricingResolver::priceForCurrency(
                $plan, (string) $sub->currency, (string) $sub->billing_cycle
            );
            $amountMinor = (int) $priced['amount_minor'];
            $currency    = strtolower((string) $sub->currency);
            $interval    = $sub->billing_cycle === 'annual' ? 'year' : 'month';

            // Base price (pre-tax plan amount for the upgraded plan).
            $priceRes = Http::withToken($secretKey)->asForm()->post(self::API . '/prices', [
                'currency'              => $currency,
                'unit_amount'           => $amountMinor,
                'recurring[interval]'   => $interval,
                'product_data[name]'    => $plan->name . ' (' . $sub->billing_cycle . ', upgrade)',
                'metadata[internal_plan_id]' => (string) $plan->id,
                'metadata[component]'   => 'base',
                'metadata[upgraded]'    => '1',
            ]);
            if (!$priceRes->successful()) {
                Log::warning('Stripe upgrade price-create failed', ['body' => $priceRes->body()]);
                return;
            }
            $priceId = (string) $priceRes->json('id');

            // Tax price (separate line, task constraint: never fold
            // tax into the recurring base). We derive the full-cycle
            // tax by applying the just-paid invoice's effective tax
            // ratio (tax_total_minor / subtotal_minor) to the new
            // cycle's base amount. This preserves the user's
            // jurisdiction/rate without re-invoking the tax engine
            // from inside an Invoice::saved listener.
            $invSub = max(0, (int) $invoice->subtotal_minor);
            $invTax = max(0, (int) $invoice->tax_total_minor);
            $taxPriceId = null;
            if ($invSub > 0 && $invTax > 0) {
                $cycleTaxMinor = (int) round($amountMinor * ($invTax / $invSub));
                if ($cycleTaxMinor > 0) {
                    $taxRes = Http::withToken($secretKey)->asForm()->post(self::API . '/prices', [
                        'currency'              => $currency,
                        'unit_amount'           => $cycleTaxMinor,
                        'recurring[interval]'   => $interval,
                        'product_data[name]'    => 'Tax (GST/VAT)',
                        'metadata[internal_plan_id]' => (string) $plan->id,
                        'metadata[component]'   => 'tax',
                        'metadata[upgraded]'    => '1',
                    ]);
                    if ($taxRes->successful()) {
                        $taxPriceId = (string) $taxRes->json('id');
                    } else {
                        Log::warning('Stripe upgrade tax-price-create failed', ['body' => $taxRes->body()]);
                    }
                }
            }

            // CRITICAL: defer Stripe's first charge to the end of the
            // current period. The one-time Checkout Session has already
            // captured the prorated upgrade amount covering the rest of
            // this cycle — letting Stripe bill immediately would
            // double-charge. `trial_end` suppresses the first invoice
            // until the anchor; `proration_behavior=none` keeps Stripe
            // from emitting a proration line item on the new sub.
            $anchor = $sub->current_period_end?->timestamp;
            $subForm = [
                'customer'                           => $customerId,
                'items[0][price]'                    => $priceId,
                'proration_behavior'                 => 'none',
                'metadata[internal_subscription_id]' => (string) $sub->id,
                'metadata[user_id]'                  => (string) $sub->user_id,
                'metadata[intent]'                   => 'upgrade_new_sub',
            ];
            if ($taxPriceId) {
                $subForm['items[1][price]'] = $taxPriceId;
            }
            if ($anchor && $anchor > time()) {
                $subForm['trial_end'] = (string) $anchor;
            }
            $subRes = Http::withToken($secretKey)->asForm()
                ->post(self::API . '/subscriptions', $subForm);
            if (!$subRes->successful()) {
                Log::warning('Stripe upgrade subscription-create failed', ['body' => $subRes->body()]);
                return;
            }
            $sub->forceFill([
                'gateway'                 => 'stripe',
                'gateway_subscription_id' => (string) $subRes->json('id'),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Stripe upgrade swap failed', [
                'subscription_id' => $sub->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    protected function findRecentStripeCustomerId(int $userId): ?string
    {
        $rows = PaymentAttempt::query()
            ->join('invoices', 'invoices.id', '=', 'payment_attempts.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('payment_attempts.gateway', 'stripe')
            ->orderByDesc('payment_attempts.id')
            ->limit(20)
            ->pluck('payment_attempts.raw_response');
        foreach ($rows as $raw) {
            $raw = is_array($raw) ? $raw : (array) json_decode((string) $raw, true);
            $cid = $raw['stripe_customer_id']
                ?? ($raw['data']['object']['customer'] ?? null);
            if (is_string($cid) && $cid !== '') return $cid;
        }
        return null;
    }
}
