<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Cashfree Easy Split adapter — India-focused creator payouts using
 * vendor sub-accounts under the platform's Cashfree merchant account
 * (same split-settlement model as Razorpay Route).
 *
 * NOTE: distinct from the plan-billing Cashfree gateway
 * (App\Services\Billing\Adapters\CashfreeAdapter) — that one charges
 * the platform's own invoices; this one routes fan money to creators.
 * They share nothing but the API conventions.
 *
 * Live mode (CASHFREE_APP_ID + CASHFREE_SECRET_KEY set):
 *   - startOnboarding() creates (or reuses) an Easy Split vendor via
 *     POST /easy-split/vendors, stores the deterministic `cfv_u{id}`
 *     vendor id on the connection, and sends the creator back (KYC /
 *     bank details complete on Cashfree's side — no hosted redirect).
 *   - syncStatus() queries the live vendor state.
 *   - createSubscriptionCheckout()/createOneTimeCheckout() create a
 *     Cashfree PG Order (INR, `order_splits` routing 100% to the
 *     creator's vendor — 0% platform fee) and return a signed URL to
 *     our hosted Cashfree checkout page (checkout.cashfree), which
 *     mounts Cashfree's JS SDK against the payment_session_id.
 *   - payment confirmation arrives on the CSRF-exempt
 *     `/webhooks/cashfree-payouts` endpoint
 *     (CashfreePayoutWebhookController) — distinct from the billing
 *     `/webhooks/cashfree` gateway route.
 *
 * Preview mode (no keys): onboarding lands on the payouts preview page
 * and checkouts return the signed checkout.preview URL from the parent
 * class — identical behaviour to the other keyless adapters.
 */
class CashfreeAdapter extends PayoutProviderAdapter
{
    public const API_VERSION = '2023-08-01';

    /** Cache-key prefix mapping a Cashfree order id → checkout context. */
    public const ORDER_CACHE_PREFIX = 'cashfree_payouts:order:';

    /** Deterministic vendor-id prefix (also distinguishes live vendors from preview_* placeholders). */
    public const VENDOR_PREFIX = 'cfv_';

    public static function apiBase(): string
    {
        return strtolower((string) env('CASHFREE_ENV', 'live')) === 'sandbox'
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    public function startOnboarding(User $user, string $returnUrl): string
    {
        if (!$this->credentialsConfigured()) {
            return route('user.payouts.preview', [
                'provider' => $this->slug(),
                'r'        => urlencode($returnUrl),
            ]);
        }

        $connection = CreatorPaymentConnection::firstOrNew([
            'user_id'  => $user->id,
            'provider' => $this->slug(),
        ]);

        // Reuse an existing live vendor; preview_* placeholder ids (from
        // a keyless era) are replaced by a real one.
        if (!$connection->account_id || !str_starts_with($connection->account_id, self::VENDOR_PREFIX)) {
            $vendorId = self::VENDOR_PREFIX . 'u' . $user->id;

            $payload = array_filter([
                'vendor_id'        => $vendorId,
                'status'           => 'ACTIVE',
                'name'             => $user->name ?: ($user->handle ?: ('Creator ' . $user->id)),
                'email'            => $user->email,
                'phone'            => $user->mobile ?: null,
                'verify_account'   => false,
                'dashboard_access' => false,
                'schedule_option'  => 1,
            ], fn ($v) => $v !== null && $v !== '');

            $resp = $this->http()->post(self::apiBase() . '/easy-split/vendors', $payload);

            // 409 / "already exists" → the vendor id is deterministic, so
            // just adopt it and let syncStatus pull the live state.
            if ($resp->failed() && $resp->status() !== 409) {
                Log::warning('cashfree_payouts.vendor_create_failed', [
                    'user'   => $user->id,
                    'status' => $resp->status(),
                    'body'   => $resp->json(),
                ]);
                $connection->status         = $connection->status ?: 'pending';
                $connection->status_reason  = 'Cashfree could not create the vendor account: '
                    . ($resp->json('message') ?: ('HTTP ' . $resp->status()));
                $connection->last_sync_at   = now();
                $connection->save();
                return $returnUrl;
            }

            $connection->account_id = (string) ($resp->json('vendor_id') ?: $vendorId);
            $connection->country    = 'IN';
            $connection->metadata   = array_merge($connection->metadata ?? [], [
                'cashfree_vendor_status' => $resp->json('status'),
            ]);
            $connection->save();
        }

        $this->syncStatus($connection);

        // Easy Split vendors have no hosted-onboarding redirect; bank /
        // KYC details finish on Cashfree's side. Send the creator back.
        return $returnUrl;
    }

    public function syncStatus(CreatorPaymentConnection $connection): void
    {
        if (!$this->credentialsConfigured()
            || !$connection->account_id
            || !str_starts_with((string) $connection->account_id, self::VENDOR_PREFIX)) {
            parent::syncStatus($connection);
            return;
        }

        $resp = $this->http()->get(self::apiBase() . '/easy-split/vendors/' . $connection->account_id);
        if ($resp->failed()) {
            $connection->status_reason = 'Cashfree vendor status check failed: '
                . ($resp->json('message') ?: ('HTTP ' . $resp->status()));
            $connection->last_sync_at  = now();
            $connection->save();
            return;
        }

        self::applyVendorStatus($connection, (string) $resp->json('status'));
    }

    /**
     * Map a live Cashfree vendor status onto the connection row. Shared
     * by syncStatus() and the vendor webhook events.
     */
    public static function applyVendorStatus(CreatorPaymentConnection $connection, string $status): void
    {
        $status = strtoupper($status);
        [$local, $enabled, $reason] = match ($status) {
            'ACTIVE'              => ['active', true, 'Vendor account active on Cashfree.'],
            'BLOCKED', 'DELETED'  => ['disabled', false, 'Vendor account ' . strtolower($status) . ' by Cashfree.'],
            'ACTION_REQUIRED'     => ['restricted', false, 'Cashfree needs more details (KYC / bank) on the vendor account.'],
            default               => ['pending', false, 'Vendor created — awaiting Cashfree activation (status: ' . ($status ?: 'unknown') . ').'],
        };

        $connection->status          = $local;
        $connection->status_reason   = $reason;
        $connection->payouts_enabled = $enabled;
        $connection->charges_enabled = $enabled;
        $connection->metadata        = array_merge($connection->metadata ?? [], [
            'cashfree_vendor_status' => $status,
        ]);
        $connection->last_sync_at    = now();
        $connection->save();
    }

    public function createSubscriptionCheckout(CreatorPaymentConnection $connection, array $context): string
    {
        return $this->liveCheckout($connection, 'subscription', $context)
            ?? parent::createSubscriptionCheckout($connection, $context);
    }

    public function createOneTimeCheckout(CreatorPaymentConnection $connection, array $context): string
    {
        return $this->liveCheckout($connection, (string) ($context['kind'] ?? 'one_time'), $context)
            ?? parent::createOneTimeCheckout($connection, $context);
    }

    /**
     * Create a Cashfree PG Order (INR) with a 100% Easy Split to the
     * creator's vendor, cache the order → checkout context mapping for
     * the webhook, and return the signed URL of our hosted Cashfree
     * checkout page. Returns null when live mode isn't possible (no
     * keys / no vendor) so callers fall back to the preview flow.
     */
    protected function liveCheckout(CreatorPaymentConnection $connection, string $kind, array $context): ?string
    {
        if (!$this->credentialsConfigured()
            || !$connection->account_id
            || !str_starts_with((string) $connection->account_id, self::VENDOR_PREFIX)) {
            return null;
        }

        $amount    = (int) ($context['amount'] ?? 0);
        $reference = (string) ($context['reference'] ?? '');
        $token     = (string) ($context['token'] ?? '');
        if ($amount <= 0 || $reference === '' || $token === '') {
            return null;
        }

        // Currency invariant: this adapter performs NO FX conversion — a
        // non-INR payload (e.g. USD cents) must never be forwarded as
        // paise, or the buyer is charged the wrong amount and the ledger
        // diverges from the provider charge. Fall back to the preview
        // flow instead. INR pricing on checkout surfaces is Task #6639.
        if (strtoupper((string) ($context['currency'] ?? '')) !== 'INR') {
            Log::info('cashfree_payouts.non_inr_checkout_fallback', [
                'connection' => $connection->id,
                'reference'  => $reference,
                'currency'   => $context['currency'] ?? null,
            ]);
            return null;
        }

        $orderId = 'mc_' . preg_replace('/[^A-Za-z0-9_-]/', '', $reference) . '_' . substr(md5($token . microtime()), 0, 10);

        // Cashfree order amounts are decimal rupees; our payloads are
        // paise. The platform takes 0%: the split routes 100% of the
        // order to the creator's vendor account.
        $resp = $this->http()->post(self::apiBase() . '/orders', [
            'order_id'       => $orderId,
            'order_amount'   => (float) number_format($amount / 100, 2, '.', ''),
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id'    => (string) ($context['customer_id'] ?? ('guest_' . substr(md5($token), 0, 12))),
                'customer_email' => (string) ($context['customer_email'] ?? 'buyer@example.com'),
                'customer_phone' => (string) ($context['customer_phone'] ?? '9999999999'),
                'customer_name'  => (string) ($context['customer_name'] ?? 'Buyer'),
            ],
            'order_meta' => [
                'return_url' => route('checkout.return', [
                    'kind'      => $kind === 'one_time' ? 'tip' : $kind,
                    'reference' => $reference,
                    'token'     => $token,
                ]),
                'notify_url' => route('webhooks.cashfree-payouts'),
            ],
            'order_tags' => [
                'kind'      => $kind,
                'reference' => $reference,
                'token'     => $token,
            ],
            'order_splits' => [[
                'vendor_id'  => (string) $connection->account_id,
                'percentage' => 100,
            ]],
            'order_note' => 'Creator checkout ' . $reference,
        ]);

        if ($resp->failed() || !$resp->json('payment_session_id')) {
            Log::error('cashfree_payouts.order_create_failed', [
                'connection' => $connection->id,
                'reference'  => $reference,
                'status'     => $resp->status(),
                'body'       => $resp->json(),
            ]);
            abort(502, 'Cashfree could not start the checkout. Please try again shortly.');
        }

        $cfOrderId = (string) ($resp->json('order_id') ?: $orderId);

        // The webhook resolves the checkout context from the order id
        // (with order_tags on the payload as fallback).
        cache()->put(self::ORDER_CACHE_PREFIX . $cfOrderId, [
            'kind'      => $kind,
            'reference' => $reference,
            'token'     => $token,
        ], now()->addDays(2));

        return URL::temporarySignedRoute('checkout.cashfree-payout', now()->addMinutes(35), [
            'order_id'   => $cfOrderId,
            'session_id' => (string) $resp->json('payment_session_id'),
            'kind'       => $kind,
            'reference'  => $reference,
            'token'      => $token,
            'amount'     => $amount,
            'currency'   => 'INR',
        ]);
    }

    public function dashboardUrl(CreatorPaymentConnection $connection): ?string
    {
        return 'https://merchant.cashfree.com/merchants/easy-split/vendors';
    }

    protected function http(): PendingRequest
    {
        return Http::withHeaders([
            'x-client-id'     => (string) env('CASHFREE_APP_ID'),
            'x-client-secret' => (string) env('CASHFREE_SECRET_KEY'),
            'x-api-version'   => self::API_VERSION,
        ])->acceptJson()->asJson()->timeout(20);
    }
}
