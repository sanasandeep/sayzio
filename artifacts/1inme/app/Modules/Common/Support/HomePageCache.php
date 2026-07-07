<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\SitePage;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Support\Facades\Cache;

/**
 * Single source for the home page's cacheable data: the anonymous payload
 * (plan teaser + link-types showcase) per currency, the featured blog-post
 * carousel rows, and the live AI-hero demo aliases.
 *
 * Two consumers share these builders:
 *   - HomeController renders from the caches (rebuilding lazily on a miss);
 *   - the scheduled `home:warm-caches` job (WarmHomePageCaches) rebuilds
 *     them proactively every few minutes so no anonymous visitor ever pays
 *     the full rebuild over the cross-region RDS (~4s+ in production, where
 *     DB_PERSISTENT=false adds a fresh SSL connect per query batch).
 *
 * Cache-freshness contract: the request path writes with TTL (5 min,
 * mirrors the AppSetting cache TTL). The warmer OVERWRITES the same keys
 * with freshly built data on every run but uses the longer WARM_TTL so a
 * single missed/slow warm run doesn't open an expiry gap before the next
 * one. Admin edits still take effect within minutes two ways: explicit
 * flushes (e.g. BlogPost::flushPublicCaches()) clear a key immediately, and
 * the warmer rebuilds everything from the DB on each tick anyway.
 *
 * Only plain-array data is cached (JSON for the payload, attribute arrays
 * for the featured posts) — never serialized Eloquent models, which don't
 * survive the file cache (__PHP_Incomplete_Class on unserialize).
 */
class HomePageCache
{
    /** Anonymous home payload cache key prefix; suffixed with the currency. */
    public const ANON_PAYLOAD_PREFIX = 'home:anon:payload:';

    /** Featured blog-post carousel rows (plain attribute arrays). */
    public const FEATURED_CACHE_KEY = 'home:featured_blog_posts';

    /** Live, publicly viewable AI-hero demo aliases. */
    public const AI_HERO_ALIASES_KEY = 'home:ai_hero_demo_aliases';

    /** TTL for lazily rebuilt caches on the request path (seconds). */
    public const TTL = 300;

    /**
     * TTL used when the scheduled warmer writes (seconds). Longer than the
     * warm cadence so one failed/slow run can't leave a visitor-facing gap;
     * content freshness still comes from the warmer overwriting each run.
     */
    public const WARM_TTL = 900;

    /**
     * Every currency the anonymous payload is cached per. Matches the set
     * PricingResolver::currencyForUser() can resolve for a signed-out
     * visitor (geo/IP based), so the warmer covers every key the request
     * path may read.
     *
     * @var array<int,string>
     */
    public const CURRENCIES = ['USD', 'INR'];

    /**
     * Rebuild every home-page cache from the database and overwrite the
     * stored copies. Called by the scheduled warmer; safe to call any time
     * (it only writes fresher data). Returns a small summary for logging.
     *
     * @return array<string,mixed>
     */
    public static function warm(): array
    {
        // Each section is fault-isolated: a failure in one builder must not
        // abort the later warms in the same run (visitors still fall back to
        // the lazy request-path rebuild for whatever stayed cold).
        $summary = [
            'payload_currencies' => [],
            'featured_posts' => 0,
            'ai_hero_aliases' => 0,
            'branding_hosts' => [],
            'errors' => [],
        ];

        foreach (self::CURRENCIES as $currency) {
            try {
                $payload = self::buildPayload(null, null, false, $currency);
                $encoded = json_encode($payload);
                if ($encoded !== false) {
                    Cache::put(self::ANON_PAYLOAD_PREFIX . $currency, $encoded, self::WARM_TTL);
                    $summary['payload_currencies'][] = $currency;
                }
            } catch (\Throwable $e) {
                $summary['errors'][] = self::reportWarmFailure('payload:' . $currency, $e);
            }
        }

        try {
            $rows = self::buildFeaturedRows();
            Cache::put(self::FEATURED_CACHE_KEY, $rows, self::WARM_TTL);
            $summary['featured_posts'] = count($rows);
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('featured_posts', $e);
        }

        try {
            $aliases = self::buildLiveAiHeroAliases(self::aiHeroCandidateAliases());
            Cache::put(self::AI_HERO_ALIASES_KEY, $aliases, self::WARM_TTL);
            $summary['ai_hero_aliases'] = count($aliases);
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('ai_hero_aliases', $e);
        }

        // Domain branding is looked up per host on every page render (global
        // brand-logo partial). Warm the per-host cache for each platform host
        // a real visitor can arrive on. (warmHost() already fails closed per
        // host on DB errors, returning false.)
        try {
            foreach (PlatformHosts::platformDomains() as $host) {
                if (DomainBranding::warmHost($host, self::WARM_TTL)) {
                    $summary['branding_hosts'][] = $host;
                }
            }
        } catch (\Throwable $e) {
            $summary['errors'][] = self::reportWarmFailure('branding_hosts', $e);
        }

        return $summary;
    }

