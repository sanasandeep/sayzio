<?php

namespace Tests\Feature;

use App\Modules\User\Support\BackgroundImageGallery;
use App\Modules\User\Support\BackgroundLibrary;
use App\Modules\User\Support\GradientCatalog;
use Tests\TestCase;

/**
 * "These cats and design seems repeate."
 *
 * Every previous fix on this panel pushed toward CONSISTENCY: one swatch
 * shape, one chip style, one grid rule, so no picker drew differently from
 * its neighbour. Each of those was right on its own, and together they
 * produced the reported bug. Once Colour, Style and Media all opened on a
 * chip row above a scrolling swatch grid, three different jobs looked like
 * one job done three times -- and two chip names, Neon and Abstract, were
 * literally in two of the rows at once over different sets of gradients.
 *
 * Consistency of COMPONENTS is worth keeping. Consistency of SHAPE is what
 * had to go. Each tab now takes the shape of its job:
 *
 *     Colour   a builder   result first, stops biggest, one row of starts
 *     Style    a library   the only chip row in the card, over one list
 *     Media    your file   dropzone first, ours behind one line
 *
 * These tests are about that difference surviving. They are deliberately
 * shape-level rather than pixel-level: the failure being guarded against is
 * "two tabs look like the same feature", which is structural.
 */
