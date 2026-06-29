<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile / REST API parity for the banned/reserved-alias block on the Create
 * Link flow, for a NON-privileged user.
 *
 * Task #2867 verified the *web* flow (check-alias + choose-type) but the Expo
 * mobile app drives the REST parity endpoints instead:
 *   - GET  /api/v1/links/check-alias  (Api\LinkController::checkAlias, backed
 *     by AliasAvailability) — the live "Custom URL availability" indicator;
 *   - POST /api/v1/links              (Api\LinkController::store) — the actual
 *     create submit, which runs the NotBannedName rule at validation time.
 *
 * This test closes that gap by driving both as a plain user with no bypass
 * permission, then pins the bypass branch with a privileged-user contrast: the
 * same banned alias must read as available *and* be accepted for a user
 * holding `user.banned_names.bypass` (via the seeded `user-admin` role), so the
 * rejection is proven to be the banned-list rule and not some unrelated error.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware (every authed request would 500), so
 * we mint a real token and send it via withToken().
 */
class MobileCreateLinkBannedAliasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A banned alias that is alpha_dash and within the default plan length
     * limits (min 3 / max 50), so the live checker and the store() validator
     * reach the banned-list branch rather than failing format/length first.
     */
    private const BANNED_ALIAS = 'reservedhandle';

    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
        // Owns a personal workspace so the link is created against a real
        // workspace (mirrors the web reference test fixture).
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function seedBannedAlias(string $name = self::BANNED_ALIAS): void
    {
        BannedName::firstOrCreate(['name' => $name]);
        // Drop the 5-minute cached lookup so the live checker / rule see the
        // row immediately within this test.
        BannedNameChecker::flush($name);
    }

    public function test_check_alias_reports_banned_for_non_privileged_user(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();

        $resp = $this->withToken($this->token($user))
            ->getJson('/api/v1/links/check-alias?alias=' . self::BANNED_ALIAS);

        $resp->assertOk();
        $resp->assertJson([
            'status'    => 'banned',
            'available' => false,
        ]);
    }

    public function test_store_rejects_banned_alias_for_non_privileged_user(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'  => 'short',
                'alias' => self::BANNED_ALIAS,
                'long_url' => 'https://example.com',
            ]);

        // The NotBannedName rule fails validation → 422 with an alias error in
        // the unified envelope, and no link row is created.
        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertFalse(
            Link::where('alias', self::BANNED_ALIAS)->exists(),
            'a banned alias must not create a link via the mobile store path'
        );
    }

    public function test_check_alias_and_store_accept_a_fresh_alias(): void
    {
        // Sanity contrast: a valid, unbanned, unused alias is reported
        // available and successfully creates a link — proving the rejection
        // above is specifically the banned-list block, not a blanket failure.
        $this->seedBannedAlias();
        $user  = $this->makeUser();
        $token = $this->token($user);
        $fresh = 'okhandle' . Str::lower(Str::random(6));

        $check = $this->withToken($token)
            ->getJson('/api/v1/links/check-alias?alias=' . $fresh);
        $check->assertOk();
        $check->assertJson(['status' => 'available', 'available' => true]);

        $resp = $this->withToken($token)
            ->postJson('/api/v1/links', [
                'type'  => 'short',
                'alias' => $fresh,
                'long_url' => 'https://example.com',
            ]);
        $resp->assertStatus(201);
        $this->assertTrue(Link::where('alias', $fresh)->exists());
    }

    public function test_privileged_user_bypasses_the_banned_alias_block(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();

        // Grant the `user.banned_names.bypass` permission via the seeded
        // `user-admin` web role (the same role the demo e2e account holds).
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

        $token = $this->token($user);

        // Live checker reports the banned alias as available for this user.
        $check = $this->withToken($token)
            ->getJson('/api/v1/links/check-alias?alias=' . self::BANNED_ALIAS);
        $check->assertOk();
        $check->assertJson(['status' => 'available', 'available' => true]);

        // And the create submit is accepted (no banned-name validation error),
        // creating the link with the reserved alias.
        $resp = $this->withToken($token)
            ->postJson('/api/v1/links', [
                'type'  => 'short',
                'alias' => self::BANNED_ALIAS,
                'long_url' => 'https://example.com',
            ]);
        $resp->assertStatus(201);
        $this->assertTrue(Link::where('alias', self::BANNED_ALIAS)->exists());
    }
}
