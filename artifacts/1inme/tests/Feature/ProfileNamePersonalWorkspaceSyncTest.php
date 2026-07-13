<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a user updates their profile Name, their personal workspace name
 * should follow along — but ONLY while that workspace still carries the
 * auto-generated default name (`{name}'s workspace`). A workspace the user
 * deliberately renamed must never be clobbered.
 *
 * Covers the web profile-update surface (POST /user/profile ->
 * ProfileController::update).
 */
class ProfileNamePersonalWorkspaceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function webProfilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name'     => $user->name,
            'email'    => $user->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ], $overrides);
    }

    public function test_renaming_profile_syncs_default_personal_workspace_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name'])->fresh();
        $personal = $user->ensureDefaultWorkspace();
        $this->assertSame("Old Name's workspace", $personal->name);

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, ['name' => 'New Name'])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame("New Name's workspace", $personal->fresh()->name);
    }

    public function test_renaming_profile_does_not_clobber_a_manually_renamed_workspace(): void
    {
        $user = User::factory()->create(['name' => 'Old Name'])->fresh();
        $personal = $user->ensureDefaultWorkspace();
        $personal->update(['name' => 'My Studio']);

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, ['name' => 'New Name'])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame('My Studio', $personal->fresh()->name);
    }
}
