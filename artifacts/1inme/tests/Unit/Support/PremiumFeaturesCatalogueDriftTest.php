<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\PlanFormCatalogue;
use App\Modules\Common\Support\PremiumFeatures;
use PHPUnit\Framework\TestCase;

/**
 * Drift guard: every plan-gated knob the admin "Create / Edit Plan" form
 * exposes ({@see PlanFormCatalogue}) must carry a plain-English entry in the
 * canonical {@see PremiumFeatures::catalogue()}.
 *
 * The catalogue is the single source the public Premium Features grid, the
 * /pricing comparison and the mobile billing API all render from. Without
 * this guard a new quantity limit, feature flag or AI-suite toggle could be
 * added to the plan form (and start gating real behaviour) while silently
 * never appearing on any pricing surface. This is a pure unit test (no DB,
 * no app boot) so it runs even when the pgsql Feature suite is skipped.
 */
class PremiumFeaturesCatalogueDriftTest extends TestCase
{
    /** @return string[] every plan-gated key the admin form understands. */
    private function planFormKeys(): array
    {
        $keys = [];
        foreach (PlanFormCatalogue::quantityLimits() as $row) {
            $keys[] = $row['key'];
        }
        foreach (PlanFormCatalogue::featureFlags() as $row) {
            $keys[] = $row['key'];
        }
        foreach (PlanFormCatalogue::aiSuite() as $row) {
            $keys[] = $row['key'];
        }
        return array_values(array_unique($keys));
    }

    /** @return array<string,array<string,mixed>> catalogue keyed by feature key. */
    private function catalogueByKey(): array
    {
        $byKey = [];
        foreach (PremiumFeatures::catalogue() as $entry) {
            $byKey[$entry['key']] = $entry;
        }
        return $byKey;
    }

    public function test_every_plan_form_key_is_documented_in_the_catalogue(): void
    {
        $documented = $this->catalogueByKey();

        $missing = array_values(array_filter(
            $this->planFormKeys(),
            fn (string $key) => ! array_key_exists($key, $documented),
        ));

        $this->assertSame(
            [],
            $missing,
            'These plan-gated keys are exposed by PlanFormCatalogue but have no '
            . 'PremiumFeatures::catalogue() entry, so they never surface on the '
            . 'pricing / premium-features surfaces: ' . implode(', ', $missing),
        );
    }

    public function test_catalogue_entries_are_well_formed(): void
    {
        $seen = [];
        foreach (PremiumFeatures::catalogue() as $i => $entry) {
            foreach (['key', 'group', 'name', 'description'] as $field) {
                $this->assertArrayHasKey($field, $entry, "Catalogue entry #{$i} is missing '{$field}'.");
                $this->assertNotSame('', trim((string) $entry[$field]), "Catalogue entry #{$i} has an empty '{$field}'.");
            }
            $this->assertArrayNotHasKey(
                $entry['key'],
                $seen,
                "Duplicate catalogue key '{$entry['key']}'.",
            );
            $seen[$entry['key']] = true;
        }
    }

    public function test_resolve_cell_reads_plan_values(): void
    {
        $plan = (object) [
            'slug'     => 'pro',
            'features' => [
                'max_links'  => -1,
                'max_forms'  => 5,
                'pixels'     => true,
                'analytics'  => 'advanced',
                'max_events' => 0,
            ],
        ];

        $byKey = $this->catalogueByKey();

        $links = PremiumFeatures::resolveCell($plan, $byKey['max_links']);
        $this->assertTrue($links['on']);
        $this->assertTrue($links['unlimited']);
        $this->assertSame('Unlimited', $links['text']);

        $forms = PremiumFeatures::resolveCell($plan, $byKey['max_forms']);
        $this->assertTrue($forms['on']);
        $this->assertSame('5', $forms['text']);

        $pixels = PremiumFeatures::resolveCell($plan, $byKey['pixels']);
        $this->assertSame('bool', $pixels['kind']);
        $this->assertTrue($pixels['on']);

        $analytics = PremiumFeatures::resolveCell($plan, $byKey['analytics']);
        $this->assertSame('analytics', $analytics['kind']);
        $this->assertTrue($analytics['on']);
        $this->assertSame('Advanced', $analytics['text']);

        $events = PremiumFeatures::resolveCell($plan, $byKey['max_events']);
        $this->assertFalse($events['on']);

        $missing = PremiumFeatures::resolveCell($plan, $byKey['ecommerce']);
        $this->assertFalse($missing['on']);
    }
}
