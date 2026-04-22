<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PremiumFeatures;
use App\Modules\User\Models\BillingAddress;
use App\Services\Billing\WalletService;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;

/**
 * Public marketing pages for plans, coin packages, and premium feature
 * descriptions. Each lives at its own URL so the landing page stays
 * focused on Free + Most-Popular and visitors looking for the full
 * catalogue can drill into a dedicated destination.
 */
class PricingPagesController extends Controller
{
    public function plans(Request $request)
    {
        $user = $request->user();
        $cycle = $request->query('cycle', 'monthly') === 'annual' ? 'annual' : 'monthly';
        $currency = PricingResolver::currencyForUser($user);

        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        $taxFor = function ($priced) use ($billing, $currency, $hasAddress) {
            if (!$hasAddress || (int) $priced['amount_minor'] === 0) return null;
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

        $plans = Plan::active()->with('prices')->ordered()->get();
        $rows = $plans->map(function (Plan $p) use ($user, $taxFor) {
            $monthly = PricingResolver::priceFor($p, $user, 'monthly');
            $annual  = PricingResolver::priceFor($p, $user, 'annual');
            return [
                'model'      => $p,
                'monthly'    => $monthly,
                'annual'     => $annual,
                'tax_monthly'=> $taxFor($monthly),
                'tax_annual' => $taxFor($annual),
            ];
        });

        $packages = CoinPackage::active()->with('prices')->ordered()->get()
            ->map(function (CoinPackage $p) use ($currency) {
                $priced = PricingResolver::priceForCurrency($p, $currency, 'monthly');
                return [
                    'model'         => $p,
                    'amount_minor'  => (int) ($priced['amount_minor'] ?? 0),
                    'formatted'     => $priced['formatted'] ?? null,
                    'currency'      => $currency,
                    'total_coins'   => $p->totalCoins(),
                ];
            });

        return view('public.pricing.plans', [
            'plans'         => $rows,
            'cycle'         => $cycle,
            'currency'      => $currency,
            'user'          => $user,
            'packages'      => $packages,
            'wallet_enabled'=> WalletService::isEnabled(),
        ]);
    }

    public function coins(Request $request)
    {
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);

        $packages = CoinPackage::active()->with('prices')->ordered()->get()
            ->map(function (CoinPackage $p) use ($currency) {
                $priced = PricingResolver::priceForCurrency($p, $currency, 'monthly');
                return [
                    'model'         => $p,
                    'amount_minor'  => (int) ($priced['amount_minor'] ?? 0),
                    'formatted'     => $priced['formatted'] ?? null,
                    'currency'      => $currency,
                    'total_coins'   => $p->totalCoins(),
                ];
            });

        return view('public.pricing.coins', [
            'packages'      => $packages,
            'currency'      => $currency,
            'user'          => $user,
            'wallet_enabled'=> WalletService::isEnabled(),
        ]);
    }

    public function features(Request $request)
    {
        $plans = Plan::active()->ordered()->get();
        $unlocks = PremiumFeatures::unlocksByFeature($plans);
        $catalogue = PremiumFeatures::catalogue();

        $grouped = [];
        foreach ($catalogue as $entry) {
            $grouped[$entry['group']][] = $entry + ['unlocked_by' => $unlocks[$entry['key']] ?? []];
        }

        $planMeta = $plans->map(fn ($p) => [
            'slug' => $p->slug, 'name' => $p->name,
        ])->keyBy('slug');

        return view('public.pricing.features', [
            'grouped'  => $grouped,
            'planMeta' => $planMeta,
        ]);
    }
}
