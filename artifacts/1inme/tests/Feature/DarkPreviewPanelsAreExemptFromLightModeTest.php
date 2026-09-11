<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Admin panels that stay dark in light mode carry .ak-on-dark.
 *
 * Sana found the Marketing Settings trust-strip preview unreadable in light
 * mode: the values ("99.9% uptime", "TLS 1.3", "GDPR-ready") had vanished
 * while their labels next to them read fine.
 *
 * The cause is a collision between two correct decisions. The ak-* classes
 * darken text so it reads on the LIGHT admin surface --
 * `html.light-mode .ak-strong { color: #0f172a }` and friends. The "Live
 * preview" panels, meanwhile, stay dark in BOTH themes on purpose, because
 * their job is to show how wording will look on the site's dark hero; a
 * preview rendered on a white card would be showing the wrong thing.
 *
 * Put an ak-* class inside one of those panels and it paints near-black text
 * on near-black. The labels survived only because they happened to carry no
 * ak-* class -- so the bug looked like half the row was missing rather than
 * like a theme fault, which is what made it hard to read as a colour problem.
 *
 * .ak-on-dark on the panel lifts the overrides back to light-on-dark for
 * everything inside it. This test pins the two halves together: the rules in
 * the layout, and the class on each panel that needs them. Either half alone
 * is silent -- CSS with nothing using it, or a dark panel quietly going
 * unreadable the next time someone opens the admin in light mode.
 */
class DarkPreviewPanelsAreExemptFromLightModeTest extends TestCase
{
    /** Panels that stay dark in light mode, and the screen each one is on. */
    private const DARK_PANELS = [
        'admin/marketing-settings/index.blade.php' => [
            'the hero badges live preview',
            'the hero marquee live preview',
            'the trust strip live preview',
            'the comparison table live preview',
        ],
        'admin/marketing-settings/partials/_testimonial-editor.blade.php' => [
            'the testimonials live preview',
        ],
        'admin/site-pages/partials/about-editor.blade.php' => [
            'the floating About preview panel',
        ],
    ];

    /**
     * A view's dark panels: elements whose class list names a hardcoded dark
     * background. Matching on the background rather than on a marker class is
     * the point -- a NEW dark panel is found by this test the day it is
     * written, instead of the day someone opens it in light mode.
     */
    private function darkPanelClassLists(string $source): array
    {
        preg_match_all('/class="([^"]*)"/', $source, $m);

        return array_values(array_filter($m[1], static function (string $classes): bool {
            $isDark = str_contains($classes, 'from-slate-900')
                // Any literal near-black, e.g. bg-[#0b0712].
                || preg_match('/bg-\[#0[0-9a-f]{5}\]/i', $classes) === 1;

            if (! $isDark) {
                return false;
            }

            // A <select>'s <option> carries a dark background on its own, to
            // keep the native dropdown readable. It is one line of text in a
            // menu the browser draws, not a panel, and it holds no ak-* text
            // -- so requiring a structural class alongside the colour keeps
            // those out without having to name them.
            return preg_match('/\b(rounded|fixed|absolute|border|p-\d|px-\d|py-\d)/', $classes) === 1;
        }));
    }

    public function test_the_layout_defines_the_dark_island_overrides(): void
    {
        $layout = (string) file_get_contents(
            resource_path('views/admin/layouts/app.blade.php')
        );

        $this->assertStringContainsString(
            'html.light-mode .ak-on-dark .ak-strong',
            $layout,
            'The .ak-on-dark overrides are gone from the admin layout, so every '
            . 'dark preview panel is back to rendering near-black text on a '
            . 'near-black ground in light mode.'
        );

        // The overrides must beat the plain ak-* rules they undo, which they
        // do by carrying one more class -- but only if they come from the same
        // stylesheet. Asserting both live in this file keeps that true.
        $this->assertStringContainsString(
            'html.light-mode .ak-strong { color: #0f172a; }',
            $layout,
            'The ak-* light-mode rules have moved out of the layout; the '
            . '.ak-on-dark overrides are written to sit after them and will '
            . 'no longer reliably win.'
        );
    }

    public function test_every_dark_panel_is_marked(): void
    {
        $unmarked = [];

        foreach (array_keys(self::DARK_PANELS) as $view) {
            $source = (string) file_get_contents(resource_path('views/' . $view));

            foreach ($this->darkPanelClassLists($source) as $classes) {
                if (! str_contains($classes, 'ak-on-dark')) {
                    $unmarked[] = $view . ' -- ' . trim(preg_replace('/\s+/', ' ', $classes));
                }
            }
        }

        $this->assertSame(
            [],
            $unmarked,
            "A panel that stays dark in light mode is missing .ak-on-dark, so any "
            . "ak-* text inside it renders near-black on near-black.\n"
            . "Add ak-on-dark to the panel's class list.\n"
            . "Unmarked panels:\n  " . implode("\n  ", $unmarked)
        );
    }

    /**
     * Counts matter here: if a preview is deleted the test above still passes
     * (nothing is unmarked), and the screen quietly loses its preview without
     * anything saying so.
     */
    public function test_the_expected_previews_are_all_still_there(): void
    {
        foreach (self::DARK_PANELS as $view => $panels) {
            $source = (string) file_get_contents(resource_path('views/' . $view));

            $this->assertCount(
                count($panels),
                $this->darkPanelClassLists($source),
                $view . ' should have ' . count($panels) . ' dark panel(s): '
                . implode(', ', $panels) . '. A different count means one was '
                . 'removed, or a new one was added without being listed here.'
            );
        }
    }
}
