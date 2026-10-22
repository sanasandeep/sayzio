<?php

namespace Tests\Feature;

use App\Modules\Common\Support\SurfaceTreatment;
use App\Modules\User\Support\PatternCatalog;
use Tests\TestCase;

/**
 * Splitting a pattern into a geometry and a colourway changes nothing
 * that is already on screen.
 *
 * The twelve shipped patterns each hardcode a background colour and an
 * ink colour. That is right for a published link page and wrong for app
 * chrome, where a background should dress the theme rather than replace
 * it -- `pattern_stripes_sand` on the side panel is a cream column in
 * dark mode with the nav's own light ink still on top of it.
 *
 * So the geometry now writes {ink} where those presets wrote a literal,
 * and a colourway supplies the colours. The risk that creates is a
 * silent visual regression on every page that already chose one of the
 * twelve, so the central test here renders each key both ways and
 * requires the declarations to match.
 *
 * It compares against PatternCatalog ITSELF rather than against a copy
 * of its CSS pasted in here. A transcription would pass forever while
 * the two drifted apart, which is the failure mode this file exists to
 * prevent.
 *
 * WHAT THIS ALREADY CAUGHT
 *
 * Two of the twelve are two or four copies of ONE gradient, told apart
 * only by background-position: checker is a checkerboard purely because
 * its second copy is offset by half a tile, and zigzag likewise. A first
 * pass dropped the offsets and folded zigzag into diamonds. Both still
 * rendered something that looked deliberate -- stripes, and a slightly
 * wrong diamond -- which is exactly how that kind of regression gets
 * merged.
 */
class TheTwelveFixedPatternsBecomeGeometryAndColourTest extends TestCase
{
    /**
     * One declaration block as a comparable map.
     *
     * `background-repeat: repeat` is dropped because it is the CSS
     * initial value: a preset that states it paints identically to one
     * that leaves it out. That is the only normalisation, and it is here
     * so that the rest of the comparison stays strict.
     *
     * @return array<string, string>
     */
    private function declarations(string $css): array
    {
        $out = [];

        foreach (explode(';', $css) as $declaration) {
            $declaration = trim(preg_replace('/\s+/', ' ', $declaration) ?? '');

            if ($declaration === '' || $declaration === 'background-repeat: repeat') {
                continue;
            }

            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $out[trim($property)] = trim($value);
        }

        ksort($out);

        return $out;
    }

    public function test_every_shipped_pattern_is_mapped(): void
    {
        $unmapped = array_values(array_diff(
            array_keys(PatternCatalog::shipped()),
            array_keys(SurfaceTreatment::LEGACY)
        ));

        $this->assertSame([], $unmapped, 'Shipped patterns with no geometry: ' . implode(', ', $unmapped));
    }

    public function test_each_one_still_paints_exactly_what_it_painted_before(): void
    {
        foreach (array_keys(PatternCatalog::shipped()) as $key) {
            $before = PatternCatalog::shipped()[$key]['css'];
            $after  = SurfaceTreatment::legacyCss($key);

            $this->assertNotNull($after, "{$key} no longer resolves to any CSS.");
            $this->assertSame(
                $this->declarations((string) $before),
                $this->declarations((string) $after),
                "{$key} renders differently than it did before the split."
            );
        }
    }

    /**
     * The regression that nearly shipped. These two geometries ARE their
     * offsets; without them the gradients stack and paint stripes.
     */
    public function test_the_geometries_that_are_nothing_but_offsets_keep_them(): void
    {
        foreach (['pattern_checker_mono', 'pattern_zigzag_teal'] as $key) {
            $this->assertStringContainsString(
                'background-position',
                (string) SurfaceTreatment::legacyCss($key),
                "{$key} lost its offsets, so its copies stack into stripes."
            );
        }
    }

