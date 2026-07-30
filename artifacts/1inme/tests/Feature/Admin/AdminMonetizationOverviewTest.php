<?php

namespace Tests\Feature\Admin;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\CoinPurchaseAllocation;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\MonetizationOverviewService;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Wallet;
use App\Modules\User\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin Monetization Overview report
 * (GET /admin/monetization):
 *
 *   1. Guests are redirected to the admin login (never a 200/500).
 *   2. An authenticated admin gets HTTP 200 with all three section
 *      headings and package/plan names rendered (any Blade crash on the
 *      page fails the suite).
 *   3. Section 1 math — effective price-per-coin and AI purchasing power
 *      at the live rates.
 *   4. Section 2 math — AI spend split into this vs last month by feature
 *      plus coin top-ups.
 *   5. Section 3 math — plan revenue, coin revenue with API-budget split,
 *      estimated AI cost and margin, kept per currency (never mixed).
 *   6. An invalid period falls back to the default without erroring.
 */
class AdminMonetizationOverviewTest extends TestCase
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

    private function makeUser(?int $planId = null): User
    {
        return User::create([
            'name'     => 'U ' . uniqid(),
            'email'    => 'u' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'plan_id'  => $planId,
        ]);
    }

    private function makePackage(array $attrs = [], array $prices = []): CoinPackage
    {
        $pkg = CoinPackage::create(array_merge([
            'name'           => 'Pack ' . uniqid(),
            'slug'           => 'pack-' . uniqid(),
            'coin_amount'    => 1000,
            'bonus_coins'    => 0,
            'is_active'      => true,
            'sort_order'     => 0,
            'api_budget_pct' => 60,
        ], $attrs));

        foreach ($prices as $currency => $minor) {
            $pkg->prices()->create([
                'currency'           => $currency,
                'billing_cycle'      => 'monthly',
                'amount_minor_units' => $minor,
                'is_active'          => true,
            ]);
        }

        return $pkg;
    }

    private function aiSpendTx(User $user, int $coins, string $feature, \DateTimeInterface $at): void
    {
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 100000]);
        WalletTransaction::create([
            'wallet_id'       => $wallet->id,
            'user_id'         => $user->id,
            'type'            => 'spend',
            'delta_coins'     => -$coins,
            'balance_after'   => 0,
            'idempotency_key' => 'test-' . uniqid(),
            'reason'          => 'test spend',
            'meta'            => ['ai' => true, 'feature' => $feature],
            'created_at'      => $at,
        ]);
    }

    private function paidCoinInvoice(User $user, string $currency, int $amountMinor, int $coins, CoinPackage $pkg): Invoice
    {
        $invoice = Invoice::create([
            'number'            => 'INV-' . uniqid(),
            'financial_year'    => '2026-27',
            'seq'               => random_int(1, 900000),
            'user_id'           => $user->id,
            'currency'          => $currency,
            'subtotal_minor'    => $amountMinor,
            'tax_total_minor'   => 0,
            'grand_total_minor' => $amountMinor,
            'line_items'        => [], 'billing_address_snapshot' => [], 'merchant_snapshot' => [], 'tax_breakdown' => [],
            'status'            => 'paid',
            'paid_at'           => now(),
        ]);

        $apiPct = $pkg->apiBudgetPct();
        $api = (int) round($amountMinor * $apiPct / 100);
        CoinPurchaseAllocation::create([
            'invoice_id'       => $invoice->id,
            'user_id'          => $user->id,
            'coin_package_id'  => $pkg->id,
            'coins'            => $coins,
            'currency'         => $currency,
            'amount_minor'     => $amountMinor,
            'api_budget_pct'   => $apiPct,
            'margin_pct'       => 100 - $apiPct,
            'api_budget_minor' => $api,
            'margin_minor'     => $amountMinor - $api,
        ]);

        return $invoice;
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/monetization')
            ->assertRedirect()
            ->assertRedirectContains('/admin/login');
    }

    public function test_admin_sees_all_three_sections_rendered(): void
    {
        $admin = $this->makeAdmin();
        $plan = $this->makePlan(['name' => 'Growth Plan']);
        $pkg = $this->makePackage(['name' => 'Starter Coins'], ['USD' => 999]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/monetization')
            ->assertOk()
            ->assertSee('Coin packages vs AI credits')
            ->assertSee('AI coin burn vs top-up')
            ->assertSee('Plan-wise profit')
            ->assertSee('Starter Coins')
            ->assertSee('Growth Plan');
    }

    public function test_invalid_period_falls_back_to_default(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->get('/admin/monetization?period=bogus')
            ->assertOk()
            ->assertViewHas('period', 'month');
    }

    public function test_package_per_coin_price_and_purchasing_power(): void
    {
        $this->makePackage(
            ['name' => 'Value Pack', 'coin_amount' => 900, 'bonus_coins' => 100],
            ['USD' => 2000, 'INR' => 150000],
        );

        // A fresh schema seeds the default coin-package lineup, so find
        // ours by name instead of assuming it is the only package.
        $svc = app(MonetizationOverviewService::class);
        $p = collect($svc->packages())->firstWhere('name', 'Value Pack');
        $this->assertNotNull($p);

        $this->assertSame(1000, $p['total_coins']);
        // 2000 minor / 1000 coins = 2.0 minor per coin; INR 150000/1000 = 150.
        $this->assertSame(2.0, $p['prices']['USD']['per_coin_minor']);
        $this->assertSame(150.0, $p['prices']['INR']['per_coin_minor']);
        // Currencies never mixed — each keeps its own row.
        $this->assertSame(['INR', 'USD'], array_keys($p['prices']));

        // Purchasing power follows the live rates exactly.
        $rates = $svc->aiRates();
        $this->assertSame(intdiv(1000, $rates['qr_coins']), $p['buys']['qr_generations']);
        $this->assertSame(intdiv(1000, $rates['brand_asset_coins']), $p['buys']['brand_assets']);
        $this->assertSame((int) floor(1000 / $rates['chat_blended_per_1k'] * 1000), $p['buys']['chat_tokens']);
        $this->assertSame((int) floor(1000 / $rates['tts_per_1k_chars'] * 1000), $p['buys']['tts_chars']);
        $this->assertSame(round(1000 / $rates['stt_per_minute'], 1), $p['buys']['stt_minutes']);
    }

    public function test_ai_spend_splits_this_vs_last_month_by_feature(): void
    {
        $user = $this->makeUser();
        $this->aiSpendTx($user, 40, 'mind', now()->startOfMonth()->addDay());
        $this->aiSpendTx($user, 25, 'mind', now()->startOfMonth()->subDays(3));
        $this->aiSpendTx($user, 10, 'persona.profile', now()->startOfMonth()->addDays(2));

        // A coin top-up this month.
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 100000]);
        WalletTransaction::create([
            'wallet_id'       => $wallet->id,
            'user_id'         => $user->id,
            'type'            => 'purchase',
            'delta_coins'     => 500,
            'balance_after'   => 500,
            'idempotency_key' => 'test-' . uniqid(),
            'reason'          => 'top-up',
            'meta'            => [],
            'created_at'      => now()->startOfMonth()->addDay(),
        ]);

        $spend = app(MonetizationOverviewService::class)->aiSpend();

        $byFeature = collect($spend['features'])->keyBy('feature');
        $this->assertSame(40, (int) $byFeature['mind']->this_month);
        $this->assertSame(25, (int) $byFeature['mind']->last_month);
        $this->assertSame(10, (int) $byFeature['persona.profile']->this_month);

        $this->assertSame(50, $spend['totals']['ai_spent']['this']);
        $this->assertSame(25, $spend['totals']['ai_spent']['last']);
        $this->assertSame(500, $spend['totals']['coins_purchased']['this']);
        $this->assertSame(0, $spend['totals']['coins_purchased']['last']);
    }

    public function test_plan_profit_math_per_currency(): void
    {
        $plan = $this->makePlan(['name' => 'Pro']);
        $user = $this->makeUser($plan->id);

        // Active subscription + one paid subscription invoice ($30.00).
        $sub = Subscription::create([
            'user_id'       => $user->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => 'monthly',
            'currency'      => 'USD',
        ]);
        Invoice::create([
            'number'            => 'INV-' . uniqid(),
            'financial_year'    => '2026-27',
            'seq'               => random_int(1, 900000),
            'user_id'           => $user->id,
            'subscription_id'   => $sub->id,
            'currency'          => 'USD',
            'subtotal_minor'    => 3000,
            'tax_total_minor'   => 0,
            'grand_total_minor' => 3000,
            'line_items'        => [], 'billing_address_snapshot' => [], 'merchant_snapshot' => [], 'tax_breakdown' => [],
            'status'            => 'paid',
            'paid_at'           => now(),
        ]);

        // Coin purchase: $20.00 for 1000 coins at 60% API budget.
        $pkg = $this->makePackage(['coin_amount' => 1000, 'api_budget_pct' => 60], ['USD' => 2000]);
        $this->paidCoinInvoice($user, 'USD', 2000, 1000, $pkg);

        // The user burned 500 of those 1000 coins on AI.
        $this->aiSpendTx($user, 500, 'mind', now());

        $svc = app(MonetizationOverviewService::class);
        $rows = collect($svc->plans($svc->periodSince('month')))
            ->firstWhere(fn ($r) => $r['plan']->id === $plan->id);

        $this->assertNotNull($rows);
        $this->assertSame(1, $rows['users']);
        $this->assertSame(1, $rows['active_subs']);
        $this->assertSame(500, $rows['ai_coins_spent']);

        $usd = $rows['currencies']['USD'];
        $this->assertSame(3000, $usd['revenue_minor']);
        $this->assertSame(2000, $usd['coin_amount_minor']);
        $this->assertSame(1200, $usd['coin_api_budget_minor']); // 60% of 2000
        $this->assertSame(800, $usd['coin_margin_minor']);
        $this->assertSame(1000, $usd['coins_purchased']);
        // 500 spent coins × (1200 budget / 1000 coins) = 600 minor.
        $this->assertSame(600, $usd['est_ai_cost_minor']);
        // Margin = 3000 + 2000 − 600.
        $this->assertSame(4400, $usd['margin_minor']);
    }

    public function test_monthly_trend_buckets_by_calendar_month(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser($plan->id);

        // AI burn: 40 this month, 25 last month.
        $this->aiSpendTx($user, 40, 'mind', now()->startOfMonth()->addDay());
        $this->aiSpendTx($user, 25, 'mind', now()->subMonthNoOverflow()->startOfMonth()->addDay());

        // Coin purchase (wallet) this month.
        $wallet = Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 100000]);
        WalletTransaction::create([
            'wallet_id'       => $wallet->id,
            'user_id'         => $user->id,
            'type'            => 'purchase',
            'delta_coins'     => 500,
            'balance_after'   => 500,
            'idempotency_key' => 'test-' . uniqid(),
            'reason'          => 'top-up',
            'meta'            => [],
            'created_at'      => now()->startOfMonth()->addDay(),
        ]);

        // Top-up revenue this month (USD 20.00) via allocation snapshot.
        $pkg = $this->makePackage(['coin_amount' => 500], ['USD' => 2000]);
        $this->paidCoinInvoice($user, 'USD', 2000, 500, $pkg);

        // Paid subscription invoice last month (USD 30.00).
        $sub = Subscription::create([
            'user_id'       => $user->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => 'monthly',
            'currency'      => 'USD',
        ]);
        Invoice::create([
            'number'            => 'INV-' . uniqid(),
            'financial_year'    => '2026-27',
            'seq'               => random_int(1, 900000),
            'user_id'           => $user->id,
            'subscription_id'   => $sub->id,
            'currency'          => 'USD',
            'subtotal_minor'    => 3000,
            'tax_total_minor'   => 0,
            'grand_total_minor' => 3000,
            'line_items'        => [], 'billing_address_snapshot' => [], 'merchant_snapshot' => [], 'tax_breakdown' => [],
            'status'            => 'paid',
            'paid_at'           => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
        ]);

        $trend = app(MonetizationOverviewService::class)->monthlyTrend(6);

        $this->assertCount(6, $trend['months']);
        $this->assertContains('USD', $trend['currencies']);

        $byMonth = collect($trend['months'])->keyBy('month');
        $thisYm = now()->format('Y-m');
        $lastYm = now()->subMonthNoOverflow()->format('Y-m');

        // Oldest first, current month last.
        $this->assertSame($thisYm, end($trend['months'])['month']);

        $this->assertSame(40, $byMonth[$thisYm]['ai_coins_spent']);
        $this->assertSame(500, $byMonth[$thisYm]['coins_purchased']);
        $this->assertSame(2000, $byMonth[$thisYm]['topup_revenue']['USD']);

        $this->assertSame(25, $byMonth[$lastYm]['ai_coins_spent']);
        $this->assertSame(0, $byMonth[$lastYm]['coins_purchased']);
        $this->assertSame(3000, $byMonth[$lastYm]['subscription_revenue']['USD']);
        // Coin-invoice money never leaks into subscription revenue.
        $this->assertArrayNotHasKey('USD', $byMonth[$thisYm]['subscription_revenue']);

        // Empty months render as zeros, not missing rows.
        $oldest = $trend['months'][0];
        $this->assertSame(0, $oldest['ai_coins_spent']);
        $this->assertSame([], $oldest['topup_revenue']);
    }

    public function test_monthly_trend_renders_on_the_page(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')
            ->get('/admin/monetization')
            ->assertOk()
            ->assertSee('Monthly trend');
    }

    public function test_est_ai_cost_is_capped_at_the_purchased_api_budget(): void
    {
        $plan = $this->makePlan();
        $user = $this->makeUser($plan->id);
        $pkg = $this->makePackage(['coin_amount' => 100, 'api_budget_pct' => 50], ['USD' => 1000]);
        $this->paidCoinInvoice($user, 'USD', 1000, 100, $pkg);

        // Burned far more coins than were purchased (bonus/adjustment coins).
        $this->aiSpendTx($user, 5000, 'mind', now());

        $svc = app(MonetizationOverviewService::class);
        $rows = collect($svc->plans($svc->periodSince('month')))
            ->firstWhere(fn ($r) => $r['plan']->id === $plan->id);

        $usd = $rows['currencies']['USD'];
        $this->assertSame(500, $usd['coin_api_budget_minor']);
        $this->assertSame(500, $usd['est_ai_cost_minor']); // capped at budget
        $this->assertSame(1000 - 500, $usd['margin_minor']); // 0 revenue + 1000 coin − 500 cost
    }

    public function test_csv_export_payloads_keep_currencies_separate(): void
    {
        $plan = $this->makePlan(['name' => 'Growth Plan']);
        $user = $this->makeUser($plan->id);

        // Package priced in two currencies → one CSV row per currency.
        $pkg = $this->makePackage(
            ['name' => 'Starter Coins', 'coin_amount' => 1000, 'bonus_coins' => 100, 'api_budget_pct' => 60],
            ['USD' => 2000, 'INR' => 90000]
        );
        $this->paidCoinInvoice($user, 'USD', 2000, 1000, $pkg);
        $this->aiSpendTx($user, 500, 'mind', now());

        $res = $this->actingAs($this->makeAdmin(), 'admin')
            ->get('/admin/monetization?period=month')
            ->assertOk()
            ->assertSee('Export CSV')
            ->assertSee('monetizationCsvExport', false);

        $csv = $res->viewData('csvExports');
        $this->assertSame(['packages', 'aiSpend', 'plans'], array_keys($csv));

        // Packages: one row per package × currency, plain decimal amounts.
        $pkgRows = collect($csv['packages']['rows'])->filter(fn ($r) => $r[0] === 'Starter Coins')->values();
        $this->assertCount(2, $pkgRows);
        $currencyIdx = array_search('currency', $csv['packages']['header'], true);
        $priceIdx = array_search('price', $csv['packages']['header'], true);
        $byCur = $pkgRows->keyBy(fn ($r) => $r[$currencyIdx]);
        $this->assertSame('20.00', $byCur['USD'][$priceIdx]);
        $this->assertSame('900.00', $byCur['INR'][$priceIdx]);

        // AI spend: top-up revenue rows carry an explicit currency column.
        $topup = collect($csv['aiSpend']['rows'])->firstWhere(fn ($r) => $r[0] === 'topup_revenue' && $r[2] === 'USD');
        $this->assertNotNull($topup);
        $this->assertSame('20.00', $topup[3]); // this month, major units
        $feature = collect($csv['aiSpend']['rows'])->firstWhere(fn ($r) => $r[0] === 'feature_coins' && $r[1] === 'mind');
        $this->assertNotNull($feature);
        $this->assertSame(500, $feature[3]);

        // Plans: one row per plan × currency; period baked into the filename.
        $this->assertStringContainsString('plan-profit-month-', $csv['plans']['filename']);
        $planRow = collect($csv['plans']['rows'])->firstWhere(fn ($r) => $r[0] === 'Growth Plan' && $r[5] === 'USD');
        $this->assertNotNull($planRow);
        $this->assertSame('20.00', $planRow[7]);  // coin revenue
        $this->assertSame('12.00', $planRow[8]);  // API budget (60%)
        $this->assertSame('6.00', $planRow[9]);   // est AI cost
    }
}
