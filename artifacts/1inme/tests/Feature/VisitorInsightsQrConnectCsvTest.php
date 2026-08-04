<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\EventQrConnect;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Visitor Insights CSV export must include the QR Connect funnel section
 * (totals + per-day scans/connects) for event ('ics') links, and must omit it
 * for non-event links (Task #6690).
 */
class VisitorInsightsQrConnectCsvTest extends TestCase
{
    use RefreshDatabase;

    private function paidUser(): User
    {
        $slug = 'p' . Str::random(6);
        $plan = Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => ['max_links' => 100, 'analytics_export' => true],
        ]);
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    private function link(User $u, string $type): Link
    {
        return $u->links()->create([
            'user_id'   => $u->id,
            'type'      => $type,
            'alias'     => 'a' . substr(Str::random(8), 0, 8),
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);
    }

    public function test_event_link_export_includes_qr_connect_section(): void
    {
        $u = $this->paidUser();
        $link = $this->link($u, 'ics');

        // Two scan days: 3 scans on day 1, 1 scan on day 2.
        foreach ([['-2 days', 3], ['-1 day', 1]] as [$offset, $n]) {
            for ($i = 0; $i < $n; $i++) {
                DB::table('link_clicks')->insert([
                    'link_id'    => $link->id,
                    'source'     => 'connect_qr',
                    'is_bot'     => false,
                    'ip_address' => '10.0.0.' . rand(2, 250),
                    'clicked_at' => now()->modify($offset),
                ]);
            }
        }

        // Two completed connects on day 2 (one new user, followed).
        $visitor = User::create([
            'name' => 'v' . Str::random(4), 'email' => 'v' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);
        $visitor2 = User::create([
            'name' => 'w' . Str::random(4), 'email' => 'w' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'), 'status' => 'active',
        ]);
        EventQrConnect::create(['link_id' => $link->id, 'user_id' => $visitor->id, 'was_new_user' => true, 'followed' => true]);
        EventQrConnect::create(['link_id' => $link->id, 'user_id' => $visitor2->id, 'was_new_user' => false, 'followed' => false]);
        EventQrConnect::where('link_id', $link->id)->update(['created_at' => now()->modify('-1 day')]);

        $resp = $this->actingAs($u)->get(route('user.links.visitors.export', $link) . '?period=30d');
        $resp->assertStatus(200);
        $csv = $resp->streamedContent();

        $this->assertStringContainsString('QR Connect', $csv);
        $this->assertStringContainsString("Scans,4", $csv);
        $this->assertStringContainsString("Connects,2", $csv);
        $this->assertStringContainsString("\"New users\",1", $csv);
        $this->assertStringContainsString("\"Existing users\",1", $csv);
        $this->assertStringContainsString("Follows,1", $csv);
        $this->assertStringContainsString("RSVPs,0", $csv);
        $this->assertStringContainsString("\"Conversion %\",50", $csv);

        // Per-day table rows.
        $this->assertStringContainsString('QR Connect daily', $csv);
        $d1 = now()->modify('-2 days')->format('Y-m-d');
        $d2 = now()->modify('-1 day')->format('Y-m-d');
        $this->assertStringContainsString("$d1,3,0", $csv);
        $this->assertStringContainsString("$d2,1,2", $csv);
    }

    public function test_non_event_link_export_omits_qr_connect_section(): void
    {
        $u = $this->paidUser();
        $link = $this->link($u, 'url');

        $resp = $this->actingAs($u)->get(route('user.links.visitors.export', $link) . '?period=30d');
        $resp->assertStatus(200);
        $this->assertStringNotContainsString('QR Connect', $resp->streamedContent());
    }
}
