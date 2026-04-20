<?php

namespace App\Services\Billing\Adapters;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use App\Services\Billing\NotImplementedException;
use App\Services\PricingResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay gateway adapter.
 *
 * Strategy:
 *   - One-time payments (upgrade proration, add-on-only, offline-style
 *     top-ups) → Razorpay Orders API. We put our invoice_id in the
 *     order's `notes` field so the webhook can round-trip it.
 *   - Subscription-first checkouts (intent=plan) → Razorpay
 *     Subscriptions API. We create a matching plan object on the fly
 *     (Razorpay's plans are immutable; we cache by (plan_id, cycle,
 *     amount_minor, currency) in plan `notes`) and then create a
 *     subscription with `total_count` large enough that Razorpay
 *     auto-renews indefinitely until we cancel.
 *   - Renewals are triggered by Razorpay, NOT by our cron. When
 *     `subscription.charged` arrives we issue a renewal invoice on
 *     the fly and let the existing activation pipeline run.
 *   - Refunds → POST /v1/payments/{payment_id}/refund. We look up the
 *     payment_id from the succeeded PaymentAttempt for the invoice.
 *
 * Webhook idempotency is keyed on Razorpay's `event.id` via the
 * payment_attempts (gateway, gateway_ref) unique index — we return
 * event.id as gateway_ref from parseEvent(), so a retried delivery
 * collides with the original row and the router converges.
 *
 * Test/live modes share the same base URL. Only the key pair differs.
 */
class RazorpayAdapter extends AbstractAdapter
{
    protected const API = 'https://api.razorpay.com/v1';

    public function slug(): string { return 'razorpay'; }
    public function displayName(): string { return 'Razorpay (cards, UPI, netbanking)'; }

    // ------------------------------------------------------------------
    //  Checkout handoff
    // ------------------------------------------------------------------

    public function createCheckout(Invoice $invoice): array
    {
        $keyId     = (string) $this->cred('key_id', '');
        $keySecret = (string) $this->cred('key_secret', '');
        if ($keyId === '' || $keySecret === '') {
            throw new NotImplementedException('Razorpay credentials are not configured.');
        }

        $items        = is_array($invoice->line_items) ? $invoice->line_items : [];
        $intent       = $this->detectIntent($items);
        $currency     = strtoupper((string) $invoice->currency);
        $amountMinor  = (int) $invoice->grand_total_minor;

        // For subscriptions (first-cycle plan purchase), Razorpay
        // requires a plan_id + subscription_id rather than an order_id.
        // Upgrades/renewals/addon-only checkouts use orders.
        $handoff = ($intent['kind'] === 'plan')
            ? $this->createSubscriptionHandoff($invoice, $intent, $keyId, $keySecret, $currency)
            : $this->createOrderHandoff($invoice, $keyId, $keySecret, $currency, $amountMinor);

        // Log the handoff for the admin's audit trail. We use a
        // deterministic gateway_ref per invoice+handoff so a user who
        // refreshes the checkout page doesn't explode the unique index.
        PaymentAttempt::updateOrCreate(
            ['gateway' => 'razorpay', 'gateway_ref' => $handoff['attempt_ref']],
            [
                'invoice_id'  => $invoice->id,
                'status'      => 'initiated',
                'raw_response' => [
                    'kind'       => $handoff['kind'],
                    'ref_id'     => $handoff['ref_id'],
                    'amount'     => $amountMinor,
                    'currency'   => $currency,
                ],
            ],
        );

        $invoice->forceFill(['gateway' => 'razorpay'])->save();

        return [
            'kind' => 'view',
            'view' => 'user.checkout.razorpay',
            'data' => [
                'invoice'         => $invoice,
                'key_id'          => $keyId,
                'order_id'        => $handoff['kind'] === 'order' ? $handoff['ref_id'] : null,
                'subscription_id' => $handoff['kind'] === 'subscription' ? $handoff['ref_id'] : null,
                'amount_minor'    => $amountMinor,
                'currency'        => $currency,
                'merchant_name'   => (string) config('billing.merchant.name', '1INME'),
                'prefill'         => [
                    'name'    => (string) ($invoice->user?->name ?? ''),
                    'email'   => (string) ($invoice->user?->email ?? ''),
                    'contact' => (string) ($invoice->user?->phone ?? ''),
                ],
                'description'     => 'Invoice ' . $invoice->number,
            ],
        ];
    }

