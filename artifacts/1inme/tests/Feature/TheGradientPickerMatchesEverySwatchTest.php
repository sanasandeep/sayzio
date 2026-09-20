<?php

namespace Tests\Feature;

use App\Modules\User\Support\BackgroundLibrary;
use App\Modules\User\Support\GradientCatalog;
use Tests\TestCase;

/**
 * The Colour tab's gradient presets: first a size bug, then a shape bug.
 *
 * ROUND ONE (#6233) was geometry. This picker declared `aspect-square`
 * inside `grid-cols-3 sm:grid-cols-4 md:grid-cols-5`, so on a desktop panel
 * its swatches came out about twice the size of the ones directly above
 * them, in the wrong shape, and the grid went ragged whenever that aspect
 * utility did not apply. The fix was to use the card's shared rules.
 *
 * ROUND TWO is why that fix was not enough. Making every picker draw alike
 * is what finally made the real problem visible: Colour and Style now
 * opened on the SAME shape -- a chip row over a scrolling swatch grid -- so
 * they read as one feature shown twice. Worse, two of the chip names were
 * literally in both rows over different sets of gradients: Neon and
 * Abstract. Consistency did not cause the duplication, but it was what made
 * the duplication impossible to miss.
 *
 * So the 166 presets moved into the Style library, where all the other
 * ready-made looks already live, and this partial became what a BUILDER
 * actually needs: one short row of starting points. A different shape,
 * because it does a different job.
 *
 * These tests guard both rounds: no private geometry (round one), and one
 * row rather than a second library (round two).
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

    /** It draws with the card's shared swatch rules, not its own. */
    public function test_it_uses_the_shared_swatch_rules(): void
    {
        $picker = $this->picker();

        foreach (['bg-lib-swatch', 'bg-lib-fill', 'bg-lib-tick'] as $shared) {
            $this->assertStringContainsString($shared, $picker,
                "the strip should reuse {$shared} rather than restate the geometry");
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

    /**
     * ROUND TWO. One row of starting points, not a second library.
     *
     * The chip row and the scrolling grid are the two things that made this
     * look like the Style library wearing a different hat. Both are gone,
     * and the card must hold exactly one chip row now -- the library's.
     */
    public function test_it_is_a_strip_rather_than_a_second_library(): void
    {
        $picker = $this->markup();

        $this->assertStringContainsString('bg-quick-strip', $picker,
            'the starting points are one scrolling row');
        $this->assertStringNotContainsString('bg-swatch-grid', $picker,
            'a grid here is what made Colour read as a copy of Style');
        $this->assertStringNotContainsString('bg-lib-chip', $picker,
            'the card may hold exactly one chip row, and it belongs to the library');

        // And in the card: the Colour and Style half holds exactly one chip
        // row, the library's. (Media has one too, over image folders, but
        // that is a different tab and a different vocabulary.)
        $card = file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );
        $styleHalf = substr($card, 0, strpos($card, "bgType === 'image'"));

        $this->assertSame(1, substr_count($styleHalf, 'class="bg-lib-chips'),
            'two chip rows over two sets of gradients is the reported duplication');
    }

    /** A short row. Showing all 166 in it would just be the grid again. */
    public function test_the_row_is_short_enough_to_be_a_row(): void
    {
        $featured = array_filter(GradientCatalog::all(), fn ($p) => $p['category'] === 'featured');

        $this->assertNotEmpty($featured, 'the strip is built from the featured presets');
        $this->assertLessThanOrEqual(20, count($featured),
            'more than a row of starting points is a grid wearing a scrollbar');
    }

    /**
     * Nothing was lost by moving them: every preset is in the library, under
     * Gradients, carrying the stops that load this builder.
     */
    public function test_every_preset_still_exists_in_the_style_library(): void
    {
        $inLibrary = [];
        foreach (BackgroundLibrary::items(collect()) as $item) {
            if ($item['type'] === 'gradient') {
                $inLibrary[$item['value']] = $item;
            }
        }

        foreach (GradientCatalog::all() as $preset) {
            $this->assertArrayHasKey($preset['id'], $inLibrary,
                "{$preset['id']} vanished when the presets moved into the library");
            $this->assertSame('gradients', $inLibrary[$preset['id']]['category']);
            $this->assertSame($preset['stops'], $inLibrary[$preset['id']]['gradient']['stops'],
                'a library pick must load the same colours the preset always had');
        }
    }

    /**
     * The mood names (Warm, Cool, Pastel...) stopped being chips. They must
     * still be findable, or dropping that row would have lost a way in.
     */
    public function test_the_mood_names_survive_as_search_words(): void
    {
        $bySearch = [];
        foreach (BackgroundLibrary::items(collect()) as $item) {
            if ($item['type'] === 'gradient') {
                $bySearch[$item['value']] = $item['search'];
            }
        }

        foreach (GradientCatalog::all() as $preset) {
            $mood = mb_strtolower(GradientCatalog::CATEGORIES[$preset['category']] ?? '');

            $this->assertStringContainsString($mood, $bySearch[$preset['id']],
                "typing \"{$mood}\" has to keep finding {$preset['id']}");
        }
    }

    /**
     * The point of these presets is unchanged: they load stops into the
     * builder. Losing that would turn the strip into a dead row.
     */
    public function test_picking_still_loads_the_stops_into_the_builder(): void
    {
        $picker = $this->picker();

        foreach ([
            'gradientStops    =', 'gradientType     =',
            'gradientAngle    =', 'gradientPresetId =',
        ] as $assignment) {
            $this->assertStringContainsString($assignment, $picker,
                'picking a start must still drive the gradient builder');
        }

        $this->assertStringContainsString("\$dispatch('change')", $picker,
            'without this the live preview never hears about the pick');
    }

    /**
     * The preset id is written from two places now -- this strip and the
     * library -- so it needs exactly one input, on the card.
     */
    public function test_the_preset_id_has_one_home(): void
    {
        $card = file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );

        $this->assertStringNotContainsString('name="gradient_preset_id"', $this->picker(),
            'the field moved to the card when the library started writing it too');
        $this->assertSame(1, substr_count($card, 'name="gradient_preset_id"'),
            'one field written from two places still needs one input');
        $this->assertStringContainsString('gradientPresetId: @json($gradientPresetIdVal)', $card,
            'the id belongs to the shared bgSettings() state, not a nested scope');
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
