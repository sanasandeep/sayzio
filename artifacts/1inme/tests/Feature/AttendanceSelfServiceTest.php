<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\AttendanceOperations;
use App\Modules\User\Services\AttendanceService;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceSelfServiceTest extends TestCase
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
        DB::table('attendance_policies')->insert(['workspace_id' => $ws->id, 'settings' => json_encode(['require_photo' => false]), 'created_at' => now(), 'updated_at' => now()]);

        return [$owner, $ws, $staff];
    }

    private function login($user, $ws): void
    {
        $this->actingAs($user)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app(WorkspaceContext::class)->clear();
        session([WorkspaceContext::SESSION_KEY => $ws->id]);
    }

    private function shift($ws, string $start = '22:00', string $end = '06:00', string $name = 'Night shift'): int
    {
        $this->post('/user/attendance/shifts', ['name' => $name, 'starts_at' => $start, 'ends_at' => $end, 'break_minutes' => 30, 'late_grace_minutes' => 10, 'early_grace_minutes' => 5, 'active' => 1, 'note' => 'Configure shift template'])->assertRedirect();

        return DB::table('attendance_shifts')->where('workspace_id', $ws->id)->where('name', $name)->value('id');
    }

    private function roster($staff, int $shift, string $date = '2026-10-24'): int
    {
        $this->post('/user/attendance/rosters', ['staff_ids' => [$staff->id], 'kind' => 'work', 'shift_id' => $shift, 'starts_on' => $date, 'ends_on' => $date, 'note' => 'Assign staff shift'])->assertRedirect();

        return DB::table('attendance_rosters')->where('shift_id', $shift)->value('id');
    }

    private function clock(string $action): int
    {
        $id = $this->postJson('/user/attendance/challenge', ['action' => $action])->assertOk()->json('id');

        return $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertOk()->json('id');
    }

    private function leaveType($ws, int $annual = 10, int $max = 5): int
    {
        $this->post('/user/attendance/setup', ['kind' => 'leave_type', 'name' => 'Annual leave', 'annual_days' => $annual, 'paid' => 1, 'encashment_enabled' => 1, 'max_encash_days' => $max, 'note' => 'Enable leave encashments'])->assertRedirect();

        return DB::table('attendance_leave_types')->where('workspace_id', $ws->id)->value('id');
    }

    public function test_shift_rosters_support_overnights_enforce_windows_and_auto_close_at_shift_end(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-24T20:00:00Z'));
        $this->login($owner, $ws);
        $shift = $this->shift($ws);
        $roster = $this->roster($staff, $shift);
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['require_photo' => false, 'enforce_roster' => true, 'early_checkin_minutes' => 60, 'auto_out_at_shift_end' => true])]);
        $this->login($staff, $ws);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertUnprocessable();
        $this->travelTo(CarbonImmutable::parse('2026-10-24T22:15:00Z'));
        $in = $this->clock('in');
        $this->assertDatabaseHas('attendance_punches', ['id' => $in, 'roster_id' => $roster]);
        $person = AttendanceParticipant::where('workspace_id', $ws->id)->where('user_id', $staff->id)->first();
        $this->assertSame('2026-10-25 06:00', $person->auto_punch_out_at->format('Y-m-d H:i'));
        $this->travelTo(CarbonImmutable::parse('2026-10-25T06:02:00Z'));
        $this->artisan('attendance:auto-punch-out')->assertSuccessful();
        $out = AttendancePunch::where('source', 'auto_punch_out')->first();
        $this->assertSame($roster, $out->roster_id);
        $this->assertNull($person->fresh()->active_roster_id);
        $page = $this->get('/user/attendance?tab=self-service')->assertOk()->assertSee('Employee Self-Service')->assertSee('Night shift')->assertSee('5m late');
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-self-service.html', $page->getContent());
        }$this->travelBack();
    }

    public function test_roster_overlap_scope_and_recorded_history_are_protected(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-24T22:00:00Z'));
        $this->login($owner, $ws);
        $night = $this->shift($ws);
        $row = $this->roster($staff, $night);
        $early = $this->shift($ws, '05:00', '13:00', 'Early shift');
        $this->post('/user/attendance/rosters', ['staff_ids' => [$staff->id], 'kind' => 'work', 'shift_id' => $early, 'starts_on' => '2026-10-25', 'ends_on' => '2026-10-25', 'note' => 'Try overlapping shift'])->assertStatus(422);
        $this->login($staff, $ws);
        $this->clock('in');
        $this->login($owner, $ws);
        $this->post('/user/attendance/rosters/'.$row.'/cancel', ['note' => 'Try changing recorded roster'])->assertStatus(409);
        $this->post('/user/attendance/shifts', ['id' => $night, 'name' => 'Revised night', 'starts_at' => '23:00', 'ends_at' => '07:00', 'break_minutes' => 30, 'late_grace_minutes' => 0, 'early_grace_minutes' => 0, 'active' => 1, 'note' => 'Future template revision'])->assertRedirect();
        $this->assertSame('2026-10-24 22:00:00', DB::table('attendance_rosters')->where('id', $row)->value('starts_at'));
        $outside = User::factory()->create();
        $this->login($outside, $outside->ownedWorkspaces()->first());
        $this->post('/user/attendance/rosters/'.$row.'/cancel', ['note' => 'Wrong workspace'])->assertNotFound();
        $this->travelBack();
    }

    public function test_encashments_and_leave_share_reservations_and_withdrawal_releases_days(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-24T09:00:00Z'));
        $this->login($owner, $ws);
        $type = $this->leaveType($ws, 6, 5);
        $this->login($staff, $ws);
        $this->post('/user/attendance/requests', ['type' => 'encashment', 'leave_type_id' => $type, 'requested_days' => 4, 'reason' => 'Encash available annual days'])->assertRedirect();
        $encash = AttendanceRequest::first();
        $this->post('/user/attendance/requests', ['type' => 'leave', 'leave_type_id' => $type, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-28', 'reason' => 'Three working days leave'])->assertStatus(422);
        $this->post('/user/attendance/requests/'.$encash->id.'/withdraw', ['note' => 'Keep these days as leave'])->assertRedirect();
        $this->post('/user/attendance/requests', ['type' => 'leave', 'leave_type_id' => $type, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-28', 'reason' => 'Three working days leave'])->assertRedirect();
        $person = AttendanceParticipant::where('workspace_id', $ws->id)->where('user_id', $staff->id)->first();
        $balance = app(AttendanceOperations::class)->leaveBalances($ws->id, $person->id, 2026)[0];
        $this->assertEquals(3, $balance['remaining']);
        $this->assertSame('withdrawn', $encash->fresh()->status);
        $this->travelBack();
    }

    public function test_encashment_approval_and_settlement_are_separate_idempotent_actions(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($owner, $ws);
        $type = $this->leaveType($ws);
        $this->login($staff, $ws);
        $this->post('/user/attendance/requests', ['type' => 'encashment', 'leave_type_id' => $type, 'requested_days' => 2.5, 'reason' => 'Encash remaining allowance'])->assertRedirect();
        $row = AttendanceRequest::first();
        $this->post('/user/attendance/requests/'.$row->id.'/settle', ['amount' => '500.25', 'currency' => 'INR', 'reference' => 'PAY-TEST', 'note' => 'External settlement'])->assertForbidden();
        $this->login($owner, $ws);
        $this->post('/user/attendance/requests/'.$row->id.'/review', ['decision' => 'approved', 'note' => 'Approve balance reservation'])->assertRedirect();
        $this->assertNull($row->fresh()->settled_at);
        $data = ['amount' => '500.25', 'currency' => 'INR', 'reference' => 'PAY-TEST', 'note' => 'External payment already completed'];
        $this->post('/user/attendance/requests/'.$row->id.'/settle', $data)->assertRedirect();
        $this->assertEquals(50025, $row->fresh()->settlement_amount_minor);
        $this->post('/user/attendance/requests/'.$row->id.'/settle', $data)->assertStatus(409);
        $this->post('/user/attendance/requests/'.$row->id.'/withdraw', ['note' => 'Cannot withdraw paid request'])->assertNotFound();
        $this->login($staff, $ws);
        $this->get('/user/attendance/leave?tab=encashments')->assertOk()->assertSee('PAY-TEST')->assertSee('500.25');
    }

    public function test_training_and_general_requests_are_personal_and_reviewable(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $today = now()->toDateString();
        $this->post('/user/attendance/requests', ['type' => 'training', 'starts_on' => $today, 'ends_on' => $today, 'title' => 'Private staff training', 'provider' => 'Learning provider', 'training_url' => 'https://example.com/course', 'reason' => 'Develop role-specific skills'])->assertRedirect();
        $row = AttendanceRequest::first();
        $this->assertSame('Private staff training', $row->metadata['title']);
        $other = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $other->id, 'role' => 'viewer']);
        $this->login($other, $ws);
        $this->get('/user/attendance?tab=requests')->assertOk()->assertDontSee('Private staff training');
        $this->post('/user/attendance/requests/'.$row->id.'/withdraw', ['note' => 'Cannot withdraw another request'])->assertNotFound();
        $this->login($owner, $ws);
        $this->post('/user/attendance/requests/'.$row->id.'/review', ['decision' => 'approved', 'note' => 'Training approved by manager'])->assertRedirect();
        $this->assertSame('approved', $row->fresh()->status);
        $this->login($staff, $ws);
        $this->post('/user/attendance/requests', ['type' => 'general', 'category' => 'document', 'title' => 'Employment letter', 'starts_on' => $today, 'ends_on' => $today, 'reason' => 'Please provide a work confirmation letter'])->assertRedirect();
        $this->assertDatabaseHas('attendance_requests', ['type' => 'general', 'status' => 'pending']);
    }

    public function test_manage_only_role_can_plan_rosters_without_receiving_review_access(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['viewer']['attendance.manage'] = true;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->login($staff, $ws);
        $this->get('/user/attendance/team?tab=shifts')->assertOk()->assertSee('Assign rosters in bulk');
        $this->shift($ws);
        $this->assertFalse($staff->canInWorkspace($ws, 'attendance.review'));
    }

    public function test_roster_policy_settings_are_persisted(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($owner, $ws);
        $this->put('/user/attendance/settings', ['timezone' => 'Asia/Kolkata', 'require_photo' => 0, 'require_location' => 0, 'retention_days' => 90, 'enforce_roster' => 1, 'early_checkin_minutes' => 30, 'auto_out_at_shift_end' => 1, 'note' => 'Enable roster attendance policy'])->assertRedirect();
        $policy = app(AttendanceService::class)->policy($ws);
        $this->assertTrue($policy['enforce_roster']);
        $this->assertTrue($policy['auto_out_at_shift_end']);
        $this->assertSame(30, $policy['early_checkin_minutes']);
    }

    public function test_internal_staff_receive_the_same_shift_and_self_service_workflows(): void
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super', 'guard' => 'admin']);
        $admin = Admin::create(['name' => 'Staff planner', 'email' => 'planner@example.test', 'password' => 'long-test-password', 'role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/attendance/shifts', ['name' => 'Office shift', 'starts_at' => '09:00', 'ends_at' => '17:00', 'break_minutes' => 30, 'late_grace_minutes' => 5, 'early_grace_minutes' => 5, 'active' => 1, 'note' => 'Internal team shift'])->assertRedirect();
        $this->assertDatabaseHas('attendance_shifts', ['workspace_id' => 0, 'name' => 'Office shift']);
        $page = $this->get('/admin/attendance?tab=self-service')->assertOk()->assertSee('Employee Self-Service');
        $this->get('/admin/attendance/team?tab=shifts')->assertOk()->assertSee('Office shift');
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-admin-self-service.html', $page->getContent());
        }
    }

    public function test_management_pages_render_only_selected_section_and_keep_role_editors_separate(): void
    {
        [$owner, $ws, $staff] = $this->team();
        $this->login($owner, $ws);
        $pages = [
            'overview' => ['/user/attendance', 'Your next action', 'Configure leave types'],
            'history' => ['/user/attendance?tab=history', 'Team attendance', 'Your next action'],
            'leave' => ['/user/attendance/leave', 'New leave request', 'Your next action'],
            'encashments' => ['/user/attendance/leave?tab=encashments', 'Encashment requests', 'Your next action'],
            'balances' => ['/user/attendance/leave?tab=balances', 'annual balances', 'Team attendance'],
            'types' => ['/user/attendance/leave?tab=types', 'Configure leave types', 'Your next action'],
            'people' => ['/user/attendance/team', 'Team members', 'Your next action'],
            'shifts' => ['/user/attendance/team?tab=shifts', 'Create shift template', 'Your next action'],
            'access' => ['/user/attendance/team?tab=access', 'Workspace team roles', 'Internal staff roles'],
            'requests' => ['/user/attendance?tab=requests', 'New employee request', 'Your next action'],
            'qr' => ['/user/attendance?tab=qr', 'QR', 'Your next action'],
            'policy' => ['/user/attendance?tab=policy', 'Save policy', 'Your next action'],
            'reports' => ['/user/attendance?tab=reports', 'Download report', 'Your next action'],
            'calendar' => ['/user/attendance/team?tab=calendar', 'holidays', 'Your next action'],
            'notices' => ['/user/attendance/team?tab=notices', 'Team notices', 'Your next action'],
            'audit' => ['/user/attendance?tab=audit', 'Review &amp; policy history', 'Your next action'],
        ];
        foreach ($pages as $name => [$url, $present, $absent]) {
            $page = $this->get($url)->assertOk()->assertSee($present, false)->assertDontSee($absent, false);
            if (getenv('ATTENDANCE_UI_FIXTURE')) {
                file_put_contents('/tmp/sayzio-attendance-ui-'.$name.'.html', $page->getContent());
            }
        }
        $person = app(AttendanceService::class)->participant($ws, $owner->id);
        foreach (['leave' => 'Leave only reason', 'training' => 'Training only reason'] as $type => $reason) {
            AttendanceRequest::create(['participant_id' => $person->id, 'type' => $type, 'starts_on' => '2026-10-24', 'ends_on' => '2026-10-24', 'reason' => $reason, 'status' => 'pending']);
        }
        $this->get('/user/attendance/leave')->assertSee('Leave only reason')->assertDontSee('Training only reason');
        $this->get('/user/attendance?tab=requests')->assertSee('Training only reason')->assertDontSee('Leave only reason');
        $this->get('/user/attendance/team?tab=access')->assertSee(route('user.team.roles.index'), false)->assertDontSee(route('admin.roles.index'), false);
        $this->get('/user/attendance?tab=unknown')->assertNotFound();
        $this->login($staff, $ws);
        $this->get('/user/attendance?tab=policy')->assertNotFound();
        $this->get('/user/attendance/team?tab=people')->assertNotFound();
        $this->get('/user/attendance/leave?tab=types')->assertNotFound();
        $this->get('/user/attendance/team?tab=access')->assertOk()->assertDontSee('Manage team roles');
        auth()->logout();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Staff manager', 'guard' => 'admin']);
        $admin = Admin::create(['name' => 'Internal manager', 'email' => 'ui-staff@example.test', 'password' => bcrypt('password'), 'role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        $page = $this->get('/admin/attendance/team?tab=access')->assertOk()->assertSee('Internal staff roles')->assertSee(route('admin.roles.index'), false)->assertDontSee(route('user.team.roles.index'), false);
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-ui-admin-access.html', $page->getContent());
        }
    }
}
