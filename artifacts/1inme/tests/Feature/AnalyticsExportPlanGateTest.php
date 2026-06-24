<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for the `analytics_export` plan gate that protects the CSV
 * export endpoints. A free-plan owner must be bounced with the upgrade error
 * (never handed a CSV); a paid-plan owner must receive the CSV download.
 *
 * Routes covered:
 *  - user.links.clicks.export       (LinkController::exportClicks)
 *  - user.links.followers.export    (LinkController::followersExport)
 *  - user.links.slides.analytics.csv (SlideDeckController::exportCsv)
 *  - user.stats.export              (CreatorStatsController::export)
 */
class AnalyticsExportPlanGateTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], ?string $slug = null): Plan
    {
        $slug = $slug ?: ('p' . Str::random(6));
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function link(User $u, string $type = 'url'): Link
    {
        return $u->links()->create([
            'user_id'   => $u->id,
            'type'      => $type,
            'alias'     => 'a' . substr(Str::random(8), 0, 8),
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);
    }

    private function freePlan(): Plan
    {
        return $this->plan(['max_links' => 100, 'analytics_export' => false]);
    }

    private function paidPlan(): Plan
    {
        return $this->plan(['max_links' => 100, 'analytics_export' => true]);
    }

    /** A blocked response must redirect with the upgrade error and never be a CSV. */
    private function assertExportBlocked($resp): void
    {
        $resp->assertSessionHas('error');
        $this->assertSame(302, $resp->status(), 'Blocked export should redirect, not stream a CSV');
        $this->assertStringNotContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
    }

    /** An allowed response must be a CSV download. */
    private function assertCsvDownload($resp): void
    {
        $resp->assertSessionMissing('error');
        $resp->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $resp->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'attachment',
            (string) $resp->headers->get('Content-Disposition'),
        );
    }

    // ===== Clicks export =====

    public function test_clicks_export_blocked_for_free_plan(): void
    {
        $u = $this->user($this->freePlan());
        $link = $this->link($u);
        $resp = $this->actingAs($u)->get(route('user.links.clicks.export', $link));
        $this->assertExportBlocked($resp);
    }

    public function test_clicks_export_allowed_for_paid_plan(): void
    {
        $u = $this->user($this->paidPlan());
        $link = $this->link($u);
        $resp = $this->actingAs($u)->get(route('user.links.clicks.export', $link));
        $this->assertCsvDownload($resp);
    }

    // ===== Followers export =====

    public function test_followers_export_blocked_for_free_plan(): void
    {
        $u = $this->user($this->freePlan());
        $link = $this->link($u);
        $resp = $this->actingAs($u)->get(route('user.links.followers.export', $link));
        $this->assertExportBlocked($resp);
    }

    public function test_followers_export_allowed_for_paid_plan(): void
    {
        $u = $this->user($this->paidPlan());
        $link = $this->link($u);
        $resp = $this->actingAs($u)->get(route('user.links.followers.export', $link));
        $this->assertCsvDownload($resp);
    }

    // ===== Slide analytics CSV =====

    public function test_slides_analytics_csv_blocked_for_free_plan(): void
    {
        $u = $this->user($this->freePlan());
        $link = $this->link($u, Link::TYPE_SLIDES);
        $resp = $this->actingAs($u)->get(route('user.links.slides.analytics.csv', $link));
        $this->assertExportBlocked($resp);
    }

    public function test_slides_analytics_csv_allowed_for_paid_plan(): void
    {
        $u = $this->user($this->paidPlan());
        $link = $this->link($u, Link::TYPE_SLIDES);
        $resp = $this->actingAs($u)->get(route('user.links.slides.analytics.csv', $link));
        $this->assertCsvDownload($resp);
    }

    // ===== Creator-stats dashboard export =====

    public function test_creator_stats_export_blocked_for_free_plan(): void
    {
        $u = $this->user($this->freePlan());
        $resp = $this->actingAs($u)->get(route('user.stats.export'));
        $this->assertExportBlocked($resp);
    }

    public function test_creator_stats_export_allowed_for_paid_plan(): void
    {
        $u = $this->user($this->paidPlan());
        $resp = $this->actingAs($u)->get(route('user.stats.export'));
        $this->assertCsvDownload($resp);
    }
}