class EachBackgroundTabHasItsOwnShapeTest extends TestCase
{
    private function card(): string
    {
        return file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );
    }

    /** Everything above the Image panel; where Colour and Style live. */
    private function styleHalf(): string
    {
        $card = $this->card();

        return substr($card, 0, strpos($card, '{{-- IMAGE --}}'));
    }

    /**
     * The duplication itself: one chip row in the whole card, and one grid
     * of ready-made looks. Two of either is the bug coming back.
     */
    public function test_the_card_holds_exactly_one_chip_row_and_one_library_grid(): void
    {
        $card = $this->card();

        $this->assertSame(2, substr_count($card, 'class="bg-lib-chips'),
            'exactly two chip rows may exist: the Style library, and the image '
            . 'gallery folders inside Media. A third is the reported repeat');

        // ...and they are in different tabs, which is what makes two legible.
        $this->assertSame(1, substr_count($this->styleHalf(), 'class="bg-lib-chips'),
            'Colour and Style may not both open on a chip row');
    }

    /** Each tab says what it is FOR, right under its name. */
    public function test_each_tab_carries_its_job_under_its_name(): void
    {
        $card = $this->card();

        foreach (["sub: 'build one'", "sub: 'pick one'", "sub: 'use your own'"] as $sub) {
            $this->assertStringContainsString($sub, $card,
                'without the sub-line, Colour and Style are two identical words');
        }
        $this->assertStringContainsString('bg-group-sub', $card, 'and it must render');
    }

    /**
     * Colour is a builder. The result comes before the controls, the stops
     * are the main event, and the presets are a row -- not a grid.
     */
    public function test_colour_is_shaped_like_a_builder(): void
    {
        $card = $this->card();

        $gradient = substr($card, strpos($card, '{{-- GRADIENT'));
        $gradient = substr($gradient, 0, strpos($gradient, '{{-- IMAGE --}}'));

        $this->assertStringContainsString('bg-grad-preview', $gradient,
            'a builder shows what it is building');
        $this->assertLessThan(
            strpos($gradient, 'Colour stops'),
            strpos($gradient, 'bg-grad-preview'),
            'the result comes before the controls that make it'
        );
        $this->assertGreaterThan(
            strpos($gradient, 'Colour stops'),
            strpos($gradient, 'gradient-catalog-picker'),
            'starting points belong at the bottom of a builder, not the top'
        );
        $this->assertStringNotContainsString('bg-swatch-grid', $gradient,
            'a swatch grid in Colour is the Style library drawn twice');
    }

    /**
     * Media leads with the user's own file. Ours is one line, and the 440
     * images are only fetched when someone opens it.
     */
    public function test_media_leads_with_your_own_file(): void
    {
        $card  = $this->card();
        $image = substr($card, strpos($card, '{{-- IMAGE --}}'));
        $image = substr($image, 0, strpos($image, '{{-- SLIDESHOW --}}'));

        $this->assertLessThan(
            strpos($image, 'galVisible()'),
            strpos($image, 'dropzone-input'),
            'the tab is called "use your own"; the upload goes first'
        );
        $this->assertStringContainsString('bg-disclosure', $image,
            'ours sits behind one line rather than a second full picker');
        $this->assertStringNotContainsString('x-init="galLoad()"', $image,
            '440 images should not be fetched before anyone asks for them');
        $this->assertMatchesRegularExpression('/galShow\s*&&\s*!galAssets\.length/', $image,
            'opening the disclosure is what loads the gallery, once');
    }

    /**
     * A gradient picked in the library lands in the builder, editable.
     *
     * This is the payoff for merging the two surfaces rather than just
     * hiding one: before, a preset in Colour was editable and a gradient
     * look in Style was not, which is a difference no one could see.
     */
    public function test_a_library_gradient_loads_the_builder(): void
    {
        $card = $this->card();

        foreach ([
            'gradientStops    =', 'gradientType     =',
            'gradientAngle    =', 'gradientPresetId =',
        ] as $assignment) {
            $this->assertStringContainsString($assignment, $card,
                'picking a gradient in the library must fill the Colour builder');
        }

        // Every gradient entry carries what the builder needs.
        foreach (BackgroundLibrary::items(collect()) as $item) {
            if ($item['type'] !== 'gradient') {
                continue;
            }
            $this->assertArrayHasKey('gradient', $item, "{$item['value']} carries no stops");
            $this->assertNotEmpty($item['gradient']['stops']);
        }
    }

    /** Hand-editing the builder drops the preset highlight, as torn does. */
    public function test_editing_the_builder_stops_claiming_a_preset(): void
    {
        $card = $this->card();

        $this->assertGreaterThanOrEqual(3, substr_count($card, "gradientPresetId = ''"),
            'changing a stop, the angle or the type means it is yours now, '
            . 'not that preset -- the same honesty matchTornCombo() already has');
    }

    /**
     * No category name may appear in more than one tab. This is the reported
     * bug stated as a rule over the three tabs' own vocabularies.
     */
    public function test_no_name_appears_in_two_tabs(): void
    {
        // Colour's chips are gone, so its vocabulary is now just its types.
        $colour = ['Solid colour', 'Gradient'];
        $style  = array_values(BackgroundLibrary::CATEGORIES);
        $media  = array_values(BackgroundImageGallery::FOLDERS);

        $dupes = array_keys(array_filter(
            array_count_values(array_merge($colour, $style, $media)),
            fn ($n) => $n > 1
        ));

        $this->assertSame([], $dupes,
            'these names are offered in more than one tab: '.implode(', ', $dupes));
    }

    /**
     * The gradient MOOD names used to be Colour's chip row, and two of them
     * collided with Style's categories. They must not be chips anywhere.
     */
    public function test_the_gradient_moods_are_not_a_chip_row_any_more(): void
    {
        $card = $this->card();
        $picker = file_get_contents(
            base_path('resources/views/user/links/partials/gradient-catalog-picker.blade.php')
        );

        // The two that literally appeared in both rows.
        foreach (['Neon', 'Abstract'] as $collision) {
            $this->assertContains($collision, array_values(BackgroundLibrary::CATEGORIES),
                "{$collision} should exist -- once, in the library");
            $this->assertArrayHasKey(mb_strtolower($collision), GradientCatalog::CATEGORIES,
                'the catalog still groups presets this way internally');
        }

        $this->assertStringNotContainsString("presetCat", $card.$picker,
            'the mood chips are what made Colour look like a second library');
    }
}
