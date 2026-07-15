<?php

namespace Tests\Feature\Admin;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the compare-grid bulk save endpoint
 * (POST /admin/plans/compare/save-bulk), focused on the optimistic-
 * concurrency guard: the compare page embeds each plan's updated_at at
 * load time (`_loaded_at`); if the plan was modified since, bulkSave must
 * reject that plan with a per-plan `_plan` error instead of silently
 * overwriting the newer edit.
 */
class AdminPlanBulkSaveTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );

        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name'               => 'Plan ' . uniqid(),
            'slug'               => 'plan-' . uniqid(),
            'description'        => 'x',
            'monthly_price'      => 10,
            'annual_price'       => 100,
            'trial_days'         => 0,
            'grace_days'         => 0,
            'refund_window_days' => 0,
            'features'           => ['max_links' => 5],
            'status'             => 'active',
            'sort_order'         => 0,
        ], $attrs));
    }

    public function test_stale_loaded_at_rejects_the_plan_with_a_per_plan_error(): void
    {
        $plan     = $this->makePlan(['name' => 'Original Name']);
        $loadedAt = $plan->updated_at->toISOString();

        // Another admin edits the plan after our page load.
        $plan->forceFill([
            'name'       => 'Newer Edit',
            'updated_at' => now()->addMinute(),
        ])->saveQuietly();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->postJson(
            route('admin.plans.compare.save'),
            ['plans' => [$plan->id => ['name' => 'Stale Overwrite', '_loaded_at' => $loadedAt]]]
        );

        $resp->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('updated', 0);

        $errors = $resp->json('errors');
        $this->assertArrayHasKey((string) $plan->id, $errors);
        $this->assertArrayHasKey('_plan', $errors[(string) $plan->id]);
        $this->assertStringContainsString('changed since you loaded', $errors[(string) $plan->id]['_plan'][0]);

        // The newer edit must survive untouched.
        $this->assertSame('Newer Edit', $plan->fresh()->name);
    }

    public function test_matching_loaded_at_saves_and_returns_fresh_saved_at(): void
    {
        $plan     = $this->makePlan(['name' => 'Original Name']);
        $loadedAt = $plan->updated_at->toISOString();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->postJson(
            route('admin.plans.compare.save'),
            ['plans' => [$plan->id => ['name' => 'Updated Name', '_loaded_at' => $loadedAt]]]
        );

        $resp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('errors', []);

        $this->assertSame('Updated Name', $plan->fresh()->name);

        // The response advertises the plan's fresh updated_at so the grid
        // can re-arm its concurrency token for subsequent saves.
        $savedAt = $resp->json('saved_at');
        $this->assertSame($plan->fresh()->updated_at->toISOString(), $savedAt[(string) $plan->id] ?? ($savedAt[$plan->id] ?? null));
    }

    public function test_missing_loaded_at_still_saves_for_backwards_compatibility(): void
    {
        $plan = $this->makePlan(['name' => 'Original Name']);

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->postJson(
            route('admin.plans.compare.save'),
            ['plans' => [$plan->id => ['name' => 'Updated Name']]]
        );

        $resp->assertOk()->assertJsonPath('updated', 1);
        $this->assertSame('Updated Name', $plan->fresh()->name);
    }

    public function test_only_the_stale_plan_is_rejected_in_a_mixed_batch(): void
    {
        $fresh = $this->makePlan(['name' => 'Fresh Plan']);
        $stale = $this->makePlan(['name' => 'Stale Plan']);

        $freshToken = $fresh->updated_at->toISOString();
        $staleToken = $stale->updated_at->toISOString();

        $stale->forceFill(['updated_at' => now()->addMinute()])->saveQuietly();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')->postJson(
            route('admin.plans.compare.save'),
            ['plans' => [
                $fresh->id => ['name' => 'Fresh Renamed', '_loaded_at' => $freshToken],
                $stale->id => ['name' => 'Stale Renamed', '_loaded_at' => $staleToken],
            ]]
        );

        $resp->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('updated', 1);

        $errors = $resp->json('errors');
        $this->assertArrayHasKey((string) $stale->id, $errors);
        $this->assertArrayNotHasKey((string) $fresh->id, $errors);

        $this->assertSame('Fresh Renamed', $fresh->fresh()->name);
        $this->assertSame('Stale Plan', $stale->fresh()->name);
    }

    public function test_compare_page_embeds_the_loaded_at_token(): void
    {
        $a = $this->makePlan();
        $b = $this->makePlan();

        $resp = $this->actingAs($this->makeAdmin(), 'admin')
            ->get(route('admin.plans.compare', ['ids' => [$a->id, $b->id]]));

        $resp->assertOk();
        $this->assertStringContainsString('_loaded_at', $resp->getContent());
    }
}
