<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\User;
use App\Services\AI\AiStaffConnections;
use App\Services\AI\AiStaffReports;
use App\Services\AI\AiStaffRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AiStaffConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_domains_reuse_general_feature_controls(): void
    {
        foreach (['sales', 'support', 'projects', 'client_success', 'marketing', 'scheduling'] as $domain) {
            $this->assertSame('ai_staff_general', (new AiStaff(['domain' => $domain]))->featureKey());
        }
    }

    public function test_foreign_mind_is_rejected(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $mind = AiMind::create(['user_id' => $other->id, 'name' => 'Private']);
        $r = Request::create('/', 'POST', ['connections' => ['mind_ids' => [$mind->id]]]);
        $r->setUserResolver(fn () => $owner);
        $this->expectException(HttpException::class);
        app(AiStaffConnections::class)->validate($r);
    }

    public function test_empty_selection_is_persisted_explicitly(): void
    {
        $u = User::factory()->create();
        $r = Request::create('/', 'POST', ['connections' => ['mode' => 'read']]);
        $r->setUserResolver(fn () => $u);
        $this->assertSame([], app(AiStaffConnections::class)->validate($r)['data_keys']);
    }

    public function test_disabled_connected_mind_is_not_read(): void
    {
        $u = User::factory()->create();
        $mind = AiMind::create(['user_id' => $u->id, 'name' => 'Knowledge']);
        $mind->sources()->create(['type' => 'text', 'title' => 'Policy', 'body' => 'Private policy wording', 'status' => 'ready']);
        $staff = AiStaff::create(['user_id' => $u->id, 'name' => 'Helper', 'domain' => 'sales', 'config' => ['connections' => ['mind_ids' => [$mind->id]]]]);
        $this->assertStringContainsString('Private policy wording', app(AiStaffConnections::class)->context($staff));
        $mind->update(['is_disabled' => true]);
        $this->assertStringNotContainsString('Private policy wording', app(AiStaffConnections::class)->context($staff));
    }

    public function test_read_only_cannot_draft_followup_even_through_runtime(): void
    {
        $u = User::factory()->create();
        $staff = AiStaff::create(['user_id' => $u->id, 'name' => 'Read', 'domain' => 'contacts', 'config' => ['connections' => ['mode' => 'read']]]);
        $this->expectException(HttpException::class);
        app(AiStaffRuntime::class)->draftFollowup($u, $staff, new Contact);
    }

    public function test_history_is_scoped_and_foreign_sessions_are_hidden(): void
    {
        $u = User::factory()->create();
        $v = User::factory()->create();
        $session = SiteAssistantConversation::create(['visitor_token' => bin2hex(random_bytes(32)), 'bound_user_id' => $v->id, 'last_page_title' => 'Private session']);
        $this->actingAs($u)->get('/user/ai/zio-conversations')->assertOk()->assertDontSee('Private session');
        $this->get('/user/ai/zio-conversations/'.$session->id)->assertNotFound();
    }

    public function test_reports_only_include_owned_staff(): void
    {
        $u = User::factory()->create();
        $v = User::factory()->create();
        AiStaff::create(['user_id' => $u->id, 'name' => 'Mine', 'domain' => 'sales']);
        AiStaff::create(['user_id' => $v->id, 'name' => 'Other', 'domain' => 'billing']);
        $report = app(AiStaffReports::class)->data($u->id, now()->subDay()->toDateTimeString(), now()->toDateTimeString());
        $this->assertSame(1, $report['staffCount']);
        $this->actingAs($u)->get('/user/ai/staff/reports')->assertOk()->assertSee('AI Staff reports');
        $this->get('/user/ai/staff')->assertOk()->assertSee('Hire');
    }
}
