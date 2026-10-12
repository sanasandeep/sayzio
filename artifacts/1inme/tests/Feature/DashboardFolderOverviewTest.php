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
            ->assertViewHas('report', fn ($report) => $report['total'] === 1)
            ->assertSee('chosen.example')->assertDontSee('excluded.example')->assertDontSee('foreign.example');
        $this->get(route('user.links.index', ['project_id' => $folder->id, 'view' => 'reports', 'export' => 'daily']))
            ->assertOk()->assertDownload('link-report-daily.csv');
    }

    public function test_default_dashboard_shows_usage_and_folders_without_traffic_charts(): void
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(2)]);
        $this->actingAs($user)->get(route('user.dashboard'))->assertOk()
            ->assertViewIs('user.dashboard.overview')->assertSee('Plan &amp; usage', false)
            ->assertSee('Your folders')->assertSee('Recently updated')->assertDontSee('Clicks this week');
    }
}
