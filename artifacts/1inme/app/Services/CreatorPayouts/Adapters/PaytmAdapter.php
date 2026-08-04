<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * Paytm adapter — Paytm Payment Gateway for India-based creators
 * (UPI, Paytm wallet, cards, netbanking).
 *
 * Real-world flow:
 *   1. Merchant onboarding is hosted on the Paytm business dashboard;
 *      the creator registers their merchant account there.
 *   2. Checkout: POST /theia/api/v1/initiateTransaction with a
 *      checksum (HMAC over the payload with PAYTM_MERCHANT_KEY),
 *      then redirect the fan to the hosted checkout with the txnToken.
 *   3. Callback URL receives the signed transaction status.
 *
 * When PAYTM_MERCHANT_ID / PAYTM_MERCHANT_KEY aren't configured, the
 * adapter falls back to the hosted preview flow so the full UX remains
 * testable without live credentials.
 */
class PaytmAdapter extends PayoutProviderAdapter
{
    public function startOnboarding(User $user, string $returnUrl): string
    {
        return route('user.payouts.preview', [
            'provider' => $this->slug(),
            'r'        => urlencode($returnUrl),
        ]);
    }

    public function dashboardUrl(CreatorPaymentConnection $connection): ?string
    {
        return 'https://dashboard.paytm.com/';
    }
}
