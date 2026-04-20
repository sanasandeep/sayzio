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
 * Cashfree gateway adapter (Indian alternative to Razorpay).
 *
 * Strategy mirrors the other adapters:
 *   - One-time payments → Cashfree PG Orders API (POST /orders).
 *     Cashfree returns a `payment_session_id`; we hand it to Cashfree's
 *     Drop-in JS SDK on our cashfree.blade.php so the buyer can pay on
 *     our domain.
 *   - Subscription-first checkouts → Cashfree PG Subscriptions API.
 *     We ensure a Plan (cached in credentials) and create a subscription
 *     with e-mandate auth; the user authorises once, then Cashfree
 *     auto-charges per cycle.
 *   - Tax is surfaced as a separate Plan level amount via the plan's
 *     `plan_tax` field (Cashfree-supported) so we never let their tax
 *     engine recalc — mirrors the Stripe two-Price pattern.
 *   - Renewals arrive via SUBSCRIPTION_PAYMENT_SUCCESS webhook; we
 *     materialise a renewal invoice and return payment.succeeded.
 *   - Refunds → POST /orders/{order_id}/refunds.
 *
 * Webhook signature scheme (API version 2023-08-01):
 *   header `x-webhook-signature` = Base64(HMAC-SHA256(
 *     x-webhook-timestamp . rawBody, clientSecret
 *   )). We verify by recomputing with a 5-minute timestamp tolerance.
 *
 * CONTRACT DEVIATION (intentional, same posture as Stripe/Razorpay):
 *   parseEvent() performs guarded DB side effects for renewal, refund,
 *   and cancellation branches. Every write is guarded by
 *   eventAlreadyProcessed() against the payment_attempts unique index.
 *
 * Sandbox vs live is derived from cred('mode','live').
 */
class CashfreeAdapter extends AbstractAdapter
{
    protected const API_VERSION = '2023-08-01';
    protected const SIG_TOLERANCE_SECONDS = 300;

    public function slug(): string { return 'cashfree'; }
    public function displayName(): string { return 'Cashfree'; }

    /**
     * Resolve the effective gateway mode.
     *
     * The admin UI persists `gateway_settings.mode` as 'test' or 'live'
     * (single column, NOT inside the encrypted credentials blob).
     * Older tests/seeds may still stash `mode` inside credentials; we
     * accept either. 'test' maps to Cashfree's `sandbox` host bucket.
     */
    protected function effectiveMode(): string
    {
        $col = $this->settings?->mode;
        $cr  = (string) $this->cred('mode', '');
        $raw = (string) ($col ?: $cr ?: 'live');
        return $raw === 'test' ? 'sandbox' : $raw;
    }

    /**
     * Read a credential with fallbacks — the admin settings UI uses
     * Cashfree's merchant-dashboard naming (`app_id`, `secret_key`),
     * while this adapter was written against the API's own naming
     * (`client_id`, `client_secret`). We accept either so admins
     * configuring through the UI and tests/seeds using API names both
     * work.
     */
    protected function credWithAlias(array $keys, string $default = ''): string
    {
        foreach ($keys as $k) {
            $v = (string) $this->cred($k, '');
            if ($v !== '') return $v;
        }
        return $default;
    }

