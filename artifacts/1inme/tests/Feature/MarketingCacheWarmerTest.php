<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\SiteStat;
use App\Modules\Common\Controllers\BlogController;
use App\Modules\Common\Controllers\CreatorsController;
use App\Modules\Common\Controllers\PricingPagesController;
use App\Modules\Common\Controllers\SitePageController;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\SiteAssistantPageHint;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Services\BlogCtaComposer;
use App\Modules\Common\Services\EventsHeroBandComposer;
use App\Modules\Common\Support\MarketingPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for the marketing-page half of the scheduled cache
 * warmer (`home:warm-caches` → MarketingPageCache::warm()).
 *
 * Pricing, features (and every SitePage-backed marketing page), creators,
 * demos and blogs cache their DB reads as plain attribute arrays, but were
 * visitor-primed only — the first visitor after a deploy/restart paid a
 * multi-second cold rebuild over the distant RDS. The warmer now rebuilds
 * those caches proactively, alongside the home-page ones.
 *
 * Guards: the command populates every marketing cache key, payloads contain
 * no serialized objects (file cache → __PHP_Incomplete_Class), a post-warm
 * render is served from the caches, and rewarming refreshes stale content.
 */
class MarketingCacheWarmerTest extends TestCase
{
    use RefreshDatabase;

    private function forgetMarketingCaches(): void
    {
        Cache::forget(PricingPagesController::CATALOG_CACHE_KEY);
        Cache::forget(CreatorsController::DEFAULT_CACHE_KEY);
        foreach (CreatorsController::trendingCarouselVariants() as [$showAdult, $onlyAdult]) {
            Cache::forget(CreatorsController::trendingCarouselCacheKey($showAdult, $onlyAdult));
        }
        Cache::forget(CreatorsController::POPULAR_TAGS_CACHE_KEY);
        Cache::forget(SitePageController::DEMOS_CACHE_KEY);
        Cache::forget(BlogController::INDEX_CACHE_KEY);
        foreach (SitePage::query()->pluck('slug') as $slug) {
            Cache::forget(SitePage::SLUG_CACHE_PREFIX . $slug);
        }
        Cache::forget(EventsHeroBandComposer::CACHE_KEY);
        Cache::forget(BlogCtaComposer::CACHE_KEY);
        Cache::forget(SiteStat::ACTIVE_CACHE_KEY);
        Cache::forget(SiteAssistantPageHint::SURFACE_CACHE_PREFIX . 'marketing');
        Cache::forget(SiteAssistantPageHint::SURFACE_CACHE_PREFIX . 'app');
        foreach (MarketingPageCache::LAYOUT_SETTING_KEYS as $key) {
            Cache::forget('app_setting:' . $key);
        }
    }

    public function test_warm_command_populates_every_marketing_cache_key(): void
    {
        // Ensure at least one SitePage row exists (features drives /features).
        $features = SitePage::firstOrCreate(
            ['slug' => 'features'],
            ['title' => 'Features', 'intro' => 'All features.']
        );

        $this->forgetMarketingCaches();

        $this->artisan('home:warm-caches')->assertSuccessful();

        $catalog = Cache::get(PricingPagesController::CATALOG_CACHE_KEY);
        $this->assertIsArray($catalog, 'Pricing catalog must be warmed.');

        $creators = Cache::get(CreatorsController::DEFAULT_CACHE_KEY);
        $this->assertIsArray($creators, 'Creators default index payload must be warmed.');
        $this->assertArrayHasKey('creators', $creators);
        $this->assertArrayHasKey('total', $creators);

        foreach (CreatorsController::trendingCarouselVariants() as $variant => [$showAdult, $onlyAdult]) {
            $this->assertIsArray(
                Cache::get(CreatorsController::trendingCarouselCacheKey($showAdult, $onlyAdult)),
                "Trending-carousel '{$variant}' variant rows must be warmed (empty array is fine)."
            );
        }
        // only_adult ignores show_adult, so (1,1) and (0,1) share one key.
        $this->assertSame(
            CreatorsController::trendingCarouselCacheKey(false, true),
            CreatorsController::trendingCarouselCacheKey(true, true),
            'only_adult variants must collapse to a single cache key.'
        );
        $this->assertIsArray(
            Cache::get(CreatorsController::POPULAR_TAGS_CACHE_KEY),
            'Popular niche-tag counts must be warmed.'
        );

        $demos = Cache::get(SitePageController::DEMOS_CACHE_KEY);
        $this->assertIsArray($demos, 'Demos gallery data must be warmed.');
        $this->assertArrayHasKey('links', $demos);
        $this->assertArrayHasKey('has_live_restaurant', $demos);

        $blogs = Cache::get(BlogController::INDEX_CACHE_KEY);
        $this->assertIsArray($blogs, 'Blogs default index payload must be warmed.');
        $this->assertArrayHasKey('posts', $blogs);
        $this->assertArrayHasKey('categories', $blogs);

        $attrs = Cache::get(SitePage::SLUG_CACHE_PREFIX . $features->slug);
        $this->assertIsArray($attrs, 'SitePage attribute caches must be warmed per slug.');
        $this->assertSame($features->slug, $attrs['slug'] ?? null);

        // Shared-layout reads (rendered on EVERY marketing page) must be
        // warmed too, or the first post-deploy /pricing still pays a
        // multi-second cold rebuild despite warm page payloads.
        $eventsBand = Cache::get(EventsHeroBandComposer::CACHE_KEY);
        $this->assertIsArray($eventsBand, 'Events hero-band rows must be warmed (empty array is fine).');

        $blogCta = Cache::get(BlogCtaComposer::CACHE_KEY);
        $this->assertIsArray($blogCta, 'Blog CTA rows must be warmed (empty array is fine).');

        $siteStats = Cache::get(SiteStat::ACTIVE_CACHE_KEY);
        $this->assertIsArray($siteStats, 'Active site-stats rows must be warmed.');

        foreach (['marketing', 'app'] as $surface) {
            $this->assertIsArray(
                Cache::get(SiteAssistantPageHint::SURFACE_CACHE_PREFIX . $surface),
                "Assistant page hints for the {$surface} surface must be warmed."
            );
        }

        // Every layout app_settings key must be cached — including UNSET
        // keys, which get the MISSING sentinel so the first request doesn't
        // pay one live query per absent row.
        foreach (MarketingPageCache::LAYOUT_SETTING_KEYS as $key) {
            $this->assertNotNull(
                Cache::get('app_setting:' . $key),
                "Layout app_setting '{$key}' must be warmed (value or MISSING sentinel)."
            );
        }

        // File cache deserializes cached Eloquent objects as
        // __PHP_Incomplete_Class — every warmed payload must be object-free.
        foreach ([$catalog, $creators, $demos, $blogs, $attrs, $eventsBand, $blogCta, $siteStats] as $payload) {
            array_walk_recursive($payload, function ($v) {
                $this->assertFalse(is_object($v), 'Warmed marketing payloads must contain no objects.');
            });
        }
    }

