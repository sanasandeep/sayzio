<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * Razorpay Route adapter — best fit for India-based creators. Routed
 * payouts in INR via the Razorpay Route product.
 */
class RazorpayRouteAdapter extends PayoutProviderAdapter
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
        return 'https://dashboard.razorpay.com/app/route/accounts';
    }
}
