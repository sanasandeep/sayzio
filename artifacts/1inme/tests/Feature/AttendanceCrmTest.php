<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminActionAudit;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\AttendanceService;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\NavRailSections;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceCrmTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function team(): array
    {
        // Existing schedule fixtures use UTC clock values.
        $owner = User::factory()->create(['timezone' => 'UTC']);
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

    private function clock(string $action, array $extra = []): int
    {
        $id = $this->postJson('/user/attendance/challenge', ['action' => $action] + $extra)->assertOk()->json('id');

        return $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertOk()->json('id');
    }

    public function test_navigation_places_attendance_in_work_and_team(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($owner, $ws);
        $page = $this->get('/user/attendance')->assertOk();
        $page->assertSee('Work &amp; Team', false)->assertSee('Attendance records')->assertSee('QR clock-in');
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-manager.html', $page->getContent());
        }
        $this->assertSame('work', NavRailSections::activeKey());
        $home = file_get_contents(resource_path('views/user/partials/nav/_home.blade.php'));
        $this->assertStringNotContainsString("route('user.attendance.index')", $home);
    }

    public function test_employee_bulk_designation_updates_are_scoped_and_ip_rules_are_enforced(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($owner, $ws);
        $this->post('/user/attendance/setup', ['kind' => 'designation', 'name' => 'Sales', 'note' => 'Create sales team'])->assertRedirect();
        $designation = DB::table('attendance_designations')->first()->id;
        $payload = ['staff_ids' => [$owner->id, $staff->id], 'fields' => ['designation_id', 'allowed_ips'], 'designation_id' => $designation, 'allowed_ips' => '192.0.2.0/24', 'note' => 'Assign sales office'];
        $this->post('/user/attendance/employees', $payload)->assertRedirect();
        $this->assertSame(2, AttendanceParticipant::where('workspace_id', $ws->id)->where('designation_id', $designation)->count());
        $this->login($staff, $ws);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertForbidden();
        $this->login($owner, $ws);
        $payload['allowed_ips'] = '127.0.0.1';
        $this->post('/user/attendance/employees', $payload)->assertRedirect();
        $this->login($staff, $ws);
        $this->clock('in');
        $this->assertDatabaseHas('attendance_punches', ['ip_address' => '127.0.0.1']);
        $outside = User::factory()->create();
        $this->login($owner, $ws);
        $payload['staff_ids'] = [$outside->id];
        $this->post('/user/attendance/employees', $payload)->assertNotFound();
    }

    public function test_disable_employee_closes_session_and_preserves_history(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $this->clock('in');
        $this->login($owner, $ws);
        $this->post('/user/attendance/employees', ['staff_ids' => [$staff->id], 'fields' => ['attendance_enabled'], 'attendance_enabled' => 0, 'note' => 'Disable attendance seat'])->assertRedirect();
        $this->assertDatabaseHas('attendance_punches', ['source' => 'system_disabled', 'action' => 'out']);
        $this->login($staff, $ws);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertForbidden();
        $this->assertSame(2, AttendancePunch::count());
    }

    public function test_holiday_bulk_creation_and_leave_balances_are_applied(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($owner, $ws);
        $this->post('/user/attendance/setup', ['kind' => 'holiday', 'name' => 'Office holidays', 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-30', 'weekdays' => [1, 3, 5], 'note' => 'Holiday week setup'])->assertRedirect();
        $this->assertSame(3, DB::table('attendance_holidays')->count());
        $this->post('/user/attendance/setup', ['kind' => 'leave_type', 'name' => 'Annual leave', 'annual_days' => 3, 'paid' => 1, 'note' => 'Annual allowance'])->assertRedirect();
        $type = DB::table('attendance_leave_types')->first()->id;
        $this->login($staff, $ws);
        $data = ['type' => 'leave', 'leave_type_id' => $type, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-30', 'reason' => 'Family leave'];
        $this->post('/user/attendance/requests', $data)->assertRedirect();
        $request = AttendanceRequest::first();
        $this->assertEquals(2, $request->requested_days);
        $this->post('/user/attendance/requests', array_replace($data, ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-03']))->assertStatus(422);
        $this->login($owner, $ws);
        $this->post('/user/attendance/requests/'.$request->id.'/review', ['decision' => 'approved', 'note' => 'Approved family leave'])->assertRedirect();
        $this->post('/user/attendance/setup', ['kind' => 'leave_type', 'id' => $type, 'delete' => 1, 'note' => 'Try removing used type'])->assertStatus(409);
    }

    public function test_only_managers_can_publish_notices_and_dates_control_visibility(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $data = ['kind' => 'notice', 'title' => 'Office update', 'body' => 'Team meeting at ten', 'starts_on' => now()->toDateString(), 'note' => 'Publish team notice'];
        $this->login($staff, $ws);
        $this->post('/user/attendance/setup', $data)->assertForbidden();
        $this->login($owner, $ws);
        $this->post('/user/attendance/setup', $data)->assertRedirect();
        $this->post('/user/attendance/setup', array_replace($data, ['title' => 'Future announcement', 'starts_on' => now()->addMonth()->toDateString()]))->assertRedirect();
        $this->login($staff, $ws);
        $this->get('/user/team/people?tab=notices')->assertOk()->assertSee('Team meeting at ten')->assertDontSee('Future announcement');
    }

    public function test_bulk_review_is_atomic_and_cannot_approve_self(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $staffPerson = app(AttendanceService::class)->participant($ws, $staff->id);
        $self = app(AttendanceService::class)->participant($ws, $owner->id);
        $a = AttendancePunch::create(['participant_id' => $staffPerson->id, 'action' => 'in', 'occurred_at' => now()]);
        $b = AttendancePunch::create(['participant_id' => $staffPerson->id, 'action' => 'out', 'occurred_at' => now()]);
        $mine = AttendancePunch::create(['participant_id' => $self->id, 'action' => 'in', 'occurred_at' => now()]);
        $this->login($owner, $ws);
        $this->post('/user/attendance/bulk-review', ['kind' => 'punch', 'ids' => [$a->id, $mine->id], 'decision' => 'approved', 'note' => 'Bulk evidence review'])->assertForbidden();
        $this->assertSame('pending', $a->fresh()->review_status);
        $this->post('/user/attendance/bulk-review', ['kind' => 'punch', 'ids' => [$a->id, $b->id], 'decision' => 'approved', 'note' => 'Bulk evidence review'])->assertRedirect();
        $this->assertSame('approved', $a->fresh()->review_status);
        $this->assertSame('approved', $b->fresh()->review_status);
    }

    public function test_auto_punch_out_is_idempotent_and_never_invents_evidence(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $person = app(AttendanceService::class)->participant($ws, $staff->id);
        $person->update(['auto_out_time' => '17:00']);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T09:00:00Z'));
        $this->login($staff, $ws);
        $this->clock('in');
        $this->assertSame('17:00', $person->fresh()->auto_punch_out_at->format('H:i'));
        $this->travelTo(CarbonImmutable::parse('2026-10-24T17:02:00Z'));
        $this->artisan('attendance:auto-punch-out')->assertSuccessful();
        $this->artisan('attendance:auto-punch-out')->assertSuccessful();
        $out = AttendancePunch::where('source', 'auto_punch_out')->first();
        $this->assertSame('17:00', $out->occurred_at->format('H:i'));
        $this->assertNull($out->photo_path);
        $this->assertNull($out->latitude);
        $this->assertSame(2, AttendancePunch::count());
        $this->assertSame('out', $person->fresh()->state);
        $this->travelBack();
    }

    public function test_qr_clock_in_expires_is_scoped_and_does_not_bypass_location(): void
    {
        [$owner,$ws,$staff] = $this->team();
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['require_photo' => false, 'require_location' => true])]);
        $this->login($owner, $ws);
        $qr = $this->postJson('/user/attendance/qr-session')->assertOk()->json();
        $token = basename($qr['url']);
        $this->assertStringContainsString('<svg', $qr['svg']);
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-workplace-qr.json', json_encode($qr));
        }
        $this->login($staff, $ws);
        $this->get('/user/attendance/scan/'.$token)->assertOk();
        $challenge = $this->postJson('/user/attendance/challenge', ['action' => 'in', 'qr_token' => $token])->assertOk()->json('id');
        $this->postJson('/user/attendance/punch', ['challenge_id' => $challenge])->assertUnprocessable();
        $this->postJson('/user/attendance/punch', ['challenge_id' => $challenge, 'latitude' => 12, 'longitude' => 77, 'accuracy' => 10])->assertOk();
        $this->assertDatabaseHas('attendance_punches', ['source' => 'qr']);
        $this->get('/user/attendance/scan/'.$token)->assertStatus(409);
        $this->login($owner, $ws);
        $token = basename($this->postJson('/user/attendance/qr-session')->assertOk()->json('url'));
        DB::table('attendance_qr_sessions')->where('id', $token)->update(['expires_at' => now()->subSecond()]);
        $this->login($staff, $ws);
        $this->get('/user/attendance/scan/'.$token)->assertStatus(422);
        $outside = User::factory()->create();
        $this->login($outside, $outside->ownedWorkspaces()->first());
        $this->get('/user/attendance/scan/'.$token)->assertNotFound();
    }

    public function test_individual_and_summary_exports_are_real_pdf_and_excel_files(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $person = app(AttendanceService::class)->participant($ws, $staff->id);
        $staff->update(['name' => '=Formula-shaped name']);
        AttendancePunch::create(['participant_id' => $person->id, 'action' => 'in', 'occurred_at' => '2026-10-24 09:00:00']);
        AttendancePunch::create(['participant_id' => $person->id, 'action' => 'out', 'occurred_at' => '2026-10-24 11:00:00']);
        $this->login($owner, $ws);
        $base = '/user/attendance/export?from=2026-10-24&to=2026-10-24&mode=summary&staff='.$staff->id;
        $csv = $this->get($base.'&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('UTC,2,0,0', $csv);
        $xlsx = $this->get($base.'&format=xlsx')->assertOk()->streamedContent();
        $file = tempnam(sys_get_temp_dir(), 'attendance-test-');
        file_put_contents($file, $xlsx);
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('=Formula-shaped name', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        $zip->close();
        unlink($file);
        $pdf = $this->get($base.'&format=pdf')->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-report.pdf', $pdf);
            file_put_contents('/tmp/sayzio-attendance-report.xlsx', $xlsx);
        }
    }

    public function test_impersonated_sessions_cannot_record_attendance(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $this->withSession(['impersonate_user_id' => $staff->id])->postJson('/user/attendance/challenge', ['action' => 'in'])->assertForbidden();
    }

    public function test_internal_staff_can_use_operations_without_accessing_workspaces(): void
    {
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super', 'guard' => 'admin']);
        $admin = Admin::create(['name' => 'HR operator', 'email' => 'hr@example.test', 'password' => 'long-test-password', 'role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        $this->post('/admin/attendance/setup', ['kind' => 'designation', 'name' => 'Operations', 'note' => 'Internal staff setup'])->assertRedirect();
        $this->assertDatabaseHas('attendance_designations', ['workspace_id' => 0, 'name' => 'Operations']);
        $this->get('/admin/attendance/team?tab=calendar')->assertOk()->assertSee('Operations')->assertSee('Billing &amp; Accounts', false);
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-admin-manager.html', $this->get('/admin/attendance')->getContent());
        }
    }

    public function test_bulk_time_modification_is_audited_and_cannot_reorder_events(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $person = app(AttendanceService::class)->participant($ws, $staff->id);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T12:00:00Z'));
        $in = AttendancePunch::create(['participant_id' => $person->id, 'action' => 'in', 'occurred_at' => '2026-10-24 09:00:00']);
        $out = AttendancePunch::create(['participant_id' => $person->id, 'action' => 'out', 'occurred_at' => '2026-10-24 11:00:00']);
        $this->login($owner, $ws);
        $this->post('/user/attendance/bulk-review', ['kind' => 'punch', 'ids' => [$in->id, $out->id], 'decision' => 'shift', 'shift_minutes' => -15, 'note' => 'Correct office clock offset'])->assertRedirect();
        $this->assertSame('08:45', $in->fresh()->occurred_at->format('H:i'));
        $this->assertSame('12:00', $in->fresh()->created_at->format('H:i'));
        $this->assertDatabaseHas('attendance_reviews', ['subject_id' => $in->id, 'decision' => 'modified']);
        $this->post('/user/attendance/bulk-review', ['kind' => 'punch', 'ids' => [$in->id], 'decision' => 'shift', 'shift_minutes' => 180, 'note' => 'Invalid reordered event'])->assertStatus(422);
        $this->assertSame('08:45', $in->fresh()->occurred_at->format('H:i'));
        $this->travelBack();
    }

    public function test_login_as_user_uses_existing_permission_and_writes_an_audit(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $limitedRole = Role::create(['name' => 'Limited attendance role', 'slug' => 'attendance-no-impersonate', 'guard' => 'admin']);
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-login@example.test', 'password' => 'long-test-password', 'role_id' => $limitedRole->id, 'status' => 'active']);
        $this->actingAs($admin, 'admin')->post('/admin/users/'.$staff->id.'/impersonate')->assertForbidden();
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super', 'guard' => 'admin']);
        $admin->update(['role_id' => $role->id]);
        $admin->unsetRelation('role');
        $this->actingAs($admin, 'admin')->post('/admin/users/'.$staff->id.'/impersonate')->assertRedirect(route('user.dashboard'));
        $this->assertDatabaseHas((new AdminActionAudit)->getTable(), ['action' => 'user.impersonated', 'target_user_id' => $staff->id, 'admin_id' => $admin->id]);
    }
}
