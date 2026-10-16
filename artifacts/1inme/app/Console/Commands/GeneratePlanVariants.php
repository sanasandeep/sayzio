<?php

namespace App\Console\Commands;

use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Support\PlanVariantNames;
use App\Modules\Common\Support\PricingPageCache;
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
            $anchor = Plan::where('plan_category', $category)
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
                $monthly = $this->money((float) $anchor->monthly_price, $multiplier);
                $annual = $this->money((float) $anchor->annual_price, $multiplier);

                $this->line(sprintf(
                    '  + %-22s %-26s %s / %s',
                    $name,
                    $slug,
                    number_format($monthly, 2),
                    number_format($annual, 2),
                ));

                if ($dry) {
                    $created++;
                    continue;
                }

                Plan::create([
                    'name' => $name,
                    'slug' => $slug,
                    'plan_category' => $category,
                    'is_category_default' => false,
                    'description' => $anchor->description,
                    'monthly_price' => $monthly,
                    'annual_price' => $annual,
                    'monthly_price_secondary' => $this->nullableMoney($anchor->monthly_price_secondary, $multiplier),
                    'annual_price_secondary' => $this->nullableMoney($anchor->annual_price_secondary, $multiplier),
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

    /** Round to a whole currency unit: fractional variant pricing reads as a bug. */
    private function money(float $base, float $multiplier): float
    {
        if ($base <= 0.0) {
            return 0.0;
        }

        return (float) max(1, round($base * $multiplier));
    }

    private function nullableMoney(mixed $base, float $multiplier): ?float
    {
        if ($base === null) {
            return null;
        }

        $value = (float) $base;

        return $value <= 0.0 ? $value : (float) max(1, round($value * $multiplier));
    }
}
