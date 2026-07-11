<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PricingPageCache;
use App\Modules\User\Models\BillingAddress;
use App\Services\Billing\WalletService;
use App\Services\BillingCyclePreference;
use App\Services\PlanRecommender;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Public marketing pages for plans and coin packages. Each lives at its
 * own URL so the landing page stays focused on Free + Most-Popular and
 * visitors looking for the full catalogue can drill into a dedicated
 * destination.
 */
class PricingPagesController extends Controller
{
    /**
     * Cache key for the plan + coin-package catalogue. Kept as an alias of
     * PricingPageCache::CATALOG_CACHE_KEY (where the builder now lives, so
     * the scheduled `home:warm-caches` job and this controller share one
     * source and can't drift).
     */
    public const CATALOG_CACHE_KEY = PricingPageCache::CATALOG_CACHE_KEY;

    public function plans(Request $request)
    {
        // Resolve via the web guard explicitly: with an active admin-guard
        // session the default guard returns an Admin, which the ?User
        // typehint on PricingResolver::currencyForUser() rejects (500).
        $user = $request->user('web');
        // When the native app opens /pricing?client=app, remember it in the
        // session for the duration of the checkout round-trip. The billing
        // success page reads this flag to fire the `sayzio://billing/refresh`
        // deep link so the app can auto-refresh the plan when the user
        // returns (see BillingController::show + billing/show.blade.php).
        // Store the moment it was set (not a bare bool) so the success page
        // can ignore a stale flag left behind by an abandoned app checkout —
        // otherwise a later plain-web upgrade in the same session would show
        // the "return to app" banner even though this browser has no app.
        if ($request->query('client') === 'app') {
            $request->session()->put('billing.app_return', time());
        }
        // Honor the visitor's last-chosen billing cycle (query param,
        // session, or long-lived cookie) so navigating from /user/upgrade
        // back to /pricing — or returning days later — keeps them on the
        // cycle they were viewing. See BillingCyclePreference for the
        // full precedence chain. We re-persist on every request so a
        // fresh `?cycle=` always wins and stays sticky.
        $cycle = BillingCyclePreference::resolve($request);
        Cookie::queue(BillingCyclePreference::remember($cycle));
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        // The /pricing currency switcher flips USD/INR instantly client-side
        // (no page reload), so every price-bearing element needs both
        // currencies pre-computed and embedded in the Alpine payload.
        $currencies = ['USD', 'INR'];

        $taxFor = function (int $amountMinor, string $cur) use ($billing, $hasAddress) {
            if (!$hasAddress || $amountMinor === 0) return null;
            return TaxCalculator::calculate(
                [['label' => 'Plan', 'amount_minor' => $amountMinor]],
                [
                    'country'     => $billing->country,
                    'region'      => $billing->region,
                    'tax_id'      => $billing->tax_id,
                    'tax_id_kind' => $billing->tax_id_kind,
                ],
                $cur,
            );
        };

        [$plans, $packageModels] = PricingPageCache::catalog();
        $rows = $plans->map(function (Plan $p) use ($currencies, $taxFor) {
            $prices = [];
            $tax = [];
            foreach ($currencies as $cur) {
                $monthly = PricingResolver::priceForCurrency($p, $cur, 'monthly');
                $annual  = PricingResolver::priceForCurrency($p, $cur, 'annual');
                // First-term intro discount display blocks (null when none).
                $monthly['intro'] = PricingResolver::introFor($p, $cur, 'monthly', (int) ($monthly['amount_minor'] ?? 0));
                $annual['intro']  = PricingResolver::introFor($p, $cur, 'annual', (int) ($annual['amount_minor'] ?? 0));
                $prices[$cur] = ['monthly' => $monthly, 'annual' => $annual];
                $tax[$cur] = [
                    'monthly' => $taxFor((int) ($monthly['amount_minor'] ?? 0), $cur),
                    'annual'  => $taxFor((int) ($annual['amount_minor'] ?? 0), $cur),
                ];
            }
            return [
                'model'   => $p,
                'prices'  => $prices,
                'tax'     => $tax,
                'is_free' => ((int) ($prices['USD']['monthly']['amount_minor'] ?? 0)) === 0,
            ];
        });

        $packages = $packageModels
            ->map(function (CoinPackage $p) use ($currencies) {
                $priced = [];
                foreach ($currencies as $cur) {
                    $pc = PricingResolver::priceForCurrency($p, $cur, 'monthly');
                    $current = (int) ($pc['amount_minor'] ?? 0);
                    $orig = $p->originalPriceDisplay($cur, $current);
                    $priced[$cur] = [
                        'amount_minor'       => $current,
                        'formatted'          => $pc['formatted'] ?? null,
                        'original_formatted' => $orig['formatted'] ?? null,
                    ];
                }
                return [
                    'model'       => $p,
                    'prices'      => $priced,
                    'total_coins' => $p->totalCoins(),
                ];
            });

        $recommendation = PlanRecommender::for($user, $plans);

        return view('public.pricing.plans', [
            'seoKey'        => 'pricing',
            'plans'         => $rows,
            'planModels'    => $plans,
            'cycle'         => $cycle,
            'currency'      => $currency,
            'currencySource'=> $currencySource,
            'user'          => $user,
            'packages'      => $packages,
            'wallet_enabled'=> WalletService::isEnabled(),
            'recommendation'=> $recommendation,
        ]);
    }

    /**
     * Lightweight endpoint the /pricing Alpine toggle pings whenever a
     * visitor flips between Monthly and Annual without navigating away.
     * The toggle is JS-only (no page reload), so without this ping the
     * choice would only persist when the visitor clicked a CTA. Storing
     * it on every flip means a refresh — or a return visit days later —
     * lands them back on the cycle they last picked.
     *
     * Returns 204 No Content so `fetch(...)` callers don't have to
     * decode a body. The cookie is queued onto the response.
     */
    public function rememberCycle(Request $request)
    {
        $data = $request->validate([
            'cycle' => 'required|in:monthly,annual',
        ]);

        Cookie::queue(BillingCyclePreference::remember($data['cycle']));

        return response()->noContent();
    }
}
