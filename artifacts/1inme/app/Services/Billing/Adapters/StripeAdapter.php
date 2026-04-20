<?php

namespace App\Services\Billing\Adapters;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Refund;
use App\Modules\User\Models\Subscription;
use App\Services\Billing\NotImplementedException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe gateway adapter.
 *
 * Strategy:
 *   - One-time payments (plan_upgrade proration, addon-only, renewal
 *     invoices we issue ourselves) → Stripe Checkout Session with
 *     mode=payment. Each internal line item becomes a `line_items[]`
 *     entry using inline `price_data` (Stripe auto-creates the Price).
 *     Our GST/VAT total is passed as its own line item — we do NOT
 *     enable Stripe Tax (the tax task owns that math).
 *   - Subscription-first checkouts (intent=plan) → Stripe Checkout
 *     Session with mode=subscription. We ensure a Stripe Price object
 *     exists for (plan, cycle, amount, currency) by ensureStripePrice()
 *     and cache the Price id in the gateway_settings credentials blob
 *     so repeat checkouts don't spam the Stripe dashboard.
 *   - Renewals are triggered by Stripe's scheduler. When `invoice.paid`
 *     arrives we materialise a renewal invoice (guarded by idempotency)
 *     and let the existing activation pipeline extend the period.
 *   - Refunds → POST /v1/refunds with payment_intent. The
 *     payment_intent id is stashed on the succeeded PaymentAttempt.
 *
 * Webhook idempotency is keyed on Stripe's `event.id` via the
 * payment_attempts (gateway, gateway_ref) unique index. Signature
 * verification is Stripe's documented scheme: header
 * `Stripe-Signature: t=TS,v1=SIG` where SIG =
 * HMAC-SHA256(secret, TS . '.' . payload) with a 5-minute tolerance.
 *
 * CONTRACT DEVIATION (intentional, gated by task constraints):
 *   The GatewayAdapter contract says parseEvent() should be a pure
 *   translation (DB-free). This adapter performs guarded DB writes in
 *   parseEvent() for charge.refunded, invoice.paid (renewals), and
 *   customer.subscription.deleted branches because the locked
 *   WebhookController router only branches on payment.succeeded /
 *   payment.failed / requires_review — there is no other hook to run
 *   these side effects. Every write is short-circuited by
 *   eventAlreadyProcessed() which checks the payment_attempts unique
 *   index, giving us single-delivery idempotency. The narrow race
 *   window on truly-parallel duplicate deliveries is the same as
 *   documented in RazorpayAdapter.
 *
 * Test/live modes share the same base URL; only the secret key differs.
 */
class StripeAdapter extends AbstractAdapter
{
    protected const API = 'https://api.stripe.com/v1';
    /** Stripe's documented replay tolerance. */
    protected const SIG_TOLERANCE_SECONDS = 300;

    public function slug(): string { return 'stripe'; }
    public function displayName(): string { return 'Stripe'; }

    // ------------------------------------------------------------------
    //  Checkout handoff
    // ------------------------------------------------------------------

    public function createCheckout(Invoice $invoice): array
    {
        $secretKey = (string) $this->cred('secret_key', '');
        if ($secretKey === '') {
            throw new NotImplementedException('Stripe credentials are not configured.');
        }

        $items    = is_array($invoice->line_items) ? $invoice->line_items : [];
        $intent   = $this->detectIntent($items);
        $currency = strtolower((string) $invoice->currency);

        $session = ($intent['kind'] === 'plan')
            ? $this->createSubscriptionSession($invoice, $intent, $secretKey, $currency)
            : $this->createPaymentSession($invoice, $secretKey, $currency);

        // Audit trail: one initiated PaymentAttempt per (invoice, session).
        // Deterministic gateway_ref so page refresh doesn't break the
        // unique index.
        PaymentAttempt::updateOrCreate(
            ['gateway' => 'stripe', 'gateway_ref' => 'session:' . $session['id']],
            [
                'invoice_id'  => $invoice->id,
                'status'      => 'initiated',
                'raw_response' => [
                    'kind'       => $session['mode'],
                    'session_id' => $session['id'],
                    'amount'     => (int) $invoice->grand_total_minor,
                    'currency'   => $currency,
                ],
            ],
        );

        $invoice->forceFill(['gateway' => 'stripe'])->save();

        // Stripe Checkout is hosted — just redirect the buyer.
        return [
            'kind' => 'redirect',
            'url'  => (string) $session['url'],
        ];
    }

