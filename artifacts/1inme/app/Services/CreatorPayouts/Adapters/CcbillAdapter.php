<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * CCBill adapter — adult-friendly processor with high-risk merchant
 * accounts and global card support. Onboarding is hosted by CCBill
 * (affiliate-style activation flow keyed on the affiliate id).
 */
class CcbillAdapter extends PayoutProviderAdapter
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
        return 'https://admin.ccbill.com/megamenus/ccbillHome.html';
    }
}
