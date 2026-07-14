<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\CoinPackage;
use Database\Seeders\CoinPackagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke-tests the v2 coin-package lineup change: the seeder must archive
 * the v1 packages idempotently, seed exactly 10 active v2 packages with
 * prices matching the $0.96/coin (USD) and ₹86.40/coin (INR) formula in
 * minor units, and the active() scope must hide legacy packages from all
 * shop surfaces while their rows survive for purchase-history references.
 */
class CoinPackagesSeederTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY_SLUGS = [
        'starter-pack',
        'mini-pack',
        'value-pack',
        'creator-pack',
        'growth-pack',
        'pro-pack',
        'mega-pack',
        'ultimate-pack',
    ];

    /** slug => coin_amount for the v2 lineup. */
    private const V2_LINEUP = [
        'ai-credits-10'    => 10,
        'ai-credits-50'    => 50,
        'ai-credits-100'   => 100,
        'ai-credits-250'   => 250,
        'ai-credits-500'   => 500,
        'ai-credits-1000'  => 1000,
        'ai-credits-2000'  => 2000,
        'ai-credits-3500'  => 3500,
        'ai-credits-5000'  => 5000,
        'ai-credits-10000' => 10000,
    ];

    /** USD cents per coin and INR paise per coin. */
    private const USD_MINOR_PER_COIN = 96;   // $0.96
    private const INR_MINOR_PER_COIN = 8640; // ₹86.40

    private function seedLegacyPackages(): void
    {
        $sort = 0;
        foreach (self::LEGACY_SLUGS as $slug) {
            CoinPackage::create([
                'slug'        => $slug,
                'name'        => ucwords(str_replace('-', ' ', $slug)),
                'description' => 'Legacy v1 package',
                'coin_amount' => 100,
                'bonus_coins' => 10,
                'status'      => 'active',
                'is_archived' => false,
                'sort_order'  => $sort += 10,
            ]);
        }
    }

    public function test_seeder_creates_ten_active_packages_with_formula_prices(): void
    {
        $this->seed(CoinPackagesSeeder::class);

        $active = CoinPackage::active()->get();
        $this->assertCount(10, $active, 'Exactly 10 packages must be active after seeding.');
        $this->assertEqualsCanonicalizing(
            array_keys(self::V2_LINEUP),
            $active->pluck('slug')->all(),
        );

        foreach ($active as $pkg) {
            $coins = self::V2_LINEUP[$pkg->slug];
            $this->assertSame($coins, (int) $pkg->coin_amount, "coin_amount mismatch for {$pkg->slug}");
            $this->assertSame(0, (int) $pkg->bonus_coins, "v2 packages have no bonus coins ({$pkg->slug})");

            $usd = $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
            $inr = $pkg->prices()->where('currency', 'INR')->where('billing_cycle', 'monthly')->first();

            $this->assertNotNull($usd, "Missing USD price for {$pkg->slug}");
            $this->assertNotNull($inr, "Missing INR price for {$pkg->slug}");
            $this->assertTrue((bool) $usd->is_active);
            $this->assertTrue((bool) $inr->is_active);

            $this->assertSame(
                $coins * self::USD_MINOR_PER_COIN,
                (int) $usd->amount_minor_units,
                "USD price off-formula for {$pkg->slug}",
            );
            $this->assertSame(
                $coins * self::INR_MINOR_PER_COIN,
                (int) $inr->amount_minor_units,
                "INR price off-formula for {$pkg->slug}",
            );

            // No compare-at (strike-through) price for the v2 lineup.
            $this->assertSame(0, $pkg->originalPriceMinor('USD'), "Unexpected compare-at USD price for {$pkg->slug}");
            $this->assertSame(0, $pkg->originalPriceMinor('INR'), "Unexpected compare-at INR price for {$pkg->slug}");
        }
    }

    public function test_legacy_packages_are_archived_not_deleted_and_hidden_from_active_scope(): void
    {
        $this->seedLegacyPackages();

        $this->seed(CoinPackagesSeeder::class);

        // Rows survive (purchase history references stay intact).
        foreach (self::LEGACY_SLUGS as $slug) {
            $pkg = CoinPackage::where('slug', $slug)->first();
            $this->assertNotNull($pkg, "Legacy package {$slug} must not be deleted.");
            $this->assertTrue($pkg->is_archived, "Legacy package {$slug} must be archived.");
            $this->assertSame('inactive', $pkg->status, "Legacy package {$slug} must be inactive.");
        }

        // Hidden from the shop-facing active() scope...
        $activeSlugs = CoinPackage::active()->pluck('slug')->all();
        $this->assertCount(10, $activeSlugs);
        $this->assertEmpty(array_intersect(self::LEGACY_SLUGS, $activeSlugs));

        // ...but returned by an archived query.
        $archivedSlugs = CoinPackage::where('is_archived', true)->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(self::LEGACY_SLUGS, $archivedSlugs);
    }

    public function test_seeder_is_idempotent_and_never_overwrites_admin_edits(): void
    {
        $this->seedLegacyPackages();
        $this->seed(CoinPackagesSeeder::class);
        $this->seed(CoinPackagesSeeder::class); // second run must be a no-op

        $this->assertCount(10, CoinPackage::active()->get());
        $this->assertSame(
            10 + count(self::LEGACY_SLUGS),
            CoinPackage::count(),
            'Re-running the seeder must not duplicate rows.',
        );

        // Exactly one price row per (package, currency, cycle).
        $pkg = CoinPackage::where('slug', 'ai-credits-100')->firstOrFail();
        $this->assertSame(1, $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->count());
        $this->assertSame(1, $pkg->prices()->where('currency', 'INR')->where('billing_cycle', 'monthly')->count());

        // Admin-edited price survives a re-run (seedPriceIfMissing existence guard).
        $usd = $pkg->prices()->where('currency', 'USD')->where('billing_cycle', 'monthly')->first();
        $usd->update(['amount_minor_units' => 12345]);
        $this->seed(CoinPackagesSeeder::class);
        $this->assertSame(12345, (int) $usd->fresh()->amount_minor_units);

        // Admin re-activated legacy package is re-retired only in the retire
        // direction: seeder moves it back to archived/inactive. But an already
        // archived row is untouched (no useless writes) — verify final state.
        $legacy = CoinPackage::where('slug', 'starter-pack')->firstOrFail();
        $this->assertTrue($legacy->is_archived);
        $this->assertSame('inactive', $legacy->status);
    }
}
