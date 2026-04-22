<?php

namespace Tests\Feature\Analytics;

use App\Events\BlockClicked;
use App\Events\LinkClicked;
use App\Modules\Common\Services\BotDetector;
use App\Modules\Common\Services\LinkTrackingService;
use App\Modules\User\Models\BiolinkBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Mockery;

/**
 * Verifies the dispatch contract for downstream click hooks: the
 * `LinkClicked` / `BlockClicked` events (which power outbound webhooks
 * and "new visitor" notifications) must fire for real humans and must
 * NOT fire for bot/scraper traffic.
 */
class ClickEventBotExclusionTest extends AnalyticsTestCase
{
    public function test_link_clicked_event_fires_for_humans(): void
    {
        Event::fake([LinkClicked::class]);
        $this->fakeBot(false);

        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        app(LinkTrackingService::class)->track($link, $this->makeRequest('Mozilla/5.0 (real)'));

        Event::assertDispatched(LinkClicked::class, fn ($e) => $e->link->id === $link->id);
    }

    public function test_link_clicked_event_does_not_fire_for_bots(): void
    {
        Event::fake([LinkClicked::class]);
        $this->fakeBot(true);

        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        app(LinkTrackingService::class)->track($link, $this->makeRequest('Googlebot/2.1'));

        Event::assertNotDispatched(LinkClicked::class);

        // Counters must also stay flat — no fan-out of any kind.
        $this->assertSame(0, (int) $link->fresh()->total_clicks);
    }

    public function test_block_clicked_event_does_not_fire_for_bots(): void
    {
        Event::fake([BlockClicked::class]);
        $this->fakeBot(true);

        $owner = $this->makeUser();
        $link = $this->makeLink($owner, ['type' => 'biolink']);
        $block = BiolinkBlock::create([
            'link_id' => $link->id,
            'type'    => 'link',
            'position' => 1,
            'data'    => ['url' => 'https://example.com'],
        ]);

        app(LinkTrackingService::class)->trackBlockClick(
            $link, $block, 'https://example.com',
            $this->makeRequest('AhrefsBot/7.0')
        );

        Event::assertNotDispatched(BlockClicked::class);
    }

    public function test_block_clicked_event_fires_for_humans(): void
    {
        Event::fake([BlockClicked::class]);
        $this->fakeBot(false);

        $owner = $this->makeUser();
        $link = $this->makeLink($owner, ['type' => 'biolink']);
        $block = BiolinkBlock::create([
            'link_id' => $link->id,
            'type'    => 'link',
            'position' => 1,
            'data'    => ['url' => 'https://example.com'],
        ]);

        app(LinkTrackingService::class)->trackBlockClick(
            $link, $block, 'https://example.com',
            $this->makeRequest('Mozilla/5.0 (real)')
        );

        Event::assertDispatched(BlockClicked::class, fn ($e) => $e->block->id === $block->id);
    }

    private function makeRequest(string $userAgent): Request
    {
        $req = Request::create('/r/abc', 'GET');
        $req->headers->set('User-Agent', $userAgent);
        $req->server->set('REMOTE_ADDR', '203.0.113.99');
        return $req;
    }

    private function fakeBot(bool $isBot): void
    {
        $mock = Mockery::mock(BotDetector::class);
        $mock->shouldReceive('isBot')->andReturn($isBot);
        $this->app->instance(BotDetector::class, $mock);
    }
}
