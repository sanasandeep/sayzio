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
 * Razorpay Route adapter — best fit for India-based creators. Routed
 * payouts in INR via the Razorpay Route product.
 *
 * Live mode (RAZORPAY_KEY_ID + RAZORPAY_KEY_SECRET set):
 *   - startOnboarding() creates (or reuses) a Route linked account via
 *     the v2 Accounts API, stores its `acc_*` id on the connection, and
 *     sends the creator back to the payouts dashboard (Razorpay Route
 *     has no hosted onboarding redirect — KYC completes in the linked
 *     account's own Razorpay dashboard).
 *   - syncStatus() queries the live linked-account state.
 *   - createSubscriptionCheckout()/createOneTimeCheckout() create a
 *     Razorpay Order in paise with a `transfers` entry routing 100% of
 *     the amount to the creator's linked account (0% platform fee),
 *     and return a signed URL to our hosted Razorpay Checkout page.
 *   - payment/account confirmation arrives on the CSRF-exempt
 *     `/webhooks/razorpay-route` endpoint (RazorpayRouteWebhookController).
 *
 * Preview mode (no keys): identical behaviour to before this task —
 * onboarding lands on the payouts preview page and checkouts return the
 * signed checkout.preview URL from the parent class.
 */
class RazorpayRouteAdapter extends PayoutProviderAdapter
{
    public const API_BASE = 'https://api.razorpay.com';

    /** Cache-key prefix mapping a Razorpay order id → checkout context. */
    public const ORDER_CACHE_PREFIX = 'razorpay_route:order:';

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

        // Reuse an existing live linked account; preview_* placeholder ids
        // (from a keyless era) are replaced by a real one.
        if (!$connection->account_id || !str_starts_with($connection->account_id, 'acc_')) {
            $payload = array_filter([
                'email'               => $user->email,
                'phone'               => $user->mobile ?: null,
                'type'                => 'route',
                'reference_id'        => 'user_' . $user->id,
                'legal_business_name' => $user->name ?: ($user->handle ?: ('Creator ' . $user->id)),
                'business_type'       => 'individual',
                'contact_name'        => $user->name ?: ($user->handle ?: ('Creator ' . $user->id)),
                'profile'             => [
                    'category'    => 'others',
                    'subcategory' => 'others',
                ],
            ], fn ($v) => $v !== null && $v !== '');

            $resp = $this->http()->post(self::API_BASE . '/v2/accounts', $payload);

            if ($resp->failed() || !$resp->json('id')) {
                Log::warning('razorpay_route.account_create_failed', [
                    'user'   => $user->id,
                    'status' => $resp->status(),
                    'body'   => $resp->json(),
                ]);
                $connection->status         = $connection->status ?: 'pending';
                $connection->status_reason  = 'Razorpay could not create the linked account: '
                    . ($resp->json('error.description') ?: ('HTTP ' . $resp->status()));
                $connection->last_sync_at   = now();
                $connection->save();
                return $returnUrl;
            }

            $connection->account_id = (string) $resp->json('id');
            $connection->country    = 'IN';
            $connection->metadata   = array_merge($connection->metadata ?? [], [
                'razorpay_account_status' => $resp->json('status'),
                'razorpay_reference_id'   => $resp->json('reference_id'),
            ]);
            $connection->save();
        }

        $this->syncStatus($connection);

        // Route linked accounts have no hosted-onboarding redirect; KYC
        // continues on Razorpay's side. Send the creator straight back.
        return $returnUrl;
    }

    public function syncStatus(CreatorPaymentConnection $connection): void
    {
        if (!$this->credentialsConfigured()
            || !$connection->account_id
            || !str_starts_with((string) $connection->account_id, 'acc_')) {
            parent::syncStatus($connection);
            return;
        }

        $resp = $this->http()->get(self::API_BASE . '/v2/accounts/' . $connection->account_id);
        if ($resp->failed()) {
            $connection->status_reason = 'Razorpay status check failed: '
                . ($resp->json('error.description') ?: ('HTTP ' . $resp->status()));
            $connection->last_sync_at  = now();
            $connection->save();
            return;
        }

        self::applyAccountStatus($connection, (string) $resp->json('status'));
    }

    /**
     * Map a live Razorpay linked-account status onto the connection row.
     * Shared by syncStatus() and the account.* webhook events.
     */
    public static function applyAccountStatus(CreatorPaymentConnection $connection, string $status): void
    {
        [$local, $enabled, $reason] = match ($status) {
            'activated'           => ['active', true, 'Linked account activated by Razorpay.'],
            'suspended'           => ['disabled', false, 'Linked account suspended by Razorpay.'],
            'needs_clarification' => ['restricted', false, 'Razorpay needs clarification on the linked account\'s KYC.'],
            default               => ['pending', false, 'Linked account created — awaiting Razorpay activation (status: ' . ($status ?: 'unknown') . ').'],
        };

        $connection->status          = $local;
        $connection->status_reason   = $reason;
        $connection->payouts_enabled = $enabled;
        $connection->charges_enabled = $enabled;
        $connection->metadata        = array_merge($connection->metadata ?? [], [
            'razorpay_account_status' => $status,
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
     * Create a Razorpay Order (amount in paise, INR) with a 100% Route
     * transfer to the creator's linked account, cache the order → checkout
     * context mapping for the webhook, and return the signed URL of our
     * hosted Razorpay Checkout page. Returns null when live mode isn't
     * possible (no keys / no linked account) so callers fall back to the
     * preview flow.
     */
    protected function liveCheckout(CreatorPaymentConnection $connection, string $kind, array $context): ?string
    {
        if (!$this->credentialsConfigured()
            || !$connection->account_id
            || !str_starts_with((string) $connection->account_id, 'acc_')) {
            return null;
        }

        $amount    = (int) ($context['amount'] ?? 0);
        $reference = (string) ($context['reference'] ?? '');
        $token     = (string) ($context['token'] ?? '');
        if ($amount <= 0 || $reference === '' || $token === '') {
            return null;
        }

        // Currency invariant: Razorpay Route orders are INR-only and this
        // adapter performs NO FX conversion. A non-INR checkout payload
        // (e.g. USD cents) must never be forwarded as paise — the buyer
        // would be charged the wrong amount and the ledger (which records
        // the payload's currency) would diverge from the provider charge.
        // Fall back to the preview flow instead. INR pricing on checkout
        // surfaces is Task #6639.
        if (strtoupper((string) ($context['currency'] ?? '')) !== 'INR') {
            Log::info('razorpay_route.non_inr_checkout_fallback', [
                'connection' => $connection->id,
                'reference'  => $reference,
                'currency'   => $context['currency'] ?? null,
            ]);
            return null;
        }

        // Razorpay orders are denominated in the currency's minor unit —
        // paise for INR. The platform takes 0%: the transfer routes the
        // full order amount to the creator's linked account.
        $resp = $this->http()->post(self::API_BASE . '/v1/orders', [
            'amount'   => $amount,
            'currency' => 'INR',
            'receipt'  => mb_substr($reference, 0, 40),
            'notes'    => [
                'kind'      => $kind,
                'reference' => $reference,
                'token'     => $token,
            ],
            'transfers' => [[
                'account'  => $connection->account_id,
                'amount'   => $amount,
                'currency' => 'INR',
                'notes'    => ['reference' => $reference],
            ]],
        ]);

        if ($resp->failed() || !$resp->json('id')) {
            Log::error('razorpay_route.order_create_failed', [
                'connection' => $connection->id,
                'reference'  => $reference,
                'status'     => $resp->status(),
                'body'       => $resp->json(),
            ]);
            abort(502, 'Razorpay could not start the checkout. Please try again shortly.');
        }

        $orderId = (string) $resp->json('id');

        // The webhook resolves the checkout context from the order id
        // (payment.notes may be absent on Route payments).
        cache()->put(self::ORDER_CACHE_PREFIX . $orderId, [
            'kind'      => $kind,
            'reference' => $reference,
            'token'     => $token,
        ], now()->addDays(2));

        return URL::temporarySignedRoute('checkout.razorpay', now()->addMinutes(35), [
            'order_id'  => $orderId,
            'kind'      => $kind,
            'reference' => $reference,
            'token'     => $token,
            'amount'    => $amount,
            'currency'  => 'INR',
        ]);
    }

    public function dashboardUrl(CreatorPaymentConnection $connection): ?string
    {
        return 'https://dashboard.razorpay.com/app/route/accounts';
    }

    protected function http(): PendingRequest
    {
        return Http::withBasicAuth((string) env('RAZORPAY_KEY_ID'), (string) env('RAZORPAY_KEY_SECRET'))
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}
