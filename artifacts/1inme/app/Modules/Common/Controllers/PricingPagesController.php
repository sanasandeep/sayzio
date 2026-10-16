<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\Plan;
use Database\Seeders\PlansAndAddonsSeeder;
use App\Modules\Common\Support\PlanSetRoller;
use App\Modules\Common\Support\PlanVariantResolver;
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

        // Reduce each plan category to the ONE variant this visitor sees,
        // BEFORE anything downstream touches the collection. The page embeds
        // its catalogue as JSON for the client-side matcher, so filtering in
        // the browser would leave every sibling price sitting in the page
        // source. Everything after this line — bands, the compare table, the
        // curator catalogue — sees representatives only.
        $plans = (new PlanVariantResolver($request))->representatives($plans);
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

        // ---- Grouping -------------------------------------------------
        // /pricing used to be one horizontal rail of every plan, which is
        // what "too many plans" actually looked like. The rail now renders
        // once per group, and the groups that only a team or a developer
        // wants sit behind a single door link rather than in front of a solo
        // creator. ?all=1 opens them on the same page.
        //
        // The map is PlansAndAddonsSeeder::publicPlanGroups() — code, not
        // plan metadata, so an admin edit cannot silently re-shuffle the page.
        $showAllGroups = $request->boolean('all');
        $groupDefs = PlansAndAddonsSeeder::publicPlanGroups();
        $rowsBySlug = $rows->keyBy(fn (array $r) => $r['model']->slug);

        $planBands = [];
        $doorBands = [];
        foreach ($groupDefs as $key => $def) {
            $banded = collect($def['slugs'])
                ->map(fn (string $slug) => $rowsBySlug->get($slug))
                ->filter()
                ->values();

            if ($banded->isEmpty()) {
                continue;
            }

            $entry = [
                'key'   => $key,
                'title' => $def['title'],
                'blurb' => $def['blurb'] ?? null,
                'rows'  => $banded,
            ];

            if (!empty($def['on_pricing']) || $showAllGroups) {
                $planBands[] = $entry;
            } else {
                $doorBands[] = $entry;
            }
        }

        // A plan that is live but missing from the map still has to be sold.
        // Dropping it would reproduce, inverted, the exact bug the map exists
        // to prevent (Bio Lite existed in production and no code knew it), so
        // ungrouped plans get their own band and PlanGroupCoverageTest fails
        // the build until someone files them properly.
        $groupedSlugs = collect($groupDefs)->pluck('slugs')->flatten()->all();
        $ungrouped = $rows
            ->reject(fn (array $r) => in_array($r['model']->slug, $groupedSlugs, true))
            ->values();
        if ($ungrouped->isNotEmpty()) {
            $planBands[] = [
                'key'   => 'ungrouped',
                'title' => 'More plans',
                'blurb' => null,
                'rows'  => $ungrouped,
            ];
        }

        $allPlanBands = array_merge($planBands, $doorBands);

        // ---- The visitor's set --------------------------------------
        // Free plus SET_SIZE paid plans, one per category, chosen by this
        // visitor's roll. The same roll always yields the same set, so a
        // refresh or a return visit is identical; "show me another set"
        // advances the roll and only then do the names and prices move.
        $roller = new PlanSetRoller($request);
        $freeRow = $rows->first(fn (array $r) => !empty($r['is_free']));
        $paidByCategory = $rows
            ->reject(fn (array $r) => !empty($r['is_free']))
            ->keyBy(fn (array $r) => $r['model']->categoryKey());

        // Before anyone presses Curate, only categories whose band is on the
        // pricing page are eligible: a solo creator landing on "Enterprise
        // API" as one of their first three plans is what the short page
        // exists to prevent.
        //
        // Pressing Curate is the visitor asking to see more, so from roll 1
        // every category is in play and the page opens out to one plan from
        // each. Pressing again keeps it open and rolls the variants, so the
        // names and prices move while the shape stays put.
        $frontCategories = $paidByCategory
            ->filter(function (array $row) use ($groupDefs) {
                foreach ($groupDefs as $def) {
                    if (in_array($row['model']->slug, $def['slugs'], true)) {
                        return !empty($def['on_pricing']);
                    }
                }

                // A plan nobody has grouped yet stays eligible: invisible is a
                // worse failure than slightly out of place.
                return true;
            })
            ->keys()
            ->all();

        $curated = $roller->current() > 0;
        $eligible = $curated ? $paidByCategory->keys()->all() : $frontCategories;
        $setCategories = $roller->categories($eligible, null, $curated ? count($eligible) : PlanSetRoller::SET_SIZE);
        // Cheapest first. The roll picks WHICH categories appear; it must not
        // decide the order they are read in, or a visitor meets $25 before $8
        // and the page stops looking like a ladder.
        $amount = fn (array $row) => (int) ($row['prices']['USD']['monthly']['amount_minor'] ?? 0);
        $primary = collect([$freeRow])
            ->concat(
                collect($setCategories)
                    ->map(fn (string $c) => $paidByCategory->get($c))
                    ->filter()
                    ->sortBy($amount)
                    ->values()
            )
            ->filter()
            ->values();

        $primaryIds = $primary->pluck('model.id')->all();
        $hidden = $rows->filter(fn ($row) => in_array($row['model']->slug, $groupedSlugs, true)
            && !in_array($row['model']->id, $primaryIds, true))->values();
        $planBands = $primary->isEmpty() ? [] : [[
            'key' => 'essentials', 'title' => 'Choose your plan',
            'blurb' => 'Start free. Build your bio. Grow your business.', 'rows' => $primary,
        ]];
        // Keep newly added, unclassified plans discoverable until they are grouped.
        if ($ungrouped->isNotEmpty()) {
            $planBands[] = ['key' => 'ungrouped', 'title' => 'More plans', 'blurb' => null, 'rows' => $ungrouped];
        }
        $doorBands = $hidden->isEmpty() ? [] : [['rows' => $hidden]];
        $displayRows = $primary->concat($ungrouped)->values();

        $recommendation = PlanRecommender::for($user, $displayRows->pluck('model'));

        return view('public.pricing.plans', [
            'seoKey'        => 'pricing',
            'plans'         => $displayRows,
            'planModels'    => $displayRows->pluck('model'),
            'cycle'         => $cycle,
            'currency'      => $currency,
            'currencySource'=> $currencySource,
            'user'          => $user,
            'packages'      => $packages,
            'wallet_enabled'=> WalletService::isEnabled(),
            'recommendation'=> $recommendation,
            'anotherSetUrl' => route('site.pricing.another'),
            'planGrid'      => $curated,
            'planRoll'      => $roller->current(),
            'planBands'     => $planBands,
            'doorBands'     => $doorBands,
            'showAllGroups' => $showAllGroups,
            'catalogueRows' => $rows,
            'allPlanBands' => $allPlanBands,
            'pricingNames' => ['free' => 'Free', 'bio-lite' => 'Bio'],
            'curationCatalogue' => $rows->map(fn ($row) => [
                'id' => $row['model']->id, 'slug' => $row['model']->slug,
                'name' => match ($row['model']->slug) { 'free' => 'Free', 'bio-lite' => 'Bio', default => $row['model']->name },
                'prices' => $row['prices'],
                'features' => array_intersect_key($row['model']->features ?? [], array_flip([
                    'max_biolinks', 'max_links', 'max_seats_per_workspace', 'custom_domains', 'paid_forms', 'api_access',
                ])),
            ])->values(),
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

    /**
     * "Show me another set": advance the roll and come back.
     *
     * A plain redirect rather than a JSON endpoint so the page re-renders
     * through exactly the same path as a normal visit. One way for a set to
     * be built means the thing a visitor sees after clicking cannot drift
     * from the thing they would have seen by typing the URL.
     */
    public function anotherSet(Request $request)
    {
        (new PlanSetRoller($request))->advance();

        return redirect()
            ->route('site.pricing', $request->only(['cycle', 'all']))
            ->withFragment('plans');
    }
}
