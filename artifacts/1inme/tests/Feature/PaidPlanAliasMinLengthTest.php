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
 * Companion to AliasMinLengthTest, which pins the FREE / unconfigured floor
 * (4 chars). This locks in that a *paid* plan's `min_alias_length` override is
 * actually honored — resolved through User::getAliasLengthLimits()
 * (getPlanFeature('min_alias_length', 4)) — on every alias-accepting surface,
 * not just defaulted back to the free floor.
 *
 * The primary case uses a STRICTER paid tier (floor 6): a 5-char alias that is
 * perfectly valid for a free user (≥ 4) must now be rejected, while a 6-char
 * alias at the configured boundary is accepted. This is the regression that
 * matters — a stricter paid tier silently accepting too-short aliases. A LOOSER
 * tier (floor 2) is also checked so the override is proven to step the floor
 * *down*, not only up.
 *
 * Surfaces covered (same set as the free test):
 *   - the live availability checker (AliasAvailability::check),
 *   - the web Create Link step (User\LinkController::chooseType),
 *   - the REST create paths (Api\LinkController store / storeSmart / storeAb)
 *     and the REST update path (Api\LinkController::update),
 *   - the web edit-alias paths (User\LinkController::updateAlias for the
 *     primary alias and LinkAliasController::store for extra aliases).
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware (every authed request would 500), so
 * we mint a real token and send it via withToken().
 */
class PaidPlanAliasMinLengthTest extends TestCase
{
    use RefreshDatabase;

    /** The stricter paid-tier floor under test (higher than the free 4). */
    private const MIN = 6;

    /**
     * alpha_dash, unbanned, unreserved alias one char below the paid floor.
     * Deliberately 5 chars — VALID for a free user (≥ 4) but too short here,
     * so the assertion can only pass if the per-plan override is honored.
     */
    private const TOO_SHORT = 'zqxwv';   // 5 chars

    /** alpha_dash, unbanned, unreserved alias exactly at the paid floor. */
    private const AT_MIN = 'zqxwvk';     // 6 chars

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
     * A user on a plan carrying the given feature flags. Callers merge in a
     * `min_alias_length` override (plus any gating features needed to reach a
     * plan-locked surface).
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

    /** A user on a stricter tier whose alias floor is self::MIN (6). */
    private function makeStrictUser(array $extraFeatures = []): User
    {
        return $this->makeUserOnPlan(array_merge(
            ['min_alias_length' => self::MIN],
            $extraFeatures,
        ));
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

    public function test_paid_plan_min_alias_length_override_is_resolved(): void
    {
        $user = $this->makeStrictUser();
        $this->assertSame(
            self::MIN,
            $user->getAliasLengthLimits()['min'],
            'a plan min_alias_length override must beat the free default of 4'
        );
    }

    // ── Live availability checker ──────────────────────────────────────

    public function test_alias_availability_honors_the_paid_minimum(): void
    {
        $user = $this->makeStrictUser();

        $tooShort = AliasAvailability::check($user, self::TOO_SHORT);
        $this->assertSame('too_short', $tooShort['status']);
        $this->assertFalse($tooShort['available']);
        $this->assertStringContainsString(
            'use at least ' . self::MIN . ' characters',
            $tooShort['message'],
            'the live checker must surface the configured per-plan minimum'
        );

        $atMin = AliasAvailability::check($user, self::AT_MIN);
        $this->assertSame('available', $atMin['status']);
        $this->assertTrue($atMin['available']);
    }

    public function test_web_check_alias_endpoint_honors_the_paid_minimum(): void
    {
        $user = $this->makeStrictUser();

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

    public function test_web_choose_type_honors_the_paid_minimum(): void
    {
        $user = $this->makeStrictUser();

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

    public function test_api_store_honors_the_paid_minimum(): void
    {
        $user  = $this->makeStrictUser();
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

    public function test_api_store_smart_honors_the_paid_minimum(): void
    {
        $user  = $this->makeStrictUser(['link_smart_rules' => true]);
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

    public function test_api_store_ab_honors_the_paid_minimum(): void
    {
        $user  = $this->makeStrictUser(['ab_tests' => true]);
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

    public function test_api_update_honors_the_paid_minimum(): void
    {
        $user  = $this->makeStrictUser();
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

    public function test_web_update_alias_honors_the_paid_minimum(): void
    {
        $user = $this->makeStrictUser();
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

    public function test_web_extra_alias_store_honors_the_paid_minimum(): void
    {
        $user = $this->makeStrictUser(['max_aliases_per_link' => 3]);
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

    // ── Looser tier: the floor must step DOWN, too ─────────────────────

    public function test_looser_paid_plan_lowers_the_floor(): void
    {
        // A tier that configures a 2-char floor — below the free default of 4.
        $user = $this->makeUserOnPlan(['min_alias_length' => 2]);
        $this->assertSame(2, $user->getAliasLengthLimits()['min']);

        // 1 char is still too short.
        $tooShort = AliasAvailability::check($user, 'z');
        $this->assertSame('too_short', $tooShort['status']);
        $this->assertFalse($tooShort['available']);

        // A 2-char alias — rejected for a free user (< 4) — is now available.
        $atMin = AliasAvailability::check($user, 'zq');
        $this->assertSame('available', $atMin['status']);
        $this->assertTrue($atMin['available']);
    }
}
