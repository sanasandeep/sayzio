<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\PerformanceBusinessController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\IndustryPerformanceReport as Report;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IndustryPerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    private function link($type = 'restaurant_menu'): Link
    {
        $u = User::create(['name' => 'Owner', 'email' => Str::random(10).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($u);

        return Link::create(['user_id' => $u->id, 'type' => $type, 'alias' => Str::random(10), 'title' => 'Business', 'is_active' => true, 'settings' => ['keep' => 'value']]);
    }

    private function report(Link $l): array
    {
        return Report::build($l, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31')->endOfDay(), ['visits' => 41, 'interactions' => 6]);
    }

    public function test_restaurant_uses_actual_type_and_scoped_orders_instead_of_creator_score(): void
    {
        $l = $this->link();
        $l->update(['settings' => ['performance_business' => ['profile' => 'creator']]]);
        foreach ([['completed', $l->id, '2026-10-10'], ['cancelled', $l->id, '2026-10-10'], ['preparing', $l->id, '2026-10-10'], ['completed', 999, '2026-10-10'], ['completed', $l->id, '2026-09-10']] as [$status,$id,$date]) {
            DB::table('restaurant_orders')->insert(['menu_id' => 1, 'link_id' => $id, 'public_token' => Str::random(40), 'status' => $status, 'created_at' => $date]);
        }
        $r = $this->report($l);
        $this->assertSame('restaurant', $r['profile']);
        $this->assertSame('3', $r['metrics'][1]['value']);
        $this->assertSame('1', $r['metrics'][2]['value']);
        $this->assertStringContainsString('1 cancelled · 1 still open', $r['metrics'][2]['note']);
        $this->assertArrayNotHasKey('score', $r);
    }

    public function test_unknown_or_invalid_industry_prompts_instead_of_assuming_creator(): void
    {
        $l = $this->link('biolink');
        $l->update(['settings' => ['performance_business' => ['profile' => 'invalid']]]);
        $r = $this->report($l);
        $this->assertNull($r['profile']);
        $this->assertSame([], $r['metrics']);
        $this->assertStringContainsString('Choose an industry', $r['checks'][0]['detail']);
    }

    public function test_club_counts_current_approved_roster_and_pending_renewals_separately(): void
    {
        Carbon::setTestNow('2026-10-13');
        $l = $this->link('community_club');
        foreach ([['active', null], ['active', '2026-10-20'], ['active', '2026-10-01'], ['pending', '2027-01-01']] as $i => [$status,$expiry]) {
            DB::table('club_memberships')->insert(['link_id' => $l->id, 'name' => 'Member', 'email' => "m$i@example.com", 'phone' => '123456789', 'status' => $status, 'expires_at' => $expiry]);
        }
        $r = $this->report($l);
        $this->assertSame('2', $r['metrics'][1]['value']);
        $this->assertSame('1', $r['metrics'][2]['value']);
        $this->assertStringContainsString('Approval and payment are separate', json_encode($r['checks']));
        Carbon::setTestNow();
    }

    public function test_context_cannot_override_specialized_link_and_preserves_settings(): void
    {
        $l = $this->link();
        $req = Request::create('/', 'POST', ['profile' => 'creator', 'industry' => 'Tiffins takeaway', 'audience' => 'Nearby workers', 'goal' => 'Breakfast orders']);
        $req->setLaravelSession(app('session')->driver());
        app(PerformanceBusinessController::class)->update($req, $l);
        $l->refresh();
        $this->assertSame('value', $l->settings['keep']);
        $this->assertSame('restaurant', $l->settings['performance_business']['profile']);
        $this->assertSame('Breakfast orders', $this->report($l)['goal']);
    }

    public function test_other_owner_cannot_change_business_context(): void
    {
        $l = $this->link();
        $other = User::create(['name' => 'Other', 'email' => Str::random(10).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($other);
        $this->expectException(HttpException::class);
        app(PerformanceBusinessController::class)->update(Request::create('/', 'POST', ['profile' => 'restaurant', 'industry' => 'Cafe', 'audience' => 'Locals', 'goal' => 'Orders']), $l);
    }

    public function test_coloured_views_render_with_missing_sessions_and_without_ai(): void
    {
        $l = $this->link();
        $business = view('user.links.partials.business-performance', ['link' => $l, 'businessPerformance' => $this->report($l)])->render();
        $stats = view('user.links.partials.secondary-metrics', ['link' => $l, 'pageVisitsInRange' => 41, 'blockClicksInRange' => 6, 'hasPollBlocks' => false, 'pollVotesInRange' => 0, 'totalSessions' => 0, 'totalEngagedSeconds' => 0, 'bounceRate' => 0, 'avgSessionSeconds' => 0, 'totalInRange' => 47, 'uniqueInRange' => 16, 'botClicksInRange' => 66])->render();
        $this->assertStringContainsString('Restaurant operations', $business);
        $this->assertStringNotContainsString('Creator / Link-in-bio', $business);
        $this->assertStringContainsString('No sessions tracked', $stats);
        $this->assertStringContainsString('Visitor ratio', $stats);
        $this->assertStringNotContainsString('Conversion', $stats);
        $this->assertStringContainsString('count-art', $stats);
        if (getenv('INDUSTRY_UI_FIXTURE')) {
            $show = file_get_contents(resource_path('views/user/links/show.blade.php'));
            $start = strpos($show, '    .kpi-strip{');
            $end = strpos($show, '    /*', $start + 20);
            $css = substr($show, $start, $end - $start);
            file_put_contents('/tmp/industry-fixture.html','<!doctype html><html><head><meta charset="utf-8"><style>:root{--bg-card:#fff;--text-muted:#687086;--border-glass:#e4e7ef;--accent:#4167ee}*{box-sizing:border-box}body{font-family:system-ui;margin:24px;color:#172239;background:#f6f8fc}h2,h3,p{margin:0}input,select{padding:12px;border:1px solid #dce2ef;border-radius:10px}'.$css.'</style></head><body>'.$business.$stats.'</body></html>');
        }
    }
}
