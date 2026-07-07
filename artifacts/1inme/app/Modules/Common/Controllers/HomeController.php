<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\AiHeroExamples;
use App\Modules\Common\Support\AiStrategistExamples;
use App\Modules\Common\Support\PlatformHosts;
use App\Modules\Common\Support\ResumePersonas;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\BillingAddress;
use App\Services\PricingResolver;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        // The short-link brand domain (1in.me) is dedicated to links/profiles;
        // its bare root has no home of its own. Permanently redirect the root to
        // the canonical primary brand domain (sayzio.app) so search engines
        // consolidate there. Only recognised non-primary brand domains redirect:
        // dev/preview hosts (Replit dev domain, localhost) and sayzio.app itself
        // keep rendering the marketing page below. Short links and profiles are
        // served by the catch-all route, not here, so they are unaffected.
        if (PlatformHosts::isNonPrimaryBrandDomain($request->getHost())) {
            return redirect()->away('https://' . PlatformHosts::primaryBrandDomain() . '/', 301);
        }

        // Resolve the visitor via the WEB guard explicitly: this is a public
        // web route, but when an admin-guard session is active (admin browsing
        // the marketing site, or actingAs(admin) in tests) the default guard
        // returns an Admin model — which PricingResolver::currencyForUser()
        // (?User typed) rejects with a TypeError, 500ing the home page.
        $user = $request->user('web');
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        // The plan teaser + link-types showcase issue a dozen-plus queries, and
        // over the cross-region RDS that is ~8-9s per render. For anonymous
        // visitors — every signed-out human plus the platform readiness probe —
        // that payload only varies by resolved currency, so cache it for 5
        // minutes (mirrors the AppSetting cache TTL) to keep the home page and
        // the health check fast. Authenticated users always compute fresh
        // because pricing/tax is per-user. Only plain-array data is cached here:
        // featured blog posts are Eloquent models (which don't survive the file
        // cache — "incomplete object" on unserialize) so they load fresh below.
        if ($user) {
            $payload = $this->buildHomePayload($user, $billing, $hasAddress, $currency);
        } else {
            // Cache as JSON (not PHP serialize) so the stored value is a plain
            // nested array — no Eloquent collections or other objects that fail
            // to rebuild from the file cache. The pricing partial wraps $plans
            // in collect(...) so a plain array is rendered identically to the
            // live Collection returned on the cache-miss path.
            $key = 'home:anon:payload:' . $currency;
            $json = Cache::get($key);
            if (is_string($json) && ($decoded = json_decode($json, true)) !== null) {
                $payload = $decoded;
            } else {
                $payload = $this->buildHomePayload(null, null, false, $currency);
                $encoded = json_encode($payload);
                if ($encoded !== false) {
                    Cache::put($key, $encoded, 300);
                }
            }
        }

        $plans = $payload['plans'];
        $linkTypes = $payload['linkTypes'];
        $featuredBlogPosts = $this->featuredBlogPosts();

        // Example pages the AI-builder demo cycles through (and the resting
        // no-JS state). The static data bakes in asset() URLs that vary safely
        // per host; we then attach a live "See this live" demoUrl per example
        // (resolved against actually-published demo pages, briefly cached).
        $aiHeroExamples = $this->withAiHeroDemoUrls(AiHeroExamples::all());

        // Personas the home résumé-builder demo cycles through (designer,
        // developer, marketer, student) to show the builder works for any
        // career. The first entry is also the resting/no-JS state. Static data
        // (no per-request variance), mirrors AiHeroExamples.
        $resumePersonas = ResumePersonas::all();

        // Example goals the AI Marketing Strategist demo cycles through (and the
        // resting no-JS state). Static data (no per-request variance), mirrors
        // AiHeroExamples.
        $aiStrategistExamples = AiStrategistExamples::all();

        return view('home', compact('plans', 'currency', 'currencySource', 'user', 'hasAddress', 'featuredBlogPosts', 'linkTypes', 'aiHeroExamples', 'resumePersonas', 'aiStrategistExamples'));
    }

    /**
     * Build the cacheable home-page data (plan teaser cards + the "what you can
     * create" link-types showcase). Returns only plain-array data so the
     * anonymous-visitor path can safely cache it — see index(). Featured blog
     * posts are intentionally excluded (Eloquent models don't survive the file
     * cache); fetch them via featuredBlogPosts() instead.
     *
     * @return array{plans:\Illuminate\Support\Collection,linkTypes:array}
     */
    private function buildHomePayload($user, $billing, bool $hasAddress, string $currency): array
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
                foreach (['USD', 'INR'] as $cur) {
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
     * Featured-post carousel for the landing page. The marketing seeder flags
     * the top 3 posts with `is_featured_home` (across both `hero` and `carousel`
     * slots) — we surface all of them in a single small carousel/grid below the
     * fold so new content gets immediate visibility from the homepage.
     *
     * Cached for 5 minutes as PLAIN ATTRIBUTE ARRAYS (post + category + author),
     * then rehydrated into Eloquent models on read — never as serialized models,
     * which don't survive the file cache (incomplete-object on unserialize; same
     * pattern as DomainBranding::currentGlobalDomain()). This matters in
     * production: with DB_PERSISTENT=false, this was the home page's ONLY
     * per-request query for anonymous visitors, and the fresh SSL connect to the
     * cross-region RDS it dragged in cost ~3s of TTFB on every warm render.
     * Writes invalidate the key immediately via BlogPost::flushPublicCaches().
     */
    public const FEATURED_CACHE_KEY = 'home:featured_blog_posts';

    private function featuredBlogPosts(): \Illuminate\Support\Collection
    {
        try {
            $rows = Cache::remember(self::FEATURED_CACHE_KEY, 300, function () {
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
            });

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
        } catch (\Throwable $e) {
            // Blogs migration not run yet — silently skip the carousel.
            return collect();
        }
    }

    /**
     * Attach a "See this live" `demoUrl` to each AI-hero example.
     *
     * Each example may list `demoAliases` (priority-ordered slugs of seeded
     * `/demos` pages). We resolve the FIRST alias that is actually live and
     * publicly viewable — preferring a rich industry demo, falling back to the
     * always-seeded `demo-type-*` explainer — so the link never 404s and an
     * example with no live demo simply carries no `demoUrl` (the link hides).
     *
     * The alias→live set is the same on every render, so it is cached briefly
     * (mirrors the home payload TTL) to keep this query-light. A cached miss is
     * stored as an empty array, never null, so it is not re-queried each hit.
     *
     * @param  array<int, array<string, mixed>>  $examples
     * @return array<int, array<string, mixed>>
     */
    private function withAiHeroDemoUrls(array $examples): array
    {
        // Every candidate alias across all examples (deduplicated).
        $candidates = [];
        foreach ($examples as $ex) {
            foreach ((array) ($ex['demoAliases'] ?? []) as $alias) {
                $candidates[$alias] = true;
            }
        }
        if (empty($candidates)) {
            return $examples;
        }

        // Which of those demo pages are live and publicly viewable right now?
        $live = Cache::remember('home:ai_hero_demo_aliases', 300, function () use ($candidates) {
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
        });
        $liveSet = array_flip($live);

        foreach ($examples as &$ex) {
            foreach ((array) ($ex['demoAliases'] ?? []) as $alias) {
                if (isset($liveSet[$alias])) {
                    $ex['demoUrl'] = url('/' . $alias);
                    break;
                }
            }
            unset($ex['demoAliases']);
        }
        unset($ex);

        return $examples;
    }
}
