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
    /**
     * Admin-selected homepage design. 'classic' renders the original
     * full-size landing page (home.blade.php); 'compact' renders the
     * lighter Variant B (home-b.blade.php). Stored as an AppSetting so
     * the marketing team can flip the live design from the admin panel;
     * takes effect within the AppSetting cache window (~5 min) without
     * a deploy. Any unknown stored value falls back to 'classic'.
     */
    public const DESIGN_SETTING_KEY = 'marketing_home_design';

    /**
     * Homepage design registry. Every design shares the classic animated
     * shell (hero, homeEnhance, marketingAnimScan); each one only swaps
     * the deferred below-the-fold fragment. All designs except 'classic'
     * are intentionally short and keyword-focused; their SEO title /
     * description / keywords override the generic home SEO defaults so
     * the picked design also targets its keyword cluster in search.
     */
    public const DESIGNS = [
        'classic' => [
            'fragment' => 'home.deferred-sections',
            'label' => 'Classic (full page)',
            'seo' => null,
        ],
        'compact' => [
            'fragment' => 'home.deferred-sections-b',
            'label' => 'Compact (short classic)',
            'seo' => [
                'title' => 'AI Link in Bio, Short Links & QR Codes',
                'description' => 'Build your link in bio page, short links and QR codes with Sayzio, the AI-powered bio link platform. Free to start, no card required.',
                'keywords' => 'link in bio, bio link, short links, qr code, ai page builder',
            ],
        ],
        'ai' => [
            'fragment' => 'home.deferred-sections-ai',
            'label' => 'AI builder (short)',
            'seo' => [
                'title' => 'Free AI Link in Bio & AI Page Builder',
                'description' => 'Describe your page and Sayzio\'s AI builds your link in bio, bio link page, short links and QR codes in seconds. Free AI page builder, no card required.',
                'keywords' => 'ai link in bio, ai page builder, ai website builder free, link in bio maker',
            ],
        ],
        'creators' => [
            'fragment' => 'home.deferred-sections-creators',
            'label' => 'Creators (short)',
            'seo' => [
                'title' => 'Link in Bio for Creators, Free Bio Link Page',
                'description' => 'One bio link page for Instagram, TikTok and YouTube. Sell products, grow subscribers and share every link from one free creator link in bio page.',
                'keywords' => 'link in bio, bio link page, link in bio for instagram, creator link page',
            ],
        ],
        'shortlinks' => [
            'fragment' => 'home.deferred-sections-shortlinks',
            'label' => 'Short links & QR (short)',
            'seo' => [
                'title' => 'Free URL Shortener with Branded Links & QR Code Generator',
                'description' => 'Shorten long URLs into branded short links on your own domain, generate QR codes and track every click with real-time link analytics. Free to start.',
                'keywords' => 'url shortener, free url shortener, qr code generator, branded short links, link tracking',
            ],
        ],
        'business' => [
            'fragment' => 'home.deferred-sections-business',
            'label' => 'Small business (short)',
            'seo' => [
                'title' => 'Digital Business Card & Business Mini-Site Builder',
                'description' => 'Create a digital business card, lead capture forms and a business landing page on your own custom domain, with an AI receptionist answering customers 24/7.',
                'keywords' => 'digital business card, business landing page, mini website builder, lead capture forms',
            ],
        ],
        'growth' => [
            'fragment' => 'home.deferred-sections-growth',
            'label' => 'Analytics & growth (short)',
            'seo' => [
                'title' => 'Link Analytics & Click Tracking for Short Links and Bio Pages',
                'description' => 'Track clicks, countries, devices and referrers for your short links and bio link page. Real-time link analytics, UTM insights and audience growth tools.',
                'keywords' => 'link analytics, click tracking, link tracker, utm tracking, audience growth',
            ],
        ],
    ];

    public static function activeDesign(): string
    {
        $design = (string) \App\Modules\Admin\Models\AppSetting::get(self::DESIGN_SETTING_KEY, 'classic');

        return array_key_exists($design, self::DESIGNS) ? $design : 'classic';
    }

    /**
     * SEO override (title/description/keywords) for the active design,
     * or null when the design uses the generic home SEO (classic).
     */
    public static function activeDesignSeo(): ?array
    {
        return self::DESIGNS[self::activeDesign()]['seo'];
    }

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

        // The initial response is intentionally lean: header + hero + primary
        // CTA only. Everything below the fold (plan teaser, link-types
        // showcase, AI demos, featured posts…) is rendered by sections()
        // and fetched by the homepage loader right after first paint, so the
        // initial render needs NO plan/link-type/blog queries at all.
        // Both designs share the classic animated shell (hero, homeEnhance,
        // marketingAnimScan); "compact" only swaps in a trimmed deferred
        // fragment (home/deferred-sections-b) with fewer, combined sections.
        return view('home');
    }

    /**
     * Deferred below-the-fold home sections. Fetched by the homepage loader
     * (see the placeholder + script in home.blade.php) as an HTML fragment.
     *
     * Everyone — anonymous AND logged-in — renders from the warmed
     * per-currency payload cache: the plan teaser only varies by currency
     * (both currencies' prices are embedded for the client-side switcher),
     * so logged-in visitors no longer pay the dozen-plus plan/link-type
     * queries against the cross-region RDS per request. The only per-user
     * overlay is the tax line, computed on top of the cached amounts for
     * users with a billing address (a single small jurisdiction lookup).
     */
    public function sections(Request $request)
    {
        // WEB guard explicitly — see index() for why (admin sessions).
        $user = $request->user('web');
        $currency = PricingResolver::currencyForUser($user);
        $currencySource = PricingResolver::currencySourceForUser($user);

        $billing = $user ? BillingAddress::where('user_id', $user->id)->first() : null;
        $hasAddress = $billing && !empty($billing->country);

        $payload = $this->cachedPayload($currency);

        $plans = $payload['plans'];
        if ($hasAddress) {
            $plans = HomePageCache::applyTaxOverlay($plans, $billing, $currency);
        }
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

        $fragment = self::DESIGNS[self::activeDesign()]['fragment'];

        return view($fragment, compact('plans', 'currency', 'currencySource', 'user', 'hasAddress', 'featuredBlogPosts', 'linkTypes', 'aiHeroExamples', 'resumePersonas', 'aiStrategistExamples'));
    }

    /**
     * The per-currency cached home payload (plan teaser + link types),
     * shared by every visitor. Cached as JSON (not PHP serialize) so the
     * stored value is a plain nested array — no Eloquent collections or
     * other objects that fail to rebuild from the file cache. The pricing
     * partial wraps $plans in collect(...) so a plain array renders
     * identically to the live Collection built on a cache miss. The
     * scheduled `home:warm-caches` job (HomePageCache::warm()) rebuilds
     * these same keys proactively so this miss path is a cold-boot /
     * dev-only fallback, not something real visitors normally hit.
     *
     * @return array{plans:mixed,linkTypes:array}
     */
    private function cachedPayload(string $currency): array
    {
        $key = HomePageCache::anonPayloadKey($currency);
        $json = Cache::get($key);
        if (is_string($json) && ($decoded = json_decode($json, true)) !== null) {
            return $decoded;
        }

        $payload = HomePageCache::buildPayload(null, null, false, $currency);
        $encoded = json_encode($payload);
        if ($encoded !== false) {
            Cache::put($key, $encoded, HomePageCache::TTL);
        }

        return $payload;
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
