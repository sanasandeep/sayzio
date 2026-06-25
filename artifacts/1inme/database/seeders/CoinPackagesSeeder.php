<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\CoinPackage;
use App\Services\PricingResolver;
use Illuminate\Database\Seeder;

/**
 * Seeds the default catalog of buyable coin packages so the wallet
 * shop is not empty on a fresh install. Each package is identified
 * by a stable `slug`; running this seeder repeatedly will only top
 * up missing entries (it never overwrites admin-edited values).
 *
 * Pricing is stored in the polymorphic `prices` table in MINOR
 * units (cents/paise) under the 'monthly' billing cycle slot, which
 * is the convention CoinPackageController uses so the
 * PricingResolver can look prices back up at checkout.
 */
class CoinPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'slug' => 'starter-pack',
                'name' => 'Starter Pack',
                'description' => 'A small top-up to try out coin-priced add-ons and one-off boosts.',
                'coin_amount' => 100,
                'bonus_coins' => 0,
                'sort_order' => 10,
                'prices' => ['USD' => 199, 'INR' => 16900],
                'original' => ['USD' => 249, 'INR' => 20900],
            ],
            [
                'slug' => 'mini-pack',
                'name' => 'Mini Pack',
                'description' => 'A little more headroom for occasional boosts and small unlocks.',
                'coin_amount' => 250,
                'bonus_coins' => 15,
                'sort_order' => 15,
                'prices' => ['USD' => 449, 'INR' => 37900],
                'original' => ['USD' => 549, 'INR' => 45900],
            ],
            [
                'slug' => 'value-pack',
                'name' => 'Value Pack',
                'description' => 'Most popular. A balanced bundle for steady users with a small bonus.',
                'coin_amount' => 500,
                'bonus_coins' => 50,
                'sort_order' => 20,
                'prices' => ['USD' => 899, 'INR' => 74900],
                'original' => ['USD' => 1099, 'INR' => 89900],
            ],
            [
                'slug' => 'creator-pack',
                'name' => 'Creator Pack',
                'description' => 'For active creators running boosts and unlocks every week.',
                'coin_amount' => 1200,
                'bonus_coins' => 200,
                'sort_order' => 30,
                'prices' => ['USD' => 1999, 'INR' => 169900],
                'original' => ['USD' => 2499, 'INR' => 209900],
            ],
            [
                'slug' => 'growth-pack',
                'name' => 'Growth Pack',
                'description' => 'Scaling up — a healthy reserve for sustained campaigns and AI credits.',
                'coin_amount' => 2000,
                'bonus_coins' => 350,
                'sort_order' => 35,
                'prices' => ['USD' => 2999, 'INR' => 249900],
                'original' => ['USD' => 3699, 'INR' => 309900],
            ],
            [
                'slug' => 'pro-pack',
                'name' => 'Pro Pack',
                'description' => 'A bulk pack for teams and power users — better per-coin value.',
                'coin_amount' => 3000,
                'bonus_coins' => 600,
                'sort_order' => 40,
                'prices' => ['USD' => 4499, 'INR' => 374900],
                'original' => ['USD' => 5499, 'INR' => 459900],
            ],
            [
                'slug' => 'mega-pack',
                'name' => 'Mega Pack',
                'description' => 'Best value. A large reserve with the biggest bonus percentage.',
                'coin_amount' => 8000,
                'bonus_coins' => 2000,
                'sort_order' => 50,
                'prices' => ['USD' => 9999, 'INR' => 829900],
                'original' => ['USD' => 12999, 'INR' => 1079900],
            ],
            [
                'slug' => 'ultimate-pack',
                'name' => 'Ultimate Pack',
                'description' => 'For agencies and heavy automation — the deepest reserve and biggest savings.',
                'coin_amount' => 20000,
                'bonus_coins' => 6000,
                'sort_order' => 60,
                'prices' => ['USD' => 19999, 'INR' => 1659900],
                'original' => ['USD' => 27999, 'INR' => 2299900],
            ],
        ];

        foreach ($packages as $row) {
            $prices = $row['prices'];
            $original = $row['original'] ?? [];
            unset($row['prices'], $row['original']);

            // firstOrCreate keeps this seeder idempotent — admin edits
            // to existing packages survive re-runs of `db:seed`.
            $pkg = CoinPackage::firstOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['status' => 'active', 'is_archived' => false]),
            );

            foreach ($prices as $currency => $minor) {
                $this->seedPriceIfMissing($pkg, $currency, 'monthly', $minor);
            }

            // Sample original ("compare-at") prices so the strike-off
            // discount look is visible on a fresh install. Stored under the
            // dedicated `compare` slot; display-only (checkout charges the
            // live price).
            foreach ($original as $currency => $minor) {
                $this->seedPriceIfMissing($pkg, $currency, CoinPackage::COMPARE_CYCLE, $minor);
            }
        }
    }

    /**
     * Write a price row only when one does not already exist for this
     * (currency, billing cycle). This keeps the seeder non-destructive:
     * re-running `db:seed` tops up missing price rows but NEVER overwrites
     * an amount an admin has since edited. (`upsertFromMinor` uses
     * `updateOrCreate`, so calling it unconditionally would clobber edits —
     * hence the existence guard here.)
     */
    private function seedPriceIfMissing(CoinPackage $pkg, string $currency, string $cycle, int $minor): void
    {
        $exists = $pkg->prices()
            ->where('currency', $currency)
            ->where('billing_cycle', $cycle)
            ->exists();
        if (!$exists) {
            PricingResolver::upsertFromMinor($pkg, $currency, $cycle, $minor);
        }
    }
}
