<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\MarketingPlanCalc;
use App\Modules\User\Models\User;
use App\Services\MarketingPlanDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin-managed "what you'd need instead" tool costs + lock checkbox.
 *
 * Admin can edit the ROI table's estimated monthly costs and lock them;
 * when locked, user calculators show the admin values read-only and the
 * server refuses client-submitted cost overrides; when unlocked, users
 * edit costs freely (admin values are just the defaults).
 */
class MarketingPlanToolCostsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
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

    private function user(): User
    {
        $slug = 'p' . uniqid();
        $plan = \App\Modules\Admin\Models\Plan::create([
            'name' => 'Pro ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => ['marketing_plan_calculator' => true, 'max_marketing_plans' => -1],
        ]);

        return User::create([
            'name'         => 'Owner',
            'email'        => 'own' . uniqid() . '@example.com',
            'password'     => Hash::make('secret'),
            'status'       => 'active',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
        ]);
    }

    /** A valid save body built from the live defaults. */
    private function saveBody(User $user, array $toolCostOverride = []): array
    {
        $payload = MarketingPlanDefaults::defaults($user);
        foreach ($payload['tools'] as $i => $row) {
            if (isset($toolCostOverride[$row['key']])) {
                $payload['tools'][$i]['cost'] = $toolCostOverride[$row['key']];
            }
        }

        return ['name' => 'Test plan', 'payload' => $payload];
    }

    public function test_admin_can_save_costs_and_lock(): void
    {
        $this->be($this->admin(), 'admin');

        $costs = ['linkinbio' => 9999, 'crm' => 1234];
        $this->put(route('admin.marketing-plan-tool-costs.update'), [
            'costs'  => $costs + ['bogus_key' => 5, 'qr' => ''],
            'locked' => '1',
        ])->assertRedirect(route('admin.marketing-plan-tool-costs.index'));

        $stored = AppSetting::get(MarketingPlanDefaults::SETTING_TOOL_COSTS);
        $this->assertSame(9999.0, (float) $stored['linkinbio']);
        $this->assertSame(1234.0, (float) $stored['crm']);
        $this->assertArrayNotHasKey('bogus_key', $stored); // unknown keys dropped
        $this->assertArrayNotHasKey('qr', $stored);        // blank = keep default
        $this->assertTrue(MarketingPlanDefaults::toolCostsLocked());

        // Admin values flow into fresh user defaults.
        $tools = collect(MarketingPlanDefaults::tools())->keyBy('key');
        $this->assertSame(9999.0, (float) $tools['linkinbio']['cost']);
        $this->assertSame(1234.0, (float) $tools['crm']['cost']);
        $this->assertSame(1200, $tools['qr']['cost']); // untouched default
    }

    public function test_locked_costs_cannot_be_overridden_on_save(): void
    {
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOL_COSTS, ['linkinbio' => 7777]);
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOLS_LOCKED, true);

        $user = $this->user();
        $this->be($user, 'web');

        $res = $this->postJson(
            route('user.marketing-plan.store'),
            $this->saveBody($user, ['linkinbio' => 1, 'crm' => 2]),
        );
        $res->assertOk();

        $saved = MarketingPlanCalc::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
        $tools = collect($saved->payload['tools'])->keyBy('key');
        $this->assertSame(7777.0, (float) $tools['linkinbio']['cost']); // admin value enforced
        $this->assertSame(4000.0, (float) $tools['crm']['cost']);       // default enforced, user's 2 discarded
    }

    public function test_locked_tools_table_is_rebuilt_even_for_hostile_payloads(): void
    {
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOL_COSTS, ['linkinbio' => 7777]);
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOLS_LOCKED, true);

        $user = $this->user();
        $this->be($user, 'web');

        // Hostile payloads: empty list, renamed/unknown rows with zero cost,
        // duplicate rows. All must be discarded and rebuilt canonically.
        foreach ([
            [],
            [['key' => 'zzz', 'feature' => 'Fake tool', 'cost' => 0]],
            [['feature' => 'Link-in-Bio Page', 'cost' => 0], ['feature' => 'Link-in-Bio Page', 'cost' => 0]],
        ] as $i => $hostileTools) {
            $body = $this->saveBody($user);
            $body['name'] = 'Hostile ' . $i;
            $body['payload']['tools'] = $hostileTools;

            $this->postJson(route('user.marketing-plan.store'), $body)->assertOk();

            $saved = MarketingPlanCalc::withoutGlobalScopes()
                ->where('user_id', $user->id)->where('name', 'Hostile ' . $i)->firstOrFail();
            $tools = collect($saved->payload['tools'])->keyBy('key');

            $this->assertCount(count(MarketingPlanDefaults::TOOLS), $saved->payload['tools']);
            $this->assertSame(7777.0, (float) $tools['linkinbio']['cost']);
            $this->assertSame(4000, $tools['crm']['cost']);
            $this->assertArrayNotHasKey('zzz', $tools->all());
        }
    }

    public function test_unlocked_costs_stay_user_editable(): void
    {
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOL_COSTS, ['linkinbio' => 7777]);
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOLS_LOCKED, false);

        $user = $this->user();
        $this->be($user, 'web');

        $this->postJson(
            route('user.marketing-plan.store'),
            $this->saveBody($user, ['linkinbio' => 42]),
        )->assertOk();

        $saved = MarketingPlanCalc::withoutGlobalScopes()->where('user_id', $user->id)->firstOrFail();
        $tools = collect($saved->payload['tools'])->keyBy('key');
        $this->assertSame(42.0, (float) $tools['linkinbio']['cost']); // user's edit kept
    }

    public function test_editor_flags_lock_state_to_the_view(): void
    {
        AppSetting::put(MarketingPlanDefaults::SETTING_TOOLS_LOCKED, true);

        $user = $this->user();
        $this->be($user, 'web');

        $this->get(route('user.marketing-plan.create'))
            ->assertOk()
            ->assertViewHas('toolsLocked', true)
            ->assertSee('data-mpc-tools-locked', false);
    }
}
