<?php

namespace Tests\Feature\Admin;

use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for the compare-grid bulk save path
 * (POST /admin/plans/compare/save-bulk → PlanController::bulkSave).
 *
 * The compare grid does NOT render the block allowlist, per-kind
 * integration provider allowlists, addon attachments or the intro
 * discount, so bulkSave rebuilds a full synthetic payload from the
 * existing plan (buildSyntheticPayload) before handing it to
 * PlanWriter::updateFromRequest(). That preservation logic is intricate
 * (block_mode / provider_mode flags, '*' vs pick lists, per-kind modes)
 * and a regression would silently strip plan gating on every bulk save.
 *
 * These tests assert that a plan carrying:
 *   - a PICKED block-type allowlist,
 *   - mixed per-kind integration provider modes ('*' and pick lists),
 *   - per-kind integration account caps,
 *   - attached addons,
 *   - an enabled intro discount,
 *   - the four synced `prices` table rows,
 * survives a bulk save of unrelated fields completely unchanged.
 *
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

    private const BLOCK_PICKS = ['link', 'heading', 'image'];

    private const PROVIDERS = [
        'payment' => ['stripe'],
        'sms'     => '*',
        'email'   => ['sendgrid', 'mailgun'],
    ];

    private const CAPS = ['payment' => 2, 'sms' => 1, 'email' => 3];

    private const INTRO = [
        'enabled' => true,
        'type'    => 'percent',
        'percent' => 20,
        'cycles'  => ['monthly'],
        'label'   => 'Launch offer',
    ];

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

    private function makeAddon(): Addon
    {
        return Addon::create([
            'name'   => 'Addon ' . uniqid(),
            'slug'   => 'addon-' . Str::lower(Str::random(8)),
            'type'   => 'recurring',
            'status' => 'active',
        ]);
    }

    /**
     * A plan with everything the compare grid does NOT show: picked block
     * allowlist, mixed provider modes, per-kind caps, addons, an intro
     * discount, plus synced price rows (via the same writer path the real
     * editor uses).
     */
    private function makeGatedPlan(): Plan
    {
        $plan = Plan::create([
            'name'                    => 'Gated ' . uniqid(),
            'slug'                    => 'gated-' . Str::lower(Str::random(8)),
            'description'             => 'gated plan',
            'monthly_price'           => 10,   // $10.00 → 1000 minor
            'annual_price'            => 100,  // $100.00 → 10000 minor
            'monthly_price_secondary' => 800,  // ₹800.00 → 80000 minor
            'annual_price_secondary'  => 8000, // ₹8000.00 → 800000 minor
            'trial_days'              => 0,
            'grace_days'              => 0,
            'refund_window_days'      => 0,
            'status'                  => 'active',
            'sort_order'              => 1,
            'intro_discount'          => self::INTRO,
            'features'                => [
                // Modules gating the keys under test must be ON, otherwise
                // collectFeatures()'s module-off pass (correctly) restores
                // gated keys to their existing values on every save.
                'module_short_links'            => true,
                'module_biolinks'               => true,
                'max_links'                     => 25,
                'block_types_allowed'           => self::BLOCK_PICKS,
                'integration_providers_allowed' => self::PROVIDERS,
                'integration_accounts_max'      => self::CAPS,
                // A key no form surface renders — must also carry through.
                'referrer_free_days'            => 14,
            ],
        ]);

        $plan->addons()->sync([$this->makeAddon()->id, $this->makeAddon()->id]);

        (new \App\Modules\Admin\Support\PlanWriter())->syncPriceTable($plan, [
            'monthly_price'           => 1000,
            'annual_price'            => 10000,
            'monthly_price_secondary' => 80000,
            'annual_price_secondary'  => 800000,
        ]);

        return $plan;
    }

    private function bulkSave(array $plans): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->makeAdmin(), 'admin')
            ->postJson(route('admin.plans.compare.save'), ['plans' => $plans]);
    }

    /** @return array<string,array{string,int}> keyed "CUR/cycle" => minor amount */
    private function priceRows(Plan $plan): array
    {
        return $plan->prices()->get()
            ->mapWithKeys(fn ($p) => [$p->currency . '/' . $p->billing_cycle => (int) $p->amount_minor_units])
            ->all();
    }

    private function assertGatingIntact(Plan $plan, array $expectedAddonIds): void
    {
        $features = $plan->features;

        // Module toggles have no explicit features.* validation rule, so
        // Laravel's validated() used to strip them and every save silently
        // flipped them off — assert they survive.
        $this->assertTrue((bool) ($features['module_short_links'] ?? false),
            'module_short_links was silently disabled by bulk save.');
        $this->assertTrue((bool) ($features['module_biolinks'] ?? false),
            'module_biolinks was silently disabled by bulk save.');

        $this->assertSame(self::BLOCK_PICKS, $features['block_types_allowed'] ?? null,
            'Picked block-type allowlist was altered by bulk save.');

        $this->assertSame(self::PROVIDERS['payment'], $features['integration_providers_allowed']['payment'] ?? null,
            'Payment provider pick list was altered by bulk save.');
        $this->assertSame('*', $features['integration_providers_allowed']['sms'] ?? null,
            "SMS provider '*' (all) mode was altered by bulk save.");
        $this->assertSame(self::PROVIDERS['email'], $features['integration_providers_allowed']['email'] ?? null,
            'Email provider pick list was altered by bulk save.');

        $caps = $features['integration_accounts_max'] ?? null;
        $this->assertIsArray($caps, 'Per-kind integration account caps were wiped by bulk save.');
        ksort($caps);
        $expectedCaps = self::CAPS;
        ksort($expectedCaps);
        $this->assertSame($expectedCaps, $caps,
            'Per-kind integration account caps were altered by bulk save.');

        $this->assertSame(14, $features['referrer_free_days'] ?? null,
            'Unrendered feature key was dropped by bulk save.');

        $this->assertEqualsCanonicalizing($expectedAddonIds, $plan->addons()->pluck('addons.id')->all(),
            'Addon attachments were altered by bulk save.');

        $intro = $plan->intro_discount;
        $this->assertIsArray($intro, 'Intro discount was wiped by bulk save.');
        $this->assertTrue((bool) ($intro['enabled'] ?? false));
        $this->assertSame('percent', $intro['type'] ?? null);
        $this->assertSame(20, (int) ($intro['percent'] ?? 0));
        $this->assertSame(['monthly'], $intro['cycles'] ?? null);
        $this->assertSame('Launch offer', $intro['label'] ?? null);
    }

    public function test_bulk_saving_an_unrelated_core_field_preserves_all_gating(): void
    {
        $plan     = $this->makeGatedPlan();
        $addonIds = $plan->addons()->pluck('addons.id')->all();

        $resp = $this->bulkSave([$plan->id => ['trial_days' => 7]]);

        $resp->assertOk()->assertJson(['ok' => true, 'updated' => 1]);

        $plan->refresh();
        $this->assertSame(7, (int) $plan->trial_days, 'The submitted change itself must apply.');
        $this->assertGatingIntact($plan, $addonIds);
    }

    public function test_bulk_saving_a_feature_field_preserves_all_gating(): void
    {
        $plan     = $this->makeGatedPlan();
        $addonIds = $plan->addons()->pluck('addons.id')->all();

        $resp = $this->bulkSave([$plan->id => ['features' => ['max_links' => 50]]]);

        $resp->assertOk()->assertJson(['ok' => true, 'updated' => 1]);

        $plan->refresh();
        $this->assertSame(50, (int) $plan->features['max_links'], 'The submitted feature change must apply.');
        $this->assertGatingIntact($plan, $addonIds);
    }

    public function test_bulk_save_keeps_the_synced_prices_table_rows_unchanged(): void
    {
        $plan = $this->makeGatedPlan();

        $expected = [
            'USD/monthly' => 1000,
            'USD/annual'  => 10000,
            'INR/monthly' => 80000,
            'INR/annual'  => 800000,
        ];
        $this->assertSame($expected, $this->priceRows($plan), 'Precondition: seeded price rows.');

        $this->bulkSave([$plan->id => ['sort_order' => 5]])
            ->assertOk()->assertJson(['ok' => true, 'updated' => 1]);

        $plan->refresh();
        $this->assertSame(5, (int) $plan->sort_order);

        // Still exactly the four authoritative rows, amounts untouched, and
        // the legacy decimal columns still agree with them.
        $this->assertSame($expected, $this->priceRows($plan),
            'Prices table rows were altered by a bulk save that did not touch prices.');
        $this->assertSame(4, $plan->prices()->count());
        $this->assertSame('10.00', (string) $plan->monthly_price);
        $this->assertSame('100.00', (string) $plan->annual_price);
        $this->assertSame('800.00', (string) $plan->monthly_price_secondary);
        $this->assertSame('8000.00', (string) $plan->annual_price_secondary);
    }

    public function test_bulk_saving_a_price_updates_both_the_prices_row_and_the_gating_survives(): void
    {
        $plan     = $this->makeGatedPlan();
        $addonIds = $plan->addons()->pluck('addons.id')->all();

        // Compare grid submits prices in MINOR units.
        $this->bulkSave([$plan->id => ['monthly_price' => 1500]])
            ->assertOk()->assertJson(['ok' => true, 'updated' => 1]);

        $plan->refresh();
        $this->assertSame('15.00', (string) $plan->monthly_price);

        $rows = $this->priceRows($plan);
        $this->assertSame(1500, $rows['USD/monthly']);
        $this->assertSame(10000, $rows['USD/annual'], 'Untouched price row must stay unchanged.');
        $this->assertSame(80000, $rows['INR/monthly']);
        $this->assertSame(800000, $rows['INR/annual']);

        $this->assertGatingIntact($plan, $addonIds);
    }
}
