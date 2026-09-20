<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The page-background card must read as one system, not five pickers that
 * happened to be written on different days.
 *
 * Two things were reported and both were real:
 *
 *  1. Swatches came out at wildly different sizes between tabs. Every picker
 *     already drew a 9/14 swatch, so the aspect was never the problem -- the
 *     column counts had drifted apart as each picker was added:
 *
 *         Pattern    sm:grid-cols-6   -> 102px on a 1456px viewport
 *         Tiles      sm:grid-cols-7   ->  87px
 *         Mesh       sm:grid-cols-10  ->  61px
 *         Presets    lg:grid-cols-12  ->  50px
 *         Templates  lg:grid-cols-12  ->  50px
 *
 *     Measured on the live page, not guessed. One auto-fill rule now serves
 *     every picker, so a sixth one cannot drift.
 *
 *  2. The eleven background types read as eleven unrelated choices, six of
 *     which ask the same question: pick a ready-made look. They are grouped
 *     now -- Colour, Style, Media -- with a switch between them.
 *
 * Grouping is presentation only. Each button still sets its own
 * background_type, so nothing stored changes and the renderer is untouched.
 */
class BackgroundPickerIsOneSystemTest extends TestCase
{
    private function card(): string
    {
        return file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );
    }

    /** Every swatch grid uses the shared rule, none carries its own columns. */
    public function test_no_picker_declares_its_own_column_count(): void
    {
        $card = $this->card();

        // Grids that hold aspect-ratio swatches are the ones in scope. Any
        // grid-cols on the same element as a max-h scroller is one of them.
        preg_match_all('/class="[^"]*grid-cols-[^"]*"/', $card, $m);

        $offenders = array_values(array_filter($m[0], function (string $cls) {
            // The type-tile grid is a row of labelled buttons, not swatches.
            if (str_contains($cls, 'grid-cols-3 sm:grid-cols-6 gap-2')) return false;
            // Two-up and three-up form rows are layout, not pickers.
            return (bool) preg_match('/grid-cols-(4|5|6|7|9|10|12)\b/', $cls);
        }));

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['A swatch grid is declaring its own column count again.',
             'Use .bg-swatch-grid so every picker sizes alike:', ''],
            $offenders
        )));
    }

    /** The shared rule exists and sizes by track, not by a fixed count. */
    public function test_the_shared_grid_sizes_by_track(): void
    {
        $card = $this->card();

        $this->assertStringContainsString('.bg-swatch-grid', $card);
        $this->assertMatchesRegularExpression(
            '/\.bg-swatch-grid\s*\{[^}]*repeat\(auto-fill,\s*minmax\(/',
            $card,
            'a fixed column count is what drifted last time; size by track instead'
        );
    }

    /** Every type belongs to a group, and the groups are the three we chose. */
    public function test_every_background_type_is_grouped(): void
    {
        $card = $this->card();

        preg_match_all("/\{ key: '([a-z]+)',\s*group: '([a-z]+)'/", $card, $m, PREG_SET_ORDER);
        $grouped = [];
        foreach ($m as $row) {
            $grouped[$row[1]] = $row[2];
        }

        $expected = [
            'color' => 'colour', 'gradient' => 'colour',
            'template' => 'style', 'preset' => 'style', 'mesh' => 'style',
            'pattern' => 'style', 'tiles' => 'style', 'torn' => 'style',
            'image' => 'media', 'slideshow' => 'media', 'video' => 'media',
        ];

        ksort($grouped);
        ksort($expected);

        $this->assertSame($expected, $grouped,
            'every background type needs a group, and an ungrouped one would '
            . 'vanish from the panel entirely');
    }

    /**
     * The important guarantee: grouping changed nothing that gets saved.
     *
     * Each button must still set its own background_type. If a group ever
     * started writing a value of its own, every existing page would need
     * migrating, which is exactly what this change avoids.
     */
    public function test_grouping_did_not_change_what_gets_saved(): void
    {
        $card = $this->card();

        $this->assertStringContainsString(
            '<input type="hidden" name="background_type" :value="bgType">',
            $card,
            'the saved value must still be the type key, not the group'
        );
        $this->assertStringContainsString(
            "@click=\"bgType = t.key\"",
            $card,
            'picking a type is what sets background_type; a group switch must not'
        );
        $this->assertStringNotContainsString(
            'bgType = g.key',
            $card,
            'a group must never be written as a background_type'
        );
    }

    /** Switching groups shows different options; it never changes the saved one. */
    public function test_switching_group_does_not_touch_the_saved_background(): void
    {
        $card = $this->card();

        $this->assertStringContainsString(
            '@click="activeGroup = g.key"',
            $card,
            'the group switch sets only which options are visible'
        );
        $this->assertStringContainsString(
            'syncActiveGroup()',
            $card,
            'the panel must open on the group holding the saved background'
        );
    }
}