    public function test_post_warm_blogs_render_is_served_from_cache(): void
    {
        $post = BlogPost::create([
            'title'        => 'Warmed Marketing Story',
            'slug'         => 'warmed-marketing-' . uniqid(),
            'excerpt'      => 'Summary.',
            'body_html'    => '<p>Body.</p>',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->forgetMarketingCaches();
        $this->artisan('home:warm-caches')->assertSuccessful();

        // Deleting the row and rendering proves the request is answered from
        // the warmed cache, not a live rebuild.
        BlogPost::withoutEvents(function () use ($post) {
            BlogPost::query()->whereKey($post->id)->delete();
        });

        $resp = $this->get('/blogs');
        $resp->assertOk();
        $resp->assertSee('Warmed Marketing Story');
    }

    public function test_signed_in_default_creators_render_is_served_from_cache(): void
    {
        $plan = \App\Modules\Admin\Models\Plan::firstOrCreate(
            ['slug' => 'warm-test-' . uniqid()],
            ['name' => 'Warm Test', 'settings' => []]
        );

        $creator = \App\Modules\User\Models\User::factory()->create([
            'name' => 'Warmed Directory Creator',
            'discoverable' => true,
            'plan_id' => $plan->id,
        ]);
        \App\Modules\User\Models\Link::create([
            'user_id' => $creator->id,
            'type' => 'biolink',
            'alias' => 'warmed-' . uniqid(),
            'url' => '',
            'is_active' => true,
        ]);

        $viewer = \App\Modules\User\Models\User::factory()->create([
            'plan_id' => $plan->id,
        ]);

        $this->forgetMarketingCaches();
        $this->artisan('home:warm-caches')->assertSuccessful();

        // Removing the creator's biolink would drop them from a LIVE
        // rebuild; the signed-in render still showing them proves the
        // request was answered from the shared warmed cache.
        \App\Modules\User\Models\Link::withoutEvents(function () use ($creator) {
            \App\Modules\User\Models\Link::query()->where('user_id', $creator->id)->delete();
        });

        app()->forgetInstance('current_workspace');
        $resp = $this->actingAs($viewer, 'web')->get('/creators');
        $resp->assertOk();
        $resp->assertSee('Warmed Directory Creator');
    }

    public function test_rewarming_overwrites_stale_marketing_content(): void
    {
        $this->forgetMarketingCaches();
        $this->artisan('home:warm-caches')->assertSuccessful();

        BlogPost::create([
            'title'        => 'Fresh Marketing Post',
            'slug'         => 'fresh-marketing-' . uniqid(),
            'excerpt'      => 'Summary.',
            'body_html'    => '<p>Body.</p>',
            'status'       => 'published',
            'published_at' => now(),
        ]);

        $this->artisan('home:warm-caches')->assertSuccessful();

        $blogs = Cache::get(BlogController::INDEX_CACHE_KEY);
        $titles = array_column(array_column($blogs['posts'], 'post'), 'title');
        $this->assertContains('Fresh Marketing Post', $titles);
    }
}
