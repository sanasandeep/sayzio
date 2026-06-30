<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\AliasAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks in the per-plan custom-URL minimum length so a free / unconfigured
 * user can never mint a too-short alias (and a valid one at the boundary is
 * still accepted).
 *
 * The minimum is resolved through User::getAliasLengthLimits() — for a
 * free/unconfigured user (no plan) it defaults to 4 — and is wired through
 * every alias-accepting surface. This test pins the floor on:
 *   - the live availability checker (AliasAvailability::check, backing both
 *     the web `links.check-alias` probe and the REST mobile indicator),
 *   - the web Create Link step (User\LinkController::chooseType),
 *   - the REST create paths (Api\LinkController store / storeSmart / storeAb)
 *     and the REST update path (Api\LinkController::update),
 *   - the web edit-alias paths (User\LinkController::updateAlias for the
 *     primary alias and LinkAliasController::store for extra aliases).
 *
 * A regression here would silently let free users save 1–3 char aliases (or
 * break valid ones) and only surface in production.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware (every authed request would 500), so
 * we mint a real token and send it via withToken().
 */
class AliasMinLengthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The minimum a free/unconfigured user lands on (the largest floor —
     * paid tiers step down). Mirrors the default in getAliasLengthLimits().
     */
    private const MIN = 4;

    /** alpha_dash, unbanned, unreserved alias one char below the floor. */
    private const TOO_SHORT = 'zqx';   // 3 chars

    /** alpha_dash, unbanned, unreserved alias exactly at the floor. */
    private const AT_MIN = 'zqxw';     // 4 chars

    private function makeUser(array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name'     => 'U ' . Str::random(4),
            'email'    => 'u' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ], $attrs));
        // Owns a personal workspace so `workspace.can:links.*` passes (owner
        // status implicitly grants every workspace permission).
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    /**
     * A user on a plan with the given feature flags but NO min_alias_length
     * override — so the alias floor stays at the free default (4) while the
     * gated REST surfaces (smart links, A/B, extra aliases) become reachable.
     */
    private function makeUserOnPlan(array $features): User
    {
        $plan = Plan::create([
            'name'          => 'Test',
            'slug'          => 'test-' . Str::lower(Str::random(6)),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'grace_days'    => 0,
            'refund_window_days' => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => $features,
        ]);
        return $this->makeUser(['plan_id' => $plan->id]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function makeLink(User $user, string $alias, string $type = 'short'): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'type'      => $type,
            'alias'     => $alias,
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);
    }

    // ── The floor itself ───────────────────────────────────────────────

    public function test_free_user_min_alias_length_is_four(): void
    {
        $user = $this->makeUser();
        $this->assertSame(self::MIN, $user->getAliasLengthLimits()['min']);
    }

    // ── Live availability checker ──────────────────────────────────────

    public function test_alias_availability_reports_the_same_minimum(): void
    {
        $user = $this->makeUser();

        $tooShort = AliasAvailability::check($user, self::TOO_SHORT);
        $this->assertSame('too_short', $tooShort['status']);
        $this->assertFalse($tooShort['available']);
        $this->assertStringContainsString(
            'use at least ' . self::MIN . ' characters',
            $tooShort['message'],
            'the live checker must surface the exact per-plan minimum'
        );

        // One char longer is no longer rejected for length.
        $atMin = AliasAvailability::check($user, self::AT_MIN);
        $this->assertSame('available', $atMin['status']);
        $this->assertTrue($atMin['available']);
    }

    public function test_web_check_alias_endpoint_reports_too_short(): void
    {
        $user = $this->makeUser();

        $short = $this->actingAs($user)
            ->getJson('/user/links/check-alias?alias=' . self::TOO_SHORT);
        $short->assertOk();
        $short->assertJson(['status' => 'too_short', 'available' => false]);
        $this->assertStringContainsString(
            'use at least ' . self::MIN . ' characters',
            $short->json('message')
        );

        $ok = $this->actingAs($user)
            ->getJson('/user/links/check-alias?alias=' . self::AT_MIN);
        $ok->assertOk();
        $ok->assertJson(['status' => 'available', 'available' => true]);
    }

    // ── Web Create Link step (chooseType) ──────────────────────────────

    public function test_web_choose_type_rejects_too_short_and_accepts_min(): void
    {
        $user = $this->makeUser();

        $rejected = $this->actingAs($user)->post('/user/links/choose-type', [
            'type'  => 'url',
            'alias' => self::TOO_SHORT,
        ]);
        $rejected->assertSessionHasErrors('alias');

        $accepted = $this->actingAs($user)->post('/user/links/choose-type', [
            'type'  => 'url',
            'alias' => self::AT_MIN,
        ]);
        $accepted->assertSessionHasNoErrors();
        $accepted->assertRedirect();
        $this->assertStringContainsString('alias=' . self::AT_MIN, $accepted->headers->get('Location'));
    }

    // ── REST create: store ─────────────────────────────────────────────

    public function test_api_store_enforces_minimum(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);

        $rejected = $this->withToken($token)->postJson('/api/v1/links', [
            'type'     => 'short',
            'alias'    => self::TOO_SHORT,
            'long_url' => 'https://example.com',
        ]);
        $rejected->assertStatus(422);
        $rejected->assertJsonPath('error.code', 'validation_failed');
        $rejected->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertFalse(Link::where('alias', self::TOO_SHORT)->exists());

        $accepted = $this->withToken($token)->postJson('/api/v1/links', [
            'type'     => 'short',
            'alias'    => self::AT_MIN,
            'long_url' => 'https://example.com',
        ]);
        $accepted->assertStatus(201);
        $this->assertTrue(Link::where('alias', self::AT_MIN)->exists());
    }

    // ── REST create: storeSmart ────────────────────────────────────────

    public function test_api_store_smart_enforces_minimum(): void
    {
        // Smart links are plan-gated; grant the capability while keeping the
        // alias floor at the free default (no min_alias_length override).
        $user  = $this->makeUserOnPlan(['link_smart_rules' => true]);
        $token = $this->token($user);

        $rule = [['type' => 'device', 'match' => ['mobile'], 'url' => 'https://m.example.com']];

        $rejected = $this->withToken($token)->postJson('/api/v1/links/smart', [
            'long_url' => 'https://example.com',
            'alias'    => self::TOO_SHORT,
            'rules'    => $rule,
        ]);
        $rejected->assertStatus(422);
        $rejected->assertJsonPath('error.code', 'validation_failed');
        $rejected->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertFalse(Link::where('alias', self::TOO_SHORT)->exists());

        $accepted = $this->withToken($token)->postJson('/api/v1/links/smart', [
            'long_url' => 'https://example.com',
            'alias'    => self::AT_MIN,
            'rules'    => $rule,
        ]);
        $accepted->assertStatus(200);
        $this->assertTrue(Link::where('alias', self::AT_MIN)->exists());
    }

    // ── REST create: storeAb ───────────────────────────────────────────

    public function test_api_store_ab_enforces_minimum(): void
    {
        // A/B testing is plan-gated; grant it while keeping the free floor.
        $user  = $this->makeUserOnPlan(['ab_tests' => true]);
        $token = $this->token($user);

        $variants = [
            ['url' => 'https://a.example.com', 'weight' => 50],
            ['url' => 'https://b.example.com', 'weight' => 50],
        ];

        $rejected = $this->withToken($token)->postJson('/api/v1/links/ab', [
            'alias'    => self::TOO_SHORT,
            'variants' => $variants,
        ]);
        $rejected->assertStatus(422);
        $rejected->assertJsonPath('error.code', 'validation_failed');
        $rejected->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertFalse(Link::where('alias', self::TOO_SHORT)->exists());

        $accepted = $this->withToken($token)->postJson('/api/v1/links/ab', [
            'alias'    => self::AT_MIN,
            'variants' => $variants,
        ]);
        $accepted->assertStatus(201);
        $this->assertTrue(Link::where('alias', self::AT_MIN)->exists());
    }

    // ── REST update ────────────────────────────────────────────────────

    public function test_api_update_enforces_minimum(): void
    {
        $user  = $this->makeUser();
        $token = $this->token($user);
        $original = 'okhandle' . Str::lower(Str::random(6));
        $link = $this->makeLink($user, $original);

        $rejected = $this->withToken($token)
            ->patchJson('/api/v1/links/' . $link->id, ['alias' => self::TOO_SHORT]);
        $rejected->assertStatus(422);
        $rejected->assertJsonPath('error.code', 'validation_failed');
        $rejected->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertSame($original, $link->fresh()->alias);

        $accepted = $this->withToken($token)
            ->patchJson('/api/v1/links/' . $link->id, ['alias' => self::AT_MIN]);
        $accepted->assertStatus(200);
        $this->assertSame(self::AT_MIN, $link->fresh()->alias);
    }

    // ── Web edit primary alias (updateAlias) ───────────────────────────

    public function test_web_update_alias_enforces_minimum(): void
    {
        $user = $this->makeUser();
        $original = 'okhandle' . Str::lower(Str::random(6));
        $link = $this->makeLink($user, $original);

        $rejected = $this->actingAs($user)
            ->putJson('/user/links/' . $link->id . '/alias', ['alias' => self::TOO_SHORT]);
        $rejected->assertStatus(422);
        $rejected->assertJsonValidationErrors('alias');
        $this->assertSame($original, $link->fresh()->alias);

        $accepted = $this->actingAs($user)
            ->putJson('/user/links/' . $link->id . '/alias', ['alias' => self::AT_MIN]);
        $accepted->assertStatus(200);
        $this->assertSame(self::AT_MIN, $link->fresh()->alias);
    }

    // ── Web edit extra alias (LinkAliasController::store) ───────────────

    public function test_web_extra_alias_store_enforces_minimum(): void
    {
        // Extra aliases are plan-gated by max_aliases_per_link; grant a couple
        // while keeping the free alias floor (no min_alias_length override).
        $user = $this->makeUserOnPlan(['max_aliases_per_link' => 3]);
        $link = $this->makeLink($user, 'okhandle' . Str::lower(Str::random(6)));

        $rejected = $this->actingAs($user)
            ->postJson('/user/links/' . $link->id . '/aliases', ['alias' => self::TOO_SHORT]);
        $rejected->assertStatus(422);
        $rejected->assertJsonValidationErrors('alias');
        $this->assertFalse(
            $link->aliases()->where('alias', self::TOO_SHORT)->exists()
        );

        $accepted = $this->actingAs($user)
            ->postJson('/user/links/' . $link->id . '/aliases', ['alias' => self::AT_MIN]);
        $accepted->assertOk();
        $this->assertTrue(
            $link->aliases()->where('alias', self::AT_MIN)->exists()
        );
    }
}
