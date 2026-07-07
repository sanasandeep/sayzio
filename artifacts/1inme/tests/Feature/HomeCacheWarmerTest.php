<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\ScheduledJobRegistry;
use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Support\HomePageCache;
use App\Modules\Common\Support\PricingPageCache;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature coverage for the scheduled home-page cache warmer.
 *
 * The home page is instant while its 5-minute caches are warm, but the one
 * visitor who arrives right after expiry used to pay the full rebuild over
 * the cross-region RDS (~4s+ in production). The `home:warm-caches` job
 * (HomePageCache::warm()) now rebuilds every home cache proactively every
 * four minutes — inside the request path's TTL — so no anonymous visitor
 * ever hits a cold render.
 *
 * Guards: the command populates every key, a post-warm render is served
 * entirely from the caches (no live queries), warming overwrites stale
 * cached content with fresh DB data (admin edits land within one cadence),
 * and the job is registered in the schedule registry.
 */
class HomeCacheWarmerTest extends TestCase
{
    use RefreshDatabase;

    private function forgetHomeCaches(): void
    {
        foreach (HomePageCache::CURRENCIES as $cur) {
            Cache::forget(HomePageCache::ANON_PAYLOAD_PREFIX . $cur);
        }
        Cache::forget(HomePageCache::FEATURED_CACHE_KEY);
        Cache::forget(HomePageCache::AI_HERO_ALIASES_KEY);
        Cache::forget(PricingPageCache::CATALOG_CACHE_KEY);
    }

    private function makePlan(array $attrs = []): Plan
    {
        $plan = Plan::create(array_merge([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(6), 'description' => 'Pro plan',
            'monthly_price' => 20.00, 'annual_price' => 200.00, 'trial_days' => 0,
            'status' => 'active', 'is_archived' => false, 'is_internal' => false,
            'sort_order' => 1, 'features' => [],
        ], $attrs));
        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', 2000],
            ['USD', 'annual', 20000],
            ['INR', 'monthly', 40000],
            ['INR', 'annual', 400000],
        ]);

        return $plan;
    }

    private function makeFeaturedPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title'            => 'Warmed Story ' . uniqid(),
            'slug'             => 'warmed-' . uniqid(),
            'excerpt'          => 'A short summary of the story.',
            'body_html'        => '<p>Hello world, this is the body.</p>',
            'cover_image'      => '/storage/blogs/cover.jpg',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
            'is_featured_home' => true,
            'featured_slot'    => 'carousel',
        ], $overrides));
    }

    public function test_warm_command_populates_every_home_cache_key(): void
    {
        $this->forgetHomeCaches();
        $this->makeFeaturedPost(['title' => 'Prewarmed Post Alpha']);

        $this->artisan('home:warm-caches')->assertSuccessful();

        foreach (HomePageCache::CURRENCIES as $cur) {
            $json = Cache::get(HomePageCache::ANON_PAYLOAD_PREFIX . $cur);
            $this->assertIsString($json, "Anonymous payload for {$cur} must be warmed.");
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('plans', $decoded);
            $this->assertArrayHasKey('linkTypes', $decoded);
        }

        $rows = Cache::get(HomePageCache::FEATURED_CACHE_KEY);
        $this->assertIsArray($rows, 'Featured blog-post rows must be warmed.');
        $this->assertNotEmpty($rows);
        array_walk_recursive($rows, function ($v) {
            $this->assertFalse(is_object($v), 'Warmed featured-post payload must contain no objects.');
        });

        $this->assertIsArray(
            Cache::get(HomePageCache::AI_HERO_ALIASES_KEY),
            'AI-hero demo aliases must be warmed (empty array is fine).'
        );
    }

    public function test_warm_command_populates_the_pricing_catalog_cache(): void
    {
        $this->forgetHomeCaches();
        $this->makePlan(['name' => 'Warmed Catalog Plan']);

        $this->artisan('home:warm-caches')->assertSuccessful();

        $payload = Cache::get(PricingPageCache::CATALOG_CACHE_KEY);
        $this->assertIsArray($payload, 'Pricing catalogue must be warmed alongside the home caches.');
        $this->assertArrayHasKey('plans', $payload);
        $this->assertArrayHasKey('packages', $payload);
        $this->assertNotEmpty($payload['plans']);
        array_walk_recursive($payload, function ($v) {
            $this->assertFalse(is_object($v), 'Warmed pricing catalogue must contain no objects (plain attribute arrays only).');
        });
        $names = array_column(array_column($payload['plans'], 'attrs'), 'name');
        $this->assertContains('Warmed Catalog Plan', $names);
    }

    public function test_post_warm_pricing_render_is_served_from_cache_without_live_plan_queries(): void
    {
        $this->forgetHomeCaches();
        $plan = $this->makePlan(['name' => 'Cache Served Plan']);

        $this->artisan('home:warm-caches')->assertSuccessful();

        // Deleting the plan and its prices, then rendering, proves the
        // anonymous /pricing request is answered from the warmed catalogue
        // cache — not live plan/price queries over the distant RDS.
        $plan->prices()->delete();
        Plan::query()->whereKey($plan->id)->delete();

        $resp = $this->get('/pricing');
        $resp->assertOk();
        $resp->assertSee('Cache Served Plan');
    }

    public function test_post_warm_render_is_served_from_caches_without_live_rebuild(): void
    {
        $this->forgetHomeCaches();
        $category = BlogCategory::create(['name' => 'Warm Reads', 'slug' => 'warm-reads', 'color' => '#3d6bff']);
        $post = $this->makeFeaturedPost(['title' => 'Served From Warm Cache', 'category_id' => $category->id]);

        $this->artisan('home:warm-caches')->assertSuccessful();

        // Deleting the rows and rendering proves the visitor request is
        // answered from the warmed caches, not a live rebuild.
        BlogPost::withoutEvents(function () use ($post) {
            BlogPost::query()->whereKey($post->id)->delete();
        });
        $category->delete();

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('Served From Warm Cache');
        $resp->assertSee('Warm Reads');
    }

    public function test_rewarming_overwrites_stale_content_with_fresh_db_data(): void
    {
        $this->forgetHomeCaches();
        $this->makeFeaturedPost(['title' => 'Original Featured Post']);
        $this->artisan('home:warm-caches')->assertSuccessful();

        // Simulate an admin edit between warms: BlogPost::saved flushes the
        // key immediately...
        $second = $this->makeFeaturedPost(['title' => 'Freshly Published Post']);
        $this->assertNull(Cache::get(HomePageCache::FEATURED_CACHE_KEY));

        // ...and the next warm run repopulates it with the fresh content, so
        // even without a visitor-triggered rebuild the edit lands within one
        // warm cadence.
        $this->artisan('home:warm-caches')->assertSuccessful();
        $rows = Cache::get(HomePageCache::FEATURED_CACHE_KEY);
        $this->assertIsArray($rows);
        $titles = array_column(array_column($rows, 'post'), 'title');
        $this->assertContains('Freshly Published Post', $titles);
        $this->assertContains('Original Featured Post', $titles);

        $second->delete();
    }

    public function test_warm_job_is_registered_in_the_schedule_registry(): void
    {
        $def = ScheduledJobRegistry::find('home:warm-caches');
        $this->assertNotNull($def, 'home:warm-caches must be a registry-declared scheduled job.');
        $this->assertSame('everyFourMinutes', $def['cadence'][0]);
    }
}
