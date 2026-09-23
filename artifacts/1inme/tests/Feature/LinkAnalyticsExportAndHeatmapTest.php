<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things that shipped together and are checked together because they
 * answer the same complaint: the link page could only give its data away as
 * one CSV, and it could tell you how many clicks arrived but not when.
 *
 * The export now speaks CSV and JSON from a single row builder, so the guard
 * that matters is not "does JSON parse" but "does JSON say the same thing as
 * CSV" — two formats assembled separately is exactly how an export starts
 * reporting different numbers depending on which button was pressed.
 *
 * The heatmap is checked by planting clicks at a known instant and reading
 * the cell back out of the rendered page, because the interesting part is not
 * the COUNT but the placement: ISODOW is 1-based and Monday-first, the hour
 * is bucketed into twos, and an off-by-one in either lands a Wednesday
 * lunchtime spike on Tuesday morning and nobody notices.
 */
class LinkAnalyticsExportAndHeatmapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user whose workspace is resolved and bound before anything is created.
     *
     * Without this the link is written with a NULL workspace_id, and the
     * moment the first request binds `current_workspace` the workspace global
     * scope hides it -- so the first request in a test passes and every one
     * after it 404s, which looks like a bug in whatever you were testing.
     */
    private function user(): User
    {
        $user = User::factory()->create();
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function link(User $user): Link
    {
        return Link::create([
            'user_id' => $user->id,
            'type' => 'url',
            'alias' => 'exp'.$user->id,
            'title' => 'Export Me',
            'long_url' => 'https://example.com/destination',
            'is_active' => true,
        ]);
    }

    /**
     * Clicks at a known instant. Wednesday 15:30 is deliberately awkward: it
     * is neither the first nor the last day, and 15 is inside the 14:00 block
     * rather than on its boundary, so a bucket computed with round() instead
     * of an integer divide would land somewhere else.
     */
    private function clicksAt(Link $link, string $when, int $n, bool $bot = false): void
    {
        foreach (range(1, $n) as $i) {
            LinkClick::create([
                'link_id' => $link->id,
                'ip_address' => '203.0.113.'.$i,
                'country_code' => 'IN',
                'city' => 'Kochi',
                'browser' => 'Chrome',
                'os' => 'Android',
                'device_type' => 'mobile',
                'is_bot' => $bot,
                'clicked_at' => $when,
            ]);
        }
    }

    /**
     * Both formats are streamed responses, so the body only exists once the
     * callback has run. Capturing it with ob_start() does not work here: the
     * JSON branch calls flush() between chunks, which pushes past the buffer.
     */
    private function body($response): string
    {
        return $response->streamedContent();
    }

    // ===== export formats =====

    public function test_json_export_parses_and_describes_the_link_and_range(): void
    {
        $user = $this->user();
        $link = $this->link($user);
        $this->clicksAt($link, now()->subDays(2)->setTime(11, 0)->toDateTimeString(), 3);

        $resp = $this->actingAs($user)->get(route('user.links.clicks.export', $link).'?format=json');
        $resp->assertOk();
        $this->assertStringContainsString('application/json', (string) $resp->headers->get('Content-Type'));
        $this->assertStringContainsString('.json"', (string) $resp->headers->get('Content-Disposition'));

        $data = json_decode($this->body($resp), true);

        $this->assertIsArray($data, 'The JSON export did not parse.');
        $this->assertSame($link->alias, $data['link']['alias'] ?? null);
        $this->assertArrayHasKey('from', $data['range'] ?? []);
        $this->assertFalse($data['range']['includes_bots'] ?? null);
        $this->assertCount(3, $data['clicks'] ?? []);
        $this->assertSame('Kochi', $data['clicks'][0]['city'] ?? null);
    }

    public function test_both_formats_report_the_same_rows_under_the_same_names(): void
    {
        $user = $this->user();
        $link = $this->link($user);
        $this->clicksAt($link, now()->subDay()->setTime(9, 15)->toDateTimeString(), 4);

        $csv = $this->body($this->actingAs($user)->get(route('user.links.clicks.export', $link)));
        $json = json_decode(
            $this->body($this->actingAs($user)->get(route('user.links.clicks.export', $link).'?format=json')),
            true
        );

        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $header = array_shift($rows);

        $this->assertCount(count($rows), $json['clicks'], 'CSV and JSON disagree on how many clicks there were.');

        // The JSON keys are derived from the CSV header positionally. If the
        // two ever drift, it will be here and not in production.
        $expectedKeys = array_map(
            static fn (string $c): string => str_replace([' ', '-'], '_', strtolower($c)),
            $header
        );
        $this->assertSame($expectedKeys, array_keys($json['clicks'][0]));

        // And the same values, not just the same shape.
        $this->assertSame($rows[0], array_values($json['clicks'][0]));
    }

    public function test_including_bots_adds_the_flag_to_both_formats(): void
    {
        $user = $this->user();
        $link = $this->link($user);
        $this->clicksAt($link, now()->subDay()->setTime(10, 0)->toDateTimeString(), 2);
        $this->clicksAt($link, now()->subDay()->setTime(10, 5)->toDateTimeString(), 5, true);

        $plain = json_decode($this->body($this->actingAs($user)->get(route('user.links.clicks.export', $link).'?format=json')), true);
        $this->assertCount(2, $plain['clicks'], 'Bot clicks leaked into the default export.');
        $this->assertArrayNotHasKey('is_bot', $plain['clicks'][0]);

        $raw = json_decode($this->body($this->actingAs($user)->get(route('user.links.clicks.export', $link).'?format=json&include_bots=1')), true);
        $this->assertCount(7, $raw['clicks']);
        $this->assertTrue($raw['range']['includes_bots']);
        $this->assertContains('yes', array_column($raw['clicks'], 'is_bot'));
        $this->assertContains('no', array_column($raw['clicks'], 'is_bot'));
    }

    public function test_an_unknown_format_falls_back_to_csv_rather_than_erroring(): void
    {
        $user = $this->user();
        $link = $this->link($user);

        $resp = $this->actingAs($user)->get(route('user.links.clicks.export', $link).'?format=xlsx');
        $resp->assertOk();
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv"', (string) $resp->headers->get('Content-Disposition'));
    }

    // ===== the heatmap =====

    public function test_a_spike_lands_in_the_right_day_and_two_hour_block(): void
    {
        $user = $this->user();
        $link = $this->link($user);

        // The most recent Wednesday, at 15:30 — inside the 14:00 block.
        $wed = now()->startOfWeek()->addDays(2)->setTime(15, 30);
        if ($wed->isFuture()) {
            $wed = $wed->subWeek();
        }
        $this->clicksAt($link, $wed->toDateTimeString(), 6);

        $html = $this->actingAs($user)->followingRedirects()
            ->get(route('user.links.show', $link))->assertOk()->getContent();

        $this->assertStringContainsString('When This Link Gets Clicked', $html);
        // The cell used to carry only a title; it now carries the numbers the
        // hover readout reads out, so this asserts those instead -- same
        // claim, one step closer to what a person sees.
        $this->assertMatchesRegularExpression(
            '/data-when="Wed 14:00–16:00"\s+data-count="6"/',
            $html,
            'The spike landed in the wrong cell.'
        );
        $this->assertStringContainsString('Peak <b>6</b> clicks', $html);
    }

    public function test_a_link_with_no_clicks_says_so_instead_of_drawing_an_empty_grid(): void
    {
        $user = $this->user();
        $link = $this->link($user);

        $html = $this->actingAs($user)->followingRedirects()
            ->get(route('user.links.show', $link))->assertOk()->getContent();

        $this->assertStringContainsString('no pattern to draw yet', $html);

        // The markup, not the bare class name: the shared partial's
        // stylesheet names .hm-cell whether or not a square is drawn.
        $this->assertStringNotContainsString('class="hm-cell"', $html);
    }

    // ===== the affordances on the page =====

    public function test_the_export_button_offers_every_format_it_supports(): void
    {
        $user = $this->user();
        $link = $this->link($user);

        $html = $this->actingAs($user)->followingRedirects()
            ->get(route('user.links.show', $link))->assertOk()->getContent();

        // The hero action is a menu now, not a single link. Every branch the
        // controller understands must be reachable from it, or the format
        // exists only for whoever knows to type the query string.
        $this->assertStringContainsString('hero-menu', $html);
        $this->assertStringContainsString('format=json', $html);
        $this->assertStringContainsString('include_bots=1', $html);
    }

    public function test_every_chart_on_the_page_can_be_downloaded(): void
    {
        $user = $this->user();
        $link = $this->link($user);
        $this->clicksAt($link, now()->subDay()->setTime(12, 0)->toDateTimeString(), 3);

        $html = $this->actingAs($user)->followingRedirects()
            ->get(route('user.links.show', $link))->assertOk()->getContent();

        // Each chart that rendered carries a download button pointed at it.
        // Asserted per target rather than by counting buttons: a copy/paste
        // that leaves two cards pointing at the same canvas would still count
        // correctly and export the same picture twice.
        foreach (['clicksChart', 'browserChart', 'osChart', 'deviceChart', 'when-heat-grid'] as $target) {
            $this->assertStringContainsString(
                'data-chart-target="'.$target.'"',
                $html,
                "No export button is wired to {$target}."
            );
        }

        $this->assertStringContainsString('data-chart-action="copy"', $html);
        $this->assertStringContainsString('id="chart-export-toast"', $html);
    }
}
