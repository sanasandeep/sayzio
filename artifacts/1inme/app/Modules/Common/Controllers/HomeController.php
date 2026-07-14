<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Support\AiHeroExamples;
use App\Modules\Common\Support\AiStrategistExamples;
use App\Modules\Common\Support\HomePageCache;
use App\Modules\Common\Support\PlatformHosts;
use App\Modules\Common\Support\ResumePersonas;
use App\Modules\User\Models\BillingAddress;
use App\Services\PricingResolver;
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
        // The scheduled `home:warm-caches` job (HomePageCache::warm()) rebuilds
        // these same keys proactively so this miss path is a cold-boot /
        // dev-only fallback, not something real visitors normally hit.
        if ($user) {
            $payload = HomePageCache::buildPayload($user, $billing, $hasAddress, $currency);
        } else {
            // Cache as JSON (not PHP serialize) so the stored value is a plain
            // nested array — no Eloquent collections or other objects that fail
            // to rebuild from the file cache. The pricing partial wraps $plans
            // in collect(...) so a plain array is rendered identically to the
            // live Collection returned on the cache-miss path.
            $key = HomePageCache::anonPayloadKey($currency);
            $json = Cache::get($key);
            if (is_string($json) && ($decoded = json_decode($json, true)) !== null) {
                $payload = $decoded;
            } else {
                $payload = HomePageCache::buildPayload(null, null, false, $currency);
                $encoded = json_encode($payload);
                if ($encoded !== false) {
                    Cache::put($key, $encoded, HomePageCache::TTL);
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
     * Writes invalidate the key immediately via BlogPost::flushPublicCaches(),
     * and the scheduled `home:warm-caches` job repopulates it proactively.
     */
    public const FEATURED_CACHE_KEY = HomePageCache::FEATURED_CACHE_KEY;

    private function featuredBlogPosts(): \Illuminate\Support\Collection
    {
        try {
            $rows = Cache::remember(
                HomePageCache::FEATURED_CACHE_KEY,
                HomePageCache::TTL,
                fn () => HomePageCache::buildFeaturedRows()
            );

            return HomePageCache::hydrateFeaturedRows($rows);
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
     * (mirrors the home payload TTL, and is kept warm by `home:warm-caches`).
     * A cached miss is stored as an empty array, never null, so it is not
     * re-queried each hit.
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
        $live = Cache::remember(
            HomePageCache::AI_HERO_ALIASES_KEY,
            HomePageCache::TTL,
            fn () => HomePageCache::buildLiveAiHeroAliases($candidates)
        );
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
