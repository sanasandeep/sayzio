<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\Billing\CoinPlanBonus;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plan-based bonus coins on Pro+ coin packages.
 *
 * Paid-plan subscribers buying a coin package of tier Pro or above receive
 * an extra plan bonus (a % of the package's BASE coin_amount, floored) on
 * top of the package's built-in bonus. Sub-Pro packages and free-plan
 * buyers get nothing extra. The bonus is resolved server-side at credit
 * time by {@see CoinPlanBonus} and must survive webhook re-delivery
 * without double-crediting.
 */
class CoinPlanBonusTest extends TestCase
{
    use RefreshDatabase;

    private function planFor(string $slug): Plan
    {
        return Plan::firstOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'status' => 'active', 'monthly_price' => 9, 'annual_price' => 90, 'features' => []],
        );
    }

    private function userOnPlan(?string $slug): User
    {
        $user = User::factory()->create(['role' => 'user']);
        if ($slug !== null) {
            $user->forceFill(['plan_id' => $this->planFor($slug)->id])->save();
        }
        return $user->fresh();
    }

    private function packageFor(string $slug, int $coins, int $bonus): CoinPackage
    {
        return CoinPackage::firstOrCreate(
            ['slug' => $slug],
            ['name' => Str::headline($slug), 'coin_amount' => $coins, 'bonus_coins' => $bonus, 'status' => 'active', 'sort_order' => 1],
        );
    }

    private function makeCoinInvoice(User $user, CoinPackage $pkg): Invoice
    {
        return Invoice::create([
            'number'                   => 'INV/TEST/'.Str::upper(Str::random(8)),
            'financial_year'           => '2026-27',
            'seq'                      => random_int(1, 1_000_000),
            'user_id'                  => $user->id,
            'currency'                 => 'USD',
            'subtotal_minor'           => 1000,
            'tax_total_minor'          => 0,
            'grand_total_minor'        => 1000,
            'billing_address_snapshot' => [],
            'merchant_snapshot'        => [],
            'tax_breakdown'            => [],
            'status'                   => 'pending',
            'line_items'               => [[
                'label'        => $pkg->name.' ('.$pkg->totalCoins().' coins)',
                'amount_minor' => 1000,
                'quantity'     => 1,
                'meta'         => [
                    'kind'            => 'coin_package',
                    'coin_package_id' => $pkg->id,
                    'coins'           => (int) $pkg->coin_amount,
                    'bonus'           => (int) $pkg->bonus_coins,
                ],
            ]],
        ]);
    }

    public static function planPercentages(): array
    {
        return [
            'creator +2%'        => ['creator', 2],
            'professional +3%'   => ['professional', 3],
            'business +4%'       => ['business', 4],
            'agency +5%'         => ['agency', 5],
            'developer +6%'      => ['developer', 6],
            'enterprise-api +7%' => ['enterprise-api', 7],
            'unlimited +10%'     => ['unlimited', 10],
        ];
    }

    #[DataProvider('planPercentages')]
    public function test_each_paid_plan_gets_its_bonus_percent_on_a_pro_package(string $planSlug, int $pct): void
    {
        $user = $this->userOnPlan($planSlug);
        $pkg  = $this->packageFor('coins-pro', 70000, 10000);

        $this->assertSame($pct, CoinPlanBonus::percentFor($user, $pkg));
        $expectedBonus = (int) floor(70000 * $pct / 100);
        $this->assertSame($expectedBonus, CoinPlanBonus::bonusCoinsFor($user, $pkg));

        app(ActivateSubscription::class)->run($this->makeCoinInvoice($user, $pkg), 'stripe', 'evt_'.$planSlug);

        $this->assertSame(
            70000 + 10000 + $expectedBonus,
            app(WalletService::class)->getBalance($user),
            "Base + built-in bonus + {$pct}% plan bonus must be credited for {$planSlug}."
        );

        $tx = WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->firstOrFail();
        $this->assertStringContainsString($pct.'%', (string) $tx->reason, 'Transaction description must reflect the plan bonus.');
        $this->assertStringContainsString('plan bonus', (string) $tx->reason);
        $this->assertSame($pct, (int) ($tx->meta['plan_bonus_pct'] ?? 0));
        $this->assertSame($expectedBonus, (int) ($tx->meta['plan_bonus_coins'] ?? 0));
    }

    public function test_creator_plan_pro_package_example_totals_81400(): void
    {
        // The spec's worked example: 70,000 base + 10,000 bonus + 2% of
        // 70,000 (=1,400) = 81,400 coins.
        $user = $this->userOnPlan('creator');
        $pkg  = $this->packageFor('coins-pro', 70000, 10000);

        app(ActivateSubscription::class)->run($this->makeCoinInvoice($user, $pkg), 'stripe', 'evt_example');

        $this->assertSame(81400, app(WalletService::class)->getBalance($user));
    }

    public function test_sub_pro_packages_never_get_a_plan_bonus(): void
    {
        $user = $this->userOnPlan('unlimited');
        foreach ([['coins-starter', 7000, 0], ['coins-basic', 14000, 1000], ['coins-standard', 21000, 2000]] as [$slug, $coins, $bonus]) {
            $pkg = $this->packageFor($slug, $coins, $bonus);
            $this->assertSame(0, CoinPlanBonus::percentFor($user, $pkg), "$slug must be ineligible even on the top plan.");
            $this->assertSame(0, CoinPlanBonus::bonusCoinsFor($user, $pkg));
        }

        $pkg = CoinPackage::where('slug', 'coins-basic')->firstOrFail();
        app(ActivateSubscription::class)->run($this->makeCoinInvoice($user, $pkg), 'stripe', 'evt_subpro');
        $this->assertSame((int) $pkg->coin_amount + (int) $pkg->bonus_coins, app(WalletService::class)->getBalance($user));
        $tx = WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->firstOrFail();
        $this->assertStringNotContainsString('plan bonus', (string) $tx->reason);
    }

    public function test_free_plan_and_planless_users_get_no_bonus_on_eligible_packages(): void
    {
        $pkg = $this->packageFor('coins-ultimate', 1750000, 400000);

        $free = $this->userOnPlan('free');
        $this->assertSame(0, CoinPlanBonus::percentFor($free, $pkg));

        $planless = $this->userOnPlan(null);
        $planless->forceFill(['plan_id' => null])->save();
        $this->assertSame(0, CoinPlanBonus::percentFor($planless->fresh(), $pkg));

        app(ActivateSubscription::class)->run($this->makeCoinInvoice($free, $pkg), 'stripe', 'evt_free');
        $this->assertSame(1750000 + 400000, app(WalletService::class)->getBalance($free), 'Free plan behaves exactly as today.');
    }

    public function test_plan_bonus_rounds_down_to_whole_coins(): void
    {
        // 3% of 12,345 = 370.35 → 370 coins.
        $user = $this->userOnPlan('professional');
        $pkg = $this->packageFor('coins-pro', 12345, 0);
        $pkg->forceFill(['coin_amount' => 12345, 'bonus_coins' => 0])->save();
        $pkg->refresh();
        $this->assertSame(370, CoinPlanBonus::bonusCoinsFor($user, $pkg));
    }

    public function test_webhook_redelivery_does_not_double_credit_the_plan_bonus(): void
    {
        $user = $this->userOnPlan('unlimited');
        $pkg  = $this->packageFor('coins-business', 175000, 30000);
        $invoice = $this->makeCoinInvoice($user, $pkg);

        $activator = app(ActivateSubscription::class);
        $activator->run($invoice, 'stripe', 'evt_dup');
        $activator->run($invoice->fresh(), 'stripe', 'evt_dup');

        $expected = 175000 + 30000 + (int) floor(175000 * 0.10);
        $this->assertSame($expected, app(WalletService::class)->getBalance($user), 'Re-delivery must not double-credit base or plan bonus.');
        $this->assertCount(1, WalletTransaction::where('user_id', $user->id)->where('type', 'purchase')->get());
    }
}
