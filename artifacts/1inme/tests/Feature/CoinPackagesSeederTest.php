<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\CoinPackage;
use Database\Seeders\CoinPackagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-tests the v3 coin-package lineup: the seeder must archive the v1
 * named packs AND the v2 ai-credits formula packs idempotently, seed exactly
 * 8 active v3 tiers (Starter $10 … Ultimate $2,500) with fixed USD prices,
 * bonus coins, "best for" labels and the hidden internal api_budget_pct
 * allocation, and the active() scope must hide legacy packages from shop
 * surfaces while their rows survive for purchase-history references.
 */
class CoinPackagesSeederTest extends TestCase
{
    use RefreshDatabase;

    /** slug => [base coins, bonus, usd minor, api %, best_for]. */
    private const V3_LINEUP = [
        'coins-starter'    => [7000,    0,      1000,   70, 'Trying AI'],
        'coins-basic'      => [14000,   1000,   2000,   72, 'Casual users'],
        'coins-standard'   => [21000,   2000,   3000,   74, 'Regular users'],
        'coins-pro'        => [70000,   10000,  10000,  78, 'Professionals'],
        'coins-business'   => [175000,  30000,  25000,  80, 'Small teams'],
        'coins-enterprise' => [350000,  70000,  50000,  82, 'Growing businesses'],
        'coins-scale'      => [700000,  150000, 100000, 84, 'Large organizations'],
        'coins-ultimate'   => [1750000, 400000, 250000, 85, 'Enterprise AI'],
    ];

    private function seedLegacyPackages(): void
    {
        $sort = 0;
        foreach (CoinPackagesSeeder::LEGACY_SLUGS as $slug) {
            CoinPackage::create([
                'slug'        => $slug,
                'name'        => ucwords(str_replace('-', ' ', $slug)),
                'description' => 'Legacy package',
                'coin_amount' => 100,
                'bonus_coins' => 10,
                'status'      => 'active',
                'is_archived' => false,
                'sort_order'  => $sort += 10,
            ]);
        }
    }

    public function test_seeder_creates_eight_active_tiers_with_fixed_prices_and_allocation(): void
    {
        $this->seed(CoinPackagesSeeder::class);

        $active = CoinPackage::active()->get();
        $this->assertCount(8, $active, 'Exactly 8 packages must be active after seeding.');
        $this->assertEqualsCanonicalizing(
            array_keys(self::V3_LINEUP),
            $active->pluck('slug')->all(),
        );

        foreach ($active as $pkg) {
            [$coins, $bonus, $usdMinor, $pct, $bestFor] = self::V3_LINEUP[$pkg->slug];

            $this->assertSame($coins, (int) $pkg->coin_amount, "coin_amount mismatch for {$pkg->slug}");
            $this->assertSame($bonus, (int) $pkg->bonus_coins, "bonus_coins mismatch for {$pkg->slug}");
            $this->assertSame($coins + $bonus, $pkg->totalCoins(), "total coins mismatch for {$pkg->slug}");
            $this->assertSame($bestFor, $pkg->best_for, "best_for mismatch for {$pkg->slug}");

            // Hidden internal allocation: API budget % + derived margin.
            $this->assertSame((float) $pct, $pkg->apiBudgetPct(), "api_budget_pct mismatch for {$pkg->slug}");
            $this->assertSame(100.0 - $pct, $pkg->marginPct(), "margin mismatch for {$pkg->slug}");

            $usd = $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
            $inr = $pkg->prices()->where('currency', 'INR')->where('billing_cycle', 'monthly')->first();
            $this->assertNotNull($usd, "Missing USD price for {$pkg->slug}");
            $this->assertNotNull($inr, "Missing INR price for {$pkg->slug}");
            $this->assertTrue((bool) $usd->is_active);
            $this->assertTrue((bool) $inr->is_active);
            $this->assertSame($usdMinor, (int) $usd->amount_minor_units, "USD price mismatch for {$pkg->slug}");
            $this->assertGreaterThan(0, (int) $inr->amount_minor_units, "INR price missing for {$pkg->slug}");

            // No compare-at (strike-through) price for the v3 lineup.
            $this->assertSame(0, $pkg->originalPriceMinor('USD'), "Unexpected compare-at USD price for {$pkg->slug}");
            $this->assertSame(0, $pkg->originalPriceMinor('INR'), "Unexpected compare-at INR price for {$pkg->slug}");
        }
    }

