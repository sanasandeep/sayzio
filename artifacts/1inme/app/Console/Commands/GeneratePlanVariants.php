<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\PlanVariantNames;
use App\Modules\Common\Support\PricingPageCache;
use App\Services\PricingResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Fills each plan category out to a full set of variants.
 *
 * A category is a bucket of near-identical plans competing for one slot on
 * /pricing. One exists today (the anchor); this creates the rest from it.
 *
 * What a variant inherits, and what it does not:
 *  - FEATURES ARE COPIED VERBATIM from the anchor. That is deliberate. The
 *    whole premise is that a visitor shown any variant gets materially the
 *    same product; the moment siblings drift on a cap or a module toggle,
 *    two customers on "the same plan" have different software and support
 *    has no way to explain it. Vary features later, on purpose, one key at
 *    a time — not as a side effect of generating ninety rows.
 *  - PRICE varies within ±15% of the anchor (PlanVariantNames::multipliers).
 *  - NAME comes from the category's pool, so nothing reads as "Plan B".
 *
 * Every variant is created with is_category_default = false. The anchor stays
 * the default, so generating variants changes nothing a visitor sees until
 * someone promotes one.
 *
 * Idempotent: a slug that already exists is skipped, so re-running tops a
 * category up rather than duplicating it.
 *
 *   php artisan plans:generate-variants --dry-run
 *   php artisan plans:generate-variants --category=bio-entry --count=5
 */
class GeneratePlanVariants extends Command
{
    protected $signature = 'plans:generate-variants
        {--category=* : Only these categories (default: every category with a name pool)}
        {--count=10 : Total variants per category, anchor included}
        {--dry-run : Print what would be created and write nothing}';

    protected $description = 'Create the near-identical plan variants that each category competes with';

    public function handle(): int
    {
        $pools = PlanVariantNames::pools();
        $multipliers = PlanVariantNames::multipliers();
        $only = (array) $this->option('category');
        $count = max(1, (int) $this->option('count'));
        $dry = (bool) $this->option('dry-run');

        if ($only) {
            $unknown = array_diff($only, array_keys($pools));
            if ($unknown) {
                $this->error('No name pool for: '.implode(', ', $unknown));
                $this->line('Known: '.implode(', ', array_keys($pools)));

                return self::FAILURE;
            }
            $pools = array_intersect_key($pools, array_flip($only));
        }

        $created = 0;
        $skipped = 0;

        foreach ($pools as $category => $names) {
            $anchor = Plan::with('prices')
                ->where('plan_category', $category)
                ->where('is_archived', false)
                ->orderByDesc('is_category_default')
                ->orderBy('sort_order')
                ->first();

            if (! $anchor) {
                $this->warn("{$category}: no plan to copy from, skipped");
                continue;
            }

            $this->line("<fg=cyan>{$category}</> (from {$anchor->name})");

            for ($i = 1; $i < min($count, count($names)); $i++) {
                $name = $names[$i];
                $slug = $this->slugFor($category, $name);

                if (Plan::where('slug', $slug)->exists()) {
                    $skipped++;
                    $this->line("  · {$name} ({$slug}) already exists");
                    continue;
                }

                $multiplier = $multipliers[$i] ?? 1.0;
                $priceRows = $this->scaledPrices($anchor, $multiplier);

                if (! $priceRows) {
                    $this->warn("  ! {$name}: anchor has no price rows, skipped");
                    continue;
                }

                $major = fn (string $cur, string $cycle) => ($priceRows[$cur.'|'.$cycle] ?? 0) / 100;
                $monthly = $major('USD', 'monthly');
                $annual = $major('USD', 'annual');

                $this->line(sprintf(
                    '  + %-22s %-26s $%s / $%s  ·  Rs %s / Rs %s',
                    $name,
                    $slug,
                    number_format($monthly, 2),
                    number_format($annual, 2),
                    number_format($major('INR', 'monthly'), 2),
                    number_format($major('INR', 'annual'), 2),
                ));

                if ($dry) {
                    $created++;
                    continue;
                }

                $variant = Plan::create([
                    'name' => $name,
                    'slug' => $slug,
                    'plan_category' => $category,
                    'is_category_default' => false,
                    'description' => $anchor->description,
                    'monthly_price' => $monthly,
                    'annual_price' => $annual,
                    // Legacy decimal columns. They are NOT what /pricing
                    // reads -- the polymorphic `prices` table is -- but the
                    // admin edit form prefills from them, so they have to
                    // agree with the rows written below or a curator opening
                    // a variant sees one price and the public page another.
                    'monthly_price_secondary' => $major('INR', 'monthly'),
                    'annual_price_secondary' => $major('INR', 'annual'),
                    'trial_days' => $anchor->trial_days,
                    'features' => $anchor->features,
                    'metadata' => array_merge((array) $anchor->metadata, ['variant_of' => $anchor->slug]),
                    'is_default' => false,
                    'is_popular' => false,
                    'status' => 'active',
                    'is_archived' => false,
                    'is_internal' => false,
                    'sort_order' => (int) $anchor->sort_order,
                ]);

                // THE line that makes a variant cost anything. Without it
                // the plan row exists, the admin screen looks right, and
                // every public surface renders $0.00 / Rs 0.00, because
                // PricingResolver reads the `prices` table and has no
                // fallback to the decimal columns above ("missing INR row ->
                // Rs 0.00", by design).
                PricingResolver::upsertManyFromMinor($variant, array_map(
                    fn (string $key) => [explode('|', $key)[0], explode('|', $key)[1], $priceRows[$key]],
                    array_keys($priceRows),
                ));

                $created++;
            }
        }

        if ($dry) {
            $this->newLine();
            $this->info("Dry run: {$created} would be created, {$skipped} already there. Nothing written.");

            return self::SUCCESS;
        }

        PricingPageCache::flush();

        $this->newLine();
        $this->info("Created {$created}, skipped {$skipped}. Pricing cache flushed.");
        $this->line('Every variant is is_category_default = false, so nothing a visitor sees has changed yet.');

        return self::SUCCESS;
    }

