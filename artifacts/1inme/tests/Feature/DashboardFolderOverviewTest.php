<?php

namespace Tests\Feature;

use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFolderOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_reports_and_summary_exclude_other_folders_and_users(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(2)]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        $folder = $user->projects()->create(['name' => 'Campaign', 'workspace_id' => $ws->id]);
        $selected = $user->links()->create(['type' => 'url', 'alias' => 'scoped-report', 'title' => 'Selected link', 'workspace_id' => $ws->id, 'project_id' => $folder->id, 'is_active' => true, 'total_clicks' => 12]);
        $outside = $user->links()->create(['type' => 'url', 'alias' => 'outside-report', 'workspace_id' => $ws->id, 'is_active' => true, 'total_clicks' => 999]);
        foreach ([$selected, $outside] as $link) {
            LinkClick::create(['link_id' => $link->id, 'clicked_at' => now(), 'referrer' => $link->id === $selected->id ? 'chosen.example' : 'excluded.example']);
        }
        $other = User::factory()->create();
        $foreign = $other->links()->create(['type' => 'url', 'alias' => 'foreign-report', 'is_active' => true]);
        LinkClick::create(['link_id' => $foreign->id, 'clicked_at' => now(), 'referrer' => 'foreign.example']);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $response = $this->get(route('user.links.index', ['project_id' => $folder->id, 'view' => 'reports']));
        $response->assertOk()->assertViewHas('summary', fn ($summary) => $summary['total'] === 1 && (int) $summary['clicks'] === 12)
            // 12465cd0d ("Turn the Reports tab into a combined project
            // report", 12 Oct) replaced the flat report array with one whose
            // figures sit under 'totals'. The old 'total' was
            // (clone $clicks)->count() -- clicks in scope -- so that is what
            // this checks, and it checks the link count beside it: both are 1
            // here, and asserting only one of them would pass for the wrong
            // reason if scoping broke on the other axis.
            ->assertViewHas('report', fn ($report) => $report['totals']['clicks'] === 1 && $report['totals']['links'] === 1)
            ->assertSee('chosen.example')->assertDontSee('excluded.example')->assertDontSee('foreign.example');
        // Same commit as above gave the export its range: the name is
        // 'link-report-<section>-<from>_<to>.csv'. This asserted the old flat
        // name, and never ran -- the undefined-key error above aborted the
        // test before reaching it.
        //
        // The dates come from the plan's retention window against today, so
        // they move daily. Matching the shape rather than the value keeps this
        // from going red overnight, and says more than the old assertion did:
        // the export is named for its section AND the range it covers.
        $export = $this->get(route('user.links.index', ['project_id' => $folder->id, 'view' => 'reports', 'export' => 'daily']))
            ->assertOk()->assertDownload();

        $this->assertMatchesRegularExpression(
            '/filename="?link-report-daily-\d{4}-\d{2}-\d{2}_\d{4}-\d{2}-\d{2}\.csv"?/',
            (string) $export->headers->get('content-disposition'),
            'the daily export must be named for its section and the range it covers'
        );
    }

    public function test_saved_custom_folder_color_is_used_on_dashboard_and_folder_header(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(2)]);
        $ws = app(WorkspaceContext::class)->resolve($user);
        $folder = $user->projects()->create(['name' => 'Custom colour', 'workspace_id' => $ws->id, 'color' => '#d946ef']);
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->get(route('user.dashboard'))->assertOk()
            ->assertSee('--ov-color: #d946ef', false)->assertSee('ov-folder-art', false);
        // The folder page carried the saved colour as a hardcoded
        // `border-left:4px solid <hex>` on `.links-head` (e8ff504f8). 36468b29a
        // replaced that header with the folder shell, which sets the colour as
        // a custom property instead -- `.folder-colored-header .page-hero`
        // tints its gradient and border from it and `.hero-emblem` takes it as
        // its colour. Same feature, different mechanism; this asserted the old
        // one and had been red since the day after it was written.
        $this->get(route('user.links.index', ['project_id' => $folder->id]))->assertOk()
            ->assertSee('--folder-color:#d946ef', false)
            ->assertSee('folder-colored-header', false);
    }

    public function test_default_dashboard_shows_usage_and_folders_without_traffic_charts(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(2)]);
        $this->actingAs($user)->get(route('user.dashboard'))->assertOk()
            ->assertViewIs('user.dashboard.overview')->assertSee('Plan &amp; usage', false)
            ->assertSee('Your folders')->assertSee('Recently updated')->assertDontSee('Clicks this week');
    }
}
