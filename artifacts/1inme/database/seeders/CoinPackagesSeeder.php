<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\CoinPackage;
use App\Modules\Admin\Support\BillingFxRate;
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
 *
 * Lineup v2 (AI-credit formula)
 * ─────────────────────────────
 * 1 coin = $0.80 of AI credits + 20% platform margin → $0.96/coin customer price.
 * INR rate: admin-editable via the `billing.fx_rate_inr` app setting
 * (BillingFxRate; falls back to ₹90/$1 → ₹86.40/coin when unset).
 * Tax is NOT baked in; checkout adds GST/VAT on top.
 * Old v1 packages are archived (not deleted) so purchase history references survive.
 */
class CoinPackagesSeeder extends Seeder
{
    /**
     * Slugs that belong to the previous (v1) lineup. On each seeder run these
     * are moved to archived+inactive so they stop appearing in the shop, but
     * their rows (and any linked purchase records) are never deleted.
     */
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

    public function run(): void
    {
        // ── Step 1: archive the v1 lineup ────────────────────────────────────
        // Only touches rows that are still active/unarchived so repeated runs
        // are idempotent and admin overrides (e.g. manually re-activating one)
        // are NOT clobbered — we only ever move status in the "retire" direction.
        CoinPackage::whereIn('slug', self::LEGACY_SLUGS)
            ->where(function ($q) {
                $q->where('status', '!=', 'inactive')
                  ->orWhere('is_archived', false);
            })
            ->update(['status' => 'inactive', 'is_archived' => true]);

        // ── Step 2: seed the v2 lineup ────────────────────────────────────────
        // Formula: $0.96/coin (USD cents); INR prices are computed from the
        // admin-editable FX rate (BillingFxRate, fallback ₹90/$1 → ₹86.40/coin).
        // No bonus coins, no compare-at (original) prices per spec.
        $fxRate = BillingFxRate::get();
        $packages = [
            [
                'slug'        => 'ai-credits-10',
                'name'        => 'Micro Pack',
                'description' => '10 coins: a small top-up to try AI-powered features.',
                'coin_amount' => 10,
                'sort_order'  => 10,
                'prices'      => ['USD' => 960, 'INR' => BillingFxRate::usdMinorToInrMinor(960, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-50',
                'name'        => 'Starter Pack',
                'description' => '50 coins for occasional AI tasks and one-off boosts.',
                'coin_amount' => 50,
                'sort_order'  => 20,
                'prices'      => ['USD' => 4800, 'INR' => BillingFxRate::usdMinorToInrMinor(4800, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-100',
                'name'        => 'Basic Pack',
                'description' => '100 coins: a comfortable reserve for regular AI use.',
                'coin_amount' => 100,
                'sort_order'  => 30,
                'prices'      => ['USD' => 9600, 'INR' => BillingFxRate::usdMinorToInrMinor(9600, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-250',
                'name'        => 'Standard Pack',
                'description' => '250 coins for creators who rely on AI features daily.',
                'coin_amount' => 250,
                'sort_order'  => 40,
                'prices'      => ['USD' => 24000, 'INR' => BillingFxRate::usdMinorToInrMinor(24000, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-500',
                'name'        => 'Plus Pack',
                'description' => '500 coins: solid headroom for active AI-assisted workflows.',
                'coin_amount' => 500,
                'sort_order'  => 50,
                'prices'      => ['USD' => 48000, 'INR' => BillingFxRate::usdMinorToInrMinor(48000, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-1000',
                'name'        => 'Pro Pack',
                'description' => '1,000 coins for power users running frequent AI campaigns.',
                'coin_amount' => 1000,
                'sort_order'  => 60,
                'prices'      => ['USD' => 96000, 'INR' => BillingFxRate::usdMinorToInrMinor(96000, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-2000',
                'name'        => 'Growth Pack',
                'description' => '2,000 coins for teams scaling their AI-driven content.',
                'coin_amount' => 2000,
                'sort_order'  => 70,
                'prices'      => ['USD' => 192000, 'INR' => BillingFxRate::usdMinorToInrMinor(192000, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-3500',
                'name'        => 'Scale Pack',
                'description' => '3,500 coins: the sweet spot for high-volume AI usage.',
                'coin_amount' => 3500,
                'sort_order'  => 80,
                'prices'      => ['USD' => 336000, 'INR' => BillingFxRate::usdMinorToInrMinor(336000, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-5000',
                'name'        => 'Power Pack',
                'description' => '5,000 coins for agencies running continuous AI pipelines.',
                'coin_amount' => 5000,
                'sort_order'  => 90,
                'prices'      => ['USD' => 480000, 'INR' => BillingFxRate::usdMinorToInrMinor(480000, $fxRate)],
            ],
            [
                'slug'        => 'ai-credits-10000',
                'name'        => 'Enterprise Pack',
                'description' => '10,000 coins: maximum reserve for enterprise-scale AI automation.',
                'coin_amount' => 10000,
                'sort_order'  => 100,
                'prices'      => ['USD' => 960000, 'INR' => BillingFxRate::usdMinorToInrMinor(960000, $fxRate)],
            ],
        ];

        foreach ($packages as $row) {
            $prices = $row['prices'];
            unset($row['prices']);

            // firstOrCreate keeps this seeder idempotent — admin edits
            // to existing v2 packages survive re-runs of `db:seed`.
            $pkg = CoinPackage::firstOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'bonus_coins' => 0,
                    'status'      => 'active',
                    'is_archived' => false,
                ]),
            );

            foreach ($prices as $currency => $minor) {
                $this->seedPriceIfMissing($pkg, $currency, 'monthly', $minor);
            }
            // No compare-at (original) prices for the v2 lineup per spec.
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
