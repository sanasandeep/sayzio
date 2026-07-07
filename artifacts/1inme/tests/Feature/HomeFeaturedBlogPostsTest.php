<?php

namespace Tests\Feature;

use App\Modules\Common\Controllers\HomeController;
use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for the home-page featured-blog carousel cache.
 *
 * The carousel used to query blog_posts fresh on every request; in production
 * (DB_PERSISTENT=false, cross-region RDS) that single query dragged a fresh
 * ~3s SSL connect into every anonymous home render. It is now cached for 5
 * minutes as PLAIN ATTRIBUTE ARRAYS (post + category + author) and rehydrated
 * into Eloquent models on read — plain arrays because serialized Eloquent
 * models do not survive the file cache (incomplete-object on unserialize).
 *
 * This guards the three moving parts: the cache-miss render (live query), the
 * cache-hit render (rehydrated models must drive the Blade section — casts,
 * relations, date formatting), and write-invalidation via flushPublicCaches().
 */
class HomeFeaturedBlogPostsTest extends TestCase
{
    use RefreshDatabase;

    private function makeFeaturedPost(array $overrides = []): BlogPost
    {
        return BlogPost::create(array_merge([
            'title'            => 'Featured Story ' . uniqid(),
            'slug'             => 'featured-' . uniqid(),
            'excerpt'          => 'A short summary of the story.',
            'body_html'        => '<p>Hello world, this is the body.</p>',
            'cover_image'      => '/storage/blogs/cover.jpg',
            'status'           => 'published',
            'published_at'     => now()->subDay(),
            'is_featured_home' => true,
            'featured_slot'    => 'carousel',
        ], $overrides));
    }

    public function test_cache_miss_renders_featured_posts_with_category(): void
    {
        Cache::forget(HomeController::FEATURED_CACHE_KEY);

        $category = BlogCategory::create(['name' => 'Playbooks', 'slug' => 'playbooks', 'color' => '#3d6bff']);
        $post = $this->makeFeaturedPost(['title' => 'AI Playbook Alpha', 'category_id' => $category->id]);

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('blog-featured', false);
        $resp->assertSee('AI Playbook Alpha');
        $resp->assertSee('Playbooks');
        $resp->assertSee($post->published_at->format('M j, Y'));
    }

    public function test_cache_hit_renders_from_rehydrated_arrays_without_blog_query(): void
    {
        Cache::forget(HomeController::FEATURED_CACHE_KEY);

        $category = BlogCategory::create(['name' => 'Deep Dives', 'slug' => 'deep-dives', 'color' => '#22cc88']);
        $post = $this->makeFeaturedPost(['title' => 'Creator Deep Dive Beta', 'category_id' => $category->id]);

        // First render primes the cache.
        $this->get('/')->assertOk();

        // The cache must hold plain nested arrays (file-cache safe), never
        // Eloquent objects.
        $cached = Cache::get(HomeController::FEATURED_CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertNotEmpty($cached);
        $this->assertIsArray($cached[0]['post']);
        $this->assertIsArray($cached[0]['category']);
        array_walk_recursive($cached, function ($v) {
            $this->assertFalse(is_object($v), 'Cached featured-post payload must contain no objects.');
        });

        // Deleting the rows and re-rendering proves the cache-hit path drives
        // the section entirely from the rehydrated arrays (no live query).
        BlogPost::withoutEvents(function () use ($post) {
            BlogPost::query()->whereKey($post->id)->delete();
        });
        $category->delete();

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('Creator Deep Dive Beta');
        $resp->assertSee('Deep Dives');
        $resp->assertSee($post->published_at->format('M j, Y'));
    }

    public function test_saving_a_post_flushes_the_featured_cache(): void
    {
        Cache::forget(HomeController::FEATURED_CACHE_KEY);
        $this->makeFeaturedPost(['title' => 'First Featured Post']);
        $this->get('/')->assertOk();
        $this->assertNotNull(Cache::get(HomeController::FEATURED_CACHE_KEY));

        // A new featured post must appear on the very next render — the saved
        // hook flushes the cache.
        $this->makeFeaturedPost(['title' => 'Second Featured Post']);
        $this->assertNull(Cache::get(HomeController::FEATURED_CACHE_KEY));

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('First Featured Post');
        $resp->assertSee('Second Featured Post');
    }

    public function test_home_renders_without_featured_posts(): void
    {
        Cache::forget(HomeController::FEATURED_CACHE_KEY);
        BlogPost::query()->delete();

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertDontSee('id="blog-featured"', false);
    }
}
