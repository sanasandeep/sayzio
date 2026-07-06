<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * HTTP coverage for the admin banned-names block on the profile @handle
 * surfaces, for a NON-privileged user.
 *
 * Task #2867 only closed the Create Link *alias* surface
 * (see {@see \Tests\Feature\CreateLinkBannedAliasTest}). A profile @handle
 * is the *other* place a user-chosen name becomes a reserved public slug
 * (`/@handle`), and it is set on several surfaces:
 *   - API registration   (POST /api/v1/auth/register)
 *   - API profile update (PATCH /api/v1/profile)
 *   - web profile update (POST /user/profile)
 *   - web handle claim   (CreatorProfileController::claimHandle)
 *
 * The web profile editor and the handle-claim endpoint already ran the
 * NotBannedName rule; the two API surfaces above did not, so a
 * non-privileged user could register or PATCH their way to a reserved
 * handle. This test pins the block on the registration and profile-update
 * paths (web + API) and contrasts it with the `user.banned_names.bypass`
 * privileged path so the rejection is proven to be the banned-list rule
 * and not some unrelated failure.
 *
 * Authenticated API requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the
 * TouchSessionToken middleware — see
 * .agents/memory/sanctum-api-tests.md).
 */
class ProfileHandleBannedNameTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A banned handle that is within the regex/length limits so the
     * NotBannedName rule is reached rather than failing format first.
     */
    private const BANNED_HANDLE = 'reservedhandle';

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    private function seedBannedHandle(string $name = self::BANNED_HANDLE): void
    {
        BannedName::firstOrCreate(['name' => $name]);
        // Drop the 5-minute cached lookup so the rule sees the row now.
        BannedNameChecker::flush($name);
    }

    private function grantBypass(User $user): User
    {
        $roleId = DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')->value('id');
        $this->assertNotNull($roleId, 'user-admin role must be seeded');
        $user->roles()->syncWithoutDetaching([(int) $roleId]);
        $user->flushPermissionCache();
        $user = $user->fresh();
        $this->assertTrue(
            $user->hasPermission('user.banned_names.bypass'),
            'the user-admin role must grant user.banned_names.bypass'
        );
        return $user;
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ---------------------------------------------------------------
    // API registration
    // ---------------------------------------------------------------

    public function test_api_register_rejects_banned_handle(): void
    {
        $this->seedBannedHandle();

        $resp = $this->postJson('/api/v1/auth/register', [
            'name'     => 'New User',
            'email'    => 'newuser' . Str::random(6) . '@ex.com',
            'password' => 'password123',
            'handle'   => self::BANNED_HANDLE,
        ]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('handle');
        $this->assertDatabaseMissing('users', ['handle' => self::BANNED_HANDLE]);
    }

    public function test_api_register_accepts_a_fresh_handle(): void
    {
        // Contrast: a valid, unbanned handle registers fine — proving the
        // rejection above is the banned-list rule, not a blanket failure.
        $this->seedBannedHandle();
        $fresh = 'okhandle' . Str::lower(Str::random(6));

        $resp = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Fresh User',
            'email'    => 'fresh' . Str::random(6) . '@ex.com',
            'password' => 'password123',
            'handle'   => $fresh,
        ]);

        $resp->assertCreated();
        $this->assertDatabaseHas('users', ['handle' => $fresh]);
    }

    // ---------------------------------------------------------------
    // API profile update (PATCH /api/v1/profile)
    // ---------------------------------------------------------------

    public function test_api_profile_update_rejects_banned_handle_for_non_privileged_user(): void
    {
        $this->seedBannedHandle();
        $user = $this->makeUser();

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/profile', ['handle' => self::BANNED_HANDLE]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('handle');
        $this->assertNull($user->fresh()->handle);
    }

    public function test_api_profile_update_allows_privileged_user_to_set_banned_handle(): void
    {
        $this->seedBannedHandle();
        $user = $this->grantBypass($this->makeUser());

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/profile', ['handle' => self::BANNED_HANDLE]);

        $resp->assertOk();
        $this->assertSame(self::BANNED_HANDLE, $user->fresh()->handle);
    }

    // ---------------------------------------------------------------
    // Web profile update (POST /user/profile)
    // ---------------------------------------------------------------

    /**
     * The web profile editor's update action requires a few other fields
     * (name/email/timezone/language); supply valid values for them so the
     * only thing under test is the handle's banned-name branch.
     */
    private function webProfilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name'     => $user->name,
            'email'    => $user->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ], $overrides);
    }

    public function test_web_profile_update_rejects_banned_handle_for_non_privileged_user(): void
    {
        $this->seedBannedHandle();
        $user = $this->makeUser();

        $resp = $this->actingAs($user)->put(
            '/user/profile',
            $this->webProfilePayload($user, ['handle' => self::BANNED_HANDLE])
        );

        $resp->assertSessionHasErrors('handle');
        $this->assertNull($user->fresh()->handle);
    }

    public function test_web_profile_update_allows_privileged_user_to_set_banned_handle(): void
    {
        $this->seedBannedHandle();
        $user = $this->grantBypass($this->makeUser());

        $resp = $this->actingAs($user)->put(
            '/user/profile',
            $this->webProfilePayload($user, ['handle' => self::BANNED_HANDLE])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame(self::BANNED_HANDLE, $user->fresh()->handle);
    }
}
