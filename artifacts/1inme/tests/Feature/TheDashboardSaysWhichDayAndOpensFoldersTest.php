<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Two things Sana reported on the Aurora dashboard, 2026-09-22.
 *
 * 1. "Which dates is not shown." The week chart labelled only its two ends
 *    ("Wed ... Tue"), the heatmap labelled its rows M T W T F S S in calendar
 *    order with today somewhere in the middle, and the hero said "Tuesday"
 *    without a date. Worse, the card headed "Total clicks -- last 7 days"
 *    showed the LIFETIME total: 6,123, on a chart that covered 2,578.
 *
 * 2. "Folders not showing proper and not clickable." The rows were links,
 *    but looked like a static list with underlines; only five of them could
 *    appear; the 71 unfiled links -- most of the list -- had no way in; and
 *    /user/projects redirects to this very panel, so it IS the folders page.
 */
class TheDashboardSaysWhichDayAndOpensFoldersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-22 15:00:00'));   // a Tuesday

        // Onboarded well before the frozen clock, so none of the
        // recently-onboarded soft gates redirect the dashboard away.
        $this->user = User::factory()->create([
            'settings'     => ['ui_pack' => 'aurora'],
            'onboarded_at' => Carbon::parse('2026-01-01'),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function link(array $attrs = []): Link
    {
        $ws = app(WorkspaceContext::class)->resolve($this->user);

        return Link::create(array_merge([
            'user_id'      => $this->user->id,
            'workspace_id' => $ws?->id,
            'type'         => 'link',
            'alias'        => 'l'.fake()->unique()->numerify('#######'),
            'url'          => 'https://example.com',
            'is_active'    => true,
        ], $attrs));
    }

    private function folder(string $name, string $color = '#e0457b')
    {
        // Folders are workspace-scoped like links; without the workspace the
        // dashboard's folder query filters them out.
        $ws = app(WorkspaceContext::class)->resolve($this->user);

        return $this->user->projects()->create([
            'name' => $name, 'color' => $color, 'workspace_id' => $ws?->id,
        ]);
    }

    private function clicks(Link $link, string $when, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            LinkClick::create([
                'link_id'    => $link->id,
                'ip_address' => '203.0.113.'.($i % 250),
                'clicked_at' => Carbon::parse($when),
            ]);
        }
    }

    private function dashboard(): string
    {
        return $this->actingAs($this->user)->get('/user/dashboard')->assertOk()->getContent();
    }

    // ===== 1. Dates =====

    /** The hero names the date, not just the weekday. */
    public function test_the_hero_says_the_date(): void
    {
        $this->assertStringContainsString('Tuesday, 22 Sep', $this->dashboard());
    }

    /**
     * The week card's headline is the week, not the lifetime.
     *
     * This is the number that was wrong: lifetime clicks under a
     * "last 7 days" label.
     */
    public function test_the_week_figure_is_the_week_and_not_the_lifetime(): void
    {
        $l = $this->link(['total_clicks' => 500]);    // lifetime, mostly old
        $this->clicks($l, '2026-09-22 10:00', 7);
        $this->clicks($l, '2026-09-18 10:00', 3);
        $this->clicks($l, '2026-08-01 10:00', 40);    // outside the week

        $html = $this->dashboard();

        $this->assertStringContainsString('Clicks this week', $html);
        $this->assertMatchesRegularExpression('/Clicks this week.*?au-fig">10</s', $html,
            'the headline must be the 10 clicks this week, not a lifetime figure');
        $this->assertStringContainsString('500 all-time', $html,
            'the lifetime figure is still shown, labelled as what it is');
    }

    /** Every day on the chart is named and dated, oldest to today. */
    public function test_every_day_on_the_chart_is_dated(): void
    {
        $html = $this->dashboard();

        $this->assertStringContainsString('16–22 Sep', $html, 'the card states the range it covers');

        preg_match_all('/class="au-day[^"]*"[^>]*>.*?<b>(\w+)<\/b>\s*<em>(\d+)<\/em>/s', $html, $m, PREG_SET_ORDER);
        $days = array_map(fn ($x) => $x[1].' '.$x[2], $m);

        $this->assertSame(
            ['Wed 16', 'Thu 17', 'Fri 18', 'Sat 19', 'Sun 20', 'Mon 21', 'Tue 22'],
            $days,
            'all seven days must be labelled with their date, in order, ending today'
        );
    }

    /** The peak names its date, and each point says its count on hover. */
    public function test_the_peak_and_each_point_name_their_day(): void
    {
        $l = $this->link();
        $this->clicks($l, '2026-09-18 10:00', 5);

        $html = $this->dashboard();

        $this->assertStringContainsString('Peak 5 &middot; Fri 18 Sep', $html);
        $this->assertStringContainsString('title="Friday 18 September: 5 clicks"', $html);
    }

    /**
     * The heatmap's rows are the week, oldest first, each dated -- not
     * Monday-first letters.
     */
    public function test_the_heatmap_rows_are_dated_and_end_today(): void
    {
        $l = $this->link();
        $this->clicks($l, '2026-09-22 14:30', 4);

        $html = $this->dashboard();

        preg_match_all('/class="au-heat-day[^"]*">(\w+) <em>(\d+)<\/em>/', $html, $m, PREG_SET_ORDER);
        $rows = array_map(fn ($x) => $x[1].' '.$x[2], $m);

        $this->assertSame(
            ['Wed 16', 'Thu 17', 'Fri 18', 'Sat 19', 'Sun 20', 'Mon 21', 'Tue 22'],
            $rows,
            'heatmap rows must run oldest to today, each with its date'
        );

        // And the clicks land on the right row: today's row, the 14:00 block.
        $this->assertStringContainsString('title="Tue 22 Sep, 14:00–16:00: 4 clicks"', $html);
        $this->assertStringContainsString('Peak 4 &middot; Tue 22 Sep · 14:00', $html);
    }

    // ===== 2. Folders =====

    /** Each folder is a tile that opens that folder's links. */
    public function test_each_folder_is_a_tile_that_opens_it(): void
    {
        $partners = $this->folder('Partners');
        $this->link(['project_id' => $partners->id, 'total_clicks' => 120]);
        $this->link(['project_id' => $partners->id, 'total_clicks' => 30]);

        $html = $this->dashboard();

        $this->assertMatchesRegularExpression(
            '/<a class="au-folder" href="[^"]*\/user\/projects\/'.$partners->id.'"/',
            $html,
            'the folder tile must be a link to that folder'
        );
        $this->assertMatchesRegularExpression('/au-folder-fig">150</', $html,
            "the tile's figure is the folder's clicks");
        $this->assertStringContainsString('clicks &middot; 2 links', $html);

        // And the link actually lands on that folder's links.
        $this->actingAs($this->user)->get('/user/projects/'.$partners->id)
            ->assertRedirect();
    }

    /** Every folder is reachable: this panel is the folders page. */
    public function test_every_folder_is_shown_not_just_five(): void
    {
        foreach (range(1, 7) as $i) {
            $this->folder('Folder '.$i);
        }

        $html = $this->dashboard();

        $this->assertSame(7, substr_count($html, 'class="au-folder"'),
            '/user/projects redirects here, so no folder may be left out');
        $this->assertStringContainsString('Show 3 more folders', $html,
            'the first four show, the rest behind a disclosure');
        $this->assertStringContainsString('id="folders"', $html,
            'the #folders anchor the retired folders page redirects to must exist');
    }

    /** The unfiled links have a way in, and it shows only them. */
    public function test_the_unfiled_links_can_be_opened(): void
    {
        $f = $this->folder('Docs');
        $filed = $this->link(['project_id' => $f->id, 'alias' => 'filedone']);
        $this->link(['alias' => 'looseone']);
        $this->link(['alias' => 'loosetwo']);

        $html = $this->dashboard();
        $this->assertStringContainsString('project_id=none', $html);
        $this->assertMatchesRegularExpression('/<b>2<\/b> unfiled links/', $html);

        $list = $this->actingAs($this->user)->get('/user/links?project_id=none')->assertOk()->getContent();
        $this->assertStringContainsString('looseone', $list);
        $this->assertStringContainsString('loosetwo', $list);
        $this->assertStringNotContainsString('filedone', $list,
            'the unfiled view must not include filed links');
    }

    /** A junk folder filter is ignored, not handed to Postgres to 500 on. */
    public function test_a_junk_folder_filter_does_not_break_the_links_page(): void
    {
        $this->actingAs($this->user)->get('/user/links?project_id=abc')->assertOk();
    }

    /** The strip states how much is filed. */
    public function test_the_strip_says_how_organised_the_links_are(): void
    {
        $f = $this->folder('Docs');
        $this->link(['project_id' => $f->id]);
        foreach (range(1, 3) as $_) {
            $this->link();
        }

        $html = $this->dashboard();

        $this->assertStringContainsString('<b>25%</b> filed', $html);
        $this->assertStringContainsString('1 of 4 links', $html);
        $this->assertStringContainsString('class="is-unfiled"', $html);
    }

    /** New folders can be started from here. */
    public function test_a_new_folder_can_be_started_from_the_panel(): void
    {
        $this->folder('Docs');

        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/user\/projects\/create"[^>]*>\+ New folder/',
            $this->dashboard()
        );
    }
}
