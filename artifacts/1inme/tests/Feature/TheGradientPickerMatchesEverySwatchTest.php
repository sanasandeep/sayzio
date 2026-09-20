<?php

namespace Tests\Feature;

use App\Modules\User\Support\GradientCatalog;
use Tests\TestCase;

/**
 * The last picker drawing its own geometry.
 *
 * Every background swatch on this panel is a 9/14 portrait sized by one
 * shared auto-fill rule. The gradient preset catalog was not: it declared
 * `aspect-square` inside `grid-cols-3 sm:grid-cols-4 md:grid-cols-5`, so on
 * a desktop panel its swatches came out about twice the size of the ones
 * directly above them, in a different shape, and the grid went ragged
 * whenever that aspect utility did not apply.
 *
 * Same fix as the rest: use the shared rules rather than restate them. A
 * fixed column count is exactly what drifted the first time, which is why
 * the guard below is against declaring one at all.
 */
class TheGradientPickerMatchesEverySwatchTest extends TestCase
{
    private function picker(): string
    {
        return file_get_contents(
            base_path('resources/views/user/links/partials/gradient-catalog-picker.blade.php')
        );
    }

    /** The partial with its leading @php/docblock removed: markup only. */
    private function markup(): string
    {
        $source = $this->picker();
        $end    = strpos($source, '@endphp');

        return $end === false ? $source : substr($source, $end + 7);
    }

    /** It draws with the shared rules, not its own. */
    public function test_it_uses_the_shared_swatch_and_chip_rules(): void
    {
        $picker = $this->picker();

        foreach (['bg-swatch-grid', 'bg-lib-swatch', 'bg-lib-chip', 'bg-lib-fill'] as $shared) {
            $this->assertStringContainsString($shared, $picker,
                "the picker should reuse {$shared} rather than restate the geometry");
        }
    }

    /**
     * No private geometry: no square aspect, no fixed column count.
     *
     * Checked against the MARKUP only. The @php docblock names both mistakes
     * to explain why they are gone, and a test that cannot tell prose from
     * code would fail on its own explanation.
     */
    public function test_it_declares_no_geometry_of_its_own(): void
    {
        $picker = $this->markup();

        $this->assertStringNotContainsString('aspect-square', $picker,
            'a background fills a portrait page; square swatches preview the wrong shape');
        $this->assertDoesNotMatchRegularExpression('/grid-cols-\d/', $picker,
            'a fixed column count is what drifted the pickers apart the first time');
    }

    /** Chips carry counts and an All, like every other chip row. */
    public function test_the_chips_read_like_the_library_chips(): void
    {
        $picker = $this->picker();

        $this->assertStringContainsString("presetCat = 'all'", $picker, 'an All chip');
        $this->assertStringContainsString('bg-lib-n', $picker, 'each chip shows its count');

        // Every catalog category that has presets must be offered.
        $counts = [];
        foreach (GradientCatalog::all() as $p) {
            $counts[$p['category']] = ($counts[$p['category']] ?? 0) + 1;
        }
        $this->assertNotEmpty($counts);

        foreach (array_keys($counts) as $category) {
            $this->assertArrayHasKey($category, GradientCatalog::CATEGORIES,
                "presets are categorised '{$category}' but no chip label exists for it");
        }
    }

    /**
     * The point of these presets is unchanged: they load stops into the
     * builder. Losing that would turn the picker into a dead grid.
     */
    public function test_picking_still_loads_the_stops_into_the_builder(): void
    {
        $picker = $this->picker();

        foreach (['gradientStops =', 'gradientType  =', 'gradientAngle =', 'presetId      ='] as $assignment) {
            $this->assertStringContainsString($assignment, $picker,
                'picking a preset must still drive the gradient builder');
        }

        $this->assertStringContainsString('name="gradient_preset_id"', $picker,
            'the chosen preset id is what re-highlights the selection on edit');
        $this->assertStringContainsString("\$dispatch('change')", $picker,
            'without this the live preview never hears about the pick');
    }

    /** Every preset resolves to CSS, so no swatch can paint blank. */
    public function test_every_preset_resolves_to_css(): void
    {
        $blank = [];
        foreach (GradientCatalog::all() as $p) {
            $css = GradientCatalog::toCss($p);
            if (!is_string($css) || trim($css) === '' || !str_contains($css, 'gradient(')) {
                $blank[] = $p['id'] ?? '(no id)';
            }
        }

        $this->assertSame([], $blank,
            'these presets would render as empty swatches: '.implode(', ', $blank));
    }
}
