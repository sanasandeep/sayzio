<?php

namespace Tests\Feature;

use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VerificationTickType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /api/v1/admin/profile-verification — reviewer moderation API (mobile
 * parity for /user/profile-verification-admin). Gated by the same web-pool
 * `user.verifications.review` permission; approve/reject delegate to the
 * shared ProfileVerificationModeration cores.
 */
class ProfileVerificationAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function tickType(): VerificationTickType
    {
        return VerificationTickType::publicRequestable()->first()
            ?? VerificationTickType::create(['name' => 'Blue', 'color' => '#3d6bff', 'is_active' => true, 'admin_assigned_only' => false, 'sort_order' => 1]);
    }

    private function makePending(User $user, string $kind = 'new'): ProfileVerificationRequest
    {
        return ProfileVerificationRequest::create([
            'user_id'       => $user->id,
            'tick_type_id'  => $this->tickType()->id,
            'official_name' => 'Alex Rivera',
            'purpose'       => 'Testing',
            'status'        => 'pending',
            'kind'          => $kind,
        ]);
    }

    private function reviewerToken(): string
    {
        $reviewer = User::factory()->create()->fresh();
        $roleId = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        $this->assertNotNull($roleId);
        $reviewer->roles()->syncWithoutDetaching([(int) $roleId]);
        $reviewer->flushPermissionCache();

        return $reviewer->createToken('t')->plainTextToken;
    }

    public function test_forbidden_without_review_permission(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/profile-verification')
            ->assertStatus(403);

        $this->flushHeaders();
    }

    public function test_index_lists_queue_with_counts(): void
    {
        $applicant = User::factory()->create(['profile_verification_status' => 'pending']);
        $req = $this->makePending($applicant);

        $this->withHeader('Authorization', 'Bearer ' . $this->reviewerToken())
            ->getJson('/api/v1/admin/profile-verification?queue=new&status=pending')
            ->assertOk()
            ->assertJsonPath('data.requests.0.id', $req->id)
            ->assertJsonPath('data.requests.0.status', 'pending')
            ->assertJsonPath('data.pending_new_count', 1)
            ->assertJsonPath('data.pending_reverification_count', 0);

        $this->flushHeaders();
    }

    public function test_approve_marks_user_verified(): void
    {
        $applicant = User::factory()->create(['profile_verification_status' => 'pending']);
        $req = $this->makePending($applicant);

        $this->withHeader('Authorization', 'Bearer ' . $this->reviewerToken())
            ->postJson("/api/v1/admin/profile-verification/{$req->id}/approve", ['admin_notes' => 'Looks legit'])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'approved');

        $this->flushHeaders();

        $applicant->refresh();
        $this->assertSame('verified', $applicant->profile_verification_status);
        $this->assertSame('Alex Rivera', $applicant->profile_verified_name);
        $this->assertNotNull($applicant->profile_verified_at);
    }

    public function test_reject_requires_notes_and_reverts_status(): void
    {
        $applicant = User::factory()->create(['profile_verification_status' => 'pending']);
        $req   = $this->makePending($applicant);
        $token = $this->reviewerToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/admin/profile-verification/{$req->id}/reject", [])
            ->assertStatus(422);

        $this->postJson("/api/v1/admin/profile-verification/{$req->id}/reject", ['admin_notes' => 'Insufficient proof'])
            ->assertOk()
            ->assertJsonPath('data.request.status', 'rejected');

        $this->flushHeaders();

        $this->assertSame('unverified', $applicant->refresh()->profile_verification_status);
    }

    public function test_already_reviewed_request_returns_409(): void
    {
        $applicant = User::factory()->create();
        $req = $this->makePending($applicant);
        $req->update(['status' => 'approved']);

        $this->withHeader('Authorization', 'Bearer ' . $this->reviewerToken())
            ->postJson("/api/v1/admin/profile-verification/{$req->id}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'already_reviewed');

        $this->flushHeaders();
    }

    public function test_tick_types_list_and_update(): void
    {
        $tick  = $this->tickType();
        $token = $this->reviewerToken();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/profile-verification/tick-types')
            ->assertOk()
            ->assertJsonFragment(['id' => $tick->id]);

        $this->postJson("/api/v1/admin/profile-verification/tick-types/{$tick->id}", [
            'name'  => 'Gold',
            'color' => '#ffcc00',
        ])->assertOk()
          ->assertJsonPath('data.tick_type.name', 'Gold');

        $this->flushHeaders();

        $this->assertSame('Gold', $tick->refresh()->name);
        $this->assertSame('#ffcc00', $tick->color);
    }
}