    /**
     * Slugs are prefixed with the category so a plan's bucket is readable in
     * a checkout URL or a Stripe dashboard without a lookup, and so two
     * categories can both have a "Pro" without colliding.
     */
    private function slugFor(string $category, string $name): string
    {
        return Str::slug($category.'-'.$name);
    }

    /**
     * Scale EVERY price the anchor actually has, in minor units.
     *
     * Two things were wrong with multiplying the decimal `monthly_price`
     * column instead.
     *
     * The first is that nothing public reads that column, so a variant built
     * from it had no price at all. The prices live in the polymorphic
     * `prices` table, one row per (currency, cycle), and that is what gets
     * copied and scaled here -- whatever currencies the anchor carries, the
     * variant carries.
     *
     * The second is rounding. The old helper rounded to a whole currency
     * unit, which is fine for a Rs 167 plan and destroys a $4 one: every
     * multiplier from 0.88 to 1.15 lands back on $4, so a whole category of
     * variants came out identically priced with different names. That is the
     * one shape this feature must never have -- it reads as a trick rather
     * than a recommendation, and it would have hit the bio plans hardest,
     * which is where the paying customers are.
     *
     * So rounding happens in minor units, to the nearest half unit. $4.00
     * spreads to $3.50 / $4.00 / $4.50 and Rs 167 to Rs 147 ... Rs 192 --
     * prices a person could plausibly see on a page, in both currencies, at
     * both ends of the catalogue.
     *
     * @return array<string, int>  "CUR|cycle" => minor units
     */
    private function scaledPrices(Plan $anchor, float $multiplier): array
    {
        $out = [];

        foreach ($anchor->prices as $price) {
            if (! $price->is_active) {
                continue;
            }

            $base = (int) $price->amount_minor_units;
            if ($base <= 0) {
                // A free or unpriced row scales to itself. Multiplying zero
                // and then flooring to a minimum would invent a price for a
                // plan that deliberately has none.
                $out[$price->currency.'|'.$price->billing_cycle] = 0;
                continue;
            }

            $scaled = (int) round(($base * $multiplier) / 50) * 50;
            $out[$price->currency.'|'.$price->billing_cycle] = max(50, $scaled);
        }

        return $out;
    }
}
