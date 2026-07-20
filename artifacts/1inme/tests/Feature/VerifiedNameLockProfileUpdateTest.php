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

    public function test_verified_user_cannot_change_avatar_via_direct_post(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create([
            'name'                        => 'Verified Name',
            'avatar'                      => '/storage/avatars/original.png',
            'email_verified_at'           => now(),
            'profile_verification_status' => 'verified',
        ])->fresh();

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->payload($user, [
                'avatar' => \Illuminate\Http\UploadedFile::fake()->image('new.png'),
                'bio'    => 'Updated bio',
            ])
        );

        $resp->assertSessionHasNoErrors();
        $fresh = $user->fresh();
        $this->assertSame('/storage/avatars/original.png', $fresh->avatar);
        $this->assertSame('Updated bio', $fresh->bio);
    }

    public function test_unverified_user_can_still_change_avatar(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $user = User::factory()->create([
            'avatar'            => '/storage/avatars/original.png',
            'email_verified_at' => now(),
        ])->fresh();

        $this->assertFalse($user->isNameAvatarLocked());

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->payload($user, [
                'avatar' => \Illuminate\Http\UploadedFile::fake()->image('new.png'),
            ])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertNotSame('/storage/avatars/original.png', $user->fresh()->avatar);
    }

    public function test_api_profile_update_ignores_name_and_avatar_when_locked(): void
    {
        $user = User::factory()->create([
            'name'                        => 'Verified Name',
            'avatar'                      => '/storage/avatars/original.png',
            'email_verified_at'           => now(),
            'profile_verification_status' => 'verified',
        ])->fresh();

        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withToken($token)->patchJson('/api/v1/profile', [
            'name'   => 'Attacker Name',
            'avatar' => '/storage/avatars/attacker.png',
            'bio'    => 'API bio',
        ]);

        $resp->assertOk();
        $fresh = $user->fresh();
        $this->assertSame('Verified Name', $fresh->name);
        $this->assertSame('/storage/avatars/original.png', $fresh->avatar);
        $this->assertSame('API bio', $fresh->bio);

        $this->flushHeaders();
    }

    public function test_api_profile_update_allows_name_and_avatar_when_unlocked(): void
    {
        $user = User::factory()->create([
            'name'              => 'Old Name',
            'avatar'            => '/storage/avatars/original.png',
            'email_verified_at' => now(),
        ])->fresh();

        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withToken($token)->patchJson('/api/v1/profile', [
            'name'   => 'New Name',
            'avatar' => '/storage/avatars/new.png',
        ]);

        $resp->assertOk();
        $fresh = $user->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('/storage/avatars/new.png', $fresh->avatar);

        $this->flushHeaders();
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
