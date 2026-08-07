<?php

namespace Tests\Feature;

use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\MarketingPlanDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6766 — plan-gating for the Marketing Plan Calculator:
 * `marketing_plan_calculator` bool toggle + `max_marketing_plans` saved-plan
 * cap (-1 = unlimited). Gated-off plans get an upgrade prompt on GETs and
 * 403s on writes; capped users keep view/edit/delete on existing plans but
 * cannot create new ones.
 */
class MarketingPlanCalculatorGatingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => 'Plan ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(Plan $plan): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ]);
    }

    /**
     * Save a plan through the real store endpoint so it lands in the same
     * workspace scope the controller reads back (a raw insert with
     * workspace_id NULL would not match the request-bound active workspace).
     */
    private function savedPlan(User $user, string $name = 'Saved'): MarketingPlanCalc
    {
        $resp = $this->actingAs($user, 'web')->postJson(route('user.marketing-plan.store'), [
            'name' => $name, 'payload' => MarketingPlanDefaults::defaults($user),
        ]);
        $resp->assertStatus(200);

        return MarketingPlanCalc::findOrFail((int) $resp->json('id'));
    }

    public function test_gated_off_plan_sees_upgrade_prompt_and_writes_403(): void
    {
        $user = $this->user($this->plan(['marketing_plan_calculator' => false, 'max_marketing_plans' => 0]));

        // Raw insert (store would 403): simulates a plan saved before a
        // downgrade to a tier without the calculator. The gate fires before
        // any ownership lookup, so workspace scope is irrelevant here.
        $existing = MarketingPlanCalc::create([
            'user_id'      => $user->id,
            'workspace_id' => null,
            'name'         => 'Pre-downgrade plan',
            'payload'      => MarketingPlanDefaults::defaults($user),
        ]);

        // GET surfaces render the upgrade prompt instead of the tool.
        foreach ([
            route('user.marketing-plan.index'),
            route('user.marketing-plan.create'),
            route('user.marketing-plan.edit', $existing->id),
        ] as $url) {
            $this->actingAs($user, 'web')->get($url)
                ->assertStatus(200)
                ->assertSee('locked on your current plan')
                ->assertDontSee('New plan');
        }

        // Writes are hard-blocked server-side.
        $payload = MarketingPlanDefaults::defaults($user);
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'X', 'payload' => $payload])
            ->assertStatus(403);
        $this->actingAs($user, 'web')
            ->putJson(route('user.marketing-plan.update', $existing->id), ['name' => 'X', 'payload' => $payload])
            ->assertStatus(403);
        $this->actingAs($user, 'web')
            ->delete(route('user.marketing-plan.destroy', $existing->id))
            ->assertStatus(403);
        $this->assertDatabaseHas('marketing_plan_calcs', ['id' => $existing->id]);
    }

    public function test_gated_on_plan_gets_full_access(): void
    {
        $user = $this->user($this->plan(['marketing_plan_calculator' => true, 'max_marketing_plans' => -1]));

        $this->actingAs($user, 'web')->get(route('user.marketing-plan.index'))
            ->assertStatus(200)->assertSee('New plan');
        $this->actingAs($user, 'web')->get(route('user.marketing-plan.create'))->assertStatus(200);

        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), [
                'name' => 'Mine', 'payload' => MarketingPlanDefaults::defaults($user),
            ])
            ->assertStatus(200)->assertJson(['ok' => true]);
    }

    public function test_cap_boundary_blocks_create_but_not_existing_plans(): void
    {
        $user = $this->user($this->plan(['marketing_plan_calculator' => true, 'max_marketing_plans' => 2]));
        $payload = MarketingPlanDefaults::defaults($user);

        // Under the cap: creates succeed up to the limit.
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'One', 'payload' => $payload])
            ->assertStatus(200);
        $second = $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Two', 'payload' => $payload]);
        $second->assertStatus(200);

        // At the cap: store 403s with a clear limit message; create redirects
        // back to the index with the limit flag.
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Three', 'payload' => $payload])
            ->assertStatus(403)
            ->assertJsonFragment(['ok' => false]);
        $this->assertSame(2, MarketingPlanCalc::where('user_id', $user->id)->count());

        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create'))
            ->assertRedirect(route('user.marketing-plan.index'))
            ->assertSessionHas('limit_reached');

        // Index shows the limit-reached upsell instead of the create action.
        $this->actingAs($user, 'web')->get(route('user.marketing-plan.index'))
            ->assertStatus(200)
            ->assertSee('saved-plan limit')
            ->assertDontSee('New plan');

        // Existing plans remain fully usable at the cap.
        $id = (int) $second->json('id');
        $this->actingAs($user, 'web')->get(route('user.marketing-plan.edit', $id))->assertStatus(200);
        $this->actingAs($user, 'web')
            ->putJson(route('user.marketing-plan.update', $id), ['name' => 'Two v2', 'payload' => $payload])
            ->assertStatus(200);
        $this->actingAs($user, 'web')
            ->delete(route('user.marketing-plan.destroy', $id))
            ->assertRedirect(route('user.marketing-plan.index'));
        $this->assertDatabaseMissing('marketing_plan_calcs', ['id' => $id]);
    }

    /**
     * The cap's count-and-create critical section is serialised by a
     * per-owner Postgres advisory lock: a second protocol-following writer
     * cannot interleave between the first request's cap check and insert.
     * Simulated by attempting the same advisory lock from a SECOND raw
     * connection while store() is mid-create (the Eloquent `creating` hook
     * fires between the in-lock recount and the INSERT).
     */
    public function test_concurrent_create_cannot_exceed_a_finite_cap(): void
    {
        $user = $this->user($this->plan(['marketing_plan_calculator' => true, 'max_marketing_plans' => 1]));

        $competitorBlocked = null;
        MarketingPlanCalc::creating(function () use ($user, &$competitorBlocked) {
            if ($competitorBlocked !== null) {
                return; // only probe on the first insert
            }
            $cfg = config('database.connections.' . config('database.default'));
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $cfg['host'], $cfg['port'], $cfg['database'],
            );
            $pdo = new \PDO($dsn, $cfg['username'], $cfg['password'] ?? '');
            $pdo->exec("SET lock_timeout = '250ms'");
            try {
                // Same lock store() takes before counting: must NOT be
                // acquirable while the first request is mid-create.
                $stmt = $pdo->prepare('select pg_advisory_xact_lock(?, ?)');
                $pdo->beginTransaction();
                $stmt->execute([\App\Modules\User\Controllers\MarketingPlanCalculatorController::CAP_LOCK_CLASS, $user->id]);
                $pdo->rollBack();
                $competitorBlocked = false;
            } catch (\PDOException $e) {
                $competitorBlocked = str_contains($e->getMessage(), 'lock timeout')
                    || str_contains($e->getMessage(), 'lock_timeout')
                    || (string) $e->getCode() === '55P03';
            }
        });

        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), [
                'name' => 'Only slot', 'payload' => MarketingPlanDefaults::defaults($user),
            ])
            ->assertStatus(200);

        $this->assertTrue(
            $competitorBlocked,
            'A concurrent writer acquired the cap advisory lock while store() was mid-create — the cap check is not serialised.',
        );

        // And once the slot is used, further creates are refused.
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), [
                'name' => 'Over cap', 'payload' => MarketingPlanDefaults::defaults($user),
            ])
            ->assertStatus(403);
        $this->assertSame(1, MarketingPlanCalc::where('user_id', $user->id)->count());
    }

    public function test_downgrade_below_cap_keeps_existing_plans_accessible(): void
    {
        $big   = $this->plan(['marketing_plan_calculator' => true, 'max_marketing_plans' => -1]);
        $small = $this->plan(['marketing_plan_calculator' => true, 'max_marketing_plans' => 1]);
        $user  = $this->user($big);

        $a = $this->savedPlan($user, 'Alpha Q3 Plan');
        $b = $this->savedPlan($user, 'Beta Q3 Plan');
        $c = $this->savedPlan($user, 'Gamma Q3 Plan');

        // Downgrade to a plan whose cap (1) is below the existing count (3).
        $user->update(['plan_id' => $small->id]);
        $user = $user->fresh();

        // All three stay listed, viewable, editable and deletable.
        $index = $this->actingAs($user, 'web')->get(route('user.marketing-plan.index'));
        $index->assertStatus(200)->assertSee('Alpha Q3 Plan')->assertSee('Beta Q3 Plan')->assertSee('Gamma Q3 Plan');

        $this->actingAs($user, 'web')->get(route('user.marketing-plan.edit', $b->id))->assertStatus(200);
        $this->actingAs($user, 'web')
            ->putJson(route('user.marketing-plan.update', $a->id), [
                'name' => 'A v2', 'payload' => MarketingPlanDefaults::defaults($user),
            ])
            ->assertStatus(200);
        $this->actingAs($user, 'web')
            ->delete(route('user.marketing-plan.destroy', $c->id))
            ->assertRedirect(route('user.marketing-plan.index'));

        // But new creates stay blocked while over the cap.
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), [
                'name' => 'D', 'payload' => MarketingPlanDefaults::defaults($user),
            ])
            ->assertStatus(403);
    }
}
