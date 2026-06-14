<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Controllers\SitemapController;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use App\Modules\Common\Support\MarketingSitemap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Guards that the cached public sitemaps never go stale.
 *
 * Both the marketing sitemap (/sitemap.xml) and the sitemap index
 * (/sitemap_index.xml) are cached for 10 minutes so crawlers don't recompute
 * the DB queries on every hit. That cache is only correct if it is busted on
 * every write path that can change its contents. These tests prove:
 *
 *  - both endpoints serve cached output (the cache key is warmed after a hit)
 *  - the cached body is byte-identical to a fresh, uncached render
 *  - the cache is invalidated when a site_pages row is created / updated /
 *    deleted (SitePage model events)
 *  - the cache is invalidated when the marketing_seo override is saved through
 *    the admin screen (MarketingSeoController::update)
 *  - the sitemap-index cache is invalidated when a blog post publishes /
 *    updates (BlogPost::flushPublicCaches forgets the index key, because the
 *    index embeds a blog <lastmod>)
 *
 * Without this coverage a future refactor could silently serve a stale sitemap
 * to search engines for up to the full TTL.
 */
class SitemapCacheFreshnessTest extends TestCase
{
    use RefreshDatabase;

    /** Warm both sitemap caches and assert both keys are populated. */
    private function warmBothCaches(): void
    {
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/sitemap_index.xml')->assertOk();

        $this->assertNotNull(
            Cache::get(MarketingSitemap::CACHE_KEY),
            'marketing sitemap should be cached after a request'
        );
        $this->assertNotNull(
            Cache::get(SitemapController::INDEX_CACHE_KEY),
            'sitemap index should be cached after a request'
        );
    }

    private function assertBothCachesCleared(): void
    {
        $this->assertNull(
            Cache::get(MarketingSitemap::CACHE_KEY),
            'marketing sitemap cache should have been flushed'
        );
        $this->assertNull(
            Cache::get(SitemapController::INDEX_CACHE_KEY),
            'sitemap index cache should have been flushed'
        );
    }

    private function makeSuperAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    public function test_both_sitemaps_serve_cached_output(): void
    {
        Cache::forget(MarketingSitemap::CACHE_KEY);
        Cache::forget(SitemapController::INDEX_CACHE_KEY);

        $this->assertNull(Cache::get(MarketingSitemap::CACHE_KEY));
        $this->assertNull(Cache::get(SitemapController::INDEX_CACHE_KEY));

        $sitemap = $this->get('/sitemap.xml');
        $index   = $this->get('/sitemap_index.xml');

        $sitemap->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $index->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        // The rendered XML body is what gets warmed into each cache key.
        $this->assertSame($sitemap->getContent(), Cache::get(MarketingSitemap::CACHE_KEY));
        $this->assertSame($index->getContent(), Cache::get(SitemapController::INDEX_CACHE_KEY));

        // Both bodies are well-formed XML.
        $this->assertValidXml($sitemap->getContent(), 'urlset');
        $this->assertValidXml($index->getContent(), 'sitemapindex');
    }

    public function test_cached_output_is_byte_identical_to_uncached_render(): void
    {
        SitePage::firstOrCreate(['slug' => 'about'], ['title' => 'About']);

        // Render once with a cold cache (this is the "uncached" body).
        Cache::forget(MarketingSitemap::CACHE_KEY);
        Cache::forget(SitemapController::INDEX_CACHE_KEY);
        $coldSitemap = $this->get('/sitemap.xml')->getContent();
        $coldIndex   = $this->get('/sitemap_index.xml')->getContent();

        // A second request is served from the warm cache and must match exactly.
        $warmSitemap = $this->get('/sitemap.xml')->getContent();
        $warmIndex   = $this->get('/sitemap_index.xml')->getContent();

        $this->assertSame($coldSitemap, $warmSitemap, 'cached sitemap must equal the uncached render');
        $this->assertSame($coldIndex, $warmIndex, 'cached index must equal the uncached render');

        // And the marketing sitemap matches a direct, cache-free rebuild.
        $this->assertSame(MarketingSitemap::build(), $warmSitemap);
    }

