<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\User;
use App\Services\MarketingPlanDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6771 — manually logged monthly actuals (actuals_log) saved inside
 * the plan payload: validation, save/reload round-trip, preservation when a
 * client omits the key, and the tracked-months indicator on the plan list.
 */
class MarketingPlanActualsTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => 'Professional ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => ['marketing_plan_calculator' => true, 'max_marketing_plans' => -1],
        ]);
    }

    private function user(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $this->plan()->id,
            'onboarded_at' => now(),
        ]);
    }

    /** A 12-slot actuals log with three months (partially) tracked. */
    private function actualsLog(): array
    {
        $log = array_fill(0, 12, ['spend' => null, 'visitors' => null, 'leads' => null, 'customers' => null, 'revenue' => null]);
        $log[0] = ['spend' => 50000, 'visitors' => 1200, 'leads' => 80, 'customers' => 9, 'revenue' => 90000];
        $log[1] = ['spend' => 60000, 'visitors' => null, 'leads' => null, 'customers' => null, 'revenue' => 120000];
        $log[2] = ['spend' => null, 'visitors' => 900, 'leads' => null, 'customers' => null, 'revenue' => null]; // partial entry is fine
        return $log;
    }

    public function test_actuals_save_and_reload_with_the_plan(): void
    {
        $user = $this->user();
        $payload = MarketingPlanDefaults::defaults($user);
        $payload['actuals_log'] = $this->actualsLog();

        $store = $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Tracked', 'payload' => $payload]);
        $store->assertStatus(200)->assertJson(['ok' => true]);

        $model = MarketingPlanCalc::findOrFail((int) $store->json('id'));
        $this->assertSame(50000, $model->payload['actuals_log'][0]['spend']);
        $this->assertSame(120000, $model->payload['actuals_log'][1]['revenue']);
        $this->assertNull($model->payload['actuals_log'][3]['spend']);
        $this->assertSame(3, MarketingPlanCalc::trackedActualMonths((array) $model->payload));

        // Reload the editor: the payload (incl. actuals) is embedded for Alpine.
        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.edit', $model->id))
            ->assertStatus(200)
            ->assertSee('actuals_log', false);
    }

    public function test_actuals_survive_updates_that_omit_the_key(): void
    {
        $user = $this->user();
        $payload = MarketingPlanDefaults::defaults($user);
        $payload['actuals_log'] = $this->actualsLog();

        $store = $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Tracked', 'payload' => $payload]);
        $store->assertStatus(200);
        $id = (int) $store->json('id');

        // An update whose payload lacks actuals_log (older cached editor)
        // must keep the stored log, not wipe months of tracked history.
        $bare = MarketingPlanDefaults::defaults($user);
        $bare['annual_budget'] = 999999;
        $this->assertArrayNotHasKey('actuals_log', $bare);

        $this->actingAs($user, 'web')
            ->putJson(route('user.marketing-plan.update', $id), ['name' => 'Re-edited', 'payload' => $bare])
            ->assertStatus(200)->assertJson(['ok' => true]);

        $model = MarketingPlanCalc::findOrFail($id);
        $this->assertSame(999999, $model->payload['annual_budget']);
        $this->assertSame(50000, $model->payload['actuals_log'][0]['spend']);
        $this->assertSame(3, MarketingPlanCalc::trackedActualMonths((array) $model->payload));

        // But an explicit actuals_log in the update payload wins (that's how
        // the editor clears / edits logged months).
        $explicit = MarketingPlanDefaults::defaults($user);
        $explicit['actuals_log'] = array_fill(0, 12, ['spend' => null, 'visitors' => null, 'leads' => null, 'customers' => null, 'revenue' => null]);
        $this->actingAs($user, 'web')
            ->putJson(route('user.marketing-plan.update', $id), ['name' => 'Cleared', 'payload' => $explicit])
            ->assertStatus(200);
        $this->assertSame(0, MarketingPlanCalc::trackedActualMonths((array) MarketingPlanCalc::findOrFail($id)->payload));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badActualsProvider')]
    public function test_out_of_range_actuals_are_rejected(string $key, mixed $bad): void
    {
        $user = $this->user();
        $payload = MarketingPlanDefaults::defaults($user);
        $payload['actuals_log'] = $this->actualsLog();
        data_set($payload, $key, $bad);

        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Bad', 'payload' => $payload])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payload.' . $key]);
    }

    public static function badActualsProvider(): array
    {
        return [
            'negative spend'    => ['actuals_log.0.spend', -1],
            'negative revenue'  => ['actuals_log.1.revenue', -100],
            'huge visitors'     => ['actuals_log.2.visitors', 1e14],
            'non-numeric leads' => ['actuals_log.0.leads', 'lots'],
        ];
    }

    public function test_plan_list_shows_tracked_months_indicator(): void
    {
        $user = $this->user();

        // Create through the endpoint so the plans land in the same
        // workspace scope the index request resolves (web requests bind the
        // user's personal workspace; a raw ::create with NULL would be
        // invisible to the list).
        $payload = MarketingPlanDefaults::defaults($user);
        $payload['actuals_log'] = $this->actualsLog();
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Tracked plan', 'payload' => $payload])
            ->assertStatus(200);
        $this->actingAs($user, 'web')
            ->postJson(route('user.marketing-plan.store'), ['name' => 'Untracked plan', 'payload' => MarketingPlanDefaults::defaults($user)])
            ->assertStatus(200);

        $html = $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.index'))
            ->assertStatus(200)
            ->assertSee('3/12 months tracked')
            ->getContent();

        // Exactly one badge — the untracked plan shows none.
        $this->assertSame(1, substr_count($html, 'data-mpc-tracked-badge'));
    }
}