    public function test_a_geometry_names_a_shape_and_never_a_colour(): void
    {
        $withColour = [];

        foreach (SurfaceTreatment::GEOMETRIES as $name => $meta) {
            if ($meta['image'] !== null && preg_match('/#[0-9a-fA-F]{3,8}|rgba?\(/', $meta['image'])) {
                $withColour[] = $name;
            }
        }

        $this->assertSame([], $withColour, 'Geometries with a colour baked in: ' . implode(', ', $withColour));

        // The control. The search must be able to find a baked-in colour,
        // or the assertion above passes by looking for the wrong thing --
        // and the shipped catalogue is full of them.
        $this->assertMatchesRegularExpression(
            '/#[0-9a-fA-F]{3,8}|rgba?\(/',
            PatternCatalog::shipped()['pattern_honeycomb_amber']['css'],
            'The shipped preset has no literal colour, so the check above proves nothing.'
        );
    }

    /**
     * "all designs applicable for all" has one measured exception. The
     * icon rail is 56px wide; a 12px crosshatch across it is four
     * repeats, which reads as noise and no colour rescues it.
     */
    public function test_the_icon_rail_is_offered_nothing_that_repeats(): void
    {
        foreach (array_keys(SurfaceTreatment::offered('rail')) as $geometry) {
            $this->assertSame(
                0,
                SurfaceTreatment::GEOMETRIES[$geometry]['tile'],
                "The 56px rail is offered '{$geometry}', which repeats."
            );
        }

        // The control: wider surfaces really are offered repeating ones,
        // so the assertion above is not passing on an empty list.
        $repeating = array_filter(
            array_keys(SurfaceTreatment::offered('page')),
            static fn ($g) => SurfaceTreatment::GEOMETRIES[$g]['tile'] > 0
        );
        $this->assertNotEmpty($repeating, 'No surface offers a repeating geometry, so nothing is being restricted.');
    }

    public function test_a_surface_is_never_offered_a_tile_it_cannot_carry(): void
    {
        foreach (SurfaceTreatment::SURFACES as $surface => $limits) {
            foreach (array_keys(SurfaceTreatment::offered($surface)) as $geometry) {
                $tile = SurfaceTreatment::GEOMETRIES[$geometry]['tile'];

                if ($tile === 0) {
                    continue;
                }

                $this->assertGreaterThanOrEqual($limits['min'], $tile, "{$surface} offers {$geometry}, whose tile is too fine.");
                $this->assertLessThanOrEqual($limits['max'], $tile, "{$surface} offers {$geometry}, whose tile is clipped.");
            }
        }
    }

    /**
     * Admin rows reach this class from the database, and the catalogues'
     * own rule is that CSS is resolved server-side and never taken from
     * client input. A colour is a colour; anything carrying a semicolon,
     * a brace or a url() is not one.
     */
    public function test_a_colour_that_is_not_a_colour_drops_the_whole_treatment(): void
    {
        foreach (['#fff;background:url(//x)', 'red}body{display:none', 'url(//x/y.png)', '', '  '] as $bad) {
            $this->assertFalse(SurfaceTreatment::isColour($bad), "Accepted '{$bad}' as a colour.");
            $this->assertNull(
                SurfaceTreatment::css('dots', $bad, '#fff'),
                "A bad base colour still produced CSS; a partial background paints an empty box."
            );
            $this->assertNull(SurfaceTreatment::css('dots', '#fff', $bad), 'A bad ink colour still produced CSS.');
        }

        // The control: real colours must get through, or the test above
        // is satisfied by a function that rejects everything.
        foreach (['#0b0a11', '#fff', 'rgba(167,139,250,0.16)', 'rgb(11,10,17)', 'transparent'] as $good) {
            $this->assertTrue(SurfaceTreatment::isColour($good), "Rejected '{$good}', which is a colour.");
        }
        $this->assertNotNull(SurfaceTreatment::css('dots', '#0b0a11', 'rgba(167,139,250,0.16)'));
    }

    public function test_a_rendered_treatment_has_no_placeholder_left_in_it(): void
    {
        foreach (array_keys(SurfaceTreatment::GEOMETRIES) as $geometry) {
            $css = SurfaceTreatment::css($geometry, '#0b0a11', 'rgba(167,139,250,0.16)');

            $this->assertNotNull($css, "{$geometry} produced no CSS at all.");
            $this->assertStringNotContainsString('{ink}', (string) $css, "{$geometry} left its placeholder in the stylesheet.");
        }
    }
}
