<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\PlanFormCatalogue;
use Database\Seeders\PlansAndAddonsSeeder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Coverage guard: every plan the seeder ships must explicitly assign a value
 * for each per-plan quantity limit ({@see PlanFormCatalogue::quantityLimits()})
 * and each AI-suite boolean ({@see PlanFormCatalogue::aiSuite()}).
 *
 * Why this exists: a plan feature that a plan's seed data forgets does NOT
 * become "off" — {@see \App\Modules\User\Models\User::getPlanFeature()} falls
 * back to whatever default the call site happens to pass (often -1 / unlimited
 * or true). So a plan silently missing `max_store_menu`, `max_calendars`, an AI
 * agent toggle, etc. quietly grants the feature in production instead of gating
 * it — the exact "default-on / unlimited" bug class this work removes.
 *
 * The sibling {@see PremiumFeaturesCatalogueDriftTest} only proves a catalogue
 * key is *documented*; this test proves every catalogue key is actually *seeded*
 * for every plan. Like that test it is a pure array comparison: it reads the
 * seeder's static feature definitions directly (no DB, no app boot) so it runs
 * even when the pgsql Feature suite is skipped.
 */
class PlanSeedFeatureCoverageTest extends TestCase
{
    /**
     * The fully-merged feature map for every plan slug, mirroring exactly what
     * {@see PlansAndAddonsSeeder::seedPlans()} writes: each tier's per-plan AI
     * limits ({@see PlansAndAddonsSeeder::aiFeatureLimits()}) merged under its
     * own `features` array (the per-tier values win, same as production).
     *
     * @return array<string, array<string, mixed>> slug => merged feature map
     */
    private function mergedPlanFeatures(): array
    {
        // planDefinitions() is private (it's only ever consumed inside the
        // seeder); read it via reflection so the test stays a pure array
        // comparison without widening the production API surface.
        $method = new ReflectionMethod(PlansAndAddonsSeeder::class, 'planDefinitions');
        $method->setAccessible(true);
        $defs = $method->invoke(new PlansAndAddonsSeeder());

        $aiLimits = PlansAndAddonsSeeder::aiFeatureLimits();

        $bySlug = [];
        foreach ($defs as $def) {
            $slug = $def['slug'];
            $extra = $aiLimits[$slug] ?? [];
            // Per-tier features win over the shared AI limits — identical to
            // the array_merge order in seedPlans().
            $bySlug[$slug] = array_merge($extra, $def['features'] ?? []);
        }

        return $bySlug;
    }

    /** @return string[] every per-plan quantity-limit key the plan form exposes. */
    private function quantityLimitKeys(): array
    {
        return array_map(
            static fn (array $row): string => $row['key'],
            PlanFormCatalogue::quantityLimits(),
        );
    }

    /** @return string[] every AI-suite boolean key the plan form exposes. */
    private function aiSuiteKeys(): array
    {
        return array_map(
            static fn (array $row): string => $row['key'],
            PlanFormCatalogue::aiSuite(),
        );
    }

    public function test_the_test_can_read_every_plan(): void
    {
        $merged = $this->mergedPlanFeatures();

        // Sanity: the lineup must be non-empty, otherwise the assertions below
        // would vacuously pass and the guard would be useless.
        $this->assertNotEmpty($merged, 'No plan definitions were read from the seeder.');
        $this->assertArrayHasKey('free', $merged, 'Expected the default `free` plan in the seeder lineup.');
    }

    public function test_every_plan_seeds_every_quantity_limit(): void
    {
        $this->assertEveryPlanDefines(
            $this->quantityLimitKeys(),
            'quantity limit',
        );
    }

    public function test_every_plan_seeds_every_ai_suite_toggle(): void
    {
        $this->assertEveryPlanDefines(
            $this->aiSuiteKeys(),
            'AI-suite toggle',
        );
    }

    /**
     * Assert every plan slug defines (via `array_key_exists`) a value for each
     * of the given catalogue keys. A `null`/`false`/`0` value is fine — that's
     * an explicit decision; only a *missing* key is the silent-fallback bug.
     *
     * @param string[] $keys
     */
    private function assertEveryPlanDefines(array $keys, string $label): void
    {
        $merged = $this->mergedPlanFeatures();

        $gaps = [];
        foreach ($merged as $slug => $features) {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $features)) {
                    $gaps[] = "{$slug} → {$key}";
                }
            }
        }

        $this->assertSame(
            [],
            $gaps,
            "These plans are missing a {$label} in their seed data, so the "
            . 'feature silently falls back to its call-site default (often '
            . 'unlimited / on) in production instead of being gated: '
            . implode(', ', $gaps),
        );
    }
}