    /**
     * Returns ['kind'=>'order'|'subscription','ref_id'=>string,'attempt_ref'=>string].
     */
    protected function createOrderHandoff(Invoice $invoice, string $keyId, string $keySecret, string $currency, int $amountMinor): array
    {
        $res = Http::withBasicAuth($keyId, $keySecret)
            ->asJson()
            ->post(self::API . '/orders', [
                'amount'          => $amountMinor,
                'currency'        => $currency,
                'receipt'         => 'inv-' . $invoice->number,
                'payment_capture' => 1,
                'notes'           => [
                    'invoice_id'     => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->number,
                ],
            ]);
        $this->assertOk($res, 'create order');
        $orderId = (string) $res->json('id');
        return [
            'kind'        => 'order',
            'ref_id'      => $orderId,
            'attempt_ref' => 'order:' . $orderId,
        ];
    }

    protected function createSubscriptionHandoff(Invoice $invoice, array $intent, string $keyId, string $keySecret, string $currency): array
    {
        $plan  = Plan::findOrFail($intent['plan_id']);
        $cycle = $intent['cycle'];

        $priced       = PricingResolver::priceForCurrency($plan, $currency, $cycle);
        $amountMinor  = (int) $priced['amount_minor'];

        // 1) Plan object on Razorpay (idempotency by period+amount+cycle+plan).
        $rzpPlanId = $this->ensureRazorpayPlan($plan, $cycle, $currency, $amountMinor, $keyId, $keySecret);

        // 2) Create the subscription. total_count=120 ≈ 10 years of
        //    monthly charges — users cancel long before. Razorpay
        //    requires a finite ceiling; we pick a safe horizon.
        $res = Http::withBasicAuth($keyId, $keySecret)
            ->asJson()
            ->post(self::API . '/subscriptions', [
                'plan_id'     => $rzpPlanId,
                'total_count' => $cycle === 'annual' ? 10 : 120,
                'customer_notify' => 1,
                'notes'       => [
                    'invoice_id'     => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->number,
                    'user_id'        => (string) $invoice->user_id,
                    'internal_plan'  => (string) $plan->id,
                    'cycle'          => $cycle,
                ],
            ]);
        $this->assertOk($res, 'create subscription');
        $subId = (string) $res->json('id');

        return [
            'kind'        => 'subscription',
            'ref_id'      => $subId,
            'attempt_ref' => 'subscription:' . $subId,
        ];
    }

