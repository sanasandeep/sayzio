<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\AttendanceOperations;
use App\Modules\User\Services\AttendanceService;
use App\Modules\User\Services\PeoplePolicy;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindFeatureAdapter;
use App\Services\AI\AiStaffRuntime;
use App\Services\AI\PeopleAiContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeoplePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function team(): array
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $staff = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $staff->id, 'role' => 'viewer']);
        $this->login($owner, $ws);
        DB::table('attendance_policies')->insert(['workspace_id' => $ws->id, 'settings' => json_encode(['require_photo' => false, 'ai_people_enabled' => true]), 'created_at' => now(), 'updated_at' => now()]);

        return [$owner, $ws, $staff];
    }

    private function login($user, $ws): void
    {
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app(WorkspaceContext::class)->clear();
        session([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
    }

    private function type($ws, array $extra = []): object
    {
        $id = DB::table('attendance_leave_types')->insertGetId(array_replace(['workspace_id' => $ws->id, 'name' => 'Earned policy', 'annual_days' => 24, 'paid' => true, 'accrual_mode' => 'interval', 'accrual_days' => 1, 'accrual_every_days' => 15, 'accrual_starts_on' => '2026-10-01', 'opening_days' => 0, 'created_at' => now(), 'updated_at' => now()], $extra));

        return DB::table('attendance_leave_types')->find($id);
    }

    public function test_accrual_credits_completed_intervals_resets_and_caps_without_repeated_crediting(): void
    {
        [$owner,$ws] = $this->team();
        $person = app(AttendanceService::class)->participant($ws, $owner->id);
        $person->update(['employment_started_on' => '2026-10-01']);
        $type = $this->type($ws);
        $policy = app(PeoplePolicy::class);
        $this->travelTo(now()->setDate(2026, 10, 15)->startOfDay());
        $this->assertSame(0.0, $policy->entitlement($type, $person, 2026));
        $this->travelTo(now()->setDate(2026, 10, 16));
        $this->assertSame(1.0, $policy->entitlement($type, $person, 2026));
        $this->travelTo(now()->setDate(2026, 10, 31));
        $this->assertSame(2.0, $policy->entitlement($type, $person, 2026));
        $this->assertSame(2.0, $policy->entitlement($type, $person, 2026));
        $this->assertSame(0.0, $policy->entitlement($type, $person, 2027));
        $type->annual_days = 1;
        $this->assertSame(1.0, $policy->entitlement($type, $person, 2026));
        $type->annual_days = 24;
        $person->update(['employment_started_on' => '2026-10-20']);
        $this->assertSame(0.0, $policy->entitlement($type, $person, 2026));
    }

    public function test_earned_balance_is_used_for_leave_and_encashment_and_custom_workdays(): void
    {
        [$owner,$ws] = $this->team();
        $this->travelTo(now()->setDate(2026, 10, 31));
        $p = app(AttendanceService::class)->participant($ws, $owner->id);
        $p->update(['employment_started_on' => '2026-10-01']);
        $type = $this->type($ws, ['encashment_enabled' => true, 'max_encash_days' => 10]);
        $this->assertEquals(2, app(AttendanceOperations::class)->leaveBalances($ws->id, $p->id, 2026)[0]['allowance']);
        $this->post('/user/attendance/requests', ['type' => 'leave', 'leave_type_id' => $type->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-04', 'reason' => 'Three working days'])->assertStatus(422);
        $this->post('/user/attendance/requests', ['type' => 'encashment', 'leave_type_id' => $type->id, 'requested_days' => 3, 'reason' => 'Encash earned balance'])->assertStatus(422);
        $this->post('/user/attendance/requests', ['type' => 'leave', 'leave_type_id' => $type->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-03', 'reason' => 'Two working days'])->assertRedirect();
        $this->assertEquals(0, app(AttendanceOperations::class)->leaveBalances($ws->id, $p->id, 2026)[0]['remaining']);
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['working_days' => [0, 1, 2, 3, 4]])]);
        $this->assertSame(1, app(AttendanceOperations::class)->leaveDays($ws->id, '2026-11-01', '2026-11-01'));
        $this->assertSame(0, app(AttendanceOperations::class)->leaveDays($ws->id, '2026-11-06', '2026-11-06'));
    }

    public function test_fixed_hours_enforce_window_respect_off_rosters_and_weekly_targets(): void
    {
        [$owner,$ws] = $this->team();
        $p = app(AttendanceService::class)->participant($ws, $owner->id);
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(8, 0));
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['require_photo' => false, 'schedule_mode' => 'fixed', 'fixed_start' => '09:00', 'fixed_end' => '17:00', 'early_checkin_minutes' => 15])]);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertStatus(422);
        $this->travelTo(now()->setTime(9, 0));
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertOk();
        $this->assertDatabaseHas('attendance_rosters', ['participant_id' => $p->id, 'shift_name' => 'Fixed working hours']);
        DB::table('attendance_rosters')->where('participant_id', $p->id)->update(['kind' => 'off']);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertStatus(422);
        DB::table('attendance_rosters')->where('participant_id', $p->id)->delete();
        DB::table('attendance_holidays')->insert(['workspace_id' => $ws->id, 'name' => 'Fixed holiday', 'date' => '2026-10-26', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertStatus(422);
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['schedule_mode' => 'weekly', 'weekly_minutes' => 1800])]);
        $this->assertSame(1800, app(PeoplePolicy::class)->target($p, $ws)['target_minutes']);
    }

    public function test_owner_configures_policy_and_templates_and_native_team_is_used(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->put('/user/attendance/settings', ['timezone' => 'Asia/Kolkata', 'require_photo' => 0, 'require_location' => 0, 'retention_days' => 90, 'schedule_mode' => 'weekly', 'weekly_hours' => 35, 'daily_hours' => 7, 'working_days' => [1, 2, 3, 4, 5], 'fixed_start' => '09:30', 'fixed_end' => '17:30', 'ai_people_enabled' => 1, 'note' => 'Workspace owner policy'])->assertRedirect();
        $this->assertSame(2100, (int) app(AttendanceService::class)->policy($ws)['weekly_minutes']);
        $this->get('/user/attendance/team')->assertRedirect(route('user.team.people'));
        foreach (['/user/attendance/leave?tab=types', '/user/team/people?tab=policy', '/user/team/people?tab=ai', '/user/team/people'] as $i => $url) {
            $r = $this->get($url)->assertOk()->assertDontSee('att-area-nav"', false);
            if (getenv('ATTENDANCE_UI_FIXTURE')) {
                file_put_contents('/tmp/sayzio-people-ui-'.$i.'.html', $r->getContent());
            }
        }
        $this->get('/user/team')->assertOk()->assertSee('Employment settings')->assertSee('AI assistance');
        $template = PeoplePolicy::LEAVE_TEMPLATES['earned'];
        $this->post('/user/attendance/setup', $template + ['kind' => 'leave_type', 'note' => 'Install earned template'])->assertRedirect();
        $this->assertDatabaseHas('attendance_leave_types', ['workspace_id' => $ws->id, 'accrual_every_days' => 15, 'name' => 'Earned leave']);
        $this->login($staff, $ws);
        $this->put('/user/attendance/settings', ['note' => 'Denied configuration'])->assertForbidden();
    }

    public function test_ai_context_is_opt_in_actor_scoped_and_excludes_evidence_and_reasons(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $p = app(AttendanceService::class)->participant($ws, $staff->id);
        AttendanceRequest::create(['participant_id' => $p->id, 'type' => 'leave', 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26', 'reason' => 'SECRET medical reason', 'status' => 'pending']);
        $this->get('/user/attendance')->assertOk();
        $ctx = app(PeopleAiContext::class)->snapshot($owner);
        $this->assertStringContainsString('team aggregates', $ctx);
        $this->assertStringNotContainsString('SECRET', $ctx);
        $this->login($staff, $ws);
        $ctx = app(PeopleAiContext::class)->snapshot($staff);
        $this->assertStringContainsString('self only', $ctx);
        $this->assertSame('', app(AiMindFeatureAdapter::class)->snapshot($owner, 'people'));
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['ai_people_enabled' => false])]);
        $this->assertSame('', app(PeopleAiContext::class)->snapshot($staff));
    }

    public function test_ai_drafts_make_no_changes_until_human_apply_and_are_idempotent(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $type = $this->type($ws, ['accrual_mode' => 'annual']);
        AiEngineSettings::setEnabled(true);
        $plan = Plan::create(['name' => 'People AI test', 'slug' => 'people-ai-test', 'monthly_price' => 10, 'status' => 'active', 'features' => ['ai_staff_general' => true]]);
        $owner->update(['plan_id' => $plan->id]);
        $owner->unsetRelation('plan');
        $ai = AiStaff::create(['user_id' => $owner->id, 'name' => 'People assistant', 'domain' => 'general', 'config' => ['connections' => ['mode' => 'draft']]]);
        $runtime = \Mockery::mock(AiStaffRuntime::class);
        $runtime->shouldReceive('draftPeople')->once()->andReturn(['leave_type_id' => $type->id, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26', 'reason' => 'Personal leave draft']);
        $this->app->instance(AiStaffRuntime::class, $runtime);
        $this->post('/user/attendance/ai-draft', ['ai_staff_id' => $ai->id, 'draft_kind' => 'leave', 'prompt' => 'Prepare annual leave for Monday'])->assertRedirect();
        $this->assertDatabaseCount('attendance_requests', 0);
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-people-ai-draft.html', $this->get('/user/team/people?tab=ai')->assertOk()->getContent());
        }
        $id = DB::table('attendance_ai_drafts')->value('id');
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['ai_people_enabled' => false])]);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertForbidden();
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['ai_people_enabled' => true, 'require_photo' => false])]);
        $ai->update(['config' => ['connections' => ['mode' => 'read']]]);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertForbidden();
        $ai->update(['config' => ['connections' => ['mode' => 'draft']]]);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertRedirect();
        $this->assertDatabaseHas('attendance_requests', ['type' => 'leave', 'status' => 'pending']);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertStatus(409);
        $this->assertDatabaseCount('attendance_requests', 1);
        $this->login($staff, $ws);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertNotFound();
        $this->post('/user/attendance/ai-draft', ['ai_staff_id' => $ai->id, 'draft_kind' => 'roster', 'prompt' => 'Prepare a roster for next week'])->assertForbidden();
    }

    public function test_ai_roster_apply_rechecks_scope_and_permission(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $ai = AiStaff::create(['user_id' => $owner->id, 'name' => 'Roster assistant', 'domain' => 'general', 'config' => ['connections' => ['mode' => 'draft']]]);
        $foreign = User::factory()->create();
        $id = DB::table('attendance_ai_drafts')->insertGetId(['workspace_id' => $ws->id, 'actor_user_id' => $owner->id, 'ai_staff_id' => $ai->id, 'kind' => 'roster', 'status' => 'pending', 'payload' => json_encode(['staff_ids' => [$foreign->id], 'kind' => 'off', 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26', 'note' => 'Draft off-day assignment']), 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertNotFound();
        $this->assertDatabaseHas('attendance_ai_drafts', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseCount('attendance_rosters', 0);
        DB::table('attendance_ai_drafts')->where('id', $id)->update(['payload' => json_encode(['staff_ids' => [$staff->id], 'kind' => 'off', 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-26', 'note' => 'Draft off-day assignment'])]);
        $this->post('/user/attendance/ai-draft/'.$id, ['decision' => 'apply'])->assertRedirect();
        $this->assertDatabaseHas('attendance_rosters', ['workspace_id' => $ws->id, 'kind' => 'off']);
    }
}
