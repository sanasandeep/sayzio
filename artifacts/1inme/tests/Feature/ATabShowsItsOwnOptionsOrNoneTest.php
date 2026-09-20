<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Found on the live page, right after the three-tab redesign shipped.
 *
 * Open the card on a gradient, click Media: you got Media's three tiles
 * AND the entire Colour builder underneath them -- gradient bar, type,
 * angle, every colour stop. Two tabs' worth of controls stacked in one
 * tab, which is precisely the confusion this card has spent four changes
 * working its way out of.
 *
 * The cause is older than the redesign. Every options pane keyed on the
 * SAVED type alone (`bgType === 'gradient'`) and knew nothing about which
 * tab was open, so any pane whose type was saved drew under whatever tab
 * you happened to be browsing. It was survivable when the panes were small
 * and the tabs were new; giving Colour a full builder made it obvious.
 *
 * A tab shows its own options or none. When none, it says why rather than
 * ending in blank space, because an empty pane reads as a bug.
 *
 * The guarantee that must survive: switching tabs still changes nothing
 * that is saved. These panes hold the hidden inputs, and x-show only
 * hides -- a hidden input still posts.
 */
class ATabShowsItsOwnOptionsOrNoneTest extends TestCase
{
    private function card(): string
    {
        return file_get_contents(
            base_path('resources/views/user/links/partials/biolink-background-card.blade.php')
        );
    }

    /**
     * No options pane may key on the saved type alone. That is the bug.
     */
    public function test_no_options_pane_ignores_the_open_tab(): void
    {
        $card = $this->card();

        foreach ([
            'color', 'gradient', 'image', 'slideshow', 'video',
            'torn', 'tiles', 'mesh', 'pattern', 'preset', 'template',
        ] as $type) {
            $this->assertStringNotContainsString(
                '<div x-show="bgType === \''.$type.'\'"',
                $card,
                "the {$type} pane draws under any tab, because it only asks what is saved"
            );
            $this->assertStringContainsString(
                'panelFor(\''.$type.'\')',
                $card,
                "the {$type} pane must ask which tab is open as well"
            );
        }
    }

    /** And the helper asks both questions, in that order. */
    public function test_the_helper_checks_the_type_and_the_tab(): void
    {
        $card = $this->card();

        $this->assertStringContainsString(
            'return this.bgType === type && (!t || t.group === this.activeGroup);',
            $card,
            'a pane belongs on screen when its type is saved AND its tab is open'
        );
    }

    /**
     * The Finish section is NOT a tab's options -- it describes the saved
     * background whichever tab you are browsing -- so its fallback-image
     * field stays keyed on the type alone. Folding it into panelFor() would
     * hide the fallback for an image background the moment you clicked
     * Style, which is a different bug in the same family.
     */
    public function test_the_finish_section_still_follows_the_saved_background(): void
    {
        $card = $this->card();

        $this->assertStringContainsString(
            "x-show=\"bgType === 'image' || bgType === 'slideshow' || bgType === 'video'\"",
            $card,
            'Finish describes what is saved, not what is being browsed'
        );
    }

    /** An empty tab explains itself and points at the one holding it. */
    public function test_an_empty_tab_says_where_the_background_actually_is(): void
    {
        $card = $this->card();

        $this->assertStringContainsString('currentLabel()', $card);
        $this->assertStringContainsString('currentGroupLabel()', $card);
        $this->assertStringContainsString(
            "!typesIn(activeGroup).some(t => t.key === bgType)",
            $card,
            'the note appears exactly when this tab holds nothing that is in use'
        );

        // Style is exempt: its library is always on screen, so it is never
        // the empty tab and the note would be noise.
        $this->assertStringContainsString("activeGroup !== 'style' &&", $card);
    }

    /**
     * The whole point of hiding rather than removing: nothing stops being
     * submitted. Every field the controller reads still has its input.
     */
    public function test_hiding_a_pane_does_not_drop_what_it_saves(): void
    {
        $card = $this->card();

        foreach ([
            'background_type', 'background_color', 'gradient_colors',
            'gradient_angle', 'gradient_type', 'gradient_preset_id',
            'background_gradient', 'background_image_asset', 'video_url',
            'bg_template_id', 'bg_preset_key', 'mesh_preset', 'pattern_preset',
            'tiles_palette', 'tiles_layout', 'tiles_animate', 'torn_style',
            'torn_paper_color', 'torn_backdrop_color', 'torn_backdrop_color2',
            'bg_preset_opacity', 'slideshow_interval',
        ] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $card,
                "{$field} must still post from its pane, hidden or not");
        }

        // x-show hides; it must never become x-if, which removes from the
        // DOM and would silently stop posting.
        $this->assertStringNotContainsString('x-if="panelFor(', $card,
            'x-if would unmount the pane and drop its inputs from the form');
    }
}
