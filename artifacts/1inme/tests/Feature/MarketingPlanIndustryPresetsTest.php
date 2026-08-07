<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\User;
use App\Services\MarketingPlanDefaults;
use App\Services\MarketingPlanIndustryPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6767 — industry benchmark presets for the Marketing Plan
 * Calculator: catalog integrity, create-with-preset seeding, preset key
 * persistence and the "Custom" fallback for pre-preset payloads.
 */
class MarketingPlanIndustryPresetsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        $slug = 'p' . Str::random(6);
        $plan = Plan::create([
            'name' => 'Professional ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);

        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ]);
    }

    /** Every preset keeps the 16-channel shape and paid allocations sum to 100%. */
    public function test_catalog_integrity(): void
    {
        $this->assertNotEmpty(MarketingPlanIndustryPresets::PRESETS);

        foreach (array_keys(MarketingPlanIndustryPresets::PRESETS) as $key) {
            $rows = MarketingPlanIndustryPresets::channels($key);
            $this->assertCount(count(MarketingPlanDefaults::CHANNELS), $rows, $key);
            $this->assertSame(
                array_column(MarketingPlanDefaults::CHANNELS, 'key'),
                array_column($rows, 'key'),
                $key
            );

            $alloc = 0.0;
            foreach ($rows as $row) {
                foreach (['name', 'vl', 'lc', 'acv', 'notes'] as $f) {
                    $this->assertArrayHasKey($f, $row, "$key/{$row['key']}");
                }
                if (!$row['fixed']) {
                    $alloc += (float) $row['alloc'];
                    $this->assertGreaterThan(0, (float) $row['cpv'], "$key/{$row['key']} cpv");
                }
                $this->assertGreaterThanOrEqual(0, (float) $row['vl']);
                $this->assertLessThanOrEqual(100, (float) $row['vl']);
                $this->assertGreaterThanOrEqual(0, (float) $row['lc']);
                $this->assertLessThanOrEqual(100, (float) $row['lc']);
            }
            $this->assertEqualsWithDelta(100.0, $alloc, 0.01, "$key allocations must sum to 100%");
        }
    }

    public function test_create_with_preset_seeds_the_expected_payload(): void
    {
        $user = $this->user();

        $resp = $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['preset' => 'b2b_saas']));
        $resp->assertStatus(200);

        $payload = $resp->viewData('payload');
        $this->assertSame('b2b_saas', $payload['industry_preset']);
        $this->assertEquals(MarketingPlanIndustryPresets::channels('b2b_saas'), $payload['channels']);

        // Preset numbers actually differ from the generic table.
        $linkedin = collect($payload['channels'])->firstWhere('key', 'linkedin');
        $this->assertSame(18.0, (float) $linkedin['alloc']);
        $this->assertSame(400000, (int) $linkedin['acv']);

        // The picker catalog is exposed to the editor.
        $this->assertArrayHasKey('generic', $resp->viewData('presets'));
        $this->assertArrayHasKey('b2b_saas', $resp->viewData('presets'));
    }

    public function test_unknown_preset_key_falls_back_to_generic_defaults(): void
    {
        $user = $this->user();

        $resp = $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['preset' => 'nope']));
        $resp->assertStatus(200);

        $payload = $resp->viewData('payload');
        $this->assertSame('generic', $payload['industry_preset']);
        $this->assertEquals(MarketingPlanDefaults::CHANNELS, $payload['channels']);
    }

    public function test_saved_plan_remembers_preset_and_index_shows_badge(): void
    {
        $user = $this->user();

        $payload = MarketingPlanIndustryPresets::apply(
            MarketingPlanDefaults::defaults($user),
            'hospitality'
        );

        $store = $this->actingAs($user, 'web')->postJson(route('user.marketing-plan.store'), [
            'name' => 'Cafe Plan', 'payload' => $payload,
        ]);
        $store->assertStatus(200)->assertJson(['ok' => true]);

        $saved = MarketingPlanCalc::findOrFail((int) $store->json('id'));
        $this->assertSame('hospitality', $saved->payload['industry_preset']);

        // Badge on the saved-plan list…
        $this->actingAs($user, 'web')->get(route('user.marketing-plan.index'))
            ->assertStatus(200)
            ->assertSee('Hospitality / restaurant');

        // …and the editor reloads the preset key for the header badge.
        $edit = $this->actingAs($user, 'web')->get(route('user.marketing-plan.edit', $saved->id));
        $edit->assertStatus(200);
        $this->assertSame('hospitality', $edit->viewData('payload')['industry_preset']);
    }

    public function test_pre_preset_payloads_read_as_custom(): void
    {
        $user = $this->user();

        $payload = MarketingPlanDefaults::defaults($user);
        unset($payload['industry_preset']); // old plan saved before Task #6767

        // Save through the normal endpoint (correct workspace scoping) —
        // the raw payload is stored as-is, so the key stays absent.
        $store = $this->actingAs($user, 'web')->postJson(route('user.marketing-plan.store'), [
            'name' => 'Old Plan', 'payload' => $payload,
        ]);
        $store->assertStatus(200);
        $plan = MarketingPlanCalc::findOrFail((int) $store->json('id'));
        $this->assertArrayNotHasKey('industry_preset', $plan->payload);

        $this->assertSame('Custom', MarketingPlanIndustryPresets::label(null));
        $this->assertSame('Custom', MarketingPlanIndustryPresets::label('deleted_vertical'));

        $this->actingAs($user, 'web')->get(route('user.marketing-plan.index'))
            ->assertStatus(200)
            ->assertSee('data-mpc-preset-badge', false)
            ->assertSee('Custom');

        // Reopening merges defaults over the old payload, but must NOT stamp
        // the defaults' 'generic' key — the editor badge/picker read "Custom".
        $edit = $this->actingAs($user, 'web')->get(route('user.marketing-plan.edit', $plan->id));
        $edit->assertStatus(200);
        $this->assertArrayNotHasKey('industry_preset', $edit->viewData('payload'));
    }

    public function test_ai_strategy_seeding_still_works_with_presets_in_place(): void
    {
        $user = $this->user();

        // `?from_strategy=` with a bogus id still 404s (route intact) and a
        // plain create ignores an empty preset param.
        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['from_strategy' => 999999]))
            ->assertStatus(404);

        $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['preset' => '']))
            ->assertStatus(200);
    }
}
