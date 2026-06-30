<?php

namespace Tests\Unit;

use App\Modules\Common\Support\AiHeroExamples;
use Tests\TestCase;

/**
 * Task #3144 — the homepage AI-builder demo (home.partials.ai-hero) cycles
 * through the example pages returned by {@see AiHeroExamples::all()} and reuses
 * ONE fixed DOM: a profile block, exactly 3 link-card slots and a 3-cell
 * gallery grid, swapping only the inner content in place per example. If a new
 * example arrives with a missing key, the wrong number of links/gallery cells,
 * or a malformed avatar, the JS cycle silently paints a half-empty hero or
 * leaves stale rows from the previous example — with no error.
 *
 * This guard pins that contract so a malformed example fails fast here instead
 * of shipping a broken hero. It mirrors the sibling fixed-shape demo guard
 * {@see \Tests\Unit\AiStrategistExamplesTest}.
 *
 * Unlike the strategist examples (plain PHP), AiHeroExamples calls asset(),
 * which needs a booted Laravel app — so this extends Tests\TestCase. That also
 * rules out a #[DataProvider]: providers run at collection time before the app
 * boots, when asset() would fail. Each test therefore loops over all()
 * internally (after setUp has booted the app).
 */
class AiHeroExamplesTest extends TestCase
{
    /**
     * The fixed preview DOM has exactly this many link-card slots and this many
     * gallery cells (the grid is repeat(3, 1fr)). The JS fills slots by position
     * (links[0..2], gallery imgs/tiles map 1:1), so an example with a different
     * count leaves stale rows or a half-empty grid.
     */
    private const LINK_SLOTS = 3;
    private const GALLERY_CELLS = 3;

    public function test_all_returns_a_non_empty_list(): void
    {
        $all = AiHeroExamples::all();

        $this->assertIsArray($all);
        $this->assertNotEmpty($all, 'AiHeroExamples::all() must return at least one example.');
        $this->assertSame(
            range(0, count($all) - 1),
            array_keys($all),
            'Examples must be a 0-indexed list (the JS cycle and resting markup index by position).',
        );
    }

    public function test_each_example_has_the_required_string_fields(): void
    {
        foreach (AiHeroExamples::all() as $index => $example) {
            foreach (['prompt', 'name', 'tag', 'time'] as $key) {
                $this->assertArrayHasKey($key, $example, "Example #{$index} is missing the '{$key}' field.");
                $this->assertIsString($example[$key], "Example #{$index} '{$key}' must be a string.");
                $this->assertNotSame('', trim((string) $example[$key]), "Example #{$index} '{$key}' must not be blank.");
            }
        }
    }

    public function test_each_example_has_a_well_formed_avatar(): void
    {
        foreach (AiHeroExamples::all() as $index => $example) {
            $this->assertArrayHasKey('avatar', $example, "Example #{$index} is missing the 'avatar' field.");
            $this->assertIsArray($example['avatar'], "Example #{$index} 'avatar' must be an array.");

            $hasImg = array_key_exists('img', $example['avatar']);
            $hasIcon = array_key_exists('icon', $example['avatar']);
            $this->assertTrue(
                $hasImg xor $hasIcon,
                "Example #{$index} 'avatar' must have exactly one of 'img' or 'icon' (the markup branches on img-or-icon).",
            );

            $key = $hasImg ? 'img' : 'icon';
            $this->assertIsString($example['avatar'][$key], "Example #{$index} 'avatar.{$key}' must be a string.");
            $this->assertNotSame('', trim((string) $example['avatar'][$key]), "Example #{$index} 'avatar.{$key}' must not be blank.");
        }
    }

