<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Wallet;
use App\Modules\User\Models\WalletTransaction;
use App\Services\MarketingPlanActuals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6772 — real Sayzio usage feeding the Marketing Plan Calculator:
 * the aggregation service, the /actuals endpoint, and the
 * `?prefill=actuals` create seed. link_clicks is timestamped by
 * clicked_at (the table has NO created_at) — the service must query it.
 */
class MarketingPlanActualsTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);

        return Plan::create([
            'name' => 'Professional ' . $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features + ['marketing_plan_calculator' => true, 'max_marketing_plans' => -1],
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

    private function link(User $user): Link
    {
        return Link::withoutGlobalScopes()->forceCreate([
            'user_id' => $user->id, 'workspace_id' => null,
            'alias'   => 'mpa' . Str::lower(Str::random(8)),
            'type'    => 'url', 'long_url' => 'https://example.test',
        ]);
    }

    /**
     * Seed ONE month of realistic usage for the owner's personal workspace
     * (everything stamped inside the current calendar month so the
     * single-denominator averages are deterministic).
     */
    private function seedUsage(User $user): Link
    {
        $t    = now()->startOfMonth()->addHours(2);
        $link = $this->link($user);

        // 10 human clicks (clicked_at!), plus bot + throttled rows that
        // must be ignored by the aggregate.
        foreach (range(1, 10) as $i) {
            LinkClick::forceCreate(['link_id' => $link->id, 'clicked_at' => $t, 'is_bot' => false, 'is_throttled' => false]);
        }
        foreach (range(1, 2) as $i) {
            LinkClick::forceCreate(['link_id' => $link->id, 'clicked_at' => $t, 'is_bot' => true, 'is_throttled' => false]);
        }
        LinkClick::forceCreate(['link_id' => $link->id, 'clicked_at' => $t, 'is_bot' => false, 'is_throttled' => true]);

        // 2 form submissions (one spam — ignored) + 1 CRM contact = 3 leads.
        $form = Form::withoutGlobalScopes()->forceCreate([
            'user_id' => $user->id, 'workspace_id' => null,
            'slug' => 'f' . Str::lower(Str::random(8)), 'title' => 'Lead form',
            'fields' => [], 'is_active' => true,
        ]);
        FormSubmission::withoutGlobalScopes()->forceCreate(['form_id' => $form->id, 'workspace_id' => null, 'data' => [], 'is_spam' => false, 'created_at' => $t]);
        FormSubmission::withoutGlobalScopes()->forceCreate(['form_id' => $form->id, 'workspace_id' => null, 'data' => [], 'is_spam' => false, 'created_at' => $t]);
        FormSubmission::withoutGlobalScopes()->forceCreate(['form_id' => $form->id, 'workspace_id' => null, 'data' => [], 'is_spam' => true,  'created_at' => $t]);
        Contact::withoutGlobalScopes()->forceCreate(['user_id' => $user->id, 'workspace_id' => null, 'display_name' => 'Lead One', 'created_at' => $t]);

        // Revenue: one completed store order (₹500) + one cancelled (ignored).
        $menu = \App\Modules\User\Models\StoreMenu::forceCreate(['link_id' => $link->id, 'user_id' => $user->id, 'currency' => 'INR']);
        StoreOrder::forceCreate(['menu_id' => $menu->id, 'link_id' => $link->id, 'public_token' => (string) Str::uuid(), 'status' => 'completed', 'subtotal' => 500, 'total' => 500, 'currency' => 'INR', 'created_at' => $t]);
        StoreOrder::forceCreate(['menu_id' => $menu->id, 'link_id' => $link->id, 'public_token' => (string) Str::uuid(), 'status' => 'cancelled', 'subtotal' => 900, 'total' => 900, 'currency' => 'INR', 'created_at' => $t]);

        // AI coin spend in the last 30 days: 120 spent, 20 refunded → 100 net.
        $wallet = Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        WalletTransaction::forceCreate(['wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => 'spend',  'delta_coins' => -120, 'balance_after' => 0, 'meta' => ['ai' => true], 'created_at' => now()->subDays(5)]);
        WalletTransaction::forceCreate(['wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => 'refund', 'delta_coins' => 20,   'balance_after' => 20, 'meta' => ['ai' => true], 'created_at' => now()->subDays(5)]);
        // Non-AI spend must not count.
        WalletTransaction::forceCreate(['wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => 'spend', 'delta_coins' => -999, 'balance_after' => 0, 'meta' => [], 'created_at' => now()->subDays(5)]);

        return $link;
    }

    public function test_service_aggregates_real_usage(): void
    {
        $user = $this->user();
        $this->seedUsage($user);

        $a = MarketingPlanActuals::forUser($user, null);

        $this->assertTrue($a['has_data']);
        $this->assertSame(10, $a['monthly_visitors']);      // bots excluded
        $this->assertSame(3, $a['monthly_leads']);          // 2 subs + 1 contact, spam excluded
        $this->assertEqualsWithDelta(500.0, $a['monthly_revenue'], 0.01);
        $this->assertSame(100, $a['ai_coins_30d']);         // 120 spend − 20 refund
        $this->assertEqualsWithDelta(30.0, $a['vl_rate'], 0.01); // 3 / 10
        $this->assertTrue($a['features']['crm']);
        $this->assertFalse($a['features']['chat']);
        $this->assertFalse($a['features']['dialer']);
        $this->assertCount(12, $a['months']);
    }

    public function test_multi_month_averages_share_one_denominator(): void
    {
        $user = $this->user();
        $link = $this->link($user);

        // Month A: 10 clicks. Month B (previous): 20 clicks + 3 contacts.
        $a = now()->startOfMonth()->addHours(2);
        $b = now()->subMonthNoOverflow()->startOfMonth()->addHours(2);
        foreach (range(1, 10) as $i) {
            LinkClick::forceCreate(['link_id' => $link->id, 'clicked_at' => $a, 'is_bot' => false, 'is_throttled' => false]);
        }
        foreach (range(1, 20) as $i) {
            LinkClick::forceCreate(['link_id' => $link->id, 'clicked_at' => $b, 'is_bot' => false, 'is_throttled' => false]);
        }
        foreach (range(1, 3) as $i) {
            Contact::withoutGlobalScopes()->forceCreate(['user_id' => $user->id, 'workspace_id' => null, 'display_name' => 'Lead ' . $i, 'created_at' => $b]);
        }

        $res = MarketingPlanActuals::forUser($user, null);

        // 2 active months → visitors (10+20)/2, leads 3/2, and the
        // conversion rate comes from period TOTALS: 3 / 30 = 10%.
        $this->assertSame(15, $res['monthly_visitors']);
        $this->assertSame(2, $res['monthly_leads']); // round(1.5)
        $this->assertEqualsWithDelta(10.0, $res['vl_rate'], 0.01);
    }

    public function test_service_is_graceful_on_an_empty_workspace(): void
    {
        $a = MarketingPlanActuals::forUser($this->user(), null);

        $this->assertFalse($a['has_data']);
        $this->assertSame(0, $a['monthly_visitors']);
        $this->assertNull($a['vl_rate']);
        $this->assertSame(['chat' => false, 'crm' => false, 'dialer' => false], $a['features']);
    }

    public function test_other_owners_and_workspaces_do_not_leak_in(): void
    {
        $user  = $this->user();
        $other = $this->user();
        $this->seedUsage($other);

        $this->assertFalse(MarketingPlanActuals::forUser($user, null)['has_data']);
        // Same owner, but a different (non-null) workspace sees nothing.
        $this->assertFalse(MarketingPlanActuals::forUser($other, 999999)['has_data']);
    }

    public function test_actuals_endpoint_returns_json_and_is_plan_gated(): void
    {
        $user = $this->user();
        $this->seedUsage($user);

        $this->actingAs($user, 'web')
            ->getJson(route('user.marketing-plan.actuals'))
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('actuals.has_data', true)
            ->assertJsonPath('actuals.monthly_visitors', 10);

        $gated = $this->user($this->plan(['marketing_plan_calculator' => false]));
        $this->actingAs($gated, 'web')
            ->getJson(route('user.marketing-plan.actuals'))
            ->assertStatus(403);
    }

    public function test_create_prefill_actuals_seeds_the_editor_payload(): void
    {
        $user = $this->user();
        $this->seedUsage($user);

        $resp = $this->actingAs($user, 'web')
            ->get(route('user.marketing-plan.create', ['prefill' => 'actuals']))
            ->assertStatus(200);

        // organic_visitors prefilled from real traffic, AI credits from the
        // wallet, and the Sayzio row's visitor→lead rate from observed data.
        $resp->assertViewHas('payload', function (array $p) {
            $sayzio = collect($p['channels'])->firstWhere('key', 'sayzio');
            return $p['organic_visitors'] === 10
                && $p['ai_credits'] === 100
                && abs(((float) ($sayzio['vl'] ?? 0)) - 30.0) < 0.01;
        });

        // Empty workspace: prefill is a graceful no-op (defaults survive).
        $fresh    = $this->user();
        $defaults = \App\Services\MarketingPlanDefaults::defaults($fresh);
        $this->actingAs($fresh, 'web')
            ->get(route('user.marketing-plan.create', ['prefill' => 'actuals']))
            ->assertStatus(200)
            ->assertViewHas('payload', fn (array $p) => $p['organic_visitors'] === $defaults['organic_visitors']);
    }
}
