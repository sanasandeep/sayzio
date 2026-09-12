<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The hero's two stacked-layout fixes, both of which are invisible in markup.
 *
 * Sana: "its looks odd in mobile screeennnn". Measured at 390px, two things
 * were:
 *
 *   1. The proof badges wrapped. Three badges of different widths (219 / 182 /
 *      158px) cannot share a line, so each took its own and each was centred
 *      separately -- three ragged fragments with their dots at three different
 *      x positions. A single-column grid with the column centred and the items
 *      started lines the dots up and centres the block as a whole.
 *
 *   2. Zio's speech bubble overlapped those badges by 5px at 390px and 12px at
 *      430px. The bubble is absolutely positioned at `bottom: 100%` of the
 *      orbit, so it hangs ABOVE the visual column's box, and the grid's row gap
 *      measures from the box rather than from the bubble. No amount of gap
 *      fixes that honestly; the column has to reserve the space.
 *
 * Both are CSS-only, so these are string assertions and that is worth naming:
 * there is no DOM or HTTP signal for either, and a layout needs a browser to
 * compute. The real check was measuring the rendered page in Chromium at 360,
 * 390, 430, 600 and 767px. What these pin is that the two mechanisms are still
 * present, so removing one fails here rather than on someone's phone.
 */
class HeroStacksTidilyOnAPhoneTest extends TestCase
{
    private function hero(): string
    {
        return (string) preg_replace('/\s+/', ' ', (string) file_get_contents(
            resource_path('views/home/partials/hero.blade.php')
        ));
    }

    public function test_the_proof_badges_stack_instead_of_wrapping(): void
    {
        $hero = $this->hero();

        $this->assertMatchesRegularExpression(
            '/class="reveal rd-4 grid justify-center justify-items-start[^"]*lg:flex/',
            $hero,
            'The hero proof badges are back to a wrapping flex row at phone '
            . 'width, which cannot fit three badges on a line and centres each '
            . 'leftover separately.'
        );
    }

    /**
     * The bubble's clearance. Losing this rule is silent on desktop, where the
     * columns sit side by side and nothing is above the bubble to hit.
     */
    public function test_the_visual_column_reserves_room_for_the_speech_bubble(): void
    {
        $hero = $this->hero();

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 1023px\) \{ \.zio-hero-visual \{ padding-top: \d+px; \} \}/',
            $hero,
            'The stacked hero no longer reserves room above Zio for his speech '
            . 'bubble. The bubble hangs above the column box, so it lands on '
            . 'top of the proof badges -- 5px into them at 390px, 12px at 430px.'
        );
    }
}