    public function test_creating_a_site_page_busts_both_caches(): void
    {
        $this->warmBothCaches();

        SitePage::create(['slug' => 'fresh-page-' . uniqid(), 'title' => 'Fresh page']);

        $this->assertBothCachesCleared();
    }

    public function test_updating_a_site_page_busts_both_caches(): void
    {
        $page = SitePage::create(['slug' => 'editme-' . uniqid(), 'title' => 'Edit me']);

        $this->warmBothCaches();

        $page->update(['title' => 'Edited title']);

        $this->assertBothCachesCleared();
    }

    public function test_deleting_a_site_page_busts_both_caches(): void
    {
        $page = SitePage::create(['slug' => 'deleteme-' . uniqid(), 'title' => 'Delete me']);

        $this->warmBothCaches();

        $page->delete();

        $this->assertBothCachesCleared();
    }

    public function test_saving_marketing_seo_busts_both_caches(): void
    {
        $admin = $this->makeSuperAdmin();

        // Pick a real code-driven page key so the override actually persists.
        $key = array_key_first(MarketingSeo::codeDrivenDefaults());
        $this->assertNotNull($key);

        $this->warmBothCaches();

        $resp = $this->actingAs($admin, 'admin')->put('/admin/marketing-seo', [
            'seo' => [
                $key => [
                    'title'       => 'Custom marketing title',
                    'description' => 'Custom marketing description.',
                    'keywords'    => 'one, two, three',
                ],
            ],
        ]);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');

        // The override was persisted...
        $overrides = MarketingSeo::overrides();
        $this->assertSame('Custom marketing title', $overrides[$key]['title'] ?? null);

        // ...and both sitemap caches were flushed by the controller.
        $this->assertBothCachesCleared();
    }

    public function test_publishing_a_blog_post_busts_the_index_cache(): void
    {
        $this->warmBothCaches();

        BlogPost::create([
            'title'        => 'Hello world',
            'status'       => 'published',
            'published_at' => now(),
        ]);

        // The index embeds a blog <lastmod>, so BlogPost::flushPublicCaches
        // forgets the index key specifically.
        $this->assertNull(
            Cache::get(SitemapController::INDEX_CACHE_KEY),
            'sitemap index cache should be flushed when a blog post publishes'
        );
    }

    public function test_updating_a_blog_post_busts_the_index_cache(): void
    {
        $post = BlogPost::create([
            'title'        => 'Draft post',
            'status'       => 'draft',
        ]);

        $this->warmBothCaches();

        $post->update(['status' => 'published', 'published_at' => now()]);

        $this->assertNull(
            Cache::get(SitemapController::INDEX_CACHE_KEY),
            'sitemap index cache should be flushed when a blog post updates'
        );
    }

    public function test_busted_caches_regenerate_with_fresh_content_on_next_request(): void
    {
        // 'about' is a site_pages-backed slug that appears in the marketing
        // sitemap, so its updated_at drives a real <lastmod> in the output.
        $page = SitePage::firstOrCreate(['slug' => 'about'], ['title' => 'About']);

        // Warm, then change the page (this busts the cache via the model event)
        // and confirm the next request rebuilds rather than serving the stale
        // body.
        $before = $this->get('/sitemap.xml')->getContent();
        $this->assertNotNull(Cache::get(MarketingSitemap::CACHE_KEY));

        // Force a later updated_at so the rebuilt <lastmod> is observably newer.
        $this->travel(2)->seconds();
        $page->update(['title' => 'Updated']);
        $this->assertNull(Cache::get(MarketingSitemap::CACHE_KEY));

        $after = $this->get('/sitemap.xml')->getContent();
        $this->assertValidXml($after, 'urlset');
        $this->assertNotSame($before, $after, 'regenerated sitemap should reflect the newer lastmod');

        $this->travelBack();
    }

    private function assertValidXml(string $body, string $expectedRoot): void
    {
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $body);
        $doc = new \DOMDocument();
        $this->assertTrue($doc->loadXML($body), 'response should be valid XML');
        $this->assertSame($expectedRoot, $doc->documentElement->localName);
    }
}
