<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * Segpay adapter — adult-friendly processor with strong chargeback
 * protection and a strong EU presence. Onboarding is hosted by Segpay
 * (package-id activation flow).
 */
class SegpayAdapter extends PayoutProviderAdapter
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
        return 'https://merchant.segpay.com/';
    }
}