    protected function createPaymentSession(Invoice $invoice, string $secretKey, string $currency): array
    {
        $items = is_array($invoice->line_items) ? $invoice->line_items : [];

        // Build line_items as form-encoded array. Stripe wants each
        // line's price_data inline. The tax total (GST/VAT calculated
        // by our tax engine) is passed as its OWN line item — we
        // deliberately do NOT use Stripe Tax.
        $form = [
            'mode'                 => 'payment',
            'success_url'          => url('/user/billing?paid=' . $invoice->number),
            'cancel_url'           => url('/user/billing?cancelled=' . $invoice->number),
            'client_reference_id'  => (string) $invoice->id,
            'metadata[invoice_id]' => (string) $invoice->id,
            'metadata[invoice_number]' => (string) $invoice->number,
            'payment_intent_data[metadata][invoice_id]' => (string) $invoice->id,
        ];
        if ($invoice->user?->email) $form['customer_email'] = $invoice->user->email;

        $idx = 0;
        foreach ($items as $li) {
            $form["line_items[{$idx}][price_data][currency]"]         = $currency;
            $form["line_items[{$idx}][price_data][unit_amount]"]       = (int) ($li['amount_minor'] ?? 0);
            $form["line_items[{$idx}][price_data][product_data][name]"] = (string) ($li['label'] ?? 'Charge');
            $form["line_items[{$idx}][quantity]"]                      = (int) ($li['quantity'] ?? 1);
            $idx++;
        }
        if ((int) $invoice->tax_total_minor > 0) {
            $form["line_items[{$idx}][price_data][currency]"]           = $currency;
            $form["line_items[{$idx}][price_data][unit_amount]"]         = (int) $invoice->tax_total_minor;
            $form["line_items[{$idx}][price_data][product_data][name]"] = 'Tax (GST/VAT)';
            $form["line_items[{$idx}][quantity]"]                        = 1;
        }

        $res = Http::withToken($secretKey)->asForm()->post(self::API . '/checkout/sessions', $form);
        $this->assertOk($res, 'create payment session', $invoice);

        return [
            'id'   => (string) $res->json('id'),
            'url'  => (string) $res->json('url'),
            'mode' => 'payment',
        ];
    }

    protected function createSubscriptionSession(Invoice $invoice, array $intent, string $secretKey, string $currency): array
    {
        $plan  = Plan::findOrFail($intent['plan_id']);
        $cycle = $intent['cycle'];

        // Charge the INVOICE's grand total (tax-inclusive, addons
        // included) so the captured amount matches what the user
        // actually owes. Stripe Subscriptions charge the Price amount
        // per cycle, so pricing the Stripe Price at the invoice total
        // keeps renewals consistent. Each tax-variant customer gets
        // its own Price (cache key includes amount + currency).
        $amountMinor = (int) $invoice->grand_total_minor;

        $priceId = $this->ensureStripePrice($plan, $cycle, $currency, $amountMinor, $secretKey, $invoice);

        $form = [
            'mode'                 => 'subscription',
            'success_url'          => url('/user/billing?paid=' . $invoice->number),
            'cancel_url'           => url('/user/billing?cancelled=' . $invoice->number),
            'client_reference_id'  => (string) $invoice->id,
            'metadata[invoice_id]' => (string) $invoice->id,
            'metadata[invoice_number]' => (string) $invoice->number,
            'line_items[0][price]'     => $priceId,
            'line_items[0][quantity]'  => 1,
            'subscription_data[metadata][invoice_id]'     => (string) $invoice->id,
            'subscription_data[metadata][internal_plan]'  => (string) $plan->id,
            'subscription_data[metadata][cycle]'          => $cycle,
        ];
        if ($invoice->user?->email) $form['customer_email'] = $invoice->user->email;

        $res = Http::withToken($secretKey)->asForm()->post(self::API . '/checkout/sessions', $form);
        $this->assertOk($res, 'create subscription session', $invoice);

        return [
            'id'   => (string) $res->json('id'),
            'url'  => (string) $res->json('url'),
            'mode' => 'subscription',
        ];
    }