    protected function apiBase(): string
    {
        return $this->effectiveMode() === 'sandbox'
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    protected function httpPg()
    {
        $id     = $this->credWithAlias(['app_id', 'client_id']);
        $secret = $this->credWithAlias(['secret_key', 'client_secret']);
        if ($id === '' || $secret === '') {
            throw new NotImplementedException('Cashfree credentials are not configured.');
        }
        return Http::withHeaders([
            'x-client-id'     => $id,
            'x-client-secret' => $secret,
            'x-api-version'   => self::API_VERSION,
            'accept'          => 'application/json',
            'content-type'    => 'application/json',
        ]);
    }

    // ------------------------------------------------------------------
    //  Checkout handoff
    // ------------------------------------------------------------------

    public function createCheckout(Invoice $invoice): array
    {
        // Force creds check up-front so an unconfigured adapter throws
        // NotImplementedException (not a generic HTTP error).
        $this->httpPg();

        $items  = is_array($invoice->line_items) ? $invoice->line_items : [];
        $intent = $this->detectIntent($items);

        $handoff = ($intent['kind'] === 'plan')
            ? $this->createSubscriptionHandoff($invoice, $intent)
            : $this->createOrderHandoff($invoice);

        PaymentAttempt::updateOrCreate(
            ['gateway' => 'cashfree', 'gateway_ref' => $handoff['attempt_ref']],
            [
                'invoice_id'   => $invoice->id,
                'status'       => 'initiated',
                'raw_response' => [
                    'kind'     => $handoff['kind'],
                    'ref_id'   => $handoff['ref_id'],
                    'session'  => $handoff['session_id'] ?? null,
                    'amount'   => (int) $invoice->grand_total_minor,
                    'currency' => (string) $invoice->currency,
                ],
            ],
        );

        $invoice->forceFill(['gateway' => 'cashfree'])->save();

        // Subscription handoff where Cashfree returned ONLY an
        // authorisation link (no session id): redirect the user to
        // the hosted e-mandate page. The drop-in SDK can't consume a
        // URL; forcing it through the view would silently hang.
        if (($handoff['kind'] ?? null) === 'subscription' && empty($handoff['session_id']) && !empty($handoff['auth_link'])) {
            return [
                'kind' => 'redirect',
                'url'  => (string) $handoff['auth_link'],
            ];
        }

        return [
            'kind' => 'view',
            'view' => 'user.checkout.cashfree',
            'data' => [
                'invoice'            => $invoice,
                'payment_session_id' => $handoff['session_id'] ?? null,
                'order_id'           => $handoff['kind'] === 'order' ? $handoff['ref_id'] : null,
                'subscription_id'    => $handoff['kind'] === 'subscription' ? $handoff['ref_id'] : null,
                'mode'               => $this->effectiveMode(),
                'currency'           => strtoupper((string) $invoice->currency),
                'amount_minor'       => (int) $invoice->grand_total_minor,
                'description'        => 'Invoice ' . $invoice->number,
            ],
        ];
    }

    /** @return array{kind:string,ref_id:string,session_id:?string,attempt_ref:string} */
    protected function createOrderHandoff(Invoice $invoice): array
    {
        $orderId = 'inv_' . $invoice->id . '_' . substr(md5($invoice->number . microtime()), 0, 10);
        $amount  = (int) $invoice->grand_total_minor;
        $body = [
            'order_id'       => $orderId,
            'order_amount'   => (float) number_format($amount / 100, 2, '.', ''),
            'order_currency' => strtoupper((string) $invoice->currency),
            'customer_details' => [
                'customer_id'    => 'u_' . (int) $invoice->user_id,
                'customer_email' => (string) ($invoice->user?->email ?? 'buyer@example.com'),
                'customer_phone' => (string) ($invoice->user?->phone ?? '9999999999'),
                'customer_name'  => (string) ($invoice->user?->name ?? 'Buyer'),
            ],
            'order_meta' => [
                'return_url' => url('/user/billing?paid=' . $invoice->number),
                'notify_url' => url('/webhooks/cashfree'),
            ],
            'order_tags' => [
                'invoice_id'     => (string) $invoice->id,
                'invoice_number' => (string) $invoice->number,
            ],
            'order_note' => 'Invoice ' . $invoice->number,
        ];

        $res = $this->httpPg()->post($this->apiBase() . '/orders', $body);
        $this->assertOk($res, 'create order', $invoice);

        return [
            'kind'        => 'order',
            'ref_id'      => (string) $res->json('order_id'),
            'session_id'  => (string) $res->json('payment_session_id'),
            'attempt_ref' => 'order:' . (string) $res->json('order_id'),
        ];
    }

    /** @return array{kind:string,ref_id:string,session_id:?string,attempt_ref:string} */
    protected function createSubscriptionHandoff(Invoice $invoice, array $intent): array
    {
        $plan  = Plan::findOrFail($intent['plan_id']);
        $cycle = $intent['cycle'];
        $currency = strtoupper((string) $invoice->currency);

        // Task constraint: base and tax as separate plan amounts (not
        // folded together). Cashfree Subscription plans accept a
        // `plan_amount` and an explicit `plan_tax` field so tax is
        // surfaced on Cashfree's side without engaging their tax engine.
        $baseMinor = max(0, (int) $invoice->subtotal_minor);
        $taxMinor  = max(0, (int) $invoice->tax_total_minor);
        if ($baseMinor === 0) {
            $baseMinor = max(0, (int) $invoice->grand_total_minor - $taxMinor);
        }

        $cfPlanId = $this->ensurePlan($plan, $cycle, $currency, $baseMinor, $taxMinor);

        $subId = 'sub_' . $invoice->id . '_' . substr(md5($invoice->number . microtime()), 0, 8);
        $body = [
            'subscription_id'       => $subId,
            'plan_id'               => $cfPlanId,
            'customer_details' => [
                'customer_id'    => 'u_' . (int) $invoice->user_id,
                'customer_email' => (string) ($invoice->user?->email ?? 'buyer@example.com'),
                'customer_phone' => (string) ($invoice->user?->phone ?? '9999999999'),
                'customer_name'  => (string) ($invoice->user?->name ?? 'Buyer'),
            ],
            'subscription_meta' => [
                'return_url' => url('/user/billing?paid=' . $invoice->number),
                'notify_url' => url('/webhooks/cashfree'),
            ],
            'subscription_note' => 'Invoice ' . $invoice->number,
            'subscription_tags' => [
                'invoice_id'     => (string) $invoice->id,
                'invoice_number' => (string) $invoice->number,
                'internal_plan'  => (string) $plan->id,
                'cycle'          => $cycle,
            ],
        ];

        $res = $this->httpPg()->post($this->apiBase() . '/subscriptions', $body);
        $this->assertOk($res, 'create subscription', $invoice);

        $sessionId = (string) $res->json('subscription_session_id');
        $authLink  = (string) ($res->json('authorization_details.authorization_link') ?: '');
        // Cashfree returns EITHER an opaque session id (consumed by the
        // drop-in SDK on our domain) OR an authorisation_link (hosted
        // URL the user must visit to sign the e-mandate). The two MUST
        // NOT be conflated — passing a URL as paymentSessionId to the
        // drop-in SDK silently fails. Prefer the session id; fall back
        // to a hosted redirect when only an authorisation link exists.
        return [
            'kind'         => 'subscription',
            'ref_id'       => (string) $res->json('subscription_id'),
            'session_id'   => $sessionId !== '' ? $sessionId : null,
            'auth_link'    => $sessionId === '' ? $authLink : null,
            'attempt_ref'  => 'subscription:' . (string) $res->json('subscription_id'),
        ];
    }

    protected function ensurePlan(Plan $plan, string $cycle, string $currency, int $baseMinor, int $taxMinor): string
    {
        $cacheKey = sprintf('cf_plan:%d:%s:%s:%d:%d', $plan->id, $cycle, $currency, $baseMinor, $taxMinor);
        $cached = $this->cred($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $interval = $cycle === 'annual' ? 'yearly' : 'monthly';
        $planId   = 'pln_' . $plan->id . '_' . $cycle . '_' . substr(md5($cacheKey), 0, 8);
        $body = [
            'plan_id'       => $planId,
            'plan_name'     => $plan->name . ' (' . $cycle . ')',
            'plan_type'     => 'PERIODIC',
            'plan_currency' => $currency,
            'plan_recurring_amount' => (float) number_format($baseMinor / 100, 2, '.', ''),
            'plan_max_amount'       => (float) number_format(($baseMinor + $taxMinor) / 100, 2, '.', ''),
            'plan_interval_type'    => $interval,
            'plan_intervals'        => 1,
            'plan_note'             => 'Internal plan ' . $plan->id,
        ];
        if ($taxMinor > 0) {
            // Cashfree supports `plan_tax` on recurring plans. Separate
            // line on the buyer's subscription statement — not folded
            // into base amount.
            $body['plan_tax'] = (float) number_format($taxMinor / 100, 2, '.', '');
        }

        $res = $this->httpPg()->post($this->apiBase() . '/plans', $body);
        $this->assertOk($res, 'create plan', null);
        $id = (string) ($res->json('plan_id') ?: $planId);
        $this->cacheCred($cacheKey, $id);
        return $id;
    }

    protected function cacheCred(string $key, string $value): void
    {
        if (!$this->settings) return;
        $creds = $this->settings->credentials();
        $creds[$key] = $value;
        $this->settings->forceFill(['credentials_encrypted' => $creds])->save();
    }

    // ------------------------------------------------------------------
    //  Webhook signature + event parsing
    // ------------------------------------------------------------------

    public function verifyWebhook(Request $request): bool
    {
        // Admin UI exposes a dedicated `webhook_secret` field; if left
        // blank, Cashfree's PG v2 spec permits using the merchant
        // Secret Key (a.k.a. `client_secret` in the API) for HMAC.
        $secret    = $this->credWithAlias(['webhook_secret', 'secret_key', 'client_secret']);
        $sig       = (string) $request->header('x-webhook-signature', '');
        $timestamp = (string) $request->header('x-webhook-timestamp', '');
        if ($secret === '' || $sig === '' || $timestamp === '') return false;

        $ts = (int) $timestamp;
        if ($ts <= 0) return false;
        // Cashfree timestamps are in milliseconds. Accept a 5 min window.
        $tsSec = $ts > 1e12 ? (int) floor($ts / 1000) : $ts;
        if (abs(time() - $tsSec) > self::SIG_TOLERANCE_SECONDS) return false;

        $signed   = $timestamp . $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $signed, $secret, true));
        return hash_equals($expected, $sig);
    }

