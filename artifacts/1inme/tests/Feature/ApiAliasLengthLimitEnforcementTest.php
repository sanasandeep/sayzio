<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile / REST API parity for the per-plan alias LENGTH window.
 *
 * The web surfaces (LinkController::updateAlias + LinkAliasController::store)
 * resolve their min/max through the owner's plan via
 * User::getAliasLengthLimits() and are covered by
 * AliasLengthLimitEnforcementTest. The REST/mobile alias surfaces live in a
 * separate module (Api\LinkController::store + ::update) and historically
 * hardcoded a 80-char ceiling regardless of plan, so the API could silently
 * accept an alias the web UI rejects.
 *
 * Both API paths now resolve max:/min: through getAliasLengthLimits() like the
 * web controllers. These tests pin that an alias LONGER than the plan's max is
 * rejected (422), one exactly AT the cap is accepted, and one SHORTER than the
 * plan's min is rejected — on both the create and update paths.
 *
 * Sanctum API tests authenticate with a real Bearer token — Sanctum::actingAs
 * breaks the TouchSessionToken middleware (every authed request would 500), so
 * we mint a real token and send it via withToken().
 */
class ApiAliasLengthLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** Tight, non-default window so the old 80 ceiling is clearly out of range. */
    private const MIN = 5;
    private const MAX = 10;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name'          => $slug,
            'slug'          => $slug,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => array_merge([
                'min_alias_length' => self::MIN,
                'max_alias_length' => self::MAX,
            ], $features),
        ]);
    }

    private function user(Plan $plan): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $u->ensureDefaultWorkspace();
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function link(User $u): Link
    {
        return Link::create([
            'user_id'   => $u->id,
            'type'      => 'short',
            'alias'     => 'a' . Str::lower(Str::random(7)),
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);
    }

    /** A format-valid (alpha) alias of exactly $len characters. */
    private function aliasOfLength(int $len): string
    {
        return Str::lower(Str::random($len));
    }

    // ---- API create: Api\LinkController::store -------------------------

    public function test_store_rejects_alias_longer_than_plan_max(): void
    {
        $user = $this->user($this->plan());

        $tooLong = $this->aliasOfLength(self::MAX + 1); // 11 chars, well under the old 80
        $this->assertSame(self::MAX + 1, strlen($tooLong));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'short',
                'alias'    => $tooLong,
                'long_url' => 'https://example.com',
            ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertFalse(
            Link::where('alias', $tooLong)->exists(),
            'an over-length alias must not create a link via the mobile store path'
        );
    }

    public function test_store_accepts_alias_at_plan_max(): void
    {
        $user = $this->user($this->plan());

        $atCap = $this->aliasOfLength(self::MAX); // exactly the cap
        $this->assertSame(self::MAX, strlen($atCap));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'short',
                'alias'    => $atCap,
                'long_url' => 'https://example.com',
            ]);

        $resp->assertStatus(201);
        $this->assertTrue(Link::where('alias', $atCap)->exists());
    }

    public function test_store_rejects_alias_shorter_than_plan_min(): void
    {
        $user = $this->user($this->plan());

        $tooShort = $this->aliasOfLength(self::MIN - 1); // below the floor
        $this->assertSame(self::MIN - 1, strlen($tooShort));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/links', [
                'type'     => 'short',
                'alias'    => $tooShort,
                'long_url' => 'https://example.com',
            ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertFalse(Link::where('alias', $tooShort)->exists());
    }

    // ---- API update: Api\LinkController::update ------------------------

    public function test_update_rejects_alias_longer_than_plan_max(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $tooLong = $this->aliasOfLength(self::MAX + 1);

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'alias' => $tooLong,
            ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertSame(
            $link->alias,
            $link->fresh()->alias,
            'an over-length alias must not rename a link via the mobile update path'
        );
    }

    public function test_update_accepts_alias_at_plan_max(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $atCap = $this->aliasOfLength(self::MAX);

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'alias' => $atCap,
            ]);

        $resp->assertStatus(200);
        $this->assertSame($atCap, $link->fresh()->alias);
    }

    public function test_update_rejects_alias_shorter_than_plan_min(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $tooShort = $this->aliasOfLength(self::MIN - 1);

        $resp = $this->withToken($this->token($user))
            ->patchJson('/api/v1/links/' . $link->id, [
                'alias' => $tooShort,
            ]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'validation_failed');
        $resp->assertJsonStructure(['error' => ['details' => ['alias']]]);
        $this->assertSame($link->alias, $link->fresh()->alias);
    }
}
