<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verified (identity-locked) users must not be able to swap their creator
 * avatar through any file-upload path. The JSON profile API is already
 * covered elsewhere; this suite locks down the multipart routes that pass
 * the raw request into CreatorProfileController::saveCoreProfileFields():
 *
 *  - POST /user/settings/creator            (web creator-profile editor)
 *  - POST /user/onboarding/creator-profile  (onboarding step, shares helper)
 *  - PATCH /api/v1/me/creator-profile       (mobile parity endpoint)
 *
 * plus the upload-then-attach API flow (upload a file, then attach its URL
 * as avatar/creator_avatar via PATCH /api/v1/profile).
 *
 * Uses real personal access tokens (NOT Sanctum::actingAs) per
 * .agents/memory/sanctum-api-tests.md.
 */
class VerifiedAvatarLockUploadPathTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGINAL = '/storage/avatars/original.png';

    private function makeUser(bool $locked, array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'handle'            => 'lockav' . rand(10000, 99999),
            'profile_published' => true,
            'avatar'            => '/storage/avatars/base.png',
            'creator_avatar'    => self::ORIGINAL,
            'email_verified_at' => now(),
        ], $locked ? ['profile_verification_status' => 'verified'] : [], $attrs))->fresh();
    }

    // ── Web creator-profile editor (multipart POST) ────────────────────

    public function test_web_multipart_creator_avatar_upload_is_ignored_when_locked(): void
    {
        Storage::fake('public');
        $user = $this->makeUser(locked: true);
        $this->assertTrue($user->isNameAvatarLocked());

        $resp = $this->actingAs($user, 'web')->post(route('user.creator-profile.update'), [
            '_token'         => csrf_token(),
            'tagline'        => 'Updated tagline',
            'sections'       => [],
            'socials'        => [],
            'creator_avatar' => UploadedFile::fake()->image('sneaky.png', 100, 100),
        ]);

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame(self::ORIGINAL, $fresh->creator_avatar);
        // Non-identity fields still save normally.
        $this->assertSame('Updated tagline', $fresh->tagline);
    }

    public function test_web_multipart_creator_avatar_remove_is_ignored_when_locked(): void
    {
        $user = $this->makeUser(locked: true);

        $this->actingAs($user, 'web')->post(route('user.creator-profile.update'), [
            '_token'                => csrf_token(),
            'tagline'               => 'hello',
            'sections'              => [],
            'socials'               => [],
            'creator_avatar_remove' => '1',
        ])->assertRedirect();

        $this->assertSame(self::ORIGINAL, $user->fresh()->creator_avatar);
    }

    // ── Onboarding creator-profile step (multipart POST, shared helper) ─

    public function test_onboarding_step_creator_avatar_upload_is_ignored_when_locked(): void
    {
        Storage::fake('public');
        $user = $this->makeUser(locked: true);

        $resp = $this->actingAs($user, 'web')->post(route('user.onboarding.creator-profile.save'), [
            '_token'         => csrf_token(),
            'tagline'        => 'Onboarding tagline',
            'creator_avatar' => UploadedFile::fake()->image('sneaky.png', 100, 100),
        ]);

        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();

        $this->assertSame(self::ORIGINAL, $user->fresh()->creator_avatar);
    }

    public function test_onboarding_step_creator_avatar_upload_works_when_unlocked(): void
    {
        // Control: proves the onboarding path really attaches the file when
        // not locked — so the locked assertion above is meaningful.
        Storage::fake('public');
        $user = $this->makeUser(locked: false);
        $this->assertFalse($user->isNameAvatarLocked());

        $this->actingAs($user, 'web')->post(route('user.onboarding.creator-profile.save'), [
            '_token'         => csrf_token(),
            'tagline'        => 'Onboarding tagline',
            'creator_avatar' => UploadedFile::fake()->image('new.png', 100, 100),
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertNotSame(self::ORIGINAL, $fresh->creator_avatar);
        $this->assertStringStartsWith('/storage/avatars/', $fresh->creator_avatar);
    }

    // ── API mobile-parity endpoint (multipart PATCH) ────────────────────

    public function test_api_me_creator_profile_multipart_upload_is_ignored_when_locked(): void
    {
        Storage::fake('public');
        $user  = $this->makeUser(locked: true);
        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withToken($token)->patch('/api/v1/me/creator-profile', [
            'tagline'        => 'API tagline',
            'creator_avatar' => UploadedFile::fake()->image('sneaky.png', 100, 100),
        ], ['Accept' => 'application/json']);

        $resp->assertOk();
        $fresh = $user->fresh();
        $this->assertSame(self::ORIGINAL, $fresh->creator_avatar);
        $this->assertSame('API tagline', $fresh->tagline);

        $this->flushHeaders();
    }

    public function test_api_me_creator_profile_multipart_upload_works_when_unlocked(): void
    {
        // Control: the same multipart request DOES swap the avatar for an
        // unlocked user, proving the endpoint accepts file uploads at all.
        Storage::fake('public');
        $user  = $this->makeUser(locked: false);
        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withToken($token)->patch('/api/v1/me/creator-profile', [
            'tagline'        => 'API tagline',
            'creator_avatar' => UploadedFile::fake()->image('new.png', 100, 100),
        ], ['Accept' => 'application/json']);

        $resp->assertOk();
        $fresh = $user->fresh();
        $this->assertNotSame(self::ORIGINAL, $fresh->creator_avatar);
        $this->assertStringStartsWith('/storage/avatars/', $fresh->creator_avatar);

        $this->flushHeaders();
    }

    // ── Upload-then-attach API flow ─────────────────────────────────────

    public function test_upload_then_attach_flow_is_ignored_when_locked(): void
    {
        // Mobile avatar changes upload a file first, then attach the returned
        // URL as avatar/creator_avatar on PATCH /api/v1/profile. The attach
        // step is the security boundary: a locked user's attach must be a
        // silent no-op regardless of where the URL came from.
        $user  = $this->makeUser(locked: true);
        $token = $user->createToken('test')->plainTextToken;
        $uploadedUrl = '/storage/avatars/freshly-uploaded.png';

        $resp = $this->withToken($token)->patchJson('/api/v1/profile', [
            'avatar'         => $uploadedUrl,
            'creator_avatar' => $uploadedUrl,
            'bio'            => 'Attach-flow bio',
        ]);

        $resp->assertOk();
        $fresh = $user->fresh();
        $this->assertSame('/storage/avatars/base.png', $fresh->avatar);
        $this->assertSame(self::ORIGINAL, $fresh->creator_avatar);
        $this->assertSame('Attach-flow bio', $fresh->bio);

        $this->flushHeaders();
    }

    public function test_upload_then_attach_flow_works_when_unlocked(): void
    {
        $user  = $this->makeUser(locked: false);
        $token = $user->createToken('test')->plainTextToken;
        $uploadedUrl = '/storage/avatars/freshly-uploaded.png';

        $resp = $this->withToken($token)->patchJson('/api/v1/profile', [
            'avatar'         => $uploadedUrl,
            'creator_avatar' => $uploadedUrl,
        ]);

        $resp->assertOk();
        $fresh = $user->fresh();
        $this->assertSame($uploadedUrl, $fresh->avatar);
        $this->assertSame($uploadedUrl, $fresh->creator_avatar);

        $this->flushHeaders();
    }
}
