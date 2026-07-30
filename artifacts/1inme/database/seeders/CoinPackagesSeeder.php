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
 * Lineup v3 (8 named tiers)
 * ─────────────────────────
 * Starter $10 → Ultimate $2,500, with bonus coins from Basic upward and a
 * customer-facing "Best for" audience label per tier. Each tier also carries
 * a HIDDEN internal allocation (`api_budget_pct`): the % of the price
 * budgeted for API costs; platform margin is the remaining 100 − pct.
 * The allocation is never shown on any user-facing surface — it only feeds
 * the admin-only per-purchase revenue-split snapshot.
 *
 * INR rate: admin-editable via the `billing.fx_rate_inr` app setting
 * (BillingFxRate; falls back to ₹90/$1 when unset). Tax is NOT baked in;
 * checkout adds GST/VAT on top.
 * Old v1 + v2 packages are archived (not deleted) so purchase-history
 * references survive.
 */
class CoinPackagesSeeder extends Seeder
{
    /**
     * Slugs that belong to previous lineups (v1 named packs + v2 ai-credits
     * formula packs). On each seeder run these are moved to archived+inactive
     * so they stop appearing in the shop, but their rows (and any linked
     * purchase records) are never deleted.
     */
    public const LEGACY_SLUGS = [
        // v1
        'starter-pack',
        'mini-pack',
        'value-pack',
        'creator-pack',
        'growth-pack',
        'pro-pack',
        'mega-pack',
        'ultimate-pack',
        // v2
        'ai-credits-10',
        'ai-credits-50',
        'ai-credits-100',
        'ai-credits-250',
        'ai-credits-500',
        'ai-credits-1000',
        'ai-credits-2000',
        'ai-credits-3500',
        'ai-credits-5000',
        'ai-credits-10000',
    ];

    /**
     * The v3 lineup. `coin_amount` is the BASE coins (total − bonus) so
     * total_coins = coin_amount + bonus_coins matches the advertised total.
     * `usd` is the customer price in cents. `api_budget_pct` is the hidden
     * internal API-cost allocation; margin is always 100 − pct.
     */
    public const LINEUP = [
        [
            'slug'           => 'coins-starter',
            'name'           => 'Starter',
            'best_for'       => 'Trying AI',
            'description'    => '7,000 coins to take Sayzio AI for a spin.',
            'coin_amount'    => 7000,
            'bonus_coins'    => 0,
            'usd'            => 1000,
            'api_budget_pct' => 70,
            'sort_order'     => 10,
        ],
        [
            'slug'           => 'coins-basic',
            'name'           => 'Basic',
            'best_for'       => 'Casual users',
            'description'    => '15,000 coins (incl. 1,000 bonus) for light, everyday AI use.',
            'coin_amount'    => 14000,
            'bonus_coins'    => 1000,
            'usd'            => 2000,
            'api_budget_pct' => 72,
            'sort_order'     => 20,
        ],
        [
            'slug'           => 'coins-standard',
            'name'           => 'Standard',
            'best_for'       => 'Regular users',
            'description'    => '23,000 coins (incl. 2,000 bonus) for steady weekly AI workflows.',
            'coin_amount'    => 21000,
            'bonus_coins'    => 2000,
            'usd'            => 3000,
            'api_budget_pct' => 74,
            'sort_order'     => 30,
        ],
        [
            'slug'           => 'coins-pro',
            'name'           => 'Pro',
            'best_for'       => 'Professionals',
            'description'    => '80,000 coins (incl. 10,000 bonus) for professionals who use AI daily.',
            'coin_amount'    => 70000,
            'bonus_coins'    => 10000,
            'usd'            => 10000,
            'api_budget_pct' => 78,
            'sort_order'     => 40,
        ],
        [
            'slug'           => 'coins-business',
            'name'           => 'Business',
            'best_for'       => 'Small teams',
            'description'    => '205,000 coins (incl. 30,000 bonus) to keep a small team running on AI.',
            'coin_amount'    => 175000,
            'bonus_coins'    => 30000,
            'usd'            => 25000,
            'api_budget_pct' => 80,
            'sort_order'     => 50,
        ],
        [
            'slug'           => 'coins-enterprise',
            'name'           => 'Enterprise',
            'best_for'       => 'Growing businesses',
            'description'    => '420,000 coins (incl. 70,000 bonus) for growing businesses scaling AI output.',
            'coin_amount'    => 350000,
            'bonus_coins'    => 70000,
            'usd'            => 50000,
            'api_budget_pct' => 82,
            'sort_order'     => 60,
        ],
        [
            'slug'           => 'coins-scale',
            'name'           => 'Scale',
            'best_for'       => 'Large organizations',
            'description'    => '850,000 coins (incl. 150,000 bonus) for large organizations with heavy AI pipelines.',
            'coin_amount'    => 700000,
            'bonus_coins'    => 150000,
            'usd'            => 100000,
            'api_budget_pct' => 84,
            'sort_order'     => 70,
        ],
        [
            'slug'           => 'coins-ultimate',
            'name'           => 'Ultimate',
            'best_for'       => 'Enterprise AI',
            'description'    => '2,150,000 coins (incl. 400,000 bonus): the maximum reserve for enterprise-scale AI automation.',
            'coin_amount'    => 1750000,
            'bonus_coins'    => 400000,
            'usd'            => 250000,
            'api_budget_pct' => 85,
            'sort_order'     => 80,
        ],
    ];

    public function run(): void
    {
        // ── Step 1: archive the previous lineups ────────────────────────────
        // Only touches rows that are still active/unarchived so repeated runs
        // are idempotent and admin overrides (e.g. manually re-activating one)
        // are NOT clobbered — we only ever move status in the "retire" direction.
        CoinPackage::whereIn('slug', self::LEGACY_SLUGS)
            ->where(function ($q) {
                $q->where('status', '!=', 'inactive')
                  ->orWhere('is_archived', false);
            })
            ->update(['status' => 'inactive', 'is_archived' => true]);

        // ── Step 2: seed the v3 lineup ───────────────────────────────────────
        $fxRate = BillingFxRate::get();

        foreach (self::LINEUP as $row) {
            $usdMinor = $row['usd'];
            unset($row['usd']);

            // firstOrCreate keeps this seeder idempotent — admin edits
            // to existing packages survive re-runs of `db:seed`.
            $pkg = CoinPackage::firstOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'status'      => 'active',
                    'is_archived' => false,
                ]),
            );

            $this->seedPriceIfMissing($pkg, 'USD', 'monthly', $usdMinor);
            $this->seedPriceIfMissing($pkg, 'INR', 'monthly', BillingFxRate::usdMinorToInrMinor($usdMinor, $fxRate));
            // No compare-at (original) prices for the v3 lineup per spec.

            // Backfill the internal allocation + label on rows that pre-date
            // those columns (or were created before this seeder version) but
            // never overwrite an admin-set value.
            $dirty = [];
            if ($pkg->api_budget_pct === null) {
                $dirty['api_budget_pct'] = $row['api_budget_pct'];
            }
            if ($pkg->best_for === null) {
                $dirty['best_for'] = $row['best_for'];
            }
            if ($dirty) {
                $pkg->forceFill($dirty)->save();
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
