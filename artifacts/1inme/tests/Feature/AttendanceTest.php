<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\AttendanceChallenge;
use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\AttendanceService;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
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

    private function punch(string $action): int
    {
        $challenge = $this->postJson('/user/attendance/challenge', ['action' => $action])->assertOk()->json('id');

        return $this->postJson('/user/attendance/punch', ['challenge_id' => $challenge])->assertOk()->json('id');
    }

    public function test_member_can_clock_in_take_break_resume_and_clock_out_once(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $page = $this->get('/user/attendance')->assertOk()->assertSee('Attendance')->assertSee('No attendance recorded yet');
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-workspace.html', $page->getContent());
        }
        $challenge = $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertOk()->json('id');
        $first = $this->postJson('/user/attendance/punch', ['challenge_id' => $challenge])->assertOk()->json('id');
        $this->postJson('/user/attendance/punch', ['challenge_id' => $challenge])->assertOk()->assertJsonPath('id', $first);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertUnprocessable();
        $this->punch('break');
        $this->punch('resume');
        $this->punch('out');
        $this->assertSame(4, AttendancePunch::count());
        $this->assertSame('out', AttendanceParticipant::where('user_id', $staff->id)->where('workspace_id', $ws->id)->first()->state);
    }

    public function test_expired_or_another_persons_challenge_cannot_be_used(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $id = $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertOk()->json('id');
        $this->login($owner, $ws);
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertNotFound();
        $this->login($staff, $ws);
        AttendanceChallenge::whereKey($id)->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertUnprocessable();
        $this->assertSame(0, AttendancePunch::count());
    }

    public function test_private_evidence_and_review_are_scoped_and_self_review_is_forbidden(): void
    {
        [$owner,$ws,$staff] = $this->team();
        Storage::fake('attendance_evidence');
        config(['attendance.evidence_bucket' => 'private-test']);
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['require_photo' => true])]);
        $this->login($staff, $ws);
        $id = $this->postJson('/user/attendance/challenge', ['action' => 'in'])->json('id');
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertUnprocessable();
        $record = $this->post('/user/attendance/punch', ['challenge_id' => $id, 'captured_at' => now()->addSecond()->toIso8601String(), 'photo' => UploadedFile::fake()->image('selfie.jpg', 640, 480)], ['Accept' => 'application/json'])->assertOk()->json('id');
        $stored = Storage::disk('attendance_evidence')->get(AttendancePunch::find($record)->photo_path);
        $this->assertStringEndsWith('.enc', AttendancePunch::find($record)->photo_path);
        $this->assertNotSame(Crypt::decryptString($stored), $stored);
        $this->get('/user/attendance/punches/'.$record.'/evidence')->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->post('/user/attendance/punches/'.$record.'/review', ['decision' => 'approved', 'note' => 'Looks correct'])->assertForbidden();
        $this->login($owner, $ws);
        $this->post('/user/attendance/punches/'.$record.'/review', ['decision' => 'approved', 'note' => 'Pose checked'])->assertRedirect();
        $this->assertDatabaseHas('attendance_reviews', ['subject_id' => $record, 'decision' => 'approved']);
        $other = User::factory()->create();
        $this->login($other, $other->ownedWorkspaces()->first());
        $this->get('/user/attendance/punches/'.$record.'/evidence')->assertNotFound();
    }

    public function test_role_revocation_and_suspension_are_enforced(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['viewer']['attendance.create'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->login($staff, $ws);
        $this->postJson('/user/attendance/challenge', ['action' => 'in'])->assertForbidden();
        $this->post('/user/attendance/settings', [])->assertStatus(405);
        $this->put('/user/attendance/settings', [])->assertForbidden();
        WorkspaceMember::where('workspace_id', $ws->id)->where('user_id', $staff->id)->update(['suspended_at' => now()]);
        $response = $this->get('/user/attendance');
        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_location_boundary_and_accuracy_are_checked(): void
    {
        [$owner,$ws,$staff] = $this->team();
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['require_photo' => false, 'require_location' => true, 'latitude' => 12.0, 'longitude' => 77.0, 'radius_m' => 200])]);
        $this->login($staff, $ws);
        $id = $this->postJson('/user/attendance/challenge', ['action' => 'in'])->json('id');
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertUnprocessable();
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id, 'latitude' => 13, 'longitude' => 77, 'accuracy' => 10])->assertUnprocessable();
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id, 'latitude' => 12, 'longitude' => 77, 'accuracy' => 150])->assertUnprocessable();
        $this->postJson('/user/attendance/punch', ['challenge_id' => $id, 'latitude' => 12, 'longitude' => 77, 'accuracy' => 10])->assertOk();
    }

    public function test_overnight_sessions_and_breaks_are_split_by_local_day(): void
    {
        $service = app(AttendanceService::class);
        $events = collect();
        foreach (['2026-10-23T22:00:00Z' => 'in', '2026-10-23T23:00:00Z' => 'break', '2026-10-23T23:30:00Z' => 'resume', '2026-10-24T02:00:00Z' => 'out'] as $time => $action) {
            $events->push(new AttendancePunch(['action' => $action, 'occurred_at' => $time]));
        }
        $this->assertSame(5400, $service->workedSeconds($events, CarbonImmutable::parse('2026-10-23T00:00:00Z'), CarbonImmutable::parse('2026-10-24T00:00:00Z')));
        $this->assertSame(7200, $service->workedSeconds($events, CarbonImmutable::parse('2026-10-24T00:00:00Z'), CarbonImmutable::parse('2026-10-25T00:00:00Z')));
    }

    public function test_requests_require_another_reviewer_and_corrections_keep_receipts(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T09:00:00Z'));
        $punch = $this->punch('in');
        $this->travelTo(CarbonImmutable::parse('2026-10-24T12:00:00Z'));
        $this->post('/user/attendance/requests', ['type' => 'correction', 'starts_on' => '2026-10-24', 'ends_on' => '2026-10-24', 'punch_id' => $punch, 'proposed_at' => '2026-10-24T08:55:00Z', 'reason' => 'Corrected starting time'])->assertRedirect();
        $request = AttendanceRequest::first();
        $this->login($owner, $ws);
        $this->post('/user/attendance/requests/'.$request->id.'/review', ['decision' => 'approved', 'note' => 'Confirmed start'])->assertRedirect();
        $record = AttendancePunch::find($punch);
        $this->assertSame('08:55', $record->occurred_at->format('H:i'));
        $this->assertSame('09:00', $record->created_at->format('H:i'));
        $this->get('/user/attendance/export?from=2026-10-24&to=2026-10-24')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->travelBack();
    }

    public function test_admin_staff_attendance_is_separate_and_works_without_customer_visibility(): void
    {
        $role = Role::create(['name' => 'Attendance staff', 'slug' => 'attendance-staff', 'guard' => 'admin']);
        $admin = Admin::create(['name' => 'Internal colleague', 'email' => 'attendance@example.test', 'password' => 'long-test-password', 'role_id' => $role->id, 'status' => 'active']);
        DB::table('attendance_policies')->insert(['workspace_id' => 0, 'settings' => json_encode(['require_photo' => false]), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($admin, 'admin');
        $page = $this->get('/admin/attendance')->assertOk()->assertSee('Sayzio internal staff');
        if (getenv('ATTENDANCE_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-attendance-admin.html', $page->getContent());
        }
        $id = $this->postJson('/admin/attendance/challenge', ['action' => 'in'])->assertOk()->json('id');
        $this->postJson('/admin/attendance/punch', ['challenge_id' => $id])->assertOk();
        $this->assertDatabaseHas('attendance_participants', ['workspace_id' => 0, 'user_id' => $admin->id, 'state' => 'working']);
        $this->put('/admin/attendance/settings', [])->assertForbidden();
        $this->get('/admin/attendance/export?from=2026-10-24&to=2026-10-24')->assertForbidden();
        $this->post('/admin/attendance/punches/'.AttendancePunch::first()->id.'/review', ['decision' => 'approved', 'note' => 'self review'])->assertForbidden();
    }

    public function test_retention_removes_private_evidence_without_deleting_time_records(): void
    {
        [$owner,$ws,$staff] = $this->team();
        Storage::fake('attendance_evidence');
        $participant = app(AttendanceService::class)->participant($ws, $staff->id);
        Storage::disk('attendance_evidence')->put('attendance/expired.jpg', 'test');
        $record = AttendancePunch::create(['participant_id' => $participant->id, 'action' => 'in', 'occurred_at' => now()->subDays(100), 'photo_path' => 'attendance/expired.jpg', 'latitude' => 12, 'longitude' => 77]);
        $this->artisan('attendance:prune-evidence')->assertSuccessful();
        $this->assertNull($record->fresh()->photo_path);
        $this->assertNull($record->fresh()->latitude);
        Storage::disk('attendance_evidence')->assertMissing('attendance/expired.jpg');
        $this->assertSame(1, AttendancePunch::count());
    }

    public function test_duplicate_requests_and_self_approval_are_rejected(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $matrix = WorkspaceRoleMatrix::defaults();
        $matrix['viewer']['attendance.review'] = true;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->login($staff, $ws);
        $data = ['type' => 'leave', 'starts_on' => '2026-10-25', 'ends_on' => '2026-10-26', 'reason' => 'Planned personal leave'];
        $this->post('/user/attendance/requests', $data)->assertRedirect();
        $this->post('/user/attendance/requests', $data)->assertStatus(409);
        $id = AttendanceRequest::first()->id;
        $this->post('/user/attendance/requests/'.$id.'/review', ['decision' => 'approved', 'note' => 'My own request'])->assertForbidden();
        $this->login($owner, $ws);
        $this->post('/user/attendance/requests/'.$id.'/review', ['decision' => 'rejected', 'note' => 'Dates need rescheduling'])->assertRedirect();
        $this->assertDatabaseHas('attendance_requests', ['id' => $id, 'status' => 'rejected']);
    }

    public function test_correction_preserves_event_order_and_normalizes_timezone_offsets(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $this->login($staff, $ws);
        $this->travelTo(CarbonImmutable::parse('2026-10-24T09:00:00Z'));
        $id = $this->punch('in');
        $this->travelTo(CarbonImmutable::parse('2026-10-24T10:00:00Z'));
        $this->punch('out');
        $this->travelTo(CarbonImmutable::parse('2026-10-24T12:00:00Z'));
        $this->post('/user/attendance/requests', ['type' => 'correction', 'starts_on' => '2026-10-24', 'ends_on' => '2026-10-24', 'punch_id' => $id, 'proposed_at' => '2026-10-24T16:30:00+05:30', 'reason' => 'Requested correction'])->assertRedirect();
        $record = AttendanceRequest::first();
        $this->assertSame('11:00', $record->proposed_at->format('H:i'));
        $this->login($owner, $ws);
        $this->post('/user/attendance/requests/'.$record->id.'/review', ['decision' => 'approved', 'note' => 'Review proposed time'])->assertStatus(422);
        $this->assertSame('09:00', AttendancePunch::find($id)->occurred_at->format('H:i'));
        $this->travelBack();
    }

    public function test_internal_reviewer_cannot_access_workspace_evidence_even_with_matching_ids(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $participant = app(AttendanceService::class)->participant($ws, $staff->id);
        $punch = AttendancePunch::create(['participant_id' => $participant->id, 'action' => 'in', 'occurred_at' => now()]);
        $role = Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super', 'guard' => 'admin']);
        $admin = Admin::create(['name' => 'Reviewer', 'email' => 'reviewer@example.test', 'password' => 'long-test-password', 'role_id' => $role->id, 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/attendance/punches/'.$punch->id.'/evidence')->assertNotFound();
        $this->post('/admin/attendance/punches/'.$punch->id.'/review', ['decision' => 'approved', 'note' => 'Should not be accessible'])->assertNotFound();
    }

    public function test_export_uses_local_day_boundaries_and_escapes_staff_formula_cells(): void
    {
        [$owner,$ws,$staff] = $this->team();
        $staff->update(['name' => '=Unsafe formula']);
        DB::table('attendance_policies')->where('workspace_id', $ws->id)->update(['settings' => json_encode(['require_photo' => false, 'timezone' => 'Asia/Kolkata'])]);
        $participant = app(AttendanceService::class)->participant($ws, $staff->id);
        AttendancePunch::create(['participant_id' => $participant->id, 'action' => 'in', 'occurred_at' => '2026-10-23 19:00:00']);
        AttendancePunch::create(['participant_id' => $participant->id, 'action' => 'out', 'occurred_at' => '2026-10-24 01:00:00']);
        $this->login($owner, $ws);
        $response = $this->get('/user/attendance/export?from=2026-10-24&to=2026-10-24')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=Unsafe formula", $csv);
        $this->assertStringContainsString('Asia/Kolkata,6,0,0', $csv);
        $this->get('/user/attendance?from=2026-10-24&to=2026-10-24')->assertOk()->assertSee('=Unsafe formula');
    }
}
