<?php

namespace Tests\Feature;

use App\Modules\User\Support\PageBackground;
use Tests\TestCase;

/**
 * The block preview is legible on the page it is previewing.
 *
 * Measured on a live restaurant page before the fix: the flyout ground came
 * back #fdfcf7 and the ink came back #ffffff. White on cream, a contrast
 * ratio of 1.02. Every text block in the palette -- Heading, Heading with
 * logo, Text -- rendered as a blank rectangle, and a creator picking a block
 * was choosing from previews that showed nothing.
 *
 * The safeguard already existed. It was gated behind "this page has no
 * background_type", so it ran only for pages that were never themed -- the
 * one case that cannot have a stale font_color. A page themed from dark to
 * light keeps its old white ink in settings, and that is exactly the page
 * that needed it.
 */
class ThePreviewInkIsReadableOnItsOwnGroundTest extends TestCase
{
    /** WCAG contrast ratio between two 6-digit hex colours. */
    private function ratio(string $a, string $b): float
    {
        $lum = function (string $c): float {
            $h = ltrim($c, '#');
            [$r, $g, $bl] = [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
            $lin = fn ($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($bl);
        };
        $la = $lum($a);
        $lb = $lum($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** The exact page that was broken: themed light, stale white ink. */
    private function creamPageThatWasThemedFromDark(): array
    {
        return [
            'background_type'  => 'color',
            'background_color' => '#fdfcf7',
            'font_color'       => '#ffffff',
        ];
    }

    public function test_white_ink_on_a_cream_ground_is_corrected(): void
    {
        $bs = $this->creamPageThatWasThemedFromDark();
        $ground = PageBackground::previewGround($bs);

        $this->assertSame('#fdfcf7', $ground['bg']);
        $this->assertNotSame(
            '#ffffff',
            strtolower($ground['ink']),
            'the preview still inks white on a cream page, which is the invisible-block bug'
        );
        $this->assertGreaterThanOrEqual(
            3.0,
            $this->ratio($ground['ink'], $ground['bg']),
            'the corrected ink is still not readable against the ground'
        );
    }

    /**
     * The control: the fixture really does carry the bad ink.
     *
     * Without this, a fixture that quietly said #212529 would make the test
     * above pass while proving nothing about the safeguard.
     */
    public function test_the_fixture_really_does_carry_the_stale_white_ink(): void
    {
        $bs = $this->creamPageThatWasThemedFromDark();

        $this->assertSame('#ffffff', $bs['font_color']);
        $this->assertLessThan(
            3.0,
            $this->ratio($bs['font_color'], $bs['background_color']),
            'the fixture colours are readable as written, so the safeguard is not being exercised'
        );
    }

    /** A dark page keeps the white ink it is right to have. */
    public function test_white_ink_on_a_dark_ground_is_left_alone(): void
    {
        $ground = PageBackground::previewGround([
            'background_type'  => 'color',
            'background_color' => '#0a0612',
            'font_color'       => '#ffffff',
        ]);

        $this->assertSame('#ffffff', strtolower($ground['ink']));
    }

    /** And a dark ink on a dark page is corrected the other way. */
    public function test_dark_ink_on_a_dark_ground_is_corrected_too(): void
    {
        $ground = PageBackground::previewGround([
            'background_type'  => 'color',
            'background_color' => '#0a0612',
            'font_color'       => '#212529',
        ]);

        $this->assertGreaterThanOrEqual(3.0, $this->ratio($ground['ink'], $ground['bg']));
    }

    /**
     * A gradient ground is left alone, deliberately.
     *
     * readableFontColor measures one colour. Handed a gradient it cannot
     * read, it assumes a dark ground and returns white -- which on a LIGHT
     * gradient is the same invisible-text bug wearing a different hat. Until
     * there is a real measure for a gradient, the page's own choice stands.
     */
    public function test_a_gradient_ground_keeps_the_pages_own_ink(): void
    {
        $ground = PageBackground::previewGround([
            'background_type'     => 'gradient',
            'background_gradient' => 'linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%)',
            'font_color'          => '#ffffff',
        ]);

        $this->assertSame('#ffffff', strtolower($ground['ink']));
    }

    /**
     * A page that was never themed still gets the guard.
     *
     * That case lands on the default gradient, which has no single
     * luminance -- but it is OUR gradient, dark by construction, so it is
     * measured by its own base colour rather than guessed at. The old gated
     * safeguard covered exactly this page, and dropping it would have let a
     * stale dark ink render invisible on the dark default.
     */
    public function test_a_page_that_was_never_themed_still_gets_a_readable_ink(): void
    {
        $ground = PageBackground::previewGround(['font_color' => '#212529']);

        $this->assertSame(PageBackground::DEFAULT_GRADIENT, $ground['bg']);
        $this->assertSame(
            '#ffffff',
            strtolower($ground['ink']),
            'a stale dark ink survived on the dark default ground, where it is invisible'
        );
    }

    /**
     * One card background, not two.
     *
     * The flyout is the card. The frame inside paints the creator's own page
     * ground. A colour on the stage between them is a third surface, and the
     * hardcoded dark wash that used to be there read as a grey band over a
     * white card.
     */
    public function test_the_flyout_stage_has_no_fill_of_its_own(): void
    {
        $css = file_get_contents(resource_path('views/user/links/biolink-editor.blade.php'));

        $this->assertMatchesRegularExpression(
            '/\.block-flyout-stage\s*\{[^}]*\}/s',
            $css,
            'the flyout stage rule is gone entirely'
        );

        preg_match('/\.block-flyout-stage\s*\{([^}]*)\}/s', $css, $m);
        $body = $m[1] ?? '';

        $this->assertDoesNotMatchRegularExpression(
            '/background(-color|-image)?\s*:/',
            $body,
            'the flyout stage paints a fill again, so the card has two backgrounds'
        );
    }
}