    public function test_each_example_has_exactly_three_well_formed_links(): void
    {
        foreach (AiHeroExamples::all() as $index => $example) {
            $this->assertArrayHasKey('links', $example, "Example #{$index} is missing the 'links' field.");
            $this->assertIsArray($example['links'], "Example #{$index} 'links' must be an array.");
            $this->assertCount(
                self::LINK_SLOTS,
                $example['links'],
                "Example #{$index} must have exactly ".self::LINK_SLOTS." links so the fixed link-card slots stay in sync (no stale or empty rows).",
            );
            // The JS fills slots by position (links[0], links[1], …), so the
            // links must be a 0-indexed sequential list — non-sequential keys
            // would JSON-encode as an object and silently drop slots.
            $this->assertSame(
                range(0, self::LINK_SLOTS - 1),
                array_keys($example['links']),
                "Example #{$index} 'links' must be a 0-indexed list so the JS fills slots by position.",
            );

            foreach (array_values($example['links']) as $i => $link) {
                $this->assertIsArray($link, "Example #{$index} 'links[{$i}]' must be an array.");
                foreach (['icon', 'label', 'color'] as $key) {
                    $this->assertArrayHasKey($key, $link, "Example #{$index} 'links[{$i}]' is missing '{$key}'.");
                    $this->assertIsString($link[$key], "Example #{$index} 'links[{$i}].{$key}' must be a string.");
                    $this->assertNotSame('', trim((string) $link[$key]), "Example #{$index} 'links[{$i}].{$key}' must not be blank.");
                }
                // rating is optional, but when present it must be a non-blank string.
                if (array_key_exists('rating', $link)) {
                    $this->assertIsString($link['rating'], "Example #{$index} 'links[{$i}].rating' must be a string when present.");
                    $this->assertNotSame('', trim((string) $link['rating']), "Example #{$index} 'links[{$i}].rating' must not be blank when present.");
                }
            }
        }
    }

    public function test_each_example_has_a_well_formed_gallery(): void
    {
        foreach (AiHeroExamples::all() as $index => $example) {
            $this->assertArrayHasKey('gallery', $example, "Example #{$index} is missing the 'gallery' field.");
            $this->assertIsArray($example['gallery'], "Example #{$index} 'gallery' must be an array.");

            $hasImgs = array_key_exists('imgs', $example['gallery']);
            $hasTiles = array_key_exists('tiles', $example['gallery']);
            $this->assertTrue(
                $hasImgs xor $hasTiles,
                "Example #{$index} 'gallery' must have exactly one of 'imgs' or 'tiles' (the markup branches on imgs-or-tiles).",
            );

            if ($hasImgs) {
                $this->assertGalleryImgs($example['gallery']['imgs'], $index);
            } else {
                $this->assertGalleryTiles($example['gallery']['tiles'], $index);
            }
        }
    }

    /**
     * A photo gallery is exactly GALLERY_CELLS non-blank image-URL strings,
     * 0-indexed (the JS maps each entry to one cell in the 3-col grid).
     *
     * @param mixed $imgs
     */
    private function assertGalleryImgs($imgs, int $index): void
    {
        $this->assertIsArray($imgs, "Example #{$index} 'gallery.imgs' must be an array.");
        $this->assertCount(
            self::GALLERY_CELLS,
            $imgs,
            "Example #{$index} 'gallery.imgs' must have exactly ".self::GALLERY_CELLS." images to fill the grid.",
        );
        $this->assertSame(
            range(0, self::GALLERY_CELLS - 1),
            array_keys($imgs),
            "Example #{$index} 'gallery.imgs' must be a 0-indexed list so the JS fills cells by position.",
        );
        foreach (array_values($imgs) as $i => $img) {
            $this->assertIsString($img, "Example #{$index} 'gallery.imgs[{$i}]' must be a string.");
            $this->assertNotSame('', trim((string) $img), "Example #{$index} 'gallery.imgs[{$i}]' must not be blank.");
        }
    }

    /**
     * An icon gallery is exactly GALLERY_CELLS {icon, color} tiles, 0-indexed,
     * with non-blank string values (the JS maps each tile to one grid cell).
     *
     * @param mixed $tiles
     */
    private function assertGalleryTiles($tiles, int $index): void
    {
        $this->assertIsArray($tiles, "Example #{$index} 'gallery.tiles' must be an array.");
        $this->assertCount(
            self::GALLERY_CELLS,
            $tiles,
            "Example #{$index} 'gallery.tiles' must have exactly ".self::GALLERY_CELLS." tiles to fill the grid.",
        );
        $this->assertSame(
            range(0, self::GALLERY_CELLS - 1),
            array_keys($tiles),
            "Example #{$index} 'gallery.tiles' must be a 0-indexed list so the JS fills cells by position.",
        );
        foreach (array_values($tiles) as $i => $tile) {
            $this->assertIsArray($tile, "Example #{$index} 'gallery.tiles[{$i}]' must be an array.");
            foreach (['icon', 'color'] as $key) {
                $this->assertArrayHasKey($key, $tile, "Example #{$index} 'gallery.tiles[{$i}]' is missing '{$key}'.");
                $this->assertIsString($tile[$key], "Example #{$index} 'gallery.tiles[{$i}].{$key}' must be a string.");
                $this->assertNotSame('', trim((string) $tile[$key]), "Example #{$index} 'gallery.tiles[{$i}].{$key}' must not be blank.");
            }
        }
    }
}
