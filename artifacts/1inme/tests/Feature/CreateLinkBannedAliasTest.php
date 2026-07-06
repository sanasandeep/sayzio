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
 * HTTP coverage for the banned/reserved-alias block on the Create Link flow,
 * for a NON-privileged user.
 *
 * The browser e2e spec (tests/Browser/create-link-picker.spec.ts) logs in as
 * the shared demo account, which holds `user.banned_names.bypass` — so for
 * that privileged user the live checker correctly reports a banned name as
 * *available* and the banned path is unverifiable there (see
 * .agents/memory/e2e-demo-user-banned-bypass.md). This feature test closes
 * that gap by driving both the live availability probe
 * (`user.links.check-alias`, backed by AliasAvailability) and the actual
 * Step 1 → Step 2 submit (`user.links.choose-type`, which runs the
 * NotBannedName rule at submit) as a plain user with no bypass permission.
 *
 * A privileged-user contrast then pins the bypass branch: the same banned
 * alias must read as available and be accepted for a user holding the
 * `user.banned_names.bypass` permission (via the seeded `user-admin` role),
 * so the block is proven to be the banned-list rule and not some unrelated
 * rejection.
 */
class CreateLinkBannedAliasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A banned alias that is alpha_dash and within the default plan length
     * limits (min 3 / max 50), so the live checker and the submit validator
     * reach the banned-list branch rather than failing format/length first.
     */
    private const BANNED_ALIAS = 'reservedhandle';

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    private function seedBannedAlias(string $name = self::BANNED_ALIAS): void
    {
        BannedName::firstOrCreate(['name' => $name]);
        // Drop the 5-minute cached lookup so the live checker sees the row
        // immediately within this test.
        BannedNameChecker::flush($name);
    }

    public function test_check_alias_reports_banned_for_non_privileged_user(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();

        $resp = $this->actingAs($user)
            ->getJson('/user/links/check-alias?alias=' . self::BANNED_ALIAS);

        $resp->assertOk();
        $resp->assertJson([
            'status'    => 'banned',
            'available' => false,
        ]);
    }

    public function test_choose_type_rejects_banned_alias_for_non_privileged_user(): void
    {
        $this->seedBannedAlias();
        $user = $this->makeUser();

        $resp = $this->actingAs($user)->post('/user/links/choose-type', [
            'type'  => 'url',
            'alias' => self::BANNED_ALIAS,
        ]);

        // The NotBannedName rule fails validation → the request redirects back
        // with an error on `alias` rather than forwarding to a Step 2 form.
        // (choose-type is only a routing/validation step — it never creates a
        // link itself — so the meaningful proof of the block is the alias error
        // plus *not* being forwarded to the create form.)
        $resp->assertSessionHasErrors('alias');
        $this->assertNotNull($resp->headers->get('Location'));
        $this->assertStringNotContainsString('links-url/create', (string) $resp->headers->get('Location'));
    }

    public function test_check_alias_and_choose_type_accept_a_fresh_alias(): void
    {
        // Sanity contrast: a valid, unbanned, unused alias is reported
        // available and forwarded to Step 2 — proving the rejection above is
        // specifically the banned-list block, not a blanket failure.
        $this->seedBannedAlias();
        $user  = $this->makeUser();
        $fresh = 'okhandle' . Str::lower(Str::random(6));

        $check = $this->actingAs($user)
            ->getJson('/user/links/check-alias?alias=' . $fresh);
        $check->assertOk();
        $check->assertJson(['status' => 'available', 'available' => true]);

        $resp = $this->actingAs($user)->post('/user/links/choose-type', [
            'type'  => 'url',
            'alias' => $fresh,
        ]);
        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();
        $this->assertStringContainsString('alias=' . $fresh, $resp->headers->get('Location'));
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

        // Live checker reports the banned alias as available for this user.
        $check = $this->actingAs($user)
            ->getJson('/user/links/check-alias?alias=' . self::BANNED_ALIAS);
        $check->assertOk();
        $check->assertJson(['status' => 'available', 'available' => true]);

        // And the submit is accepted (no banned-name validation error).
        $resp = $this->actingAs($user)->post('/user/links/choose-type', [
            'type'  => 'url',
            'alias' => self::BANNED_ALIAS,
        ]);
        $resp->assertSessionHasNoErrors();
        $resp->assertRedirect();
    }
}
