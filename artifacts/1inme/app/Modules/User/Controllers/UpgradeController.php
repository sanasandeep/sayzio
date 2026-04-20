<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;

class UpgradeController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $cycle = $request->query('cycle', 'monthly') === 'annual' ? 'annual' : 'monthly';
        $currency = PricingResolver::currencyForUser($user);

        // Eager-load `prices` so PricingResolver doesn't re-query per row
        // (avoids the obvious N+1 on this page).
        $plans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()->get();
        $addons = Addon::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()->get();

        // Pull the buyer's billing address (if any) so we can show real
        // tax breakdowns instead of the "+ taxes as applicable" placeholder.
        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        $taxFor = function ($priced) use ($billing, $currency, $hasAddress) {
            if (!$hasAddress || (int) $priced['amount_minor'] === 0) {
                return null;
            }
            return TaxCalculator::calculate(
                [['label' => 'Plan', 'amount_minor' => (int) $priced['amount_minor']]],
                [
                    'country'     => $billing->country,
                    'region'      => $billing->region,
                    'tax_id'      => $billing->tax_id,
                    'tax_id_kind' => $billing->tax_id_kind,
                ],
                $currency,
            );
        };

        $plansPriced = $plans->map(function ($p) use ($user, $cycle, $taxFor) {
            $monthly = PricingResolver::priceFor($p, $user, 'monthly');
            $annual  = PricingResolver::priceFor($p, $user, 'annual');
            $shown   = $cycle === 'annual' ? $annual : $monthly;
            return [
                'model'   => $p,
                'monthly' => $monthly,
                'annual'  => $annual,
                'shown'   => $shown,
                'tax'     => $taxFor($shown),
            ];
        });

        $addonsPriced = $addons->map(function ($a) use ($user, $cycle, $taxFor) {
            $shown = PricingResolver::priceFor($a, $user, $cycle);
            return [
                'model'  => $a,
                'shown'  => $shown,
                'tax'    => $taxFor($shown),
            ];
        });

        return view('user.upgrade.show', [
            'plans'      => $plansPriced,
            'addons'     => $addonsPriced,
            'cycle'      => $cycle,
            'currency'   => $currency,
            'user'       => $user,
            'hasAddress' => $hasAddress,
            'billing'    => $billing,
        ]);
    }

    /**
     * Anonymous (or signed-in override) currency switcher.
     * Persists in the session so subsequent requests render the same currency.
     * Signed-in users with a country still see their country-default until they
     * change it on the profile page — this only affects the session preview.
     */
    public function switchCurrency(Request $request)
    {
        $currency = strtoupper((string) $request->input('currency', 'USD'));
        if (!in_array($currency, ['USD', 'INR'], true)) {
            $currency = 'USD';
        }
        session([PricingResolver::SESSION_KEY => $currency]);
        return back();
    }
}
