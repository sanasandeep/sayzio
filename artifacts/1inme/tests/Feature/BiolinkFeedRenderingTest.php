<?php

namespace Tests\Feature;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Services\BiolinkFeedService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BiolinkFeedRenderingTest extends TestCase
{
    private const RSS = '<rss version="2.0"><channel><item><title>Design &amp; craft</title><link>https://example.com/one</link></item><item><title>Second post</title><link>https://example.com/two</link></item></channel></rss>';

    public function test_rss_entries_are_cached_and_count_is_applied_per_block(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response(self::RSS, 200)]);
        $service = new BiolinkFeedService;
        $url = 'https://93.184.216.34/feed.xml';
        $this->assertCount(1, $service->entries($url, 1));
        $this->assertCount(2, $service->entries($url, 2));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === $url);
    }

    public function test_atom_namespace_and_alternate_video_links_are_supported(): void
    {
        $xml = '<feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Latest video</title><link rel="self" href="https://example.com/feed"/><link rel="alternate" href="https://www.youtube.com/watch?v=abc123"/></entry></feed>';
        $this->assertSame([['title' => 'Latest video', 'url' => 'https://www.youtube.com/watch?v=abc123']], (new BiolinkFeedService)->parse($xml));
    }

    public function test_bad_xml_external_entities_and_unsafe_entry_links_are_rejected(): void
    {
        $service = new BiolinkFeedService;
        $this->assertSame([], $service->parse('<rss>'));
        $this->assertSame([], $service->parse('<!DOCTYPE rss [<!ENTITY leak SYSTEM "file:///etc/passwd">]>'.self::RSS));
        $this->assertSame([], $service->parse(str_repeat('x', BiolinkFeedService::MAX_BYTES + 1)));
        $this->assertSame([], $service->parse('<rss><channel><item><title>Click</title><link>javascript:alert(1)</link></item></channel></rss>'));
    }

    public function test_private_targets_and_non_http_urls_never_make_requests(): void
    {
        Http::fake();
        $service = new BiolinkFeedService;
        foreach (['http://127.0.0.1/feed', 'http://[::1]/feed', 'http://[::ffff:127.0.0.1]/feed', 'http://10.0.0.1/feed',
            'http://localhost/feed', 'file:///etc/passwd', 'https://user:password@example.com/feed'] as $url) {
            $this->assertSame([], $service->entries($url));
        }
        Http::assertNothingSent();
    }

    public function test_redirects_are_not_followed_and_fetch_failures_return_empty_entries(): void
    {
        Cache::flush();
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/feed'])]);
        $this->assertSame([], (new BiolinkFeedService)->entries('https://93.184.216.34/redirect'));
        Http::assertSentCount(1);
    }

    public function test_feed_renderer_uses_saved_count_and_escapes_remote_titles(): void
    {
        $block = new BiolinkBlock(['type' => 'youtube_feed']);
        $block->exists = true;
        $this->mock(BiolinkFeedService::class, function ($mock) {
            $mock->shouldReceive('entries')->once()
                ->with('https://www.youtube.com/feeds/videos.xml?channel_id=UC_x5XG1OV2P6uZZ5FSM9Ttw', 2)
                ->andReturn([['title' => '<script>Remote title</script>', 'url' => 'https://example.com/watch']]);
        });
        $html = view('common.blocks.feed', ['block' => $block,
            's' => ['channel_id' => 'UC_x5XG1OV2P6uZZ5FSM9Ttw', 'count' => 2]])->render();
        $this->assertStringContainsString('&lt;script&gt;Remote title&lt;/script&gt;', $html);
        $this->assertStringContainsString('https://example.com/watch', $html);
        $this->assertStringContainsString('Open channel', $html);
    }

    public function test_unsaved_preview_does_not_fetch_and_unsafe_fallback_is_not_linked(): void
    {
        $this->mock(BiolinkFeedService::class, fn ($mock) => $mock->shouldNotReceive('entries'));
        $html = view('common.blocks.feed', ['block' => new BiolinkBlock(['type' => 'rss_feed']),
            's' => ['url' => 'javascript:alert(1)']])->render();
        $this->assertStringContainsString('Entries appear on your published page.', $html);
        $this->assertStringNotContainsString('href=', $html);
    }
}