    public function parseEvent(Request $request): array
    {
        $payload = $request->json()->all();
        $eventId = (string) ($payload['data']['event_id'] ?? $payload['event_id'] ?? ($payload['data']['payment']['cf_payment_id'] ?? ''));
        $type    = (string) ($payload['type'] ?? $payload['event'] ?? '');
        $raw     = $payload;
        $data    = $payload['data'] ?? [];

        // ---------- PAYMENT_SUCCESS_WEBHOOK / PAYMENT_FAILED_WEBHOOK ----------
        if ($type === 'PAYMENT_SUCCESS_WEBHOOK' || $type === 'PAYMENT_FAILED_WEBHOOK') {
            $order   = $data['order'] ?? [];
            $payment = $data['payment'] ?? [];
            $tags    = $data['order_tags'] ?? ($order['order_tags'] ?? []);
            $invoiceId = (int) ($tags['invoice_id'] ?? 0);
            $amount    = (int) round(((float) ($order['order_amount'] ?? ($payment['payment_amount'] ?? 0))) * 100);
            $currency  = strtoupper((string) ($order['order_currency'] ?? ''));
            $cfPayId   = (string) ($payment['cf_payment_id'] ?? '');
            return [
                'type'         => $type === 'PAYMENT_SUCCESS_WEBHOOK' ? 'payment.succeeded' : 'payment.failed',
                'invoice_id'   => $invoiceId ?: null,
                'gateway_ref'  => $eventId ?: $cfPayId,
                'amount_minor' => $amount,
                'currency'     => $currency,
                'raw'          => $raw + ['cashfree_payment_id' => $cfPayId,
                                          'cashfree_order_id'   => (string) ($order['order_id'] ?? '')],
            ];
        }

        // ---------- SUBSCRIPTION_PAYMENT_SUCCESS (first cycle or renewal) ----------
        if ($type === 'SUBSCRIPTION_PAYMENT_SUCCESS') {
            $sub     = $data['subscription'] ?? [];
            $payment = $data['payment'] ?? [];
            $cfSubId = (string) ($sub['subscription_id'] ?? '');
            $amount  = (int) round(((float) ($payment['payment_amount'] ?? 0)) * 100);
            $currency = strtoupper((string) ($payment['payment_currency'] ?? $sub['plan_currency'] ?? ''));
            if ($this->eventAlreadyProcessed($eventId)) {
                return [
                    'type'         => 'payment.requires_review',
                    'invoice_id'   => null,
                    'gateway_ref'  => $eventId,
                    'amount_minor' => $amount,
                    'currency'     => $currency,
                    'raw'          => $raw,
                ];
            }

            // First-cycle mapping: Cashfree echoes our
            // subscription_tags on the subscription webhook payload.
            // If the pending invoice recorded at handoff is still
            // awaiting payment, activate THAT invoice instead of
            // materialising a renewal. Without this branch, the very
            // first cycle of every subscription lands as
            // requires_review and the buyer's pending invoice stays
            // unpaid while they see a successful charge on Cashfree.
            $tags = $sub['subscription_tags'] ?? ($data['subscription_tags'] ?? []);
            $tagInvoiceId = (int) ($tags['invoice_id'] ?? 0);
            if ($tagInvoiceId > 0) {
                $pending = Invoice::where('id', $tagInvoiceId)
                    ->where('gateway', 'cashfree')
                    ->whereIn('status', ['pending', 'processing'])
                    ->first();
                if ($pending) {
                    return [
                        'type'         => 'payment.succeeded',
                        'invoice_id'   => (int) $pending->id,
                        'gateway_ref'  => $eventId,
                        'amount_minor' => $amount,
                        'currency'     => $currency,
                        'raw'          => $raw + ['cashfree_subscription_id' => $cfSubId],
                    ];
                }
            }

            $invId = $this->resolveRenewalInvoiceId($cfSubId, $amount, $currency);
            return [
                'type'         => $invId ? 'payment.succeeded' : 'payment.requires_review',
                'invoice_id'   => $invId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $amount,
                'currency'     => $currency,
                'raw'          => $raw + ['cashfree_subscription_id' => $cfSubId],
            ];
        }

        // ---------- SUBSCRIPTION_PAYMENT_FAILED ----------
        if ($type === 'SUBSCRIPTION_PAYMENT_FAILED') {
            $sub = $data['subscription'] ?? [];
            $cfSubId = (string) ($sub['subscription_id'] ?? '');
            $match = $cfSubId ? Subscription::where('gateway', 'cashfree')
                ->where('gateway_subscription_id', $cfSubId)->first() : null;
            $latestInvoiceId = $match
                ? (int) (Invoice::where('subscription_id', $match->id)->orderByDesc('id')->value('id') ?? 0)
                : 0;
            return [
                'type'         => 'payment.failed',
                'invoice_id'   => $latestInvoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => null,
                'currency'     => null,
                'raw'          => $raw,
            ];
        }

        // ---------- REFUND_STATUS_WEBHOOK ----------
        if ($type === 'REFUND_STATUS_WEBHOOK') {
            $refund   = $data['refund'] ?? [];
            $refundId = (string) ($refund['refund_id'] ?? ($refund['cf_refund_id'] ?? ''));
            $status   = strtoupper((string) ($refund['refund_status'] ?? ''));
            $amount   = (int) round(((float) ($refund['refund_amount'] ?? 0)) * 100);

            $row = $refundId ? Refund::where('gateway', 'cashfree')
                ->where('gateway_ref', $refundId)->first() : null;
            if ($row && !$this->eventAlreadyProcessed($eventId)) {
                if ($status === 'SUCCESS') {
                    try {
                        app(\App\Services\Billing\RefundService::class)
                            ->handleGatewaySuccess($row, $refundId);
                    } catch (\Throwable $e) {
                        Log::warning('Cashfree refund gateway-success finalisation failed', [
                            'refund_id' => $row->id, 'error' => $e->getMessage(),
                        ]);
                    }
                } elseif ($status === 'FAILED' || $status === 'CANCELLED') {
                    $row->forceFill([
                        'status'       => 'failed',
                        'processed_at' => $row->processed_at ?: now(),
                    ])->save();
                }
            }
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => $row ? (int) $row->invoice_id : null,
                'gateway_ref'  => $eventId ?: ('refund:' . $refundId),
                'amount_minor' => $amount,
                'currency'     => strtoupper((string) ($refund['refund_currency'] ?? '')),
                'raw'          => $raw,
            ];
        }

