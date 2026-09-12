<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zio is the same animated character in both places he appears.
 *
 * He is drawn twice on the homepage: large in the hero, and small at the
 * centre of the "Zio is not eight tools. It is one." hub. The hub used a flat
 * PNG of his head, which quietly argued the opposite of the section it sits
 * in -- a diagram about one living assistant with a dead thing at the middle.
 *
 * Both now build him from home/partials/zio-face, so he blinks and his
 * antennae sway in both.
 *
 * WHAT THIS GUARDS, AND WHY IT IS SHAPED THIS WAY
 *
 * The markup is in the partial; the CSS that animates it lives in the hero
 * partial's <style> block. That is a real coupling across two files, and the
 * hub is delivered as a SEPARATE HTTP response -- the deferred sections
 * fragment -- so the two halves never appear in the same document at build
 * time and nothing about the fragment alone would show the animation missing.
 *
 * Earlier this week a light-mode fix shipped broken because its test asserted
 * some CSS existed rather than that it reached the element it had to. So this
 * asserts both ends: the fragment carries the faces, and the page that hosts
 * the fragment carries the rules and keyframes that bring them to life. Drop
 * either and Zio silently goes back to being a still.
 */
class HomepageZioIsAliveInBothPlacesTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    /** The below-the-fold sections, which is where the hub lives. */
    private function sections(): string
    {
        return $this->get('/home/sections')->assertOk()->getContent();
    }

    public function test_the_hub_draws_the_live_zio_not_a_still(): void
    {
        $sections = $this->sections();

        $this->assertStringContainsString(
            'class="zio-face"',
            $sections,
            'the Zio hub is back to a flat image at its centre'
        );

        $this->assertStringNotContainsString(
            'zio-bot.png',
            $sections,
            'the old still of Zio\'s head is back in the hub; the hub should '
            . 'render the same animated face the hero does'
        );

        // Both placements: the diagram at wide widths, and the list that
        // replaces it below 900px.
        $this->assertSame(
            2,
            substr_count($sections, 'class="zio-face"'),
            'the hub has a diagram version and a narrow-screen list version, '
            . 'and both should show the same live Zio'
        );

        // The layers that do the moving.
        foreach (['zio-ant--l', 'zio-ant--r', 'zio-lid--l', 'zio-lid--r'] as $layer) {
            $this->assertStringContainsString($layer, $sections, "the $layer layer is missing from the hub");
        }
    }

    /**
     * The mouth is timed to the hero's speech bubbles, line for line. There
     * are no bubbles in the hub, so a mouth there would be Zio chattering at
     * nothing -- he keeps the smile painted into the artwork instead.
     */
    public function test_the_hub_zio_does_not_talk_to_nobody(): void
    {
        $this->assertStringNotContainsString(
            'zio-mouth-gate',
            $this->sections(),
            'the hub Zio has a mouth again; it animates on the hero bubble '
            . 'cycle and there are no bubbles here, so it would open and close '
            . 'at nothing'
        );
    }

    public function test_the_hero_zio_still_talks(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('class="zio-face"', $page);
        $this->assertStringContainsString(
            'zio-mouth-gate',
            $page,
            'the hero Zio lost his mouth, so he no longer speaks his lines'
        );
    }

    /**
     * The half that a fragment-only test cannot see.
     */
    public function test_the_page_carries_the_css_that_animates_him(): void
    {
        $page = $this->page();

        foreach (['zioBlink', 'zioAntL', 'zioAntR', 'zioFloat'] as $keyframes) {
            $this->assertStringContainsString(
                '@keyframes ' . $keyframes,
                $page,
                "@keyframes $keyframes is not on the page. The hub's Zio is "
                . 'delivered in a separate fragment and relies on this document '
                . 'for its animation, so losing it leaves a still image with no '
                . 'error anywhere.'
            );
        }

        $this->assertStringContainsString(
            '.zio-face {',
            $page,
            'the .zio-face rules are gone from the page, so neither Zio has a '
            . 'size or a float to animate'
        );
    }

    /**
     * The float is the one distance that was a fixed pixel value, and Zio is
     * now drawn at sizes an order of magnitude apart. Left at 12px it would
     * bob a 66px head as far as it bobs a 500px one.
     */
    public function test_the_float_scales_with_the_artwork(): void
    {
        $hero = (string) file_get_contents(
            resource_path('views/home/partials/hero.blade.php')
        );

        // Not [^}]* -- the keyframe's own percentage blocks carry braces, so
        // that stops at the first one and never reaches the 50% step.
        $this->assertMatchesRegularExpression(
            '/@keyframes zioFloat \{.{0,400}?translateY\(calc\(var\(--size/s',
            (string) preg_replace('/\s+/', ' ', $hero),
            'the hero float is back to a fixed pixel rise; at the hub\'s size '
            . 'that is a small head jumping out of its card'
        );
    }
}
