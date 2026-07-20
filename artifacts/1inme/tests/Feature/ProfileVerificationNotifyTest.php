<?php

namespace Tests\Feature;

use App\Modules\User\Models\ProfileVerificationRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\VerificationTickType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Auto-notify on verification review (Task #5440): approving or rejecting a
 * profile verification request (new or re-verification) from the admin
 * dashboard must write an in-app UserNotification (email is sent best-effort
 * through the Emailer under the `verification.approved` / `verification.rejected`
 * registry keys; a missing SMTP config must never block the review action).
 */
class ProfileVerificationNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function makeReviewer(): User
    {
        $user = User::factory()->create()->fresh();
        $roleId = DB::table('roles')->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        $this->assertNotNull($roleId, 'user-admin role must be seeded');
        $user->roles()->syncWithoutDetaching([(int) $roleId]);
        $user->flushPermissionCache();
        return $user->fresh();
    }

    private function tickType(): VerificationTickType
    {
        return VerificationTickType::query()->first()
            ?? VerificationTickType::create(['name' => 'Blue', 'color' => '#3d6bff', 'is_active' => true, 'sort_order' => 1]);
    }

    private function makeRequest(User $applicant, array $attrs = []): ProfileVerificationRequest
    {
        return ProfileVerificationRequest::create(array_merge([
            'user_id'       => $applicant->id,
            'tick_type_id'  => $this->tickType()->id,
            'official_name' => 'Alex Rivera',
            'purpose'       => 'Testing',
            'status'        => 'pending',
            'kind'          => 'new',
        ], $attrs));
    }

    public function test_approving_a_new_request_notifies_the_user(): void
    {
        $reviewer  = $this->makeReviewer();
        $applicant = User::factory()->create(['profile_verification_status' => 'pending']);
        $req = $this->makeRequest($applicant);

        $this->actingAs($reviewer)
            ->post("/user/profile-verification-admin/{$req->id}/approve", [])
            ->assertRedirect();

        $n = UserNotification::where('user_id', $applicant->id)
            ->where('type', 'account.verification_approved')->first();
        $this->assertNotNull($n, 'approval must create an in-app notification');
        $this->assertSame('Alex Rivera', $n->data['verified_name']);
        $this->assertFalse((bool) $n->data['reverification']);
        $this->assertStringContainsString('verified', $n->data['message']);
    }

    public function test_rejecting_a_new_request_notifies_with_admin_note(): void
    {
        $reviewer  = $this->makeReviewer();
        $applicant = User::factory()->create(['profile_verification_status' => 'pending']);
        $req = $this->makeRequest($applicant);

        $this->actingAs($reviewer)
            ->post("/user/profile-verification-admin/{$req->id}/reject", [
                'admin_notes' => 'Proof documents unreadable.',
            ])
            ->assertRedirect();

        $n = UserNotification::where('user_id', $applicant->id)
            ->where('type', 'account.verification_rejected')->first();
        $this->assertNotNull($n, 'rejection must create an in-app notification');
        $this->assertSame('Proof documents unreadable.', $n->data['reason']);
        $this->assertStringContainsString('Proof documents unreadable.', $n->data['message']);
    }

    public function test_reverification_outcomes_are_flagged_as_reverification(): void
    {
        $reviewer  = $this->makeReviewer();
        $tick      = $this->tickType();
        $applicant = User::factory()->create([
            'profile_verification_status'  => 'pending_reverification',
            'profile_verification_type_id' => $tick->id,
            'profile_verified_name'        => 'Old Name',
            'profile_verified_at'          => now(),
        ]);
        $req = $this->makeRequest($applicant, [
            'kind'               => 'reverification',
            'prev_verified_name' => 'Old Name',
            'new_name'           => 'New Name',
        ]);

        $this->actingAs($reviewer)
            ->post("/user/profile-verification-admin/{$req->id}/approve", [])
            ->assertRedirect();

        $n = UserNotification::where('user_id', $applicant->id)
            ->where('type', 'account.verification_approved')->first();
        $this->assertNotNull($n);
        $this->assertTrue((bool) $n->data['reverification']);
        $this->assertSame('New Name', $n->data['verified_name']);
    }
}
