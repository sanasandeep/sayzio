<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorProfile;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VerificationTickType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileCreatorWorkspaceParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_exclusive_phone_cannot_move_between_profiles_without_admin(): void
    {
        $user = User::factory()->create()->fresh();
        $personal = CreatorProfile::forWorkspace($user->ensureDefaultWorkspace());
        $team = $user->ownedWorkspaces()->create(['name' => 'Team', 'slug' => 'mobile-team-'.$user->id]);
        $teamProfile = CreatorProfile::forWorkspace($team);
        LinkedIdentifier::create(['user_id' => $user->id, 'kind' => 'phone', 'value' => '+919876543210', 'verified_at' => now()]);
        $this->withToken($user->createToken('mobile')->plainTextToken);
        $this->postJson('/api/v1/me/creator-profile/phones', ['creator_profile_id' => $personal->id, 'phone' => '+91 98765 43210'])->assertOk();
        $this->postJson('/api/v1/workspaces/'.$team->id.'/activate')->assertOk();
        $this->postJson('/api/v1/me/creator-profile/phones', ['creator_profile_id' => $teamProfile->id, 'phone' => '+919876543210'])->assertUnprocessable();
        $this->assertDatabaseHas('creator_phone_assignments', ['phone' => '+919876543210', 'creator_profile_id' => $personal->id]);
        $this->postJson('/api/v1/me/creator-profile/phone-claims', ['creator_profile_id' => $teamProfile->id, 'phone' => '+919876543210', 'message' => 'Please review ownership', 'supporting_urls' => ['https://example.com/proof']])->assertOk();
        $this->assertDatabaseHas('creator_phone_claims', ['creator_profile_id' => $teamProfile->id, 'status' => 'pending']);
        $this->assertDatabaseHas('creator_phone_assignments', ['creator_profile_id' => $personal->id]);
    }

    public function test_workspace_verification_is_separate_from_personal_account(): void
    {
        $user = User::factory()->create()->fresh();
        $team = $user->ownedWorkspaces()->create(['name' => 'Studio', 'slug' => 'studio-'.$user->id]);
        $profile = CreatorProfile::forWorkspace($team);
        $type = VerificationTickType::create(['slug' => 'test-creator', 'name' => 'Creator', 'color' => '#778899', 'icon' => 'fa-star', 'is_active' => true, 'admin_assigned_only' => false]);
        $this->withToken($user->createToken('mobile')->plainTextToken);
        $this->postJson('/api/v1/workspaces/'.$team->id.'/activate')->assertOk();
        $this->postJson('/api/v1/profile-verification', ['context' => 'workspace', 'creator_profile_id' => $profile->id, 'tick_type_id' => $type->id, 'official_name' => 'Studio', 'purpose' => 'Verify our studio', 'supporting_urls' => ['https://example.com']])->assertCreated();
        $this->assertDatabaseHas('profile_verification_requests', ['creator_profile_id' => $profile->id, 'status' => 'pending']);
        $this->assertSame('pending', $profile->refresh()->profile_verification_status);
        $this->assertNotSame('pending', $user->refresh()->profile_verification_status);
        $this->getJson('/api/v1/profile-verification?context=workspace')->assertOk()->assertJsonCount(1, 'data.requests');
        $this->getJson('/api/v1/profile-verification')->assertOk()->assertJsonCount(0, 'data.requests');
    }

    public function test_workspace_handle_change_revokes_verification(): void
    {
        $user = User::factory()->create()->fresh();
        $team = $user->ownedWorkspaces()->create(['name' => 'Verified team', 'slug' => 'verified-team-'.$user->id]);
        $profile = CreatorProfile::forWorkspace($team);
        $profile->forceFill(['handle' => 'previous_'.$user->id, 'profile_verification_status' => 'verified'])->save();
        $this->withToken($user->createToken('mobile')->plainTextToken);
        $this->postJson('/api/v1/workspaces/'.$team->id.'/activate')->assertOk();
        $this->postJson('/api/v1/me/creator-profile/handle', ['creator_profile_id' => $profile->id, 'handle' => 'updated_'.$user->id])->assertOk();
        $this->assertSame('unverified', $profile->refresh()->profile_verification_status);
    }
}
