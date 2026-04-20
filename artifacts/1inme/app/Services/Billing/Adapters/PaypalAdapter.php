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
 * PayPal gateway adapter.
 *
 * Mirrors StripeAdapter's strategy:
 *   - One-time payments → PayPal Orders v2 (`POST /v2/checkout/orders`,
 *     intent=CAPTURE). We hand a pre-created order_id to Smart Buttons
 *     on our own paypal.blade.php so the SDK can capture client-side
 *     after approval. custom_id on the purchase_unit carries our
 *     invoice id so the webhook round-trips it.
 *   - Subscription-first checkouts → PayPal Billing Subscriptions API.
 *     We ensure a catalog Product and a Plan exist on the PayPal side
 *     (cached in gateway_settings credentials, same pattern as Stripe
 *     Prices and Razorpay Plans) and then create a subscription the
 *     Smart Buttons SDK activates after approval.
 *   - Tax is passed as the plan's `taxes.percentage` (derived from our
 *     invoice's tax_total_minor / subtotal_minor). PayPal's Billing
 *     Plans API does not support separate line items the way Stripe
 *     Prices do — tax as a percentage is the idiomatic way to surface
 *     our pre-calculated GST/VAT on PayPal receipts without turning
 *     PayPal's own tax engine on.
 *   - Renewals are driven by PayPal. PAYMENT.SALE.COMPLETED fires on
 *     each subscription charge with `billing_agreement_id` = the
 *     PayPal subscription id; we materialise a renewal invoice on
 *     the first unseen event (idempotency via event.id).
 *   - Refunds → POST /v2/payments/captures/{capture_id}/refund.
 *
 * Webhook idempotency is keyed on PayPal's event `id` via the
 * payment_attempts (gateway, gateway_ref) unique index.
 *
 * Signature verification uses PayPal's server-side `verify-webhook-
 * signature` API (POST /v1/notifications/verify-webhook-signature):
 * PayPal signs webhooks with an X.509 certificate chain, and the
 * documented recommended path for non-SDK code is to POST the
 * transmission headers + body + webhook_id back to PayPal and check
 * the returned `verification_status`. No private-key math on our side.
 *
 * CONTRACT DEVIATION (intentional, same posture as Stripe/Razorpay):
 *   parseEvent() performs guarded DB side effects for
 *   PAYMENT.SALE.COMPLETED (renewal), PAYMENT.CAPTURE.REFUNDED, and
 *   BILLING.SUBSCRIPTION.CANCELLED branches because the locked
 *   WebhookController router only branches on succeeded/failed/
 *   requires_review. Every write is short-circuited by
 *   eventAlreadyProcessed().
 *
 * Sandbox vs live is derived from cred('mode','live') and toggles the
 * API base host (api-m.sandbox.paypal.com vs api-m.paypal.com).
 */
class PaypalAdapter extends AbstractAdapter
{
    public function slug(): string { return 'paypal'; }
    public function displayName(): string { return 'PayPal'; }

    protected function apiBase(): string
    {
        return ((string) $this->cred('mode', 'live')) === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /** Cached OAuth2 token per adapter instance. */
    protected ?string $accessToken = null;

    protected function accessToken(): string
    {
        if ($this->accessToken) return $this->accessToken;
        $clientId = (string) $this->cred('client_id', '');
        $secret   = (string) $this->cred('client_secret', '');
        if ($clientId === '' || $secret === '') {
            throw new NotImplementedException('PayPal credentials are not configured.');
        }
        $res = Http::withBasicAuth($clientId, $secret)
            ->asForm()
            ->post($this->apiBase() . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (!$res->successful()) {
            throw new \RuntimeException('PayPal OAuth2 token request failed: HTTP ' . $res->status());
        }
        $this->accessToken = (string) $res->json('access_token');
        return $this->accessToken;
    }

    // ------------------------------------------------------------------
    //  Checkout handoff
    // ------------------------------------------------------------------

    public function createCheckout(Invoice $invoice): array
    {
        $clientId = (string) $this->cred('client_id', '');
        if ($clientId === '') {
            throw new NotImplementedException('PayPal credentials are not configured.');
        }

        $items  = is_array($invoice->line_items) ? $invoice->line_items : [];
        $intent = $this->detectIntent($items);

        $handoff = ($intent['kind'] === 'plan')
            ? $this->createSubscriptionHandoff($invoice, $intent)
            : $this->createOrderHandoff($invoice);

        PaymentAttempt::updateOrCreate(
            ['gateway' => 'paypal', 'gateway_ref' => $handoff['attempt_ref']],
            [
                'invoice_id'   => $invoice->id,
                'status'       => 'initiated',
                'raw_response' => [
                    'kind'     => $handoff['kind'],
                    'ref_id'   => $handoff['ref_id'],
                    'amount'   => (int) $invoice->grand_total_minor,
                    'currency' => (string) $invoice->currency,
                ],
            ],
        );

        $invoice->forceFill(['gateway' => 'paypal'])->save();

        return [
            'kind' => 'view',
            'view' => 'user.checkout.paypal',
            'data' => [
                'invoice'         => $invoice,
                'client_id'       => $clientId,
                'order_id'        => $handoff['kind'] === 'order' ? $handoff['ref_id'] : null,
                'subscription_id' => $handoff['kind'] === 'subscription' ? $handoff['ref_id'] : null,
                'mode'            => (string) $this->cred('mode', 'live'),
                'currency'        => strtoupper((string) $invoice->currency),
                'description'     => 'Invoice ' . $invoice->number,
            ],
        ];
    }

    /** @return array{kind:string,ref_id:string,attempt_ref:string} */
    protected function createOrderHandoff(Invoice $invoice): array
    {
        $currency = strtoupper((string) $invoice->currency);
        $items    = is_array($invoice->line_items) ? $invoice->line_items : [];

        $itemTotalMinor = 0;
        $ppItems = [];
        foreach ($items as $li) {
            $amt = (int) ($li['amount_minor'] ?? 0);
            $qty = (int) ($li['quantity'] ?? 1);
            $itemTotalMinor += $amt * $qty;
            $ppItems[] = [
                'name'        => substr((string) ($li['label'] ?? 'Charge'), 0, 127),
                'quantity'    => (string) $qty,
                'unit_amount' => ['currency_code' => $currency, 'value' => $this->minorToMoney($amt)],
            ];
        }
        $taxMinor   = (int) $invoice->tax_total_minor;
        $grandMinor = (int) $invoice->grand_total_minor ?: ($itemTotalMinor + $taxMinor);

        $breakdown = [
            'item_total' => ['currency_code' => $currency, 'value' => $this->minorToMoney($itemTotalMinor)],
        ];
        if ($taxMinor > 0) {
            $breakdown['tax_total'] = ['currency_code' => $currency, 'value' => $this->minorToMoney($taxMinor)];
        }

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'inv-' . $invoice->number,
                'custom_id'    => (string) $invoice->id,
                'description'  => 'Invoice ' . $invoice->number,
                'amount' => [
                    'currency_code' => $currency,
                    'value'         => $this->minorToMoney($grandMinor),
                    'breakdown'     => $breakdown,
                ],
                'items' => $ppItems,
            ]],
            'application_context' => [
                'brand_name'          => (string) config('billing.merchant.name', '1INME'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'PAY_NOW',
                'return_url'          => url('/user/billing?paid=' . $invoice->number),
                'cancel_url'          => url('/user/billing?cancelled=' . $invoice->number),
            ],
        ];

        $res = Http::withToken($this->accessToken())
            ->asJson()
            ->post($this->apiBase() . '/v2/checkout/orders', $body);
        $this->assertOk($res, 'create order', $invoice);

        $orderId = (string) $res->json('id');
        return [
            'kind'        => 'order',
            'ref_id'      => $orderId,
            'attempt_ref' => 'order:' . $orderId,
        ];
    }

    /** @return array{kind:string,ref_id:string,attempt_ref:string} */
    protected function createSubscriptionHandoff(Invoice $invoice, array $intent): array
    {
        $plan  = Plan::findOrFail($intent['plan_id']);
        $cycle = $intent['cycle'];
        $currency = strtoupper((string) $invoice->currency);

        // Task constraint: pass tax as a separate amount — do NOT fold
        // it into the recurring base price and do NOT let PayPal Tax
        // recalculate. PayPal Billing Plans surface tax via
        // `taxes.percentage` + `inclusive=false`; we derive the
        // percentage from the just-computed invoice ratio so PayPal
        // shows it as a separate line on receipts/invoices.
        $baseMinor = max(0, (int) $invoice->subtotal_minor);
        $taxMinor  = max(0, (int) $invoice->tax_total_minor);
        if ($baseMinor === 0) {
            $baseMinor = max(0, (int) $invoice->grand_total_minor - $taxMinor);
        }
        $taxPercent = ($baseMinor > 0 && $taxMinor > 0)
            ? round(($taxMinor / $baseMinor) * 100, 2)
            : 0.0;

        $productId = $this->ensureProduct($plan);
        $planId    = $this->ensurePlan($plan, $cycle, $currency, $baseMinor, $taxPercent, $productId);

        $body = [
            'plan_id'   => $planId,
            'custom_id' => (string) $invoice->id,
            'application_context' => [
                'brand_name'          => (string) config('billing.merchant.name', '1INME'),
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'SUBSCRIBE_NOW',
                'return_url'          => url('/user/billing?paid=' . $invoice->number),
                'cancel_url'          => url('/user/billing?cancelled=' . $invoice->number),
            ],
        ];
        if ($invoice->user?->email) {
            $body['subscriber'] = ['email_address' => (string) $invoice->user->email];
        }

        $res = Http::withToken($this->accessToken())
            ->asJson()
            ->post($this->apiBase() . '/v1/billing/subscriptions', $body);
        $this->assertOk($res, 'create subscription', $invoice);

        $subId = (string) $res->json('id');
        return [
            'kind'        => 'subscription',
            'ref_id'      => $subId,
            'attempt_ref' => 'subscription:' . $subId,
        ];
    }

    protected function ensureProduct(Plan $plan): string
    {
        $cacheKey = 'pp_product:' . $plan->id;
        $cached = $this->cred($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $res = Http::withToken($this->accessToken())
            ->asJson()
            ->post($this->apiBase() . '/v1/catalogs/products', [
                'name'        => $plan->name,
                'description' => substr((string) $plan->description ?: $plan->name, 0, 256),
                'type'        => 'SERVICE',
                'category'    => 'SOFTWARE',
            ]);
        $this->assertOk($res, 'create product', null);
        $id = (string) $res->json('id');
        $this->cacheCred($cacheKey, $id);
        return $id;
    }

    protected function ensurePlan(Plan $plan, string $cycle, string $currency, int $baseMinor, float $taxPercent, string $productId): string
    {
        $cacheKey = sprintf(
            'pp_plan:%d:%s:%s:%d:%s',
            $plan->id, $cycle, $currency, $baseMinor,
            number_format($taxPercent, 2, '.', ''),
        );
        $cached = $this->cred($cacheKey);
        if (is_string($cached) && $cached !== '') return $cached;

        $intervalUnit = $cycle === 'annual' ? 'YEAR' : 'MONTH';
        $body = [
            'product_id' => $productId,
            'name'       => $plan->name . ' (' . $cycle . ')',
            'status'     => 'ACTIVE',
            'billing_cycles' => [[
                'frequency'      => ['interval_unit' => $intervalUnit, 'interval_count' => 1],
                'tenure_type'    => 'REGULAR',
                'sequence'       => 1,
                'total_cycles'   => 0, // 0 == infinite
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value'         => $this->minorToMoney($baseMinor),
                        'currency_code' => $currency,
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding'     => true,
                'setup_fee'                 => ['value' => '0', 'currency_code' => $currency],
                'setup_fee_failure_action'  => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ];
        if ($taxPercent > 0) {
            $body['taxes'] = [
                'percentage' => number_format($taxPercent, 2, '.', ''),
                'inclusive'  => false,
            ];
        }

        $res = Http::withToken($this->accessToken())
            ->asJson()
            ->post($this->apiBase() . '/v1/billing/plans', $body);
        $this->assertOk($res, 'create plan', null);
        $id = (string) $res->json('id');
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
        $webhookId = (string) $this->cred('webhook_id', '');
        if ($webhookId === '') return false;

        // PayPal's documented non-SDK verification path: post the
        // transmission headers back to them with the raw body +
        // our webhook_id. They confirm signature server-side.
        $required = [
            'PAYPAL-AUTH-ALGO', 'PAYPAL-CERT-URL', 'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG', 'PAYPAL-TRANSMISSION-TIME',
        ];
        foreach ($required as $h) {
            if (!$request->headers->has($h)) return false;
        }

        try {
            $res = Http::withToken($this->accessToken())
                ->asJson()
                ->post($this->apiBase() . '/v1/notifications/verify-webhook-signature', [
                    'auth_algo'         => $request->header('PAYPAL-AUTH-ALGO'),
                    'cert_url'          => $request->header('PAYPAL-CERT-URL'),
                    'transmission_id'   => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'transmission_sig'  => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                    'webhook_id'        => $webhookId,
                    'webhook_event'     => $request->json()->all(),
                ]);
            if (!$res->successful()) return false;
            return strtoupper((string) $res->json('verification_status')) === 'SUCCESS';
        } catch (\Throwable $e) {
            Log::warning('PayPal webhook verify threw', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function parseEvent(Request $request): array
    {
        $payload = $request->json()->all();
        $eventId = (string) ($payload['id'] ?? '');
        $type    = (string) ($payload['event_type'] ?? '');
        $raw     = $payload;
        $res     = $payload['resource'] ?? [];

        // ---------- PAYMENT.CAPTURE.COMPLETED (one-time) ----------
        if ($type === 'PAYMENT.CAPTURE.COMPLETED') {
            $customId  = (string) ($res['custom_id'] ?? ($res['supplementary_data']['related_ids']['order_id'] ?? ''));
            $invoiceId = ctype_digit($customId) ? (int) $customId : 0;
            $captureId = (string) ($res['id'] ?? '');
            $amount    = $res['amount'] ?? [];
            $amountMinor = $this->moneyToMinor((string) ($amount['value'] ?? '0'));
            // Renewal path: PAYMENT.SALE.COMPLETED is the subscription
            // cycle event. If billing_agreement_id is present on a
            // capture, treat it the same way.
            $agreement = (string) ($res['billing_agreement_id'] ?? '');
            if ($agreement !== '' && $invoiceId === 0) {
                return $this->handleSubscriptionRenewal($eventId, $agreement, $amountMinor, (string) ($amount['currency_code'] ?? ''), $raw);
            }
            return [
                'type'         => 'payment.succeeded',
                'invoice_id'   => $invoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $amountMinor,
                'currency'     => strtoupper((string) ($amount['currency_code'] ?? '')),
                'raw'          => $raw + ['paypal_capture_id' => $captureId],
            ];
        }

        // ---------- PAYMENT.CAPTURE.DENIED / PAYMENT.CAPTURE.DECLINED ----------
        if ($type === 'PAYMENT.CAPTURE.DENIED' || $type === 'PAYMENT.CAPTURE.DECLINED') {
            $customId  = (string) ($res['custom_id'] ?? '');
            $invoiceId = ctype_digit($customId) ? (int) $customId : 0;
            $amount    = $res['amount'] ?? [];
            return [
                'type'         => 'payment.failed',
                'invoice_id'   => $invoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $this->moneyToMinor((string) ($amount['value'] ?? '0')),
                'currency'     => strtoupper((string) ($amount['currency_code'] ?? '')),
                'raw'          => $raw,
            ];
        }

        // ---------- BILLING.SUBSCRIPTION.ACTIVATED (first cycle) ----------
        if ($type === 'BILLING.SUBSCRIPTION.ACTIVATED') {
            $customId  = (string) ($res['custom_id'] ?? '');
            $invoiceId = ctype_digit($customId) ? (int) $customId : 0;
            $amount    = $res['billing_info']['last_payment']['amount'] ?? [];
            return [
                'type'         => 'payment.succeeded',
                'invoice_id'   => $invoiceId ?: null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $this->moneyToMinor((string) ($amount['value'] ?? '0')),
                'currency'     => strtoupper((string) ($amount['currency_code'] ?? '')),
                'raw'          => $raw + ['paypal_subscription_id' => (string) ($res['id'] ?? '')],
            ];
        }

        // ---------- PAYMENT.SALE.COMPLETED (subscription renewal) ----------
        if ($type === 'PAYMENT.SALE.COMPLETED') {
            $agreement = (string) ($res['billing_agreement_id'] ?? '');
            $amount    = $res['amount'] ?? [];
            $amountMinor = $this->moneyToMinor((string) ($amount['total'] ?? ($amount['value'] ?? '0')));
            $currency  = strtoupper((string) ($amount['currency'] ?? ($amount['currency_code'] ?? '')));
            if ($agreement !== '') {
                return $this->handleSubscriptionRenewal($eventId, $agreement, $amountMinor, $currency, $raw);
            }
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $amountMinor,
                'currency'     => $currency,
                'raw'          => $raw,
            ];
        }

        // ---------- BILLING.SUBSCRIPTION.PAYMENT.FAILED ----------
        if ($type === 'BILLING.SUBSCRIPTION.PAYMENT.FAILED') {
            $ppSubId = (string) ($res['id'] ?? '');
            $sub = $ppSubId ? Subscription::where('gateway', 'paypal')
                ->where('gateway_subscription_id', $ppSubId)->first() : null;
            $latestInvoiceId = $sub
                ? (int) (Invoice::where('subscription_id', $sub->id)
                    ->orderByDesc('id')->value('id') ?? 0)
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

        // ---------- PAYMENT.CAPTURE.REFUNDED ----------
        if ($type === 'PAYMENT.CAPTURE.REFUNDED') {
            $refundId = (string) ($res['id'] ?? '');
            $amount   = $res['amount'] ?? [];
            $row = $refundId ? Refund::where('gateway', 'paypal')
                ->where('gateway_ref', $refundId)->first() : null;
            if (!$row) {
                // Dashboard-initiated refund: PayPal puts the captured
                // payment's id at links[rel=up].href as the final path
                // segment (.../v2/payments/captures/{captureId}). The
                // merchant-visible `resource.invoice_id` is the invoice
                // NUMBER, never a capture id, so matching on it was a
                // bug and never fired.
                $captureId = '';
                foreach ((array) ($res['links'] ?? []) as $link) {
                    if ((string) ($link['rel'] ?? '') === 'up') {
                        $href = (string) ($link['href'] ?? '');
                        $captureId = (string) (preg_match('~/([^/?#]+)/?$~', $href, $m) ? $m[1] : '');
                        break;
                    }
                }
                $inv = $captureId !== '' ? $this->invoiceForCaptureId($captureId) : null;
                if ($inv) {
                    $row = Refund::where('invoice_id', $inv->id)
                        ->where('status', 'pending')
                        ->orderByDesc('id')->first();
                }
            }
            if ($row && !$this->eventAlreadyProcessed($eventId)) {
                try {
                    app(\App\Services\Billing\RefundService::class)
                        ->handleGatewaySuccess($row, $refundId ?: ('paypal-refund:' . $row->id));
                } catch (\Throwable $e) {
                    Log::warning('PayPal refund gateway-success finalisation failed', [
                        'refund_id' => $row->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => $row ? (int) $row->invoice_id : null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $this->moneyToMinor((string) ($amount['value'] ?? '0')),
                'currency'     => strtoupper((string) ($amount['currency_code'] ?? '')),
                'raw'          => $raw,
            ];
        }

        // ---------- BILLING.SUBSCRIPTION.CANCELLED / EXPIRED ----------
        if ($type === 'BILLING.SUBSCRIPTION.CANCELLED' || $type === 'BILLING.SUBSCRIPTION.EXPIRED') {
            $ppSubId = (string) ($res['id'] ?? '');
            $match = $ppSubId ? Subscription::where('gateway', 'paypal')
                ->where('gateway_subscription_id', $ppSubId)->first() : null;
            if ($match && !$this->eventAlreadyProcessed($eventId)) {
                if ($match->status !== 'cancelled') {
                    $match->forceFill(['status' => 'cancelled', 'cancel_at' => now()])->save();
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

    protected function handleSubscriptionRenewal(string $eventId, string $agreement, int $amountMinor, string $currency, array $raw): array
    {
        if ($this->eventAlreadyProcessed($eventId)) {
            return [
                'type'         => 'payment.requires_review',
                'invoice_id'   => null,
                'gateway_ref'  => $eventId,
                'amount_minor' => $amountMinor,
                'currency'     => $currency,
                'raw'          => $raw,
            ];
        }

        // Duplicate-SALE guard: PayPal emits BILLING.SUBSCRIPTION.ACTIVATED
        // AND PAYMENT.SALE.COMPLETED for the initial charge. A genuine
        // renewal SALE only arrives at or after `current_period_end`;
        // anything landing while we are still comfortably inside the
        // paid period is the duplicate PayPal fires alongside the
        // activation event — treat as noise and do NOT materialise a
        // second renewal invoice.
        $sub = $agreement !== '' ? Subscription::where('gateway', 'paypal')
            ->where('gateway_subscription_id', $agreement)->first() : null;
        if ($sub) {
            $hasPaid = Invoice::where('subscription_id', $sub->id)
                ->where('status', 'paid')->exists();
            $periodEnd = $sub->current_period_end;
            $wellInsidePeriod = $periodEnd instanceof \Illuminate\Support\Carbon
                && now()->lt($periodEnd->copy()->subHours(6));
            if ($wellInsidePeriod && $hasPaid) {
                return [
                    'type'         => 'payment.requires_review',
                    'invoice_id'   => null,
                    'gateway_ref'  => $eventId,
                    'amount_minor' => $amountMinor,
                    'currency'     => $currency,
                    'raw'          => $raw + [
                        'paypal_subscription_id'  => $agreement,
                        'duplicate_sale_skipped'  => true,
                    ],
                ];
            }
            if (!$hasPaid) {
                // No paid invoice yet: ACTIVATED owns first-cycle
                // activation. A SALE arriving before the user-visible
                // invoice is paid must not materialise a phantom
                // renewal invoice.
                return [
                    'type'         => 'payment.requires_review',
                    'invoice_id'   => null,
                    'gateway_ref'  => $eventId,
                    'amount_minor' => $amountMinor,
                    'currency'     => $currency,
                    'raw'          => $raw + [
                        'paypal_subscription_id' => $agreement,
                        'first_cycle_skipped'    => true,
                    ],
                ];
            }
        }

        $invId = $this->resolveRenewalInvoiceId($agreement, $amountMinor, $currency);
        return [
            'type'         => $invId ? 'payment.succeeded' : 'payment.requires_review',
            'invoice_id'   => $invId ?: null,
            'gateway_ref'  => $eventId,
            'amount_minor' => $amountMinor,
            'currency'     => $currency,
            'raw'          => $raw + ['paypal_subscription_id' => $agreement],
        ];
    }

    protected function eventAlreadyProcessed(string $eventId): bool
    {
        if ($eventId === '') return false;
        return PaymentAttempt::where('gateway', 'paypal')
            ->where('gateway_ref', $eventId)->exists();
    }

    protected function resolveRenewalInvoiceId(string $ppSubId, int $amountMinor, string $currency): int
    {
        if ($ppSubId === '') return 0;
        $sub = Subscription::where('gateway', 'paypal')
            ->where('gateway_subscription_id', $ppSubId)->first();
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
            'gateway'         => 'paypal',
            'subscription_id' => $sub->id,
        ])->save();
        return (int) $invoice->id;
    }

    protected function invoiceForCaptureId(string $captureId): ?Invoice
    {
        if ($captureId === '') return null;
        $rows = PaymentAttempt::where('gateway', 'paypal')
            ->orderByDesc('id')->limit(200)->get(['invoice_id', 'raw_response']);
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            $c   = $raw['paypal_capture_id'] ?? null;
            if (is_string($c) && $c === $captureId) {
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
        $captureId = $this->lookupCaptureId($invoice);
        if (!$captureId) {
            throw new \RuntimeException('No PayPal capture_id found for invoice ' . $invoice->number);
        }

        $res = Http::withToken($this->accessToken())
            ->asJson()
            ->post($this->apiBase() . '/v2/payments/captures/' . $captureId . '/refund', [
                'amount' => [
                    'value'         => $this->minorToMoney($amountMinor),
                    'currency_code' => strtoupper((string) $invoice->currency),
                ],
                'note_to_payer' => substr($reason, 0, 255),
                'invoice_id'    => $invoice->number,
            ]);
        $this->assertOk($res, 'create refund', $invoice);

        $refundId = (string) $res->json('id');
        $status   = strtoupper((string) $res->json('status'));
        return [
            'gateway_ref' => $refundId,
            'status'      => $status === 'COMPLETED' ? 'succeeded'
                            : ($status === 'FAILED' || $status === 'CANCELLED' ? 'failed' : 'pending'),
        ];
    }

    protected function lookupCaptureId(Invoice $invoice): ?string
    {
        $rows = PaymentAttempt::where('invoice_id', $invoice->id)
            ->where('gateway', 'paypal')
            ->whereIn('status', ['succeeded', 'initiated'])
            ->orderByDesc('id')
            ->get();
        foreach ($rows as $row) {
            $raw = (array) $row->raw_response;
            $id  = $raw['paypal_capture_id'] ?? null;
            if (is_string($id) && $id !== '') return $id;
        }
        return null;
    }

    // ------------------------------------------------------------------
    //  Recurring
    // ------------------------------------------------------------------

    /**
     * PayPal auto-charges subscriptions on its own schedule; our cron
     * is a no-op for paypal-gateway subs. Renewals arrive via
     * PAYMENT.SALE.COMPLETED webhook.
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

    protected function minorToMoney(int $minor): string
    {
        // PayPal money is always 2-decimal strings regardless of
        // currency. Our minor units are also 1/100, so a simple
        // divide works for all supported currencies (USD/EUR/GBP/INR).
        return number_format($minor / 100, 2, '.', '');
    }

    protected function moneyToMinor(string $money): int
    {
        return (int) round(((float) $money) * 100);
    }

    protected function assertOk(Response $res, string $op, ?Invoice $invoice): void
    {
        if ($res->successful()) return;
        $body = $res->json() ?: ['body' => $res->body()];
        Log::warning("PayPal {$op} failed", ['status' => $res->status(), 'body' => $body]);

        if ($invoice) {
            try {
                PaymentAttempt::create([
                    'invoice_id'  => $invoice->id,
                    'gateway'     => 'paypal',
                    'gateway_ref' => 'failed:' . $op . ':' . substr(md5(json_encode($body) . microtime()), 0, 16),
                    'status'      => 'failed',
                    'raw_response' => [
                        'op'     => $op,
                        'status' => $res->status(),
                        'body'   => $body,
                    ],
                ]);
            } catch (\Throwable $ignore) {
                // Never mask the user-facing error.
            }
        }

        $msg = $body['message'] ?? $body['error_description'] ?? ('PayPal API error (HTTP ' . $res->status() . ')');
        throw new \RuntimeException("PayPal {$op} failed: {$msg}");
    }
}
