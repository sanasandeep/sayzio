<?php

namespace Tests\Feature;

use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the public blog JSON feed at /blogs/feed.json and
 * /blogs/feed/{slug}.json. These power the standalone marketing site
 * (1inme.com) so its blog stays in sync with the database-driven blog here.
 *
 * Guards against regressions that would silently desync the two:
 *  - dropping the camelCase shape the marketing BlogPost interface expects
 *  - leaking drafts / scheduled / future-dated posts into the public feed
 *  - removing the CORS headers the cross-origin marketing fetch relies on
 */
class BlogFeedJsonTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_lists_only_published_posts_in_expected_shape(): void
    {
        BlogPost::query()->delete();

        $category = BlogCategory::create(['name' => 'Product', 'slug' => 'product']);

        $published = BlogPost::create([
            'title'        => 'A Published Post',
            'slug'         => 'a-published-post',
            'excerpt'      => 'Short summary.',
            'body_html'    => '<p>Hello world.</p>',
            'cover_image'  => '/storage/blogs/cover.jpg',
            'category_id'  => $category->id,
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        // Draft — must never appear in the public feed.
        BlogPost::create([
            'title'     => 'A Draft Post',
            'slug'      => 'a-draft-post',
            'body_html' => '<p>WIP.</p>',
            'status'    => 'draft',
        ]);

        // Future-dated published post — published() scope must hide it.
        BlogPost::create([
            'title'        => 'A Future Post',
            'slug'         => 'a-future-post',
            'body_html'    => '<p>Later.</p>',
            'status'       => 'published',
            'published_at' => now()->addWeek(),
        ]);

        $res = $this->getJson('/blogs/feed.json');

        $res->assertOk();
        $res->assertHeader('Access-Control-Allow-Origin', '*');

        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertContains('a-published-post', $slugs);
        $this->assertNotContains('a-draft-post', $slugs);
        $this->assertNotContains('a-future-post', $slugs);

        $res->assertJsonFragment([
            'slug'        => 'a-published-post',
            'title'       => 'A Published Post',
            'excerpt'     => 'Short summary.',
            'category'    => 'Product',
            'readingTime' => max(1, (int) $published->fresh()->reading_time_min) . ' min read',
            // Relative stored paths must be absolutised for the cross-origin
            // marketing site.
            'coverImage'  => url('/storage/blogs/cover.jpg'),
        ]);
    }

    public function test_single_post_returns_full_body_html(): void
    {
        BlogPost::create([
            'title'        => 'Deep Dive',
            'slug'         => 'deep-dive',
            'excerpt'      => 'A deep dive.',
            'body_html'    => '<p>The full body.</p>',
            'status'       => 'published',
            'published_at' => now()->subDay(),
        ]);

        $res = $this->getJson('/blogs/feed/deep-dive.json');

        $res->assertOk();
        $res->assertHeader('Access-Control-Allow-Origin', '*');
        $res->assertJsonPath('data.slug', 'deep-dive');
        $res->assertJsonPath('data.bodyHtml', '<p>The full body.</p>');
    }

    public function test_single_post_404_uses_error_envelope(): void
    {
        $res = $this->getJson('/blogs/feed/does-not-exist.json');

        $res->assertStatus(404);
        $res->assertHeader('Access-Control-Allow-Origin', '*');
        $res->assertJsonPath('error.code', 'not_found');
    }

    public function test_draft_post_is_not_reachable_via_single_endpoint(): void
    {
        BlogPost::create([
            'title'     => 'Secret Draft',
            'slug'      => 'secret-draft',
            'body_html' => '<p>Hidden.</p>',
            'status'    => 'draft',
        ]);

        $res = $this->getJson('/blogs/feed/secret-draft.json');

        $res->assertStatus(404);
        $res->assertJsonPath('error.code', 'not_found');
    }
}
