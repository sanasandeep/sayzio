<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Two things Sana reported on the dashboard, 2026-09-22.
 *
 * 1. "Which dates is not shown." The hero said "Tuesday" without a date.
 * 2. "Folders not showing proper and not clickable." The rows were links,
 *    but looked like a static list with underlines; only five of them could
 *    appear; the unfiled links -- most of the list -- had no way in; and
 *    /user/projects redirects to this very panel, so it IS the folders page.
 *
 * ---
 *
 * Re-pointed 2026-10-17. These tests were written against the Aurora
 * dashboard and fetched it at `/user/dashboard?view=custom`. Commit
 * 9116072e4 ("Remove duplicate dashboard actions and retire custom dashboard
 * entry", 2026-10-13) turned that query into a redirect back to
 * `/user/dashboard`, so every test here had been asserting 200 against a 302
 * ever since -- twelve reds that said nothing about the page users see.
 *
 * The live dashboard is the account overview. It kept both fixes: the hero
 * names the date, and every folder is a tile that opens it. So the guards
 * below are the same two guards, moved to the page that still exists.
 *
 * What did NOT move, because the overview does not have it: the week-vs-
 * lifetime clicks figure, the dated 7-day chart, the hour-by-day heatmap and
 * the "% filed" strip. Those assertions are deleted rather than weakened --
 * a guard pointed at a retired page is worse than no guard, because it reads
 * green-adjacent in a filter nobody opens. The numbers themselves moved to
 * account analytics and folder-scoped reports; the last test here is what
 * holds the dashboard to still having a door to them.
 */
class TheDashboardSaysWhichDayAndOpensFoldersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // A Tuesday. Chosen at 15:00 UTC on purpose: the overview renders the
        // date in the user's timezone, which falls back to IST (+05:30), so
        // the frozen instant is 20:30 on the same calendar day. Moving this
        // past 18:30 UTC would roll the displayed date forward a day.
        Carbon::setTestNow(Carbon::parse('2026-09-22 15:00:00'));

        // Onboarded well before the frozen clock, so none of the
        // recently-onboarded soft gates redirect the dashboard away.
        $this->user = User::factory()->create([
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

    /**
     * The live dashboard. No `?view=custom` -- that is the retired entry and
     * it answers 302, which is what made this whole file red.
     */
    private function dashboard(): string
    {
        return $this->actingAs($this->user)->get('/user/dashboard')->assertOk()->getContent();
    }

    // ===== 1. The date =====

    /** The hero names the date, not just the weekday. */
    public function test_the_hero_says_the_date(): void
    {
        $this->assertStringContainsString('Tuesday, 22 Sep', $this->dashboard());
    }

    /**
     * The retired entry answers a redirect, not a 404 and not a half-wired
     * page. Anyone still holding a `?view=custom` bookmark lands on the
     * dashboard rather than an error -- and if that ever regresses to
     * rendering something again, this says so.
     */
    public function test_the_retired_custom_entry_sends_you_to_the_dashboard(): void
    {
        $this->actingAs($this->user)
            ->get('/user/dashboard?view=custom')
            ->assertRedirect(route('user.dashboard'));
    }

    // ===== 2. Folders =====

    /** Each folder is a tile that opens that folder. */
    public function test_each_folder_is_a_tile_that_opens_it(): void
    {
        $partners = $this->folder('Partners');
        $this->link(['project_id' => $partners->id]);
        $this->link(['project_id' => $partners->id]);

        $html = $this->dashboard();

        $this->assertMatchesRegularExpression(
            '/<a class="ov-folder"[^>]*href="[^"]*\/user\/projects\/'.$partners->id.'"/',
            $html,
            'the folder tile must be a link to that folder'
        );
        $this->assertStringContainsString('Partners', $html, 'the tile names the folder');
        $this->assertStringContainsString('2 links', $html, "the tile states the folder's size");

        // And the link actually lands on that folder's own page.
        //
        // The test this replaced asserted a redirect here. That was true when
        // /user/projects/{project} bounced to the dashboard panel; commit
        // 36468b29a built the folder hub at that route, so it answers 200 and
        // renders the folder. I carried the old assertion over without
        // rechecking it, and the run caught it.
        $this->actingAs($this->user)->get('/user/projects/'.$partners->id)
            ->assertOk()
            ->assertSee('Partners');
    }

    /** Every folder is reachable: this panel is the folders page. */
    public function test_every_folder_is_shown_not_just_five(): void
    {
        foreach (range(1, 8) as $i) {
            $this->folder('Folder '.$i);
        }

        $html = $this->dashboard();

        $this->assertSame(8, substr_count($html, 'class="ov-folder"'),
            '/user/projects redirects here, so no folder may be left out');
        $this->assertStringContainsString('id="folders"', $html,
            'the #folders anchor the retired folders page redirects to must exist');
    }

    /** The unfiled links have a way in, and it shows only them. */
    public function test_the_unfiled_links_can_be_opened(): void
    {
        $f = $this->folder('Docs');
        $this->link(['project_id' => $f->id, 'alias' => 'filedone']);
        $this->link(['alias' => 'looseone']);
        $this->link(['alias' => 'loosetwo']);

        $html = $this->dashboard();
        $this->assertStringContainsString('project_id=none', $html);
        $this->assertStringContainsString('2 unfiled links', $html);

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

    /** New folders can be started from here. */
    public function test_a_new_folder_can_be_started_from_the_panel(): void
    {
        $this->folder('Docs');

        $html = $this->dashboard();

        $this->assertStringContainsString('Create a folder', $html);
        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="[^"]*\/user\/projects"/',
            $html,
            'the panel must post a new folder, not just link away'
        );
        $this->assertStringContainsString('name="name"', $html, 'the form takes a folder name');
    }

    // ===== 3. Where the numbers went =====

    /**
     * The Aurora dashboard carried the week chart and the heatmap inline.
     * Retiring it moved those to account analytics and folder-scoped
     * reports, which is fine -- as long as the dashboard still has a door to
     * them. A dashboard that shows counts and no way through to the numbers
     * is the 2026-09-22 complaint in a new shape.
     */
    public function test_the_dashboard_still_opens_the_numbers(): void
    {
        $html = $this->dashboard();

        $this->assertMatchesRegularExpression(
            '/href="[^"]*\/user\/stats"/',
            $html,
            'the overview must still lead to account analytics'
        );
        $this->assertStringContainsString('Open a folder to manage its links and view its reports.', $html,
            'and say that the per-folder numbers live behind a folder');
    }

    /**
     * Both pages that draw a click heatmap draw the same one.
     *
     * They did not: each had its own ramp, cell size and hover behaviour, and
     * Sana asked for "better and similar look for both places". One partial
     * is the only way they stay alike after the next change. The dashboard
     * used to be the third caller; it no longer draws a heatmap at all, so it
     * is no longer checked here.
     */
    public function test_every_page_that_draws_the_heatmap_uses_the_shared_partial(): void
    {
        $partial = (string) file_get_contents(resource_path('views/user/partials/click-heatmap.blade.php'));
        $this->assertStringContainsString('--hl6', $partial,
            'the shared ramp is stepped, not an alpha blend of the accent colour');

        foreach (['user/links/show.blade.php', 'user/links/partials/folder-reports.blade.php'] as $page) {
            $this->assertStringContainsString(
                "@include('user.partials.click-heatmap'",
                (string) file_get_contents(resource_path('views/'.$page)),
                $page.' must draw the heatmap from the shared partial'
            );
        }
    }
}
