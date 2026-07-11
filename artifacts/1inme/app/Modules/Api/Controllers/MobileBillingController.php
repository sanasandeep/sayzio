<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Services\PricingResolver;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile billing catalogue endpoints.
 *
 *   GET  /api/v1/billing/plans
 *       Active plan + addon catalogue priced for the signed-in user.
 *
 *   POST /api/v1/billing/currency
 *       Persist a manual USD ⇄ INR currency choice for the signed-in user.
 *
 * Plan upgrades and coin purchases are completed on the web pricing page
 * (opened in the OS external browser); the mobile app no longer performs
 * in-app purchases, so there is no receipt-verification endpoint here.
 */
class MobileBillingController extends Controller
{
    use ApiResponses;

    /**
     * Plan + addon catalogue, priced in the user's currency. Returned
     * shape mirrors the web upgrade page so the mobile client can
     * render features/cycles without a second round-trip.
     */
    public function plans(Request $request)
    {
        $user = $request->user();
        $currency = PricingResolver::currencyForUser($user);

        // Mirror the web /pricing page: pre-compute BOTH currencies so the
        // mobile client can flip USD ⇄ INR instantly client-side (no
        // round-trip) just like the web switcher. `currency` is the
        // currently-resolved default; `prices[CUR]` carries every cell.
        $currencies = ['USD', 'INR'];

        $pricePair = function ($priceable, string $cur): array {
            $m = PricingResolver::priceForCurrency($priceable, $cur, 'monthly');
            $a = PricingResolver::priceForCurrency($priceable, $cur, 'annual');
            // First-term intro discount blocks (null when none / not a Plan).
            $introM = $priceable instanceof Plan
                ? PricingResolver::introFor($priceable, $cur, 'monthly', (int) ($m['amount_minor'] ?? 0)) : null;
            $introA = $priceable instanceof Plan
                ? PricingResolver::introFor($priceable, $cur, 'annual', (int) ($a['amount_minor'] ?? 0)) : null;
            return [
                'monthly' => [
                    'amount_minor' => (int) ($m['amount_minor'] ?? 0),
                    'formatted'    => $m['formatted'] ?? null,
                    'intro'        => $introM,
                ],
                'annual'  => [
                    'amount_minor' => (int) ($a['amount_minor'] ?? 0),
                    'formatted'    => $a['formatted'] ?? null,
                    'intro'        => $introA,
                ],
            ];
        };

        $plans = Plan::query()
            ->public()
            ->where('status', 'active')
            ->where('is_archived', false)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function (Plan $p) use ($user, $currency, $currencies, $pricePair) {
                $prices = [];
                foreach ($currencies as $cur) {
                    $prices[$cur] = $pricePair($p, $cur);
                }
                return [
                    'id'           => $p->id,
                    'slug'         => $p->slug,
                    'name'         => $p->name,
                    'description'  => $p->description,
                    // Plain-English highlight bullets derived from the shared
                    // PremiumFeatures catalogue (same copy as the web cards),
                    // so the mobile upgrade/plan cards never render raw gating
                    // values.
                    'feature_highlights' => \App\Modules\Common\Support\PremiumFeatures::planHighlights($p),
                    'currency'     => $currency,
                    'is_default'   => (bool) $p->is_default,
                    'is_popular'   => (bool) $p->is_popular,
                    'is_current'   => $user && (int) $user->plan_id === (int) $p->id,
                    'features_map' => is_array($p->features) ? $p->features : [],
                    'trial_days'   => (int) ($p->trial_days ?? 0),
                    // Backward-compat: resolved-currency prices kept so older
                    // mobile builds keep working. `prices` is the new map.
                    'monthly'      => $prices[$currency]['monthly'],
                    'annual'       => $prices[$currency]['annual'],
                    'prices'       => $prices,
                ];
            })->all();

        $addons = Addon::query()
            ->where('status', 'active')
            ->where('is_archived', false)
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(function (Addon $a) use ($currency, $currencies, $pricePair) {
                $prices = [];
                foreach ($currencies as $cur) {
                    $prices[$cur] = $pricePair($a, $cur);
                }
                return [
                    'id'          => $a->id,
                    'slug'        => $a->slug,
                    'name'        => $a->name,
                    'description' => $a->description,
                    'type'        => $a->type,
                    'currency'    => $currency,
                    'monthly'     => $prices[$currency]['monthly'],
                    'annual'      => $prices[$currency]['annual'],
                    'prices'      => $prices,
                ];
            })->all();

        // Premium feature catalogue with plain-English copy is owned
        // by the API so mobile billing clients can render the canonical
        // feature descriptions without the bundle duplicating strings.
        $planModels = Plan::query()
            ->public()
            ->where('status', 'active')->where('is_archived', false)->get();
        $unlocks = \App\Modules\Common\Support\PremiumFeatures::unlocksByFeature($planModels);
        // Per-plan resolved cell for every feature, keyed by plan slug, so
        // mobile billing clients get the SAME number / "Unlimited" /
        // "Advanced"/"Basic" / "Custom" / on-off values without
        // re-implementing PremiumFeatures::resolveCell() on the client.
        $premiumFeatures = collect(\App\Modules\Common\Support\PremiumFeatures::catalogue())
            ->map(function ($e) use ($unlocks, $planModels) {
                $cells = [];
                foreach ($planModels as $p) {
                    $cells[$p->slug] = \App\Modules\Common\Support\PremiumFeatures::resolveCell($p, $e);
                }
                return $e + ['unlocked_by' => $unlocks[$e['key']] ?? [], 'cells' => $cells];
            })
            ->values()->all();

        return $this->ok([
            'currency'         => $currency,
            'currencies'       => $currencies,
            'plans'            => $plans,
            'addons'           => $addons,
            'premium_features' => $premiumFeatures,
        ]);
    }

    /**
     * Persist a manual USD ⇄ INR currency choice for the signed-in user,
     * mirroring the web /pricing switcher (POST /pricing/currency). The
     * choice is stored on `users.preferred_currency` (when the user has no
     * billing country — country always wins, same as web) so it follows
     * them across devices and is honoured by subsequent `priceFor()` calls.
     * The plans endpoint already returns both currencies so the UI flips
     * instantly; this only persists the pick.
     */
    public function setCurrency(Request $request)
    {
        $data = $request->validate([
            'currency' => 'required|in:USD,INR',
        ]);

        PricingResolver::rememberManualChoice($data['currency'], $request->user());

        return $this->ok([
            'currency' => $data['currency'],
        ]);
    }
}
