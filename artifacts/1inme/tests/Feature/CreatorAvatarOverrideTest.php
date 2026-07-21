<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Creator-profile-specific avatar override (Task #5494).
 *
 * - `users.creator_avatar` is null by default and public creator surfaces
 *   fall back to the account profile photo.
 * - Web upload/remove via the Creator settings tab.
 * - Verified (name/avatar-locked) users cannot change it.
 * - API parity: self resource fields + profile update set/clear, and the
 *   public creator-profile endpoints show the effective avatar.
 *
 * Uses real personal access tokens (NOT Sanctum::actingAs) per the
 * documented gotcha in .agents/memory/sanctum-api-tests.md.
 */
class CreatorAvatarOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeCreator(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'handle'            => 'cavatar' . rand(1000, 9999),
            'profile_published' => true,
            'avatar'            => '/storage/avatars/base.png',
        ], $attrs));
    }

    // ── Model fallback semantics ───────────────────────────────────────

    public function test_defaults_to_account_avatar_when_no_override(): void
    {
        $user = $this->makeCreator();

        $this->assertNull($user->creator_avatar);
        $this->assertSame('/storage/avatars/base.png', $user->creatorAvatarRaw());
    }

    public function test_override_wins_when_set(): void
    {
        $user = $this->makeCreator(['creator_avatar' => '/storage/avatars/custom.png']);

        $this->assertSame('/storage/avatars/custom.png', $user->creatorAvatarRaw());
    }

    // ── Web settings save ──────────────────────────────────────────────

    public function test_web_upload_sets_creator_avatar(): void
    {
        Storage::fake('public');
        $user = $this->makeCreator();

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'         => csrf_token(),
                'tagline'        => 'hello',
                'sections'       => [],
                'socials'        => [],
                'creator_avatar' => UploadedFile::fake()->image('me.png', 100, 100),
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->creator_avatar);
        $this->assertStringStartsWith('/storage/avatars/', $user->creator_avatar);
        $this->assertSame($user->creator_avatar, $user->creatorAvatarRaw());
    }

    public function test_web_remove_clears_override(): void
    {
        $user = $this->makeCreator(['creator_avatar' => '/storage/avatars/custom.png']);

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'                => csrf_token(),
                'tagline'               => 'hello',
                'sections'              => [],
                'socials'               => [],
                'creator_avatar_remove' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->creator_avatar);
        $this->assertSame('/storage/avatars/base.png', $user->creatorAvatarRaw());
    }

    public function test_verified_lock_ignores_upload_and_remove(): void
    {
        Storage::fake('public');
        $user = $this->makeCreator([
            'creator_avatar'              => '/storage/avatars/locked.png',
            'profile_verification_status' => 'verified',
        ]);

        $this->assertTrue($user->isNameAvatarLocked());

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'         => csrf_token(),
                'tagline'        => 'hello',
                'sections'       => [],
                'socials'        => [],
                'creator_avatar' => UploadedFile::fake()->image('sneaky.png', 100, 100),
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('/storage/avatars/locked.png', $user->creator_avatar);

        $this->actingAs($user, 'web')
            ->post(route('user.creator-profile.update'), [
                '_token'                => csrf_token(),
                'tagline'               => 'hello',
                'sections'              => [],
                'socials'               => [],
                'creator_avatar_remove' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertSame('/storage/avatars/locked.png', $user->creator_avatar);
    }

    // ── Public surfaces ────────────────────────────────────────────────

    public function test_web_mini_endpoint_shows_effective_avatar(): void
    {
        $user = $this->makeCreator(['creator_avatar' => '/storage/avatars/custom.png']);

        $this->getJson('/@' . $user->handle . '/mini')
            ->assertOk()
            ->assertJsonPath('data.avatar', \App\Support\PublicStorageUrl::resolve('/storage/avatars/custom.png'));
    }

    public function test_web_mini_endpoint_falls_back_to_account_avatar(): void
    {
        $user = $this->makeCreator();

        $this->getJson('/@' . $user->handle . '/mini')
            ->assertOk()
            ->assertJsonPath('data.avatar', \App\Support\PublicStorageUrl::resolve('/storage/avatars/base.png'));
    }

    // ── API parity ─────────────────────────────────────────────────────

    public function test_api_creator_profile_shows_effective_avatar(): void
    {
        $user = $this->makeCreator(['creator_avatar' => '/storage/avatars/custom.png']);

        $this->getJson('/api/v1/creator-profile/' . $user->handle)
            ->assertOk()
            ->assertJsonPath('data.profile.avatar', \App\Support\PublicStorageUrl::resolve('/storage/avatars/custom.png'));
    }

    public function test_api_profile_update_sets_and_clears_override(): void
    {
        $user  = $this->makeCreator();
        $token = $this->token($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', ['creator_avatar' => '/storage/avatars/api.png'])
            ->assertOk()
            ->assertJsonPath('data.user.creator_avatar', \App\Support\PublicStorageUrl::resolve('/storage/avatars/api.png'));

        $user->refresh();
        $this->assertSame('/storage/avatars/api.png', $user->creator_avatar);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', ['creator_avatar' => null])
            ->assertOk()
            ->assertJsonPath('data.user.creator_avatar', null);

        $user->refresh();
        $this->assertNull($user->creator_avatar);
        // Effective URL falls back to the account avatar chain.
        $this->assertSame($user->resolveAvatarUrl(), $user->resolveCreatorAvatarUrl());
    }

    public function test_api_profile_update_ignores_override_when_locked(): void
    {
        $user  = $this->makeCreator(['profile_verification_status' => 'verified']);
        $token = $this->token($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/profile', ['creator_avatar' => '/storage/avatars/sneaky.png'])
            ->assertOk();

        $this->assertNull($user->fresh()->creator_avatar);
    }
}