        // ---------- SUBSCRIPTION_CANCELLED ----------
        if ($type === 'SUBSCRIPTION_CANCELLED') {
            $sub = $data['subscription'] ?? [];
            $cfSubId = (string) ($sub['subscription_id'] ?? '');
            $match = $cfSubId ? Subscription::where('gateway', 'cashfree')
                ->where('gateway_subscription_id', $cfSubId)->first() : null;
            if ($match && !$this->eventAlreadyProcessed($eventId)) {
                if ($match->status !== 'cancelled') {
                    $match->forceFill(['status' => 'cancelled', 'cancel_at' => now()])->save();
                }
            }
            $latestInvoiceId = $match
                ? (int) (Invoice::where('subscription_id', $match->id)->orderByDesc('id')->value('id') ?? 0)
                : 0;
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => $latestInvoiceId ?: null,
                'gateway_ref'  => $eventId ?: ('sub-cancelled:' . $cfSubId),
                'amount_minor' => null,
                'currency'     => null,
                'raw'          => $raw,
            ];
        }

        // Unknown event → ack.
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
        return PaymentAttempt::where('gateway', 'cashfree')
            ->where('gateway_ref', $eventId)->exists();
    }

    protected function resolveRenewalInvoiceId(string $cfSubId, int $amountMinor, string $currency): int
    {
        if ($cfSubId === '') return 0;
        $sub = Subscription::where('gateway', 'cashfree')
            ->where('gateway_subscription_id', $cfSubId)->first();
        if (!$sub) return 0;

        $plan  = $sub->plan;
        $cycle = $sub->billing_cycle;
        $items = [[
            'label'        => ($plan?->name ?? 'Plan') . ' (' . $cycle . ' renewal)',
            'amount_minor' => $amountMinor,
            'quantity'     => 1,
            'meta' => [
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
            'gateway'         => 'cashfree',
            'subscription_id' => $sub->id,
        ])->save();
        return (int) $invoice->id;
    }

    // ------------------------------------------------------------------
    //  Refund
    // ------------------------------------------------------------------

    public function refund(Invoice $invoice, int $amountMinor, string $reason = ''): array
    {
        $orderId = $this->lookupOrderId($invoice);
        if (!$orderId) {
            throw new \RuntimeException('No Cashfree order id found for invoice ' . $invoice->number);
        }

        $refundId = 'ref_' . $invoice->id . '_' . substr(md5($invoice->number . microtime()), 0, 8);
        $res = $this->httpPg()->post($this->apiBase() . '/orders/' . $orderId . '/refunds', [
            'refund_id'     => $refundId,
            'refund_amount' => (float) number_format($amountMinor / 100, 2, '.', ''),
            'refund_note'   => substr($reason, 0, 100),
            'refund_speed'  => 'STANDARD',
        ]);
        $this->assertOk($res, 'create refund', $invoice);

        $cfRefundId = (string) ($res->json('refund_id') ?: $refundId);
        $status = strtoupper((string) $res->json('refund_status'));
        return [
            'gateway_ref' => $cfRefundId,
            'status'      => $status === 'SUCCESS' ? 'succeeded'
                            : ($status === 'FAILED' || $status === 'CANCELLED' ? 'failed' : 'pending'),
        ];
    }

    protected function lookupOrderId(Invoice $invoice): ?string
    {
        $rows = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'cashfree')
            ->whereIn('status', ['succeeded', 'initiated'])
            ->orderByDesc('id')
            ->get();
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            if (($raw['kind'] ?? null) === 'order' && !empty($raw['ref_id'])) {
                return (string) $raw['ref_id'];
            }
            $fromWebhook = $raw['cashfree_order_id'] ?? null;
            if (is_string($fromWebhook) && $fromWebhook !== '') return $fromWebhook;
        }
        return null;
    }

    // ------------------------------------------------------------------
    //  Recurring
    // ------------------------------------------------------------------

    public function chargeRecurring(Subscription $subscription): array
    {
        return ['kind' => 'pending_gateway', 'invoice_id' => null];
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
        Log::warning("Cashfree {$op} failed", ['status' => $res->status(), 'body' => $body]);

        if ($invoice) {
            try {
                PaymentAttempt::create([
                    'invoice_id'  => $invoice->id,
                    'gateway'     => 'cashfree',
                    'gateway_ref' => 'failed:' . $op . ':' . substr(md5(json_encode($body) . microtime()), 0, 16),
                    'status'      => 'failed',
                    'raw_response' => [
                        'op'     => $op,
                        'status' => $res->status(),
                        'body'   => $body,
                    ],
                ]);
            } catch (\Throwable $ignore) {}
        }

        $msg = $body['message'] ?? $body['error_description'] ?? ('Cashfree API error (HTTP ' . $res->status() . ')');
        throw new \RuntimeException("Cashfree {$op} failed: {$msg}");
    }
}
