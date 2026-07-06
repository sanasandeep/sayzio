<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile / REST API parity for the banned/reserved-alias block on the *edit*
 * (update) Link flow, for a NON-privileged user.
 *
 * Task #2870 closed the create gap (Api\LinkController::store runs the
 * NotBannedName rule). The update path had the same hole: it validated the
 * alias for format + uniqueness only and did NOT run NotBannedName, so a
 * non-privileged user could rename an existing link to a reserved/banned
 * handle via PATCH /api/v1/links/{id}, bypassing the create-time block.
 *
 * This test pins the fix: a non-privileged user gets 422 (alias unchanged),
 * and a privileged `user.banned_names.bypass` holder can still rename to the
 * reserved alias — proving the rejection is specifically the banned-list rule.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware (every authed request would 500), so
 * we mint a real token and send it via withToken().
 */
class MobileUpdateLinkBannedAliasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A banned alias that is alpha_dash and within the default plan length
     * limits, so the update() validator reaches the banned-list branch rather
     * than failing format/length first.
     */
    private const BANNED_ALIAS = 'reservedhandle';

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function seedBannedAlias(string $name = self::BANNED_ALIAS): void
    {
        BannedName::firstOrCreate(['name' => $name]);
        BannedNameChecker::flush($name);
    }

    private function makeLink(User $user, string $alias): Link
    {
        return Link::create([
            'user_id'  => $user->id,
            'type'     => 'short',
            'alias'    => $alias,
            'long_url' => 'https://example.com',
            'is_active' => true,
        ]);
    }

    public function test_update_rejects_renaming_to_banned_alias_for_non_privileged_user(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();
        $original = 'okhandle' . Str::lower(Str::random(6));
        $link = $this->makeLink($user, $original);

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'alias' => self::BANNED_ALIAS,
            ]);

        // The NotBannedName rule fails validation → 422 with an alias error in
        // the unified envelope, and the alias is left unchanged.
        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertSame(
            $original,
            $link->fresh()->alias,
            'a banned alias must not rename a link via the mobile update path'
        );
    }

    public function test_update_accepts_renaming_to_a_fresh_alias(): void
    {
        // Sanity contrast: a valid, unbanned, unused alias updates successfully
        // — proving the rejection above is specifically the banned-list block,
        // not a blanket failure of the update path.
        $this->seedBannedAlias();
        $user = $this->makeUser();
        $link = $this->makeLink($user, 'okhandle' . Str::lower(Str::random(6)));
        $fresh = 'newhandle' . Str::lower(Str::random(6));

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'alias' => $fresh,
            ]);

        $resp->assertStatus(200);
        $this->assertSame($fresh, $link->fresh()->alias);
    }

    public function test_privileged_user_can_rename_to_a_banned_alias(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();
        $original = 'okhandle' . Str::lower(Str::random(6));
        $link = $this->makeLink($user, $original);

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

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'alias' => self::BANNED_ALIAS,
            ]);

        $resp->assertStatus(200);
        $this->assertSame(self::BANNED_ALIAS, $link->fresh()->alias);
    }
}