    /**
     * Reuse or create a Stripe Price for (internal plan, cycle, amount,
     * currency). Stripe Prices are effectively immutable; we cache the
     * id in the gateway_settings credentials blob so repeat checkouts
     * don't spam the dashboard.
     */
    protected function ensureStripePrice(Plan $plan, string $cycle, string $currency, int $amountMinor, string $secretKey, ?Invoice $invoice = null): string
    {
        $cacheKey = sprintf('stripe_price:%d:%s:%s:%d', $plan->id, $cycle, $currency, $amountMinor);
        $cached   = $this->cred($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $interval = $cycle === 'annual' ? 'year' : 'month';
        $res = Http::withToken($secretKey)->asForm()->post(self::API . '/prices', [
            'currency'              => $currency,
            'unit_amount'           => $amountMinor,
            'recurring[interval]'   => $interval,
            'product_data[name]'    => $plan->name . ' (' . $cycle . ')',
            'metadata[internal_plan_id]' => (string) $plan->id,
            'metadata[cycle]'       => $cycle,
        ]);
        $this->assertOk($res, 'create price', $invoice);
        $priceId = (string) $res->json('id');

        if ($this->settings) {
            $creds = $this->settings->credentials();
            $creds[$cacheKey] = $priceId;
            $this->settings->forceFill(['credentials_encrypted' => $creds])->save();
        }
        return $priceId;
    }

    // ------------------------------------------------------------------
    //  Webhook signature + event parsing
    // ------------------------------------------------------------------

    public function verifyWebhook(Request $request): bool
    {
        $secret = (string) $this->cred('webhook_secret', '');
        $header = (string) $request->header('Stripe-Signature', '');
        if ($secret === '' || $header === '') return false;

        // Header format: `t=TIMESTAMP,v1=SIG[,v1=SIG2...]`
        $timestamp = null;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            if ($kv[0] === 't') $timestamp = (int) $kv[1];
            elseif ($kv[0] === 'v1') $sigs[] = $kv[1];
        }
        if ($timestamp === null || $sigs === []) return false;
        if (abs(time() - $timestamp) > self::SIG_TOLERANCE_SECONDS) return false;

        $signed   = $timestamp . '.' . $request->getContent();
        $expected = hash_hmac('sha256', $signed, $secret);
        foreach ($sigs as $sig) {
            if (hash_equals($expected, $sig)) return true;
        }
        return false;
    }

    public function parseEvent(Request $request): array
    {
        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? '');
        $type    = (string) ($payload['type'] ?? '');
        $raw     = $payload;
        $object  = $payload['data']['object'] ?? [];

        // ---------- checkout.session.completed (one-time + first cycle) ----------
        if ($type === 'checkout.session.completed') {
            $meta       = $object['metadata'] ?? [];
            $invoiceId  = (int) ($meta['invoice_id'] ?? 0);
            $mode       = (string) ($object['mode'] ?? '');
            $rawExtras  = [
                'stripe_session_id'        => $object['id']                   ?? null,
                'stripe_payment_intent_id' => $object['payment_intent']       ?? null,
                'stripe_subscription_id'   => $object['subscription']         ?? null,
                'stripe_customer_id'       => $object['customer']             ?? null,
                'stripe_mode'              => $mode,
            ];
            return [
                // Checkout completed == paid. Router's ActivateSubscription
                // extends the period / marks the invoice paid.
                'type'         => $object['payment_status'] === 'unpaid'
                                    ? 'payment.requires_review'
                                    : 'payment.succeeded',
                'invoice_id'   => $invoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => (int) ($object['amount_total'] ?? 0),
                'currency'     => strtoupper((string) ($object['currency'] ?? '')),
                'raw'          => $raw + $rawExtras,
            ];
        }

        // ---------- invoice.paid (subscription renewal) ----------
        if ($type === 'invoice.paid') {
            // Two sub-cases:
            //   (a) Stripe's "invoice.paid" for the FIRST cycle fires
            //       after checkout.session.completed for the same
            //       charge; we skip it to avoid double activation.
            //       checkout.session.completed is our canonical first-
            //       cycle event.
            //   (b) Stripe's "invoice.paid" for RENEWAL cycles carries
            //       a subscription id. We materialise a renewal invoice
            //       (idempotency-guarded) and return payment.succeeded.
            $billingReason = (string) ($object['billing_reason'] ?? '');
            $stripeSubId   = (string) ($object['subscription'] ?? '');
            $amountPaid    = (int) ($object['amount_paid'] ?? 0);
            $currency      = strtoupper((string) ($object['currency'] ?? ''));

            if ($billingReason !== 'subscription_cycle' && $billingReason !== 'subscription') {
                // First-cycle invoice.paid — already handled by
                // checkout.session.completed. No-op.
                return [
                    'type'         => 'payment.requires_review',
                    'invoice_id'   => null,
                    'gateway_ref'  => $eventId,
                    'amount_minor' => $amountPaid,
                    'currency'     => $currency,
                    'raw'          => $raw,
                ];
            }
            if ($this->eventAlreadyProcessed($eventId)) {
                return [
                    'type'         => 'payment.requires_review',
                    'invoice_id'   => null,
                    'gateway_ref'  => $eventId,
                    'amount_minor' => $amountPaid,
                    'currency'     => $currency,
                    'raw'          => $raw,
                ];
            }
            $invId = $this->resolveRenewalInvoiceId($stripeSubId, $amountPaid, $currency);
            return [
                'type'         => $invId ? 'payment.succeeded' : 'payment.requires_review',
                'invoice_id'   => $invId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $amountPaid,
                'currency'     => $currency,
                'raw'          => $raw + [
                    'stripe_payment_intent_id' => $object['payment_intent'] ?? null,
                ],
            ];
        }

