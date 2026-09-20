<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\BgTemplate;
use App\Modules\User\Support\TilesBgCatalog;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A category with six entries in it is not a category, it is an apology.
 *
 * Merging the six pickers into one library made the thin ones impossible to
 * miss: against 129 gradients and 120 patterns, Neon had 8, Tiles 7 and
 * Torn paper 6. The floor is now fifty per category, and this test is what
 * stops the next one being added at a handful.
 *
 * Two of the four grew by fixing what the unit of a "look" actually is:
 *
 *   Torn paper counted tear SHAPES, but a shape with no colourway is not
 *   something anyone picks -- the combo is. TornStyleCatalog::PRESETS was
 *   already that combo and already what the panel offered as chips.
 *
 *   Illustrated counted rows, 25 of which never painted anything: their
 *   SVG data URI was percent-encoded twice, so `<` was stored as `%253C`,
 *   the browser parsed no document, and the template rendered as its flat
 *   ground colour. Counting them was counting blanks.
 */
class EveryBackgroundCategoryIsWorthBrowsingTest extends TestCase
{
    use RefreshDatabase;

    /** Library categories are seeded content; the two catalogs are code. */
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

    private const FLOOR = 50;

    /** Every template category clears the floor. */
    public function test_no_template_category_is_left_thin(): void
    {
        $counts = BgTemplate::active()
            ->get()
            ->groupBy(fn ($t) => $t->category ?: 'pattern')
            ->map->count()
            ->all();

        $thin = array_filter($counts, fn ($n) => $n < self::FLOOR);

        $this->assertSame([], $thin, 'these categories are below the floor of '
            .self::FLOOR.': '.json_encode($thin));
    }

    /** Tiles and Torn paper are code catalogs, and they clear it too. */
    public function test_the_code_backed_categories_clear_the_floor_as_well(): void
    {
        $this->assertGreaterThanOrEqual(self::FLOOR, count(TilesBgCatalog::palettes()));
        $this->assertGreaterThanOrEqual(self::FLOOR, count(TornStyleCatalog::PRESETS));
    }

    /**
     * The bug that made Illustrated a wall of identical dark rectangles.
     *
     * `background-image:url("data:image/svg+xml;utf8,%253Csvg ...")` is not
     * a document. One layer of encoding too many and the browser silently
     * paints nothing, which reads as "this template is just a dark colour"
     * rather than as a failure -- so it sat there across 25 rows.
     */
    public function test_no_template_stores_a_double_encoded_data_uri(): void
    {
        $bad = BgTemplate::all()
            ->filter(fn ($t) => str_contains((string) $t->css, '%25')
                             || str_contains((string) $t->preview_color, '%25'))
            ->pluck('name')
            ->all();

        $this->assertSame([], $bad,
            'these templates carry a doubled percent-encoding and will render '
            .'as their flat ground colour: '.implode(', ', $bad));
    }

    /** An SVG template that paints nothing is worse than no template. */
    public function test_every_svg_template_actually_carries_a_parseable_document(): void
    {
        $empty = [];
        foreach (BgTemplate::where('category', 'svg')->get() as $t) {
            $css = (string) $t->css;
            if (!str_contains($css, 'data:image/svg+xml')) {
                continue;
            }
            // Raw `<svg` is the form the renderer needs; `%3Csvg` also
            // decodes, but `%253Csvg` does not.
            if (!str_contains($css, '<svg') && !str_contains($css, '%3Csvg')) {
                $empty[] = $t->name;
            }
        }

        $this->assertSame([], $empty,
            'no SVG document survives in: '.implode(', ', $empty));
    }

    /** Every tile palette is shaped the way the renderer expects. */
    public function test_every_tile_palette_is_well_formed(): void
    {
        foreach (TilesBgCatalog::palettes() as $key => $p) {
            $this->assertArrayHasKey('label', $p, $key);
            $this->assertNotSame('', trim($p['label']), $key);
            $this->assertCount(6, $p['tiles'], "{$key} should cycle six tiles");
            $this->assertCount(3, $p['colors'], "{$key} needs three fallback colors");

            foreach ($p['tiles'] as $css) {
                $this->assertStringStartsWith('linear-gradient(', $css, $key);
            }
            foreach ($p['colors'] as $hex) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $hex, $key);
            }
            // tiles() resolves through the catalog, never from client input.
            $this->assertCount(
                TilesBgCatalog::TILE_COUNT,
                TilesBgCatalog::tiles($key, 'metro'),
                "{$key} does not resolve a full grid"
            );
        }
    }

    /** Every torn preset names a real tear shape and real colours. */
    public function test_every_torn_preset_resolves(): void
    {
        foreach (TornStyleCatalog::PRESETS as $key => $combo) {
            $this->assertTrue(
                TornStyleCatalog::isValidStyle($combo['style']),
                "{$key} names a tear style that does not exist: {$combo['style']}"
            );
            $this->assertNotEmpty(TornStyleCatalog::sheets($combo['style']), $key);

            foreach ([$combo['paper'], $combo['backdrop'][0], $combo['backdrop'][1]] as $hex) {
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $hex, $key);
            }
        }
    }

    /** Labels are what the library searches on, so they have to be distinct. */
    public function test_nothing_in_the_library_shares_a_name(): void
    {
        $names = array_merge(
            BgTemplate::active()->pluck('name')->all(),
            array_column(TilesBgCatalog::palettes(), 'label'),
            array_column(TornStyleCatalog::PRESETS, 'label'),
        );

        $dupes = array_keys(array_filter(array_count_values($names), fn ($n) => $n > 1));

        $this->assertSame([], $dupes,
            'two looks answer to the same name, so search cannot tell them '
            .'apart: '.implode(', ', $dupes));
    }
}
