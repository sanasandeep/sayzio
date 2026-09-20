<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Services\BiolinkThemeResolver;
use App\Modules\User\Support\PageBackground;
use Tests\TestCase;

/**
 * A theme that captured the background type but not the background.
 *
 * BiolinkThemeResolver::THEMABLE_KEYS is the list of settings a saved or
 * scheduled theme snapshots. It was written by hand before presets, mesh,
 * pattern, tiles and torn paper existed, and never caught up -- eleven
 * fields short by the time this was found:
 *
 *     bg_preset_key, bg_preset_opacity, mesh_preset, pattern_preset,
 *     tiles_palette, tiles_layout, tiles_animate, torn_style,
 *     torn_paper_color, torn_backdrop_color, torn_backdrop_color2
 *
 * So a theme captured from a page using Tiles stored `background_type:
 * tiles` and nothing that says WHICH tiles. When the schedule activated,
 * or reverted, it restored a background the creator had never chosen --
 * silently, on a timer, which is the worst way to find a bug.
 *
 * It drifted because it was a second hand-maintained copy of a list that
 * already existed elsewhere. The fix is not to top it up; it is to stop
 * keeping a copy. These tests hold that line.
 */
class ScheduledThemesCaptureTheWholeBackgroundTest extends TestCase
{
    /** Every field the renderer reads is a field a theme captures. */
    public function test_a_theme_captures_every_field_the_renderer_reads(): void
    {
        $missing = array_values(array_diff(
            PageBackground::FIELDS,
            BiolinkThemeResolver::THEMABLE_KEYS
        ));

        $this->assertSame([], $missing,
            'a theme that does not capture these cannot restore the background '
            .'it was taken from: '.implode(', ', $missing));
    }

    /**
     * And the design-lock list -- the other list of "this is a design
     * surface" -- agrees about the background too.
     *
     * These two drifted apart in opposite directions: the design-lock list
     * was kept current as each background type shipped, while the theme
     * list was not. Whichever one is wrong, they must not disagree, because
     * a field being a design surface for one purpose and not the other is
     * always a bug in one of them.
     */
    public function test_the_design_lock_list_and_the_theme_list_agree(): void
    {
        $lockedBackgroundKeys = array_values(array_intersect(
            BiolinkBlockController::DESIGN_LOCKED_PAGE_KEYS,
            PageBackground::FIELDS
        ));

        $notThemable = array_values(array_diff(
            $lockedBackgroundKeys,
            BiolinkThemeResolver::THEMABLE_KEYS
        ));

        $this->assertSame([], $notThemable,
            'these are locked as design surfaces but not captured by a theme: '
            .implode(', ', $notThemable));
    }

    /**
     * The background half is DERIVED, not restated.
     *
     * This is the actual fix. A hand-written copy would pass the test above
     * on the day it was written and rot exactly as the last one did, so the
     * guard has to be against the copy existing at all.
     */
    public function test_the_theme_list_does_not_keep_its_own_copy(): void
    {
        $source = file_get_contents(
            base_path('app/Modules/User/Services/BiolinkThemeResolver.php')
        );

        $this->assertStringContainsString('...\App\Modules\User\Support\PageBackground::FIELDS', $source,
            'the background half must be spread from the renderer, not retyped');

        // Nothing background-shaped may be typed out in this file by hand.
        $start = strpos($source, 'const THEMABLE_NON_BACKGROUND_KEYS');
        $end   = strpos($source, 'const THEMABLE_KEYS');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $handWritten = substr($source, $start, $end - $start);
        foreach (['background_', 'bg_', 'tiles_', 'torn_', 'mesh_', 'pattern_', 'slideshow_', 'video_', 'gradient_'] as $prefix) {
            $this->assertStringNotContainsString("'".$prefix, $handWritten,
                "a background field ({$prefix}...) is being restated by hand again");
        }
    }

    /** The non-background half still covers what it always covered. */
    public function test_the_rest_of_a_theme_is_unchanged(): void
    {
        foreach ([
            'font_family', 'font_color',
            'button_style', 'button_color', 'button_text_color',
            'biolink_title', 'biolink_description', 'block_theme',
        ] as $key) {
            $this->assertContains($key, BiolinkThemeResolver::THEMABLE_KEYS,
                "{$key} was themable before and must stay themable");
        }
    }

    /** No duplicates: the two halves must not overlap. */
    public function test_the_list_has_no_duplicates(): void
    {
        $keys = BiolinkThemeResolver::THEMABLE_KEYS;
        $dupes = array_keys(array_filter(array_count_values($keys), fn ($n) => $n > 1));

        $this->assertSame([], $dupes,
            'duplicated themable key: '.implode(', ', $dupes));
    }

    /**
     * A snapshot of a tiles page round-trips -- the case that was broken.
     */
    public function test_a_tiles_background_survives_a_snapshot(): void
    {
        $saved = [
            'background_type' => 'tiles',
            'tiles_palette'   => 'tiles_midnight',
            'tiles_layout'    => 'metro',
            'tiles_animate'   => '1',
            'font_color'      => '#ffffff',
            'analytics_id'    => 'should-not-be-captured',
        ];

        $snapshot = array_intersect_key($saved, array_flip(BiolinkThemeResolver::THEMABLE_KEYS));

        $this->assertSame([
            'background_type' => 'tiles',
            'tiles_palette'   => 'tiles_midnight',
            'tiles_layout'    => 'metro',
            'tiles_animate'   => '1',
            'font_color'      => '#ffffff',
        ], $snapshot);

        // And what it restores is the same background, not a bare type.
        $before = PageBackground::resolve($saved);
        $after  = PageBackground::resolve($snapshot);

        $this->assertSame($before['tiles'], $after['tiles']);
        $this->assertTrue($after['tilesActive']);
        $this->assertTrue($after['tilesAnimate']);
    }
}