    /**
     * Reuse or create a Razorpay plan for (internal plan, cycle, amount,
     * currency). Razorpay plans are effectively immutable, so we
     * create a new one when parameters change. We cache the mapping
     * in the gateway_settings credentials blob so we don't spam the
     * Razorpay dashboard with duplicates per request.
     */
    protected function ensureRazorpayPlan(Plan $plan, string $cycle, string $currency, int $amountMinor, string $keyId, string $keySecret): string
    {
        $cacheKey = sprintf('rzp_plan:%d:%s:%s:%d', $plan->id, $cycle, $currency, $amountMinor);
        $cached   = $this->cred($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $period   = $cycle === 'annual' ? 'yearly' : 'monthly';
        $res = Http::withBasicAuth($keyId, $keySecret)
            ->asJson()
            ->post(self::API . '/plans', [
                'period'   => $period,
                'interval' => 1,
                'item'     => [
                    'name'     => $plan->name . ' (' . $cycle . ')',
                    'amount'   => $amountMinor,
                    'currency' => $currency,
                ],
                'notes'    => [
                    'internal_plan_id' => (string) $plan->id,
                    'cycle'            => $cycle,
                ],
            ]);
        $this->assertOk($res, 'create plan');
        $rzpPlanId = (string) $res->json('id');

        // Cache it so subsequent checkouts don't re-create.
        if ($this->settings) {
            $creds = $this->settings->credentials();
            $creds[$cacheKey] = $rzpPlanId;
            $this->settings->forceFill(['credentials_encrypted' => $creds])->save();
        }
        return $rzpPlanId;
    }

    // ------------------------------------------------------------------
    //  Webhook signature + event parsing
    // ------------------------------------------------------------------

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) $this->cred('webhook_secret', '');
        $header = (string) $request->header('X-Razorpay-Signature', '');
        if ($secret === '' || $header === '') return false;
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $header);
    }

    public function parseEvent(Request $request): array
    {
        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? '');
        $type    = (string) ($payload['event'] ?? '');
        $raw     = $payload;

        // ---------- payment.captured / payment.failed ----------
        if ($type === 'payment.captured' || $type === 'payment.failed') {
            $payment = $payload['payload']['payment']['entity'] ?? [];
            $notes   = $payment['notes'] ?? [];
            $invoiceId = (int) ($notes['invoice_id'] ?? 0);
            // If this is a subscription renewal, Razorpay sends
            // `payment.captured` with a subscription_id; map that to
            // an internal renewal invoice below.
            if (!$invoiceId && !empty($payment['subscription_id'])) {
                $invoiceId = $this->resolveRenewalInvoiceId(
                    (string) $payment['subscription_id'],
                    (int)    ($payment['amount']   ?? 0),
                    (string) ($payment['currency'] ?? '')
                );
            }
            // Record the actual Razorpay payment_id on the attempt row
            // so refund() can find it later. We keep event.id as the
            // router-facing gateway_ref (idempotency), and stash the
            // payment_id on the raw_response for lookup.
            return [
                'type'         => $type === 'payment.captured' ? 'payment.succeeded' : 'payment.failed',
                'invoice_id'   => $invoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => (int) ($payment['amount'] ?? 0),
                'currency'     => (string) ($payment['currency'] ?? ''),
                'raw'          => $raw + ['razorpay_payment_id' => $payment['id'] ?? null],
            ];
        }

        // ---------- subscription.charged (renewal) ----------
        if ($type === 'subscription.charged') {
            $sub     = $payload['payload']['subscription']['entity'] ?? [];
            $payment = $payload['payload']['payment']['entity'] ?? [];
            $invId   = $this->resolveRenewalInvoiceId(
                (string) ($sub['id'] ?? ''),
                (int)    ($payment['amount']   ?? 0),
                (string) ($payment['currency'] ?? '')
            );
            return [
                'type'         => $invId ? 'payment.succeeded' : 'payment.requires_review',
                'invoice_id'   => $invId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => (int) ($payment['amount'] ?? 0),
                'currency'     => (string) ($payment['currency'] ?? ''),
                'raw'          => $raw + ['razorpay_payment_id' => $payment['id'] ?? null],
            ];
        }

        // ---------- refund.processed ----------
        if ($type === 'refund.processed' || $type === 'refund.failed') {
            $refund = $payload['payload']['refund']['entity'] ?? [];
            $refundId = (string) ($refund['id'] ?? '');
            $row = $refundId ? Refund::where('gateway', 'razorpay')
                ->where('gateway_ref', $refundId)->first() : null;
            if ($row) {
                $row->forceFill([
                    'status'       => $type === 'refund.processed' ? 'succeeded' : 'failed',
                    'processed_at' => $type === 'refund.processed' ? now() : $row->processed_at,
                ])->save();
            }
            // Router has no refund branch — return a type that triggers
            // the 202 "requires_review" path with invoice_id=0 so it
            // short-circuits cleanly.
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => null,
                'gateway_ref'  => $eventId ?: ('refund:' . $refundId),
                'amount_minor' => (int) ($refund['amount'] ?? 0),
                'currency'     => (string) ($refund['currency'] ?? ''),
                'raw'          => $raw,
            ];
        }

        // ---------- subscription.cancelled ----------
        if ($type === 'subscription.cancelled') {
            $sub = $payload['payload']['subscription']['entity'] ?? [];
            $rzpSubId = (string) ($sub['id'] ?? '');
            if ($rzpSubId) {
                $match = Subscription::where('gateway', 'razorpay')
                    ->where('gateway_subscription_id', $rzpSubId)->first();
                if ($match && $match->status !== 'cancelled') {
                    $match->forceFill([
                        'status'    => 'cancelled',
                        'cancel_at' => now(),
                    ])->save();
                }
            }
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => null,
                'gateway_ref'  => $eventId ?: ('sub-cancelled:' . $rzpSubId),
                'amount_minor' => null,
                'currency'     => null,
                'raw'          => $raw,
            ];
        }

        // Unknown event → acknowledge without side-effects.
        return [
            'type'         => 'payment.requires_review',
            'invoice_id'   => null,
            'gateway_ref'  => $eventId ?: ('unknown:' . substr(md5($request->getContent()), 0, 16)),
            'amount_minor' => null,
            'currency'     => null,
            'raw'          => $raw,
        ];
    }

    /**
     * When Razorpay auto-charges a subscription, our side has no
     * invoice yet. Issue a pending renewal invoice for the subscription
     * (stamped 'razorpay') so the webhook router's ActivateSubscription
     * call can extend the period. Returns the new invoice id, or 0 if
     * we can't locate the internal subscription.
     */
    protected function resolveRenewalInvoiceId(string $rzpSubId, int $amountMinor, string $currency): int
    {
        if ($rzpSubId === '') return 0;
        $sub = Subscription::where('gateway', 'razorpay')
            ->where('gateway_subscription_id', $rzpSubId)->first();
        if (!$sub) {
            // First charge on a brand-new subscription arrives before we've
            // stamped gateway_subscription_id on the subscription row.
            // In that flow the invoice already exists (from checkout)
            // and its id is in the payment's notes; the caller handles
            // that path. Return 0 here.
            return 0;
        }

        $plan  = $sub->plan;
        $cycle = $sub->billing_cycle;
        $items = [[
            'label'        => ($plan?->name ?? 'Plan') . ' (' . $cycle . ' renewal)',
            'amount_minor' => $amountMinor,
            'quantity'     => 1,
            'meta'         => [
                'kind'                  => 'plan_renewal',
                'plan_id'               => (int) $sub->plan_id,
                'cycle'                 => $cycle,
                'renew_subscription_id' => (int) $sub->id,
            ],
        ]];
        $invoice = ActivateSubscription::issuePendingInvoice(
            $sub->user,
            $items,
            $currency !== '' ? $currency : (string) $sub->currency,
        );
        $invoice->forceFill([
            'gateway'         => 'razorpay',
            'subscription_id' => $sub->id,
        ])->save();
        return (int) $invoice->id;
    }

    // ------------------------------------------------------------------
    //  Refund
    // ------------------------------------------------------------------

    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        $keyId     = (string) $this->cred('key_id', '');
        $keySecret = (string) $this->cred('key_secret', '');
        if ($keyId === '' || $keySecret === '') {
            throw new NotImplementedException('Razorpay credentials are not configured.');
        }

        $paymentId = $this->lookupPaymentId($invoice);
        if (!$paymentId) {
            throw new \RuntimeException('No Razorpay payment id found for invoice ' . $invoice->number);
        }

        $res = Http::withBasicAuth($keyId, $keySecret)
            ->asJson()
            ->post(self::API . '/payments/' . $paymentId . '/refunds', [
                'amount' => $amountMinor,
                'notes'  => [
                    'invoice_id'     => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->number,
                    'reason'         => $reason,
                ],
            ]);
        $this->assertOk($res, 'create refund');

        $refundId = (string) $res->json('id');
        $status   = (string) $res->json('status'); // processed|pending|failed
        return [
            'gateway_ref' => $refundId,
            'status'      => $status === 'processed' ? 'succeeded' : ($status === 'failed' ? 'failed' : 'pending'),
        ];
    }

    /**
     * Scan payment_attempts for the succeeded charge on this invoice and
     * pull Razorpay's payment_id out of its raw_response snapshot.
     */
    protected function lookupPaymentId(Invoice $invoice): ?string
    {
        $rows = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'razorpay')
            ->where('status', 'succeeded')
            ->orderByDesc('id')
            ->get();
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            $id  = $raw['razorpay_payment_id']
                ?? ($raw['payload']['payment']['entity']['id'] ?? null);
            if (is_string($id) && $id !== '') return $id;
        }
        return null;
    }

    // ------------------------------------------------------------------
    //  Recurring
    // ------------------------------------------------------------------

    /**
     * Razorpay auto-charges subscriptions on its own schedule — we don't
     * pull-charge from cron. The renew-due command calls us anyway; we
     * return a sentinel that the lifecycle treats as "not our turn,
     * gateway will notify via webhook". No invoice is created here.
     */
    public function chargeRecurring(Subscription $subscription): array
    {
        return [
            'kind'       => 'pending_gateway',
            'invoice_id' => null,
        ];
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    protected function detectIntent(array $items): array
    {
        foreach ($items as $li) {
            $meta = $li['meta'] ?? [];
            $kind = $meta['kind'] ?? null;
            if (in_array($kind, ['plan', 'plan_renewal', 'plan_upgrade'], true)) {
                return [
                    'kind'    => (string) $kind,
                    'plan_id' => (int) ($meta['plan_id'] ?? 0),
                    'cycle'   => (string) ($meta['cycle'] ?? 'monthly'),
                ];
            }
        }
        return ['kind' => 'plan_upgrade', 'plan_id' => 0, 'cycle' => 'monthly'];
    }

    protected function assertOk(Response $res, string $op): void
    {
        if ($res->successful()) return;
        $body = $res->json() ?: ['body' => $res->body()];
        Log::warning("Razorpay {$op} failed", ['status' => $res->status(), 'body' => $body]);
        $msg = $body['error']['description']
            ?? $body['error']['message']
            ?? ('Razorpay API error (HTTP ' . $res->status() . ')');
        throw new \RuntimeException("Razorpay {$op} failed: {$msg}");
    }
}
