<?php

namespace Tests\Feature\Analytics;

use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\LinkClick;
use Illuminate\Http\Request;

/**
 * Per-user blocked bot families: matching hits must NOT be persisted
 * in link_clicks at all (so they vanish from totals, breakdowns,
 * exports, and the "bot hits filtered" badge).
 */
class BlockedBotFamiliesTest extends AnalyticsTestCase
{
    public function test_blocked_family_hit_is_not_recorded(): void
    {
        $owner = $this->makeUser(['blocked_bot_families' => ['GPTBot (OpenAI)']]);
        $link = $this->makeLink($owner);

        $result = app(LinkTrackingService::class)->track($link, $this->makeRequest('Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)'));

        $this->assertNull($result, 'track() must return null when the family is blocked.');
        $this->assertSame(0, LinkClick::withBots()->where('link_id', $link->id)->count(),
            'Blocked-family hits must not be persisted at all.');
    }

    public function test_non_blocked_bot_family_still_recorded_as_bot(): void
    {
        $owner = $this->makeUser(['blocked_bot_families' => ['GPTBot (OpenAI)']]);
        $link = $this->makeLink($owner);

        $click = app(LinkTrackingService::class)->track($link, $this->makeRequest('Mozilla/5.0 (compatible; AhrefsBot/7.0)'));

        $this->assertNotNull($click);
        $this->assertTrue((bool) $click->is_bot);
        $this->assertSame(1, LinkClick::withBots()->where('link_id', $link->id)->count());
        // Cached counters stay flat — it's still a bot, just not a blocked one.
        $this->assertSame(0, (int) $link->fresh()->total_clicks);
    }

    public function test_human_hit_is_recorded_even_when_blocklist_is_set(): void
    {
        $owner = $this->makeUser(['blocked_bot_families' => ['GPTBot (OpenAI)', 'AhrefsBot']]);
        $link = $this->makeLink($owner);

        $click = app(LinkTrackingService::class)->track($link, $this->makeRequest(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ));

        $this->assertNotNull($click);
        $this->assertFalse((bool) $click->is_bot);
        $this->assertSame(1, (int) $link->fresh()->total_clicks);
    }

    public function test_blocked_family_hit_is_not_recorded_for_block_clicks(): void
    {
        $owner = $this->makeUser(['blocked_bot_families' => ['ClaudeBot']]);
        $link = $this->makeLink($owner, ['type' => 'biolink']);
        $block = BiolinkBlock::create([
            'link_id' => $link->id,
            'type'    => 'link',
            'position' => 1,
            'data'    => ['url' => 'https://example.com'],
        ]);

        $result = app(LinkTrackingService::class)->trackBlockClick(
            $link, $block, 'https://example.com',
            $this->makeRequest('Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)')
        );

        $this->assertNull($result);
        $this->assertSame(0, LinkClick::withBots()->where('link_id', $link->id)->count());
    }

    public function test_empty_blocklist_records_bot_hit_normally(): void
    {
        $owner = $this->makeUser(['blocked_bot_families' => []]);
        $link = $this->makeLink($owner);

        $click = app(LinkTrackingService::class)->track($link, $this->makeRequest('Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)'));

        $this->assertNotNull($click);
        $this->assertTrue((bool) $click->is_bot);
    }

    private function makeRequest(string $userAgent): Request
    {
        $req = Request::create('/r/abc', 'GET');
        $req->headers->set('User-Agent', $userAgent);
        $req->server->set('REMOTE_ADDR', '203.0.113.99');
        return $req;
    }
}
