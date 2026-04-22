<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Models\LinkClick;

/**
 * Verifies that bot/scraper rows (`is_bot = true`) are hidden from the
 * downstream surfaces — live SSE/heatmap stream, the recent-clicks
 * activity table, and the CSV export — and that the export's
 * `?include_bots=1` opt-in correctly brings them back.
 */
class BotExclusionDownstreamTest extends AnalyticsTestCase
{
    public function test_recent_clicks_partial_hides_bot_rows(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.10',
            'is_bot' => false,
            'clicked_at' => now()->subMinute(),
        ]);
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.11',
            'is_bot' => true,
            'clicked_at' => now()->subSeconds(30),
        ]);

        $response = $this->actingAs($owner)
            ->get(route('user.links.clicks.partial', $link));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('203.0.113.10', $body);
        $this->assertStringNotContainsString('203.0.113.11', $body);
    }

    public function test_heatmap_live_stream_skips_bot_rows(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.20',
            'country_code' => 'GB', 'city' => 'London',
            'latitude' => 51.50853, 'longitude' => -0.12574,
            'is_bot' => false,
            'clicked_at' => now()->subMinute(),
        ]);
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.21',
            'country_code' => 'FR', 'city' => 'Paris',
            'latitude' => 48.85341, 'longitude' => 2.3488,
            'is_bot' => true,
            'clicked_at' => now()->subSeconds(20),
        ]);

        $response = $this->actingAs($owner)
            ->get(route('user.links.heatmap.live.stream', $link) . '?once=1');

        $response->assertOk();
        $body = $response->streamedContent();

        // Initial snapshot includes the human pin but not the bot pin.
        $this->assertStringContainsString('London', $body);
        $this->assertStringNotContainsString('Paris', $body);
        $this->assertStringContainsString('"unique_visitors":1', $body);
    }

    public function test_csv_export_excludes_bots_by_default(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.30',
            'is_bot' => false,
            'clicked_at' => now()->subMinute(),
        ]);
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.31',
            'is_bot' => true,
            'clicked_at' => now()->subSeconds(30),
        ]);

        $response = $this->actingAs($owner)
            ->get(route('user.links.clicks.export', $link));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('203.0.113.30', $csv);
        $this->assertStringNotContainsString('203.0.113.31', $csv);
        $this->assertStringNotContainsString('Is Bot', $csv);
    }

    public function test_csv_export_includes_bots_when_opted_in(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.40',
            'is_bot' => false,
            'clicked_at' => now()->subMinute(),
        ]);
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.41',
            'is_bot' => true,
            'clicked_at' => now()->subSeconds(30),
        ]);

        $response = $this->actingAs($owner)
            ->get(route('user.links.clicks.export', $link) . '?include_bots=1');

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('203.0.113.40', $csv);
        $this->assertStringContainsString('203.0.113.41', $csv);
        $this->assertStringContainsString('Is Bot', $csv);
        // The bot row should be flagged "yes" in the appended column.
        $this->assertMatchesRegularExpression('/203\.0\.113\.41[^\n]*,yes/', $csv);
        $this->assertMatchesRegularExpression('/203\.0\.113\.40[^\n]*,no/', $csv);
        // Filename should signal the opt-in.
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('with-bots', $disposition);
    }
}