    /**
     * Log a warm-section failure and return a short label for the summary.
     */
    private static function reportWarmFailure(string $section, \Throwable $e): string
    {
        \Illuminate\Support\Facades\Log::warning('home:warm-caches section failed', [
            'section' => $section,
            'error' => $e->getMessage(),
        ]);

        return $section;
    }

    /**
     * Build the cacheable home-page data (plan teaser cards + the "what you
     * can create" link-types showcase). Returns only plain-array data so the
     * anonymous-visitor path can safely cache it. Featured blog posts are
     * intentionally excluded (cached separately as attribute arrays).
     *
     * @param  \App\Modules\User\Models\User|null  $user
     * @param  \App\Modules\User\Models\BillingAddress|null  $billing
     * @return array{plans:\Illuminate\Support\Collection,linkTypes:array}
     */
    public static function buildPayload($user, $billing, bool $hasAddress, string $currency): array
    {
        // Landing-page pricing is intentionally just two cards now:
        // the always-free entry plan and the curator-flagged "popular"
        // plan. The full plan grid lives at /pricing — keeping the
        // landing page focused removes choice paralysis above the fold.
        $allPlans = Plan::where('status', 'active')
            ->where('is_archived', false)
            ->public()
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
                $annual = PricingResolver::priceFor($p, $user, 'annual');
                // Annual teaser payload: surfaced whenever both monthly AND
                // annual rows exist for the resolved currency. The savings
                // percentage is only meaningful when the annual price is
                // genuinely cheaper than 12× monthly — otherwise we still
                // show "Billed annually at X/yr" so visitors comparing on
                // yearly cost always see the number, with `percent` = 0 so
                // the view can hide the discount label.
                // Stored in MINOR units so we don't reintroduce float math.
                $annualTeaser = null;
                $monthlyMinor = (int) $monthly['amount_minor'];
                $annualMinor = (int) $annual['amount_minor'];
                if ($monthlyMinor > 0 && $annualMinor > 0) {
                    $fullYearMinor = $monthlyMinor * 12;
                    $savedMinor = max(0, $fullYearMinor - $annualMinor);
                    $annualTeaser = [
                        'percent'         => $fullYearMinor > 0
                            ? (int) round(($savedMinor / $fullYearMinor) * 100)
                            : 0,
                        'annual'          => $annual,
                        'saved_formatted' => PricingResolver::money($savedMinor, $annual['currency']),
                    ];
                }
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
                // Both-currency monthly prices so the landing teaser's
                // currency switcher flips USD/INR instantly client-side
                // (no page reload) — mirroring /pricing and /user/upgrade.
                $pricesByCur = [];
                foreach (self::CURRENCIES as $cur) {
                    $pricesByCur[$cur] = ['monthly' => PricingResolver::priceForCurrency($p, $cur, 'monthly')];
                }

                return [
                    'name'        => $p->name,
                    'description' => $p->description,
                    'features'    => $p->features ?? [],
                    'is_free'     => $monthly['amount_minor'] <= 0,
                    'is_popular'  => (bool) $p->is_popular,
                    'monthly'     => $monthly,
                    'annual_teaser' => $annualTeaser,
                    'tax'         => $tax,
                    'prices'      => $pricesByCur,
                ];
            });

        // The homepage pricing block is designed around two side-by-side
        // cards (Free + Popular). When the DB doesn't have both — empty
        // catalogue, single seeded plan, or every paid plan archived —
        // top up the missing card(s) with sensible static defaults so
        // the section is never blank or lopsided.
        $fallbackCurrency = $currency ?: 'USD';
        $hasFree = $plans->contains(fn ($p) => !empty($p['is_free']));
        $hasPaid = $plans->contains(fn ($p) => empty($p['is_free']));
        if (!$hasFree) {
            $plans->prepend([
                'name'        => 'Free',
                'description' => 'Forever free — no card required',
                'features'    => ['max_links' => 5, 'max_biolinks' => 1, 'storage_limit_mb' => 100],
                'is_free'     => true,
                'is_popular'  => false,
                'monthly'     => ['amount_minor' => 0, 'currency' => $fallbackCurrency, 'formatted' => 'FREE'],
                'annual_teaser' => null,
                'tax'         => null,
                'prices'      => [
                    'USD' => ['monthly' => ['amount_minor' => 0, 'currency' => 'USD', 'formatted' => 'FREE']],
                    'INR' => ['monthly' => ['amount_minor' => 0, 'currency' => 'INR', 'formatted' => 'FREE']],
                ],
            ]);
        }
        if (!$hasPaid) {
            $plans->push([
                'name'        => 'Pro',
                'description' => 'Per user, billed monthly',
                'features'    => ['max_links' => -1, 'max_biolinks' => -1, 'storage_limit_mb' => 5000, 'contacts_max' => -1],
                'is_free'     => false,
                'is_popular'  => true,
                'monthly'     => ['amount_minor' => 900, 'currency' => $fallbackCurrency, 'formatted' => PricingResolver::money(900, $fallbackCurrency)],
                'annual_teaser' => null,
                'tax'         => null,
                'prices'      => [
                    'USD' => ['monthly' => ['amount_minor' => 900, 'currency' => 'USD', 'formatted' => PricingResolver::money(900, 'USD')]],
                    'INR' => ['monthly' => ['amount_minor' => 900, 'currency' => 'INR', 'formatted' => PricingResolver::money(900, 'INR')]],
                ],
            ]);
        }
        $plans = $plans->values();

        // "What you can create" link-types showcase. Admin-editable from the
        // `home` SitePage row under extra.link_types; falls back to the shared
        // SitePagesContent defaults when unset (or the table isn't migrated).
        $linkTypes = SitePagesContent::homeLinkTypesDefault();
        try {
            $homePage = SitePage::where('slug', 'home')->first();
            $saved = $homePage
                ? SitePagesContent::normalizeHomeLinkTypes((array) data_get($homePage->extra, 'link_types', []))
                : [];
            if (!empty($saved)) {
                $linkTypes = $saved;
            }
        } catch (\Throwable $e) {
            // SitePages migration not run yet — use defaults.
        }

        return compact('plans', 'linkTypes');
    }

    /**
     * Fetch the featured-post rows from the DB as PLAIN ATTRIBUTE ARRAYS
     * (post + category + author) — the cacheable representation. Rehydrate
     * with {@see hydrateFeaturedRows()} on read.
     *
     * @return array<int,array{post:array,category:?array,author:?array}>
     */
    public static function buildFeaturedRows(): array
    {
        return \App\Modules\Common\Models\BlogPost::published()
            ->featured()
            ->with('category', 'author')
            ->orderByRaw("CASE WHEN featured_slot = 'hero' THEN 0 WHEN featured_slot = 'carousel' THEN 1 ELSE 2 END")
            ->orderByDesc('published_at')
            ->take(3)
            ->get()
            ->map(fn ($p) => [
                'post'     => $p->getAttributes(),
                'category' => $p->category?->getAttributes(),
                'author'   => $p->author?->getAttributes(),
            ])
            ->all();
    }

    /**
     * Rehydrate cached featured-post rows into Eloquent models with their
     * category/author relations set (never serialized models — they don't
     * survive the file cache).
     *
     * @param  array<int,array{post:array,category:?array,author:?array}>  $rows
     */
    public static function hydrateFeaturedRows(array $rows): \Illuminate\Support\Collection
    {
        return collect($rows)->map(function (array $row) {
            $post = \App\Modules\Common\Models\BlogPost::query()
                ->hydrate([$row['post']])
                ->first();
            $post->setRelation('category', !empty($row['category'])
                ? \App\Modules\Common\Models\BlogCategory::query()->hydrate([$row['category']])->first()
                : null);
            $post->setRelation('author', !empty($row['author'])
                ? \App\Modules\Admin\Models\Admin::query()->hydrate([$row['author']])->first()
                : null);

            return $post;
        });
    }

    /**
     * Every candidate demo alias across all AI-hero examples (deduplicated),
     * as an alias => true set.
     *
     * @return array<string,true>
     */
    public static function aiHeroCandidateAliases(): array
    {
        $candidates = [];
        foreach (AiHeroExamples::all() as $ex) {
            foreach ((array) ($ex['demoAliases'] ?? []) as $alias) {
                $candidates[$alias] = true;
            }
        }

        return $candidates;
    }

    /**
     * Which of the candidate demo pages are live and publicly viewable right
     * now. Degrades to an empty list if the links table isn't migrated yet.
     *
     * @param  array<string,true>  $candidates
     * @return array<int,string>
     */
    public static function buildLiveAiHeroAliases(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        try {
            return \App\Modules\User\Models\Link::query()
                ->whereIn('alias', array_keys($candidates))
                ->where('is_active', true)
                ->where('visibility', 'public')
                ->pluck('alias')
                ->all();
        } catch (\Throwable $e) {
            // Links table not migrated yet — degrade to "no live demos".
            return [];
        }
    }
}
