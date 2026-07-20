<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verified users have their display name locked. The profile edit view
 * hides the name input, but the server must also ignore a submitted
 * `name` so a direct POST cannot bypass the verified-name lock.
 */
class VerifiedNameLockProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name'     => $user->name,
            'email'    => $user->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ], $overrides);
    }

    public function test_verified_user_cannot_change_name_via_direct_post(): void
    {
        $user = User::factory()->create([
            'name'                        => 'Verified Name',
            'email_verified_at'           => now(),
            'profile_verification_status' => 'verified',
        ])->fresh();

        $this->assertTrue($user->isNameAvatarLocked());

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->payload($user, ['name' => 'Attacker Name', 'bio' => 'Updated bio'])
        );

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('Verified Name', $fresh->name);
        // Other fields still save normally.
        $this->assertSame('Updated bio', $fresh->bio);
    }

    public function test_pending_reverification_user_name_is_also_locked(): void
    {
        $user = User::factory()->create([
            'name'                        => 'Locked Name',
            'email_verified_at'           => now(),
            'profile_verification_status' => 'pending_reverification',
        ])->fresh();

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->payload($user, ['name' => 'New Name'])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame('Locked Name', $user->fresh()->name);
    }

    public function test_unverified_user_can_still_change_name(): void
    {
        $user = User::factory()->create([
            'name'              => 'Old Name',
            'email_verified_at' => now(),
        ])->fresh();

        $this->assertFalse($user->isNameAvatarLocked());

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->payload($user, ['name' => 'New Name'])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);
    }
}
