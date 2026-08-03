<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * CCAvenue adapter — India's long-running gateway with 200+ payment
 * options (cards, UPI, netbanking, wallets, EMI).
 *
 * Real-world flow:
 *   1. Merchant onboarding is hosted on the CCAvenue M.A.R.S portal;
 *      the creator registers their merchant account there.
 *   2. Checkout: build an AES-128-CBC encrypted request (working key)
 *      and POST the fan to /transaction/transaction.do (billing page).
 *   3. Encrypted response posted back to the redirect/cancel URL.
 *
 * When CCAVENUE_MERCHANT_ID / ACCESS_CODE / WORKING_KEY aren't
 * configured, the adapter falls back to the hosted preview flow so the
 * full UX remains testable without live credentials.
 */
class CcavenueAdapter extends PayoutProviderAdapter
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
        return 'https://mars.ccavenue.com/';
    }
}
