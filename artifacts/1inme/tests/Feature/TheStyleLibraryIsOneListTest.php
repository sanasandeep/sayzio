<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BgTemplate;
use App\Modules\User\Support\BackgroundLibrary;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reported bug was "background categories are repeated", and it survived
 * a redesign because the redesign grouped the pickers instead of merging them.
 *
 * Six pickers over six catalogs is the duplication. Presets, Mesh, Pattern,
 * Tiles, Torn Paper and Template all asked "pick a ready-made look", and
 * because each was written on its own day over its own catalog, the same
 * words landed at two levels at once:
 *
 *     Mesh      a picker of 10   AND a Template chip of 100
 *     Patterns  a picker of 12   AND a Template chip of 92  AND a preset group
 *     Gradients a Template chip of 129 while "Gradient" sat over in Colour
 *
 * Putting a Colour/Style/Media switch above them made that legible. It did
 * not remove it. One library does: the chips filter a single merged list, so
 * a category name exists exactly once and holds every look of that kind
 * whichever catalog it came from.
 *
 * "Template" goes with it. They were never templates.
 *
 * The guarantee underneath all of it: picking a look still writes the same
 * background_type and the same key field its old picker wrote, so no page
 * needs migrating and the public renderer is untouched.
 */
class TheStyleLibraryIsOneListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\BgTemplateSeeder::class);
        $this->seed(\Database\Seeders\BgPatternTemplatesSeeder::class);
        $this->seed(\Database\Seeders\ClassicGradientBgTemplatesSeeder::class);
        $this->seed(\Database\Seeders\LightBgTemplatesSeeder::class);
        $this->seed(\Database\Seeders\NeonBgTemplatesSeeder::class);
        $this->seed(\Database\Seeders\IllustratedBgTemplatesSeeder::class);
    }

    private function card(): string
    {
        return file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );
    }

    /** No category name may appear twice, at any level. */
    public function test_a_category_name_exists_exactly_once(): void
    {
        $names = array_values(BackgroundLibrary::CATEGORIES);
        $dupes = array_keys(array_filter(array_count_values($names), fn ($n) => $n > 1));

        $this->assertSame([], $dupes, 'duplicated category label: '.implode(', ', $dupes));

        // And the old second level is gone from the panel entirely: no chip
        // row of its own inside what used to be the Template picker.
        $card = $this->card();
        foreach (['tplCat', 'tplCategoryLabels', 'presetGroup', 'Choose a Template', 'Choose a Preset'] as $relic) {
            $this->assertStringNotContainsString($relic, $card,
                "{$relic} is part of the second level of categories that caused the duplication");
        }
    }

    /**
     * Mesh and Patterns each hold their looks from BOTH sources.
     *
     * This is the actual fix. Before, picking "Mesh" got you 10 looks and the
     * other 100 lived behind a different button under the same word.
     */
    public function test_a_category_holds_every_look_of_that_kind(): void
    {
        $items = BackgroundLibrary::items();
        $byCategory = [];
        foreach ($items as $item) {
            $byCategory[$item['category']][$item['type']] = true;
        }

        $this->assertEqualsCanonicalizing(
            ['template', 'mesh'],
            array_keys($byCategory['mesh']),
            'Mesh must gather the mesh templates and the mesh catalog into one chip'
        );
        $this->assertEqualsCanonicalizing(
            ['template', 'pattern', 'preset'],
            array_keys($byCategory['patterns']),
            'Patterns was three different places; it must now be one'
        );
    }

    /** The word "Template" is gone from what the user reads. */
    public function test_nothing_calls_these_templates_any_more(): void
    {
        $this->assertArrayNotHasKey('template', BackgroundLibrary::CATEGORIES);
        $this->assertNotContains('Template', BackgroundLibrary::CATEGORIES);
        $this->assertNotContains('Templates', BackgroundLibrary::CATEGORIES);

        // The stored type key is untouched -- renaming that would need a
        // migration, and the name was never the part that mattered.
        $items = array_column(BackgroundLibrary::items(), 'type');
        $this->assertContains('template', $items);
    }

    /**
     * One grid of ready-made looks, not six.
     *
     * The card keeps a second swatch grid, and it is meant to: the stock
     * image gallery inside the Image panel. That one answers a different
     * question -- which photo -- so it is not part of this merge.
     */
    public function test_the_card_renders_one_grid_of_looks(): void
    {
        $card = $this->card();

        // Everything before the Image panel is the Style half of the card;
        // exactly one grid of looks may live in it. Count the markup rather
        // than the class name, which also appears in the stylesheet.
        $styleHalf = substr($card, 0, strpos($card, "bgType === 'image'"));

        $this->assertSame(1, substr_count($styleHalf, 'class="bg-swatch-grid'),
            'six pickers meant six grids of looks; the library is one');
        $this->assertStringContainsString('bg-lib-swatch', $styleHalf);

        // And the one that remains further down is the photo gallery.
        $rest = substr($card, strpos($card, "bgType === 'image'"));
        $this->assertSame(1, substr_count($rest, 'class="bg-swatch-grid'));
        $this->assertStringContainsString('galVisible()', $rest,
            'the only other grid should be the stock image gallery');
    }

    /**
     * What a pick saves is unchanged. This is what makes the change safe to
     * ship without a migration: each entry carries the type and the value its
     * own picker used to write.
     */
    public function test_picking_writes_exactly_what_the_old_picker_wrote(): void
    {
        $byType = [];
        foreach (BackgroundLibrary::items() as $item) {
            $byType[$item['type']] ??= $item;
        }

        $this->assertEqualsCanonicalizing(
            ['template', 'preset', 'mesh', 'pattern', 'tiles', 'torn', 'gradient'],
            array_keys($byType),
            'every retired picker must still be reachable through the library'
        );

        // A gradient entry carries its stops, because picking one loads the
        // Colour builder instead of freezing a background -- the one thing
        // the two separate surfaces could never do.
        $this->assertSame(
            ['stops', 'type', 'angle'],
            array_keys($byType['gradient']['gradient'])
        );

        // A template value is the row id the radio used to post.
        $this->assertTrue(BgTemplate::whereKey($byType['template']['value'])->exists());
        // A torn entry carries the four fields its panel wrote.
        $this->assertSame(
            ['style', 'paper', 'backdrop', 'backdrop2'],
            array_keys($byType['torn']['torn'])
        );
        $this->assertTrue(TornStyleCatalog::isValidStyle($byType['torn']['torn']['style']));
    }

    /** The hidden fields the controller reads are all still rendered. */
    public function test_every_saved_field_survives_the_merge(): void
    {
        $card = $this->card();

        foreach ([
            'background_type', 'bg_template_id', 'bg_preset_key', 'mesh_preset',
            'pattern_preset', 'tiles_palette', 'tiles_layout', 'tiles_animate',
            'torn_style', 'torn_paper_color', 'torn_backdrop_color',
            'torn_backdrop_color2', 'bg_preset_opacity',
        ] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $card,
                "dropping {$field} would silently lose that setting on the next save");
        }
    }

    /**
     * A page saved before the library existed still opens on its own look.
     *
     * The library stores no id of its own -- it reads the same settings the
     * six pickers always wrote -- so this is the check that old pages are not
     * orphaned.
     */
    public function test_a_page_saved_by_the_old_pickers_still_shows_as_selected(): void
    {
        $template = BgTemplate::active()->first();

        $cases = [
            [['background_type' => 'template', 'bg_template_id' => $template->id], (string) $template->id],
            [['background_type' => 'mesh',     'mesh_preset'     => 'mesh_aurora'], 'mesh_aurora'],
            [['background_type' => 'tiles',    'tiles_palette'   => 'tiles_midnight'], 'tiles_midnight'],
            [['background_type' => 'color'],   null],
        ];

        foreach ($cases as [$settings, $expected]) {
            $this->assertSame($expected, BackgroundLibrary::selectedValue($settings),
                'saved as '.json_encode($settings));
        }
    }

    /**
     * Torn stores its parts rather than a combo key, so the library matches
     * them back. Hand-edited colours match nothing, and showing no selection
     * is the honest answer rather than highlighting a look that isn't theirs.
     */
    public function test_a_torn_page_resolves_to_its_combo_or_to_nothing(): void
    {
        $key = array_key_first(TornStyleCatalog::PRESETS);
        $combo = TornStyleCatalog::PRESETS[$key];

        $saved = [
            'background_type'      => 'torn',
            'torn_style'           => $combo['style'],
            'torn_paper_color'     => $combo['paper'],
            'torn_backdrop_color'  => $combo['backdrop'][0],
            'torn_backdrop_color2' => $combo['backdrop'][1],
        ];
        $this->assertSame($key, BackgroundLibrary::selectedValue($saved));

        $this->assertNull(BackgroundLibrary::selectedValue(
            array_merge($saved, ['torn_paper_color' => '#123456'])
        ));
    }

    /** Chip counts must add up to the library, with no empty chips. */
    public function test_the_counts_describe_the_list(): void
    {
        $items  = BackgroundLibrary::items();
        $counts = BackgroundLibrary::counts($items);

        $this->assertSame(count($items), array_sum($counts));
        $this->assertSame([], array_filter($counts, fn ($n) => $n < 1),
            'a chip promising zero looks is worse than no chip');
        $this->assertSame(
            array_values(array_intersect(array_keys(BackgroundLibrary::CATEGORIES), array_keys($counts))),
            array_keys($counts),
            'chips must keep the declared order'
        );
    }
}
