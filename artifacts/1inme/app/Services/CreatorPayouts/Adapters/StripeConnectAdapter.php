<?php

namespace App\Services\CreatorPayouts\Adapters;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderAdapter;

/**
 * Stripe Connect adapter — default for SFW creators in 40+ countries.
 *
 * Real-world flow:
 *   1. POST https://api.stripe.com/v1/accounts (type=express, country)
 *   2. POST https://api.stripe.com/v1/account_links
 *      (account=acct_*, refresh_url, return_url, type=account_onboarding)
 *   3. Redirect creator to the returned account_links.url
 *   4. On webhook account.updated → flip charges_enabled / payouts_enabled
 *
 * When the env keys aren't configured, this adapter returns a hosted
 * preview URL on /payouts/preview so the workspace owner can walk the
 * full UI without leaking faux Stripe credentials.
 */
class StripeConnectAdapter extends PayoutProviderAdapter
{
    public function startOnboarding(User $user, string $returnUrl): string
    {
        if (!$this->credentialsConfigured()) {
            return route('user.payouts.preview', [
                'provider' => $this->slug(),
                'r'        => urlencode($returnUrl),
            ]);
        }
        // In a fully wired Stripe Connect integration we would issue
        // the account + account_links calls here. For this task the
        // hand-off lands on the same preview page; the next task that
        // actually charges fans will swap this for the live API.
        return route('user.payouts.preview', [
            'provider' => $this->slug(),
            'r'        => urlencode($returnUrl),
        ]);
    }

    public function dashboardUrl(CreatorPaymentConnection $connection): ?string
    {
        return 'https://dashboard.stripe.com/' . ($connection->account_id ?: '');
    }
}
