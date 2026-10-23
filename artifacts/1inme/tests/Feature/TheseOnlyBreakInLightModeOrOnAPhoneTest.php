<?php

namespace Tests\Feature;

use App\Modules\Common\Support\NavigationAppearance;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Four faults that were all invisible from a wide screen in dark mode.
 *
 * None of them threw, logged, or looked broken to anyone developing on a
 * dark desktop. They are grouped because that is what they have in
 * common, and because it is the condition -- not the component -- that
 * needs a guard.
 *
 *   The rail card had no shadow and a border on one side, so in light
 *   mode it was a white card on a white page at contrast 1.00.
 *
 *   The selected nav row was painted #e8edff on white: contrast 1.17,
 *   drawn but not visible. Reported as "active button css missing".
 *
 *   A toned top bar was a square full-bleed slab beside an inset,
 *   rounded sidebar card.
 *
 *   The page hero's narrow-screen padding lost a specificity contest it
 *   never looked like it was in, so on a phone a 150px inset meant for
 *   decorative rings left about 190px for the content.
 */
class TheseOnlyBreakInLightModeOrOnAPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): string
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        return (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    /**
     * Perceptual lightness, 0..100.
     *
     * A WCAG contrast RATIO is the wrong measure for two surfaces that
     * sit next to each other near the dark end: #0b0a11 against #201f2e
     * scores 1.22 and is plainly visible, while #ffffff against #e8edff
     * scores 1.17 and is not. L* does not compress that way, so one
     * threshold works at both ends of the range.
     */
    private function lightness(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        $y = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $y > 0.008856 ? 116 * ($y ** (1 / 3)) - 16 : 903.3 * $y;
    }

    private function gap(string $a, string $b): float
    {
        return abs($this->lightness($a) - $this->lightness($b));
    }

    /**
     * "sidebar and panel border not clear in light mode" (Sana).
     *
     * The aurora rule that strips the shadow is correct for the flush
     * slab it was written for, and must simply stop reaching the card.
     */
    public function test_the_rail_card_keeps_the_shadow_that_separates_it_from_the_page(): void
    {
        $html = $this->dashboard();

        foreach (['html.aurora aside.sidebar-shell:not(.rail-card)',
                  'html.aurora.light-mode aside.sidebar-shell:not(.rail-card)'] as $selector) {
            $this->assertStringContainsString(
                $selector,
                $html,
                'The aurora shadow rule still reaches the rail card, which leaves it a white card on a white page.'
            );
        }

        // The control. The rule must still apply to the classic slab --
        // excluding it from everything would also pass the assertion above.
        $this->assertStringContainsString(
            'border-right: 1px solid var(--border-glass) !important',
            $html,
            'The slab treatment vanished entirely, so the exclusion above proves nothing.'
        );

        // And the card still states a shadow of its own to be stripped.
        $this->assertMatchesRegularExpression(
            '/\.sidebar-shell\.rail-card \{[^}]*box-shadow: var\(--card-shadow-hover\)/s',
            $html,
            'The card no longer asks for a shadow, so restoring it changes nothing.'
        );
    }

    /**
     * "active button css missing on light mode" (Sana). It was not
     * missing; it was 6.1 points of lightness away from the surface
     * under it.
     */
    public function test_a_selected_row_is_visible_against_its_own_surface(): void
    {
        $checked = 0;

        foreach (array_keys(NavigationAppearance::PRESETS) as $preset) {
            $tokens = NavigationAppearance::tokens($preset);

            if ($tokens === []) {
                continue; // gradients derive their active fill from the ink.
            }

            $gap = $this->gap($tokens['active'], $tokens['surface']);
            $this->assertGreaterThanOrEqual(
                8.0,
                $gap,
                "The {$preset} preset's selected row is only " . round($gap, 1)
                    . ' points of lightness from its own surface.'
            );
            $checked++;
        }

        $this->assertGreaterThan(1, $checked, 'No preset was checked, so this test proves nothing.');

        // The control: the value this replaced must fail the same test.
        $this->assertLessThan(
            8.0,
            $this->gap('#e8edff', '#ffffff'),
            'The colour that was invisible now passes, so the threshold is not discriminating.'
        );
    }

    /** A toned top bar should read as the same object as the sidebar card. */
    public function test_a_toned_top_bar_is_a_card_and_an_untoned_one_is_not(): void
    {
        $html = $this->dashboard();

        $ok = preg_match(
            '/html\[data-topbar-appearance\]:not\(\[data-topbar-appearance="theme"\]\)\s+header\.header-v2\s*\{([^}]*)\}/',
            $html,
            $m
        );
        $this->assertSame(1, $ok, 'A toned top bar has no card treatment.');

        // The radius must be !important: the header carries Tailwind's
        // `!rounded-none`, which is itself !important.
        $this->assertMatchesRegularExpression(
            '/border-radius:\s*16px\s*!important/',
            $m[1],
            'Without !important the radius loses to the header\'s own !rounded-none.'
        );

        // The control: the selector must exclude `theme`, or an untoned
        // top bar stops merging into the page, which is what light mode
        // is supposed to do.
        $this->assertStringContainsString(':not([data-topbar-appearance="theme"])', $m[0]);
    }

    /**
     * The hero's narrow-screen padding has to out-specify the rule it
     * overrides.
     *
     * WHAT THIS ASSERTS, AND WHAT IT DOES NOT
     *
     * It checks that the override carries the SAME selector as the rule
     * it overrides, which is what makes it win on source order. It does
     * not compute specificity, so it would not catch an override written
     * at some third, higher specificity that also happened to work.
     *
     * That is deliberate: same-selector is the property the fix actually
     * relies on, and the bug it guards against is precisely writing the
     * override at a different specificity -- which is what shipped, and
     * which looked completely reasonable:
     *
     *     html.light-mode[data-hero-style="rings-violet"] .page-hero   (0,3,1)
     *     html[data-hero-style] .page-hero                             (0,2,1)
     */
    public function test_the_heros_phone_padding_is_written_at_the_same_specificity_as_the_inset(): void
    {
        $html = $this->dashboard();

        // The bare form that lost. If it comes back, so does the bug.
        $this->assertStringNotContainsString(
            'html[data-hero-style] .page-hero { padding: 14px; }',
            $html,
            'The old global override is back; it never applied, because it was less specific.'
        );

        // Anchored to a line start, and barred from crossing a brace.
        // The obvious version -- /(\S[^{]*?)\s*\.page-hero \{/ -- captured
        // the TAIL of the preceding rule along with the selector, because
        // \S happily matched a closing brace. It produced a failure that
        // looked like a real one.
        preg_match_all('/^\s*([^{}\n]+?)\s*\.page-hero \{\s*padding: 18px 22px 18px 150px/m', $html, $insets);
        $this->assertNotEmpty($insets[1], 'No ring inset found; the hero styles did not render.');

        foreach (array_unique($insets[1]) as $selector) {
            $this->assertMatchesRegularExpression(
                '/@media \(max-width: 900px\) \{\s*' . preg_quote($selector, '/') . ' \.page-hero/',
                $html,
                "The 150px inset on `{$selector}` has no narrow-screen override at its own specificity."
            );
        }
    }
}
