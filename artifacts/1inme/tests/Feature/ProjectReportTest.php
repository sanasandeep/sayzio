<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\LinkReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The combined project report: whatever the filters resolve to, reported on
 * as one project.
 *
 * The case that matters most here is the time zone. `clicked_at` is a naive
 * UTC timestamp, so a heatmap built on the raw column is wrong by the
 * viewer's offset — for an owner in Asia/Kolkata that is five and a half
 * hours, which moves late-evening traffic onto the wrong day and makes
 * "busiest hour" advice actively misleading. That is the kind of wrong that
 * looks right on screen, so it is pinned first.
 */
class ProjectReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Links are scoped to a workspace. A link created without one is visible
     * until the first request binds a workspace context and then silently
     * disappears from every scoped query — which is exactly how this test
     * file first "proved" the report returned nothing. Pin the workspace the
     * way the rest of the suite does.
     */
    private function link(User $user, ?Project $folder = null, array $attrs = []): Link
    {
        $link = $user->links()->create(array_merge([
            'user_id'    => $user->id,
            'project_id' => $folder?->id,
            'type'       => 'url',
            'alias'      => Link::generateAlias(),
            'title'      => 'Link',
            'long_url'   => 'https://example.com',
            'is_active'  => true,
        ], $attrs));

        $workspace = $user->ownedWorkspaces()->first();
        if ($workspace && (int) $link->workspace_id !== (int) $workspace->id) {
            $link->forceFill(['workspace_id' => $workspace->id])->save();
        }

        return $link->fresh();
    }

    /** A request with the workspace context the real page always carries. */
    private function visit(User $user, array $query = [])
    {
        $workspace = $user->ownedWorkspaces()->first();

        return $this->actingAs($user)
            ->withSession($workspace ? [WorkspaceContext::SESSION_KEY => $workspace->id] : [])
            ->get(route('user.links.index', array_merge(['view' => 'reports'], $query)));
    }

    private function click(Link $link, string $utc, array $attrs = []): LinkClick
    {
        return LinkClick::create(array_merge([
            'link_id'    => $link->id,
            'ip_address' => '203.0.113.1',
            'clicked_at' => Carbon::parse($utc, 'UTC'),
        ], $attrs));
    }

    private function report(User $user, string $from, string $to, string $zone, bool $bots = false): LinkReport
    {
        return new LinkReport(
            Link::query()->where('user_id', $user->id),
            Carbon::parse($from, $zone)->startOfDay(),
            Carbon::parse($to, $zone)->endOfDay(),
            $zone,
            $bots,
        );
    }

    public function test_heatmap_buckets_clicks_in_the_viewers_zone_not_utc(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);

        // 20:30 UTC on Monday 5 May is 02:00 on TUESDAY in Asia/Kolkata.
        $this->click($link, '2026-05-04 20:30:00');

        $ist = $this->report($user, '2026-05-01', '2026-05-10', 'Asia/Kolkata')->heatmap();
        $utc = $this->report($user, '2026-05-01', '2026-05-10', 'UTC')->heatmap();

        $this->assertSame(
            ['day' => 2, 'hour' => 2],
            $ist['peak'],
            'In Asia/Kolkata a 20:30 UTC click belongs to Tuesday 02:00, not Monday evening.',
        );
        $this->assertSame(['day' => 1, 'hour' => 20], $utc['peak']);
        $this->assertSame(7, count($ist['grid']), 'The grid is always a full week.');
        $this->assertSame(24, count($ist['grid'][0]), 'Every day is a full 24 hours, empty cells included.');
    }

    public function test_daily_series_is_zero_filled_across_the_whole_range(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, '2026-05-03 09:00:00');

        $daily = $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->daily();

        $this->assertSame(
            ['2026-05-01', '2026-05-02', '2026-05-03', '2026-05-04', '2026-05-05'],
            array_keys($daily),
            'A day with no traffic still gets a bar, otherwise the chart lies about its own x-axis.',
        );
        $this->assertSame(1, $daily['2026-05-03']);
        $this->assertSame(0, $daily['2026-05-02']);
    }

    public function test_clicks_outside_the_range_are_excluded(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, '2026-05-03 09:00:00');
        $this->click($link, '2026-04-01 09:00:00');

        $this->assertSame(1, $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->totals()['clicks']);
    }

    public function test_bots_are_excluded_unless_asked_for(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, '2026-05-03 09:00:00');
        $this->click($link, '2026-05-03 10:00:00', ['is_bot' => true]);

        $this->assertSame(1, $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->totals()['clicks']);
        $this->assertSame(2, $this->report($user, '2026-05-01', '2026-05-05', 'UTC', true)->totals()['clicks']);
    }

    public function test_a_folder_report_counts_only_that_folders_links(): void
    {
        $user = User::factory()->create();
        $folder = Project::create(['user_id' => $user->id, 'name' => 'Marketing']);
        $inside = $this->link($user, $folder);
        $outside = $this->link($user);

        $this->click($inside, '2026-05-03 09:00:00');
        $this->click($outside, '2026-05-03 09:30:00');

        $scoped = new LinkReport(
            Link::query()->where('user_id', $user->id)->where('project_id', $folder->id),
            Carbon::parse('2026-05-01', 'UTC')->startOfDay(),
            Carbon::parse('2026-05-05', 'UTC')->endOfDay(),
            'UTC',
        );

        $this->assertSame(1, $scoped->totals()['clicks']);
        $this->assertSame(2, $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->totals()['clicks']);
    }

    public function test_links_with_no_clicks_are_still_listed(): void
    {
        $user = User::factory()->create();
        $busy = $this->link($user, null, ['title' => 'Busy']);
        $this->link($user, null, ['title' => 'Quiet']);
        $this->click($busy, '2026-05-03 09:00:00');

        $rows = $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->links();

        $this->assertCount(2, $rows, 'A link with no clicks is half the answer, so it belongs in the table.');
        $this->assertSame('Busy', $rows[0]['link']->title, 'Busiest first.');
        $this->assertSame(0, $rows[1]['clicks']);
    }

    /**
     * My Links hands this class the `HasMany` relation it already built from
     * the request filters, not an Eloquent builder. Typing the constructor
     * for a builder alone passed every test that constructed the report
     * directly and broke every request that went through the page.
     */
    public function test_a_relation_is_accepted_as_the_link_scope(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, '2026-05-03 09:00:00');

        $report = new LinkReport(
            $user->links(),
            Carbon::parse('2026-05-01', 'UTC')->startOfDay(),
            Carbon::parse('2026-05-05', 'UTC')->endOfDay(),
            'UTC',
        );

        $this->assertSame(1, $report->totals()['clicks']);
    }

    /**
     * `MAX(clicked_at)` arrives from the query builder as a string, and both
     * the view and the CSV format it as a date. Returning the raw string
     * passed every unit assertion and 500'd the page on the first render.
     */
    public function test_last_click_is_a_date_not_a_string(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, '2026-05-03 09:00:00');

        $rows = $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->links();

        $this->assertInstanceOf(Carbon::class, $rows[0]['lastClick']);
        $this->assertSame('2026-05-03 09:00:00', $rows[0]['lastClick']->toDateTimeString());
    }

    public function test_range_is_clamped_to_what_the_plan_retains(): void
    {
        [$from] = LinkReport::range('2020-01-01', null, 30, 'UTC');

        $this->assertTrue(
            $from->gte(Carbon::now('UTC')->subDays(31)),
            'A 30-day plan cannot answer a 2020 question; returning zeros would read as "no traffic".',
        );
    }

    public function test_breakdowns_collapse_nulls_rather_than_dropping_them(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, '2026-05-03 09:00:00', ['source' => 'instagram']);
        $this->click($link, '2026-05-03 10:00:00');

        $rows = $this->report($user, '2026-05-01', '2026-05-05', 'UTC')->breakdown('sources');

        $this->assertSame(
            2,
            $rows->sum('clicks'),
            'Rows with no source become "Direct"; dropping them would make the table disagree with the headline.',
        );
        $this->assertContains('Direct', $rows->pluck('label')->all());
    }

    /**
     * The drift guard. Every breakdown declared in the catalogue must be
     * queryable, rendered on the page, and exportable — this codebase's
     * recurring failure is a control that exists in one of those three
     * places and not the others.
     */
    public function test_every_breakdown_the_catalogue_declares_is_rendered_and_exportable(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user);
        $this->click($link, Carbon::now('UTC')->subDay()->toDateTimeString(), [
            'source' => 'instagram', 'referrer' => 'instagram.com', 'channel' => 'social',
            'country_code' => 'IN', 'city' => 'Bengaluru', 'device_type' => 'mobile',
            'browser' => 'Chrome', 'os' => 'Android', 'language' => 'en',
        ]);

        $page = $this->visit($user);
        $page->assertOk();

        $report = $this->report($user, Carbon::now('UTC')->subDays(7)->toDateString(), Carbon::now('UTC')->toDateString(), 'UTC');

        foreach (LinkReport::BREAKDOWNS as $key => $spec) {
            $this->assertNotEmpty(
                $report->breakdown($key),
                "Breakdown [$key] is declared but produced no rows for a click that set every column.",
            );
            $page->assertSee($spec['label'], false);

            $csv = $this->visit($user, ['export' => $key]);
            $csv->assertOk();
            $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        }
    }

    public function test_export_all_contains_every_section(): void
    {
        $user = User::factory()->create();
        $link = $this->link($user, null, ['title' => 'Measured']);
        $this->click($link, Carbon::now('UTC')->subDay()->toDateTimeString(), ['source' => 'instagram']);

        $csv = $this->visit($user, ['export' => 'all']);
        $csv->assertOk();
        $body = $csv->streamedContent();

        foreach (['Clicks per day', 'Clicks by weekday and hour', 'Links', 'UTM campaigns'] as $title) {
            $this->assertStringContainsString($title, $body);
        }
        foreach (LinkReport::BREAKDOWNS as $spec) {
            $this->assertStringContainsString($spec['label'], $body);
        }
        $this->assertStringContainsString('Measured', $body);
    }

    public function test_an_unknown_export_section_is_not_silently_an_empty_file(): void
    {
        $user = User::factory()->create();
        $this->link($user);

        $this->visit($user, ['export' => 'nonsense'])->assertNotFound();
    }
}
