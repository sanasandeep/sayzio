<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression guard for the per-plan alias LENGTH cap on the two surfaces that
 * used to hardcode a 60-char ceiling regardless of plan:
 *   - editing the primary alias  → LinkController::updateAlias
 *     (PUT user/links/{link}/alias, route user.links.update-alias)
 *   - adding an extra alias       → LinkAliasController::store
 *     (POST user/links/{link}/aliases, route user.links.aliases.store)
 *
 * Both now resolve their min/max through the owner's plan via
 * User::getAliasLengthLimits(). These tests pin that an alias LONGER than the
 * plan's max is rejected, one exactly AT the cap is accepted, and one SHORTER
 * than the plan's min is rejected — on each path.
 */
class AliasLengthLimitEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** Tight, non-default window so 60 is clearly out of range and the cap bites. */
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
                'min_alias_length'     => self::MIN,
                'max_alias_length'     => self::MAX,
                // Allow extra aliases so LinkAliasController::store reaches the
                // length validation rather than short-circuiting on the
                // per-link alias-count gate.
                'max_aliases_per_link' => 5,
            ], $features),
        ]);
    }

    private function user(Plan $plan): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u->fresh();
    }

    private function link(User $u): Link
    {
        return $u->links()->create([
            'user_id'   => $u->id,
            'type'      => 'url',
            'alias'     => 'a' . Str::lower(Str::random(7)),
            'is_active' => true,
        ]);
    }

    /** A format-valid (alpha) alias of exactly $len characters. */
    private function aliasOfLength(int $len): string
    {
        return Str::lower(Str::random($len));
    }

    // ---- Primary alias edit: LinkController::updateAlias ----------------

    public function test_update_alias_rejects_alias_longer_than_plan_max(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $tooLong = $this->aliasOfLength(self::MAX + 1); // 11 chars, well under the old 60
        $this->assertSame(self::MAX + 1, strlen($tooLong));

        $resp = $this->actingAs($user)
            ->putJson(route('user.links.update-alias', $link), ['alias' => $tooLong]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('alias');
        $this->assertSame(
            $link->alias,
            $link->fresh()->alias,
            'Primary alias must be unchanged when the new value exceeds the plan max'
        );
    }

    public function test_update_alias_accepts_alias_at_plan_max(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $atCap = $this->aliasOfLength(self::MAX); // exactly the cap
        $this->assertSame(self::MAX, strlen($atCap));

        $resp = $this->actingAs($user)
            ->putJson(route('user.links.update-alias', $link), ['alias' => $atCap]);

        $resp->assertOk();
        $resp->assertJson(['success' => true, 'alias' => $atCap]);
        $this->assertSame($atCap, $link->fresh()->alias);
    }

    public function test_update_alias_rejects_alias_shorter_than_plan_min(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $tooShort = $this->aliasOfLength(self::MIN - 1); // below the floor
        $this->assertSame(self::MIN - 1, strlen($tooShort));

        $resp = $this->actingAs($user)
            ->putJson(route('user.links.update-alias', $link), ['alias' => $tooShort]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('alias');
        $this->assertSame($link->alias, $link->fresh()->alias);
    }

    // ---- Extra alias add: LinkAliasController::store --------------------

    public function test_store_extra_alias_rejects_alias_longer_than_plan_max(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $tooLong = $this->aliasOfLength(self::MAX + 1);

        $resp = $this->actingAs($user)
            ->postJson(route('user.links.aliases.store', $link), ['alias' => $tooLong]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('alias');
        $this->assertDatabaseMissing('link_aliases', ['alias' => $tooLong]);
    }

    public function test_store_extra_alias_accepts_alias_at_plan_max(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $atCap = $this->aliasOfLength(self::MAX);

        $resp = $this->actingAs($user)
            ->postJson(route('user.links.aliases.store', $link), ['alias' => $atCap]);

        $resp->assertOk();
        $resp->assertJson(['success' => true]);
        $this->assertDatabaseHas('link_aliases', [
            'link_id' => $link->id,
            'alias'   => $atCap,
        ]);
    }

    public function test_store_extra_alias_rejects_alias_shorter_than_plan_min(): void
    {
        $user = $this->user($this->plan());
        $link = $this->link($user);

        $tooShort = $this->aliasOfLength(self::MIN - 1);

        $resp = $this->actingAs($user)
            ->postJson(route('user.links.aliases.store', $link), ['alias' => $tooShort]);

        $resp->assertStatus(422);
        $resp->assertJsonValidationErrors('alias');
        $this->assertDatabaseMissing('link_aliases', ['alias' => $tooShort]);
    }
}
