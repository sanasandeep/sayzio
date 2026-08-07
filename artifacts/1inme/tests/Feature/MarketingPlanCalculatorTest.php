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
 * Task #6737 — Marketing Plan Calculator: create → defaults → save →
 * reload → update → delete, plus ownership scoping.
 */
class MarketingPlanCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => 'Professional ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => [],
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => ($plan ?? $this->plan())->id,
            'onboarded_at' => now(),
        ]);
    }

    public function test_index_and_create_pages_render_with_defaults(): void
    {
        $plan = $this->plan();
        $user = $this->user($plan);

        $this->actingAs($user, 'web')->get(route('user.marketing-plan.index'))
            ->assertStatus(200)
            ->assertSee('Marketing Plan Calculator');

        $resp = $this->actingAs($user, 'web')->get(route('user.marketing-plan.create'));
        $resp->assertStatus(200)
            // Spreadsheet channel benchmarks seeded into the editor payload.
            ->assertSee('Instagram Ads')
            ->assertSee('Paid Search (Google Ads)')
            // ROI standalone-tool table.
            ->assertSee('Linktree Pro', false)
            // The user's own plan is the default selection.
            ->assertSee($plan->slug);
    }

    public function test_create_save_reload_update_and_delete(): void
    {
        $user = $this->user();

        $payload = MarketingPlanDefaults::defaults($user);
        $payload['company'] = 'Acme Fitness App';
        $payload['annual_budget'] = 250000;

        // Save a new named plan.
        $store = $this->actingAs($user, 'web')->postJson(route('user.marketing-plan.store'), [
            'name'    => '2026 Growth Plan',
            'payload' => $payload,
        ]);
        $store->assertStatus(200)->assertJson(['ok' => true]);
        $id = (int) $store->json('id');

        $this->assertDatabaseHas('marketing_plan_calcs', [
            'id' => $id, 'user_id' => $user->id, 'name' => '2026 Growth Plan',
        ]);

        // Reopening restores all inputs.
        $this->actingAs($user, 'web')->get(route('user.marketing-plan.edit', $id))
            ->assertStatus(200)
            ->assertSee('Acme Fitness App')
            ->assertSee('2026 Growth Plan');

        $saved = MarketingPlanCalc::findOrFail($id);
        $this->assertSame('Acme Fitness App', $saved->payload['company']);
        $this->assertSame(250000, (int) $saved->payload['annual_budget']);
        $this->assertCount(16, $saved->payload['channels']);
        $this->assertCount(11, $saved->payload['tools']);

        // Update.
        $payload['company'] = 'Beta Corp';
        $this->actingAs($user, 'web')->putJson(route('user.marketing-plan.update', $id), [
            'name'    => 'Renamed Plan',
            'payload' => $payload,
        ])->assertStatus(200)->assertJson(['ok' => true]);

        $saved->refresh();
        $this->assertSame('Renamed Plan', $saved->name);
        $this->assertSame('Beta Corp', $saved->payload['company']);

        // Delete.
        $this->actingAs($user, 'web')->delete(route('user.marketing-plan.destroy', $id))
            ->assertRedirect(route('user.marketing-plan.index'));
        $this->assertDatabaseMissing('marketing_plan_calcs', ['id' => $id]);
    }

    public function test_other_users_plans_are_not_accessible(): void
    {
        $owner  = $this->user();
        $other  = $this->user();

        $plan = MarketingPlanCalc::create([
            'user_id'      => $owner->id,
            'workspace_id' => null,
            'name'         => 'Private Plan',
            'payload'      => MarketingPlanDefaults::defaults($owner),
        ]);

        $this->actingAs($other, 'web')->get(route('user.marketing-plan.edit', $plan->id))->assertStatus(404);
        $this->actingAs($other, 'web')->putJson(route('user.marketing-plan.update', $plan->id), [
            'name' => 'x', 'payload' => ['a' => 1],
        ])->assertStatus(404);
        $this->actingAs($other, 'web')->delete(route('user.marketing-plan.destroy', $plan->id))->assertStatus(404);
    }

    public function test_oversized_payload_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user, 'web')->postJson(route('user.marketing-plan.store'), [
            'name'    => 'Huge',
            'payload' => ['blob' => str_repeat('x', 130_000)],
        ])->assertStatus(422);
    }
}