        // ---------- invoice.payment_failed ----------
        if ($type === 'invoice.payment_failed') {
            $stripeSubId = (string) ($object['subscription'] ?? '');
            $match = $stripeSubId ? Subscription::where('gateway', 'stripe')
                ->where('gateway_subscription_id', $stripeSubId)->first() : null;
            $latestInvoiceId = $match
                ? (int) (Invoice::where('subscription_id', $match->id)
                    ->orderByDesc('id')->value('id') ?? 0)
                : 0;
            return [
                'type'         => 'payment.failed',
                'invoice_id'   => $latestInvoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => (int) ($object['amount_due'] ?? 0),
                'currency'     => strtoupper((string) ($object['currency'] ?? '')),
                'raw'          => $raw,
            ];
        }

        // ---------- charge.refunded ----------
        if ($type === 'charge.refunded') {
            $refunds = $object['refunds']['data'] ?? [];
            // Stripe fires charge.refunded for EACH refund; find the
            // most recent one and match it to our Refund row either by
            // our own pre-recorded gateway_ref (rf_XXX) or by the
            // payment_intent (invoice.gateway_ref_fallback).
            $latest = end($refunds) ?: null;
            $stripeRefundId = is_array($latest) ? (string) ($latest['id'] ?? '') : '';
            $paymentIntent  = (string) ($object['payment_intent'] ?? '');

            $row = null;
            if ($stripeRefundId) {
                $row = Refund::where('gateway', 'stripe')
                    ->where('gateway_ref', $stripeRefundId)->first();
            }
            if (!$row && $paymentIntent) {
                // Stripe dashboard refunds come back without a row we
                // created. Locate the invoice via the PaymentAttempt
                // that recorded payment_intent, then its most recent
                // pending refund (if any).
                $inv = $this->invoiceForPaymentIntent($paymentIntent);
                if ($inv) {
                    $row = Refund::where('invoice_id', $inv->id)
                        ->where('status', 'pending')
                        ->orderByDesc('id')->first();
                }
            }

            if ($row && !$this->eventAlreadyProcessed($eventId)) {
                try {
                    app(\App\Services\Billing\RefundService::class)
                        ->handleGatewaySuccess($row, $stripeRefundId ?: ('stripe-refund:' . $paymentIntent));
                } catch (\Throwable $e) {
                    Log::warning('Stripe refund gateway-success finalisation failed', [
                        'refund_id' => $row->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => $row ? (int) $row->invoice_id : null,
                'gateway_ref'  => $eventId,
                'amount_minor' => (int) ($object['amount_refunded'] ?? 0),
                'currency'     => strtoupper((string) ($object['currency'] ?? '')),
                'raw'          => $raw,
            ];
        }

        // ---------- customer.subscription.deleted ----------
        if ($type === 'customer.subscription.deleted') {
            $stripeSubId = (string) ($object['id'] ?? '');
            $match = $stripeSubId ? Subscription::where('gateway', 'stripe')
                ->where('gateway_subscription_id', $stripeSubId)->first() : null;
            if ($match && !$this->eventAlreadyProcessed($eventId)) {
                if ($match->status !== 'cancelled') {
                    $match->forceFill([
                        'status'    => 'cancelled',
                        'cancel_at' => now(),
                    ])->save();
                }
            }
            $latestInvoiceId = $match
                ? (int) (Invoice::where('subscription_id', $match->id)
                    ->orderByDesc('id')->value('id') ?? 0)
                : 0;
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => $latestInvoiceId ?: null,
                'gateway_ref'  => $eventId,
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

    protected function eventAlreadyProcessed(string $eventId): bool
    {
        if ($eventId === '') return false;
        return PaymentAttempt::where('gateway', 'stripe')
            ->where('gateway_ref', $eventId)->exists();
    }

    /**
     * Materialise an internal renewal invoice for a Stripe subscription
     * that Stripe just auto-charged. Returns the new invoice id, or 0
     * if we can't locate the internal subscription (which means the
     * first-cycle listener hasn't stamped gateway_subscription_id yet
     * — caller will return requires_review).
     */
    protected function resolveRenewalInvoiceId(string $stripeSubId, int $amountMinor, string $currency): int
    {
        if ($stripeSubId === '') return 0;
        $sub = Subscription::where('gateway', 'stripe')
            ->where('gateway_subscription_id', $stripeSubId)->first();
        if (!$sub) return 0;

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
            'gateway'         => 'stripe',
            'subscription_id' => $sub->id,
        ])->save();
        return (int) $invoice->id;
    }

    /**
     * Reverse-lookup: given a Stripe payment_intent id, find the
     * invoice whose PaymentAttempt recorded that PI.
     */
    protected function invoiceForPaymentIntent(string $paymentIntent): ?Invoice
    {
        // DB-agnostic: fetch a bounded recent window and filter in PHP
        // so the lookup works on sqlite (tests), Postgres (prod), or
        // any other driver without relying on JSON/text cast syntax.
        $rows = PaymentAttempt::where('gateway', 'stripe')
            ->orderByDesc('id')->limit(200)->get(['id', 'invoice_id', 'raw_response']);
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            $pi  = $raw['stripe_payment_intent_id']
                ?? ($raw['data']['object']['payment_intent'] ?? null);
            if (is_string($pi) && $pi === $paymentIntent) {
                return Invoice::find($row->invoice_id);
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    //  Refund
    // ------------------------------------------------------------------

    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        $secretKey = (string) $this->cred('secret_key', '');
        if ($secretKey === '') {
            throw new NotImplementedException('Stripe credentials are not configured.');
        }

        $paymentIntent = $this->lookupPaymentIntent($invoice);
        if (!$paymentIntent) {
            throw new \RuntimeException('No Stripe payment_intent found for invoice ' . $invoice->number);
        }

        $res = Http::withToken($secretKey)->asForm()->post(self::API . '/refunds', [
            'payment_intent'         => $paymentIntent,
            'amount'                 => $amountMinor,
            'metadata[invoice_id]'   => (string) $invoice->id,
            'metadata[invoice_number]' => (string) $invoice->number,
            'metadata[reason]'       => $reason,
        ]);
        $this->assertOk($res, 'create refund', $invoice);

        $refundId = (string) $res->json('id');
        $status   = (string) $res->json('status'); // succeeded|pending|failed|canceled
        return [
            'gateway_ref' => $refundId,
            'status'      => $status === 'succeeded' ? 'succeeded'
                             : ($status === 'failed' || $status === 'canceled' ? 'failed' : 'pending'),
        ];
    }

    protected function lookupPaymentIntent(Invoice $invoice): ?string
    {
        $rows = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'stripe')
            ->whereIn('status', ['succeeded', 'initiated'])
            ->orderByDesc('id')
            ->get();
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            $id  = $raw['stripe_payment_intent_id']
                ?? ($raw['data']['object']['payment_intent'] ?? null);
            if (is_string($id) && $id !== '') return $id;
        }
        return null;
    }

    // ------------------------------------------------------------------
    //  Recurring
    // ------------------------------------------------------------------

    /**
     * Stripe auto-charges subscriptions on its own schedule. The
     * renew-due cron is a no-op for stripe-gateway subs; we react to
     * invoice.paid / invoice.payment_failed webhooks instead.
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

    protected function assertOk(Response $res, string $op, ?Invoice $invoice): void
    {
        if ($res->successful()) return;
        $body = $res->json() ?: ['body' => $res->body()];
        Log::warning("Stripe {$op} failed", ['status' => $res->status(), 'body' => $body]);

        if ($invoice) {
            try {
                PaymentAttempt::create([
                    'invoice_id'  => $invoice->id,
                    'gateway'     => 'stripe',
                    'gateway_ref' => 'failed:' . $op . ':' . substr(md5(json_encode($body) . microtime()), 0, 16),
                    'status'      => 'failed',
                    'raw_response' => [
                        'op'     => $op,
                        'status' => $res->status(),
                        'body'   => $body,
                    ],
                ]);
            } catch (\Throwable $ignore) {
                // Never mask the user-facing error with an attempt-logging failure.
            }
        }

        $msg = $body['error']['message']
            ?? ('Stripe API error (HTTP ' . $res->status() . ')');
        throw new \RuntimeException("Stripe {$op} failed: {$msg}");
    }
}
