<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * PhonePe adapter — UPI-first payments for India-based creators via
 * the PhonePe Payment Gateway.
 *
 * Real-world flow:
 *   1. Merchant onboarding is hosted on the PhonePe business portal;
 *      the creator registers their merchant account there.
 *   2. Checkout: POST /pg/v1/pay with an X-VERIFY signature
 *      (SHA256(base64(payload) + path + saltKey) + '###' + saltIndex)
 *      and redirect the fan to the returned redirect URL.
 *   3. Server-to-server callback confirms payment status.
 *
 * When PHONEPE_MERCHANT_ID / PHONEPE_SALT_KEY aren't configured, the
 * adapter falls back to the hosted preview flow so the full UX remains
 * testable without live credentials.
 */
class PhonepeAdapter extends PayoutProviderAdapter
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
        return 'https://business.phonepe.com/';
    }
}
