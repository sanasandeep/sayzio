<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Admin panels that stay dark in light mode stay readable.
 *
 * Sana found the Marketing Settings trust-strip preview unreadable in light
 * mode: the values ("99.9% uptime", "TLS 1.3", "GDPR-ready") had vanished
 * while their labels next to them read fine.
 *
 * The "Live preview" panels stay dark in BOTH themes on purpose -- their job
 * is to show how wording will look on the site's dark hero, and a preview
 * rendered on a white card would be showing the wrong thing. Two separate
 * light-mode rules repaint the text inside them:
 *
 *   1. The blanket repaint in common/partials/theme-styles:
 *
 *          html.light-mode [class*="text-white"]:not(...20 more...) {
 *              color: var(--text-primary) !important;
 *          }
 *
 *      This is the one that caused the reported symptom, and the half-a-row
 *      shape is its fingerprint: the values carry `text-white` and were
 *      repainted, the labels carry `text-gray-500` and were never matched.
 *
 *   2. The ak-* classes, which darken text for the light admin surface.
 *
 * The first cannot be outranked -- it is !important and twenty :not() clauses
 * deep. The fix redefines --text-primary / --text-secondary / --text-muted on
 * the panel so that rule RESOLVES to dark-surface colours instead of being
 * fought. The second is handled by ordinary .ak-on-dark overrides.
 *
 * The first version of this fix shipped with only the ak-* overrides and was
 * still broken on the deployed page, because a test that asserts CSS EXISTS
 * cannot tell whether that CSS WINS. Hence the variable assertions below:
 * they pin the mechanism the fix depends on, so that if the blanket rule is
 * ever changed to a literal colour, this fails loudly instead of the panels
 * going quietly unreadable again.
 */
class AdminDarkPanelsStayReadableTest extends TestCase
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

    /**
     * The half of the fix that handles the rule nothing can outrank.
     */
    public function test_the_panel_redefines_the_text_variables(): void
    {
        $layout = (string) file_get_contents(
            resource_path('views/admin/layouts/app.blade.php')
        );

        // Normalise whitespace so the assertion is about the declarations,
        // not about how the block happens to be indented.
        $flat = (string) preg_replace('/\s+/', ' ', $layout);

        $this->assertStringContainsString(
            'html.light-mode .ak-on-dark { --text-primary:',
            $flat,
            'The dark panels no longer redefine --text-primary, so the '
            . '!important [class*="text-white"] repaint in theme-styles lands '
            . 'on the LIGHT text colour again and every value inside a Live '
            . 'preview goes near-black on near-black. The ak-* overrides alone '
            . 'do not fix this -- they lose to that rule.'
        );

        foreach (['--text-secondary', '--text-muted'] as $var) {
            $this->assertMatchesRegularExpression(
                '/html\.light-mode \.ak-on-dark \{[^}]*' . preg_quote($var, '/') . '\s*:/',
                $flat,
                $var . ' is not redefined on .ak-on-dark; the text-white/NN '
                . 'variants of the blanket rule resolve through it, so those '
                . 'elements stay unreadable inside a dark panel.'
            );
        }
    }

    /**
     * The variable fix only works while the blanket rule keeps resolving
     * through variables. If someone swaps it for a literal colour, the panels
     * break again and nothing else would notice.
     */
    public function test_the_blanket_repaint_still_resolves_through_variables(): void
    {
        $themeStyles = (string) file_get_contents(
            resource_path('views/common/partials/theme-styles.blade.php')
        );

        $this->assertStringContainsString(
            'color: var(--text-primary) !important;',
            $themeStyles,
            'The light-mode [class*="text-white"] repaint no longer goes '
            . 'through var(--text-primary). Dark admin panels neutralise it by '
            . 'redefining that variable, so a literal colour there silently '
            . 'breaks every Live preview. Either restore the variable, or give '
            . '.ak-on-dark a different escape and update this test.'
        );

        $this->assertStringContainsString(
            'color: var(--text-muted) !important;',
            $themeStyles,
            'The text-white/NN repaint no longer goes through var(--text-muted); '
            . 'see the assertion above.'
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
