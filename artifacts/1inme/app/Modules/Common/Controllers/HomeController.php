<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        // Landing-page pricing is intentionally just two cards now:
        // the always-free entry plan and the curator-flagged "popular"
        // plan. The full plan grid lives at /pricing — keeping the
        // landing page focused removes choice paralysis above the fold.
        $allPlans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->with('prices')
            ->ordered()
            ->get();

        $isFree = fn ($p) => (int) PricingResolver::priceFor($p, $user, 'monthly')['amount_minor'] === 0;
        $freePlan = $allPlans->first($isFree);
        $popularPlan = $allPlans->firstWhere('is_popular', true)
            ?? $allPlans->first(fn ($p) => !$isFree($p)); // Fallback if none flagged.

        $plans = collect([$freePlan, $popularPlan])->filter()->unique('id')->values()
            ->map(function ($p) use ($user, $billing, $hasAddress, $currency) {
                $monthly = PricingResolver::priceFor($p, $user, 'monthly');
                $tax = null;
                if ($hasAddress && (int) $monthly['amount_minor'] > 0) {
                    $tax = TaxCalculator::calculate(
                        [['label' => 'Plan', 'amount_minor' => (int) $monthly['amount_minor']]],
                        [
                            'country'     => $billing->country,
                            'region'      => $billing->region,
                            'tax_id'      => $billing->tax_id,
                            'tax_id_kind' => $billing->tax_id_kind,
                        ],
                        $currency,
                    );
                }
                return [
                    'name'        => $p->name,
                    'description' => $p->description,
                    'features'    => $p->features ?? [],
                    'is_free'     => $monthly['amount_minor'] <= 0,
                    'is_popular'  => (bool) $p->is_popular,
                    'monthly'     => $monthly,
                    'tax'         => $tax,
                ];
            });

        return view('home', compact('plans', 'currency', 'currencySource', 'user', 'hasAddress'));
    }
}