    public function test_legacy_packages_are_archived_not_deleted_and_hidden_from_active_scope(): void
    {
        $this->seedLegacyPackages();

        $this->seed(CoinPackagesSeeder::class);

        // Rows survive (purchase history references stay intact).
        foreach (CoinPackagesSeeder::LEGACY_SLUGS as $slug) {
            $pkg = CoinPackage::where('slug', $slug)->first();
            $this->assertNotNull($pkg, "Legacy package {$slug} must not be deleted.");
            $this->assertTrue($pkg->is_archived, "Legacy package {$slug} must be archived.");
            $this->assertSame('inactive', $pkg->status, "Legacy package {$slug} must be inactive.");
        }

        // Hidden from the shop-facing active() scope...
        $activeSlugs = CoinPackage::active()->pluck('slug')->all();
        $this->assertCount(8, $activeSlugs);
        $this->assertEmpty(array_intersect(CoinPackagesSeeder::LEGACY_SLUGS, $activeSlugs));

        // ...but returned by an archived query.
        $archivedSlugs = CoinPackage::where('is_archived', true)->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(CoinPackagesSeeder::LEGACY_SLUGS, $archivedSlugs);
    }

    public function test_seeder_is_idempotent_and_never_overwrites_admin_edits(): void
    {
        $this->seedLegacyPackages();
        $this->seed(CoinPackagesSeeder::class);
        $this->seed(CoinPackagesSeeder::class); // second run must be a no-op

        $this->assertCount(8, CoinPackage::active()->get());
        $this->assertSame(
            8 + count(CoinPackagesSeeder::LEGACY_SLUGS),
            CoinPackage::count(),
            'Re-running the seeder must not duplicate rows.',
        );

        // Exactly one price row per (package, currency, cycle).
        $pkg = CoinPackage::where('slug', 'coins-pro')->firstOrFail();
        $this->assertSame(1, $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->count());
        $this->assertSame(1, $pkg->prices()->where('currency', 'INR')->where('billing_cycle', 'monthly')->count());

        // Admin-edited price survives a re-run (seedPriceIfMissing existence guard).
        $usd = $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
        $usd->update(['amount_minor_units' => 12345]);
        $this->seed(CoinPackagesSeeder::class);
        $this->assertSame(12345, (int) $usd->fresh()->amount_minor_units);

        // Admin-edited allocation + label survive a re-run (null-only backfill).
        $pkg->forceFill(['api_budget_pct' => 55.5, 'best_for' => 'Custom label'])->save();
        $this->seed(CoinPackagesSeeder::class);
        $pkg->refresh();
        $this->assertSame(55.5, $pkg->apiBudgetPct());
        $this->assertSame('Custom label', $pkg->best_for);

        // Legacy packages remain retired.
        $legacy = CoinPackage::where('slug', 'starter-pack')->firstOrFail();
        $this->assertTrue($legacy->is_archived);
        $this->assertSame('inactive', $legacy->status);
    }

    public function test_buy_page_shows_total_coins_and_best_for_labels(): void
    {
        $this->seed(CoinPackagesSeeder::class);
        // The wallet is an admin-toggled feature; buy() 404s when disabled.
        \App\Modules\Admin\Models\AppSetting::put(\App\Services\Billing\WalletService::FEATURE_KEY, true);
        // Fresh users are redirected into onboarding by the gate middleware.
        $user = \App\Modules\User\Models\User::factory()->create(['onboarded_at' => now()]);

        $resp = $this->actingAs($user, 'web')
            ->get(route('user.wallet.buy'))
            ->assertOk();

        foreach (self::V3_LINEUP as [$base, $bonus, $usdMinor, $pct, $bestFor]) {
            // Headline is the coin TOTAL (base + bonus), with the split below.
            $resp->assertSee(number_format($base + $bonus));
            $resp->assertSee('Best for '.$bestFor);
            if ($bonus > 0) {
                $resp->assertSee(number_format($bonus).' bonus coins');
            }
            // The hidden internal allocation must never leak to customers.
            $resp->assertDontSee((string) $pct.'% API');
        }
    }
}
