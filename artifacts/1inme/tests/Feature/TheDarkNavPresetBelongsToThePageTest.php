<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Dark nav preset is part of THIS product's dark, not a generic one.
 *
 * "sidebar should sync with body colors... make everything towards
 * black" (Sana). Two things were wrong at once, and only one of them
 * was about being dark:
 *
 *   LUMINANCE  the sidebar measured 24.2 against cards at 15.8 and a
 *              body at 6.6. The chrome was the brightest plane on the
 *              page -- sitting on top of the content instead of under
 *              it.
 *   HUE        every surface this product paints leans violet (blue
 *              channel 7-10 above red). The preset was Tailwind zinc,
 *              lean 3. Side by side it read grey against a violet page,
 *              which is the half a lightness-only check would miss.
 *
 * So the assertions below are about RELATIONSHIPS, not literals. A
 * future retune of the ramp should not have to edit this file; a
 * regression to a brighter or greyer chrome should fail it.
 */
class TheDarkNavPresetBelongsToThePageTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): string
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        return (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    /** The declarations inside the Dark preset's rule for one area. */
    private function darkPreset(string $html, string $area = 'rail'): string
    {
        $scope = $area === 'rail' ? '\.navrail' : ':is\(\.sidebar-shell,\.navigation-mobile,\.navrail-panel\)';
        $ok = preg_match(
            '/html\[data-' . $area . '-appearance="dark"\]\s+' . $scope . '\s*\{([^}]*)\}/',
            $html,
            $m
        );
        $this->assertSame(1, $ok, "The Dark preset rule for '{$area}' is not on the page.");

        return $m[1];
    }

    private function token(string $block, string $name): string
    {
        $ok = preg_match('/' . preg_quote($name, '/') . '\s*:\s*(#[0-9a-fA-F]{6})/', $block, $m);
        $this->assertSame(1, $ok, "{$name} is missing, or is not a plain hex.");

        return strtolower($m[1]);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($hex);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** Positive when the colour leans blue/violet, as every page surface does. */
    private function blueLean(string $hex): int
    {
        [$r, , $b] = $this->rgb($hex);

        return $b - $r;
    }

    public function test_the_nav_surface_sits_below_the_content_cards(): void
    {
        $html = $this->dashboard();
        $navSurface = $this->token($this->darkPreset($html), '--nav-surface');

        // The page's own card colour, read from the theme rather than
        // restated here -- so this compares the two systems that were
        // out of step, instead of comparing the preset to a number.
        $ok = preg_match('/html\.aurora\s*\{(.*?)\}/s', $html, $m);
        $this->assertSame(1, $ok, 'The aurora theme block is not on the page.');
        $bgCard = $this->token($m[1], '--bg-card');
        $bgBody = $this->token($m[1], '--bg-body');

        $this->assertLessThan(
            $this->luminance($bgCard),
            $this->luminance($navSurface),
            'The sidebar is brighter than the content cards: chrome above content.'
        );
        $this->assertGreaterThan(
            $this->luminance($bgBody),
            $this->luminance($navSurface),
            'The sidebar is at or below the page ground, so the card has nothing to stand on.'
        );
    }

    public function test_the_nav_surface_carries_the_page_s_violet_lean(): void
    {
        $navSurface = $this->token($this->darkPreset($this->dashboard()), '--nav-surface');

        $this->assertGreaterThanOrEqual(
            4,
            $this->blueLean($navSurface),
            'The sidebar is neutral grey against a violet-black page.'
        );
    }

    /**
     * The control. Both checks above pass trivially against a value
     * that was never tested, so the palette this change REPLACED has to
     * fail them -- otherwise they are not discriminating anything.
     */
    public function test_the_zinc_palette_this_replaced_would_fail_both_checks(): void
    {
        $zinc = '#18181b';

        $html = $this->dashboard();
        preg_match('/html\.aurora\s*\{(.*?)\}/s', $html, $m);
        $bgCard = $this->token($m[1], '--bg-card');

        $this->assertGreaterThan(
            $this->luminance($bgCard),
            $this->luminance($zinc),
            'Zinc is no longer brighter than the cards, so the luminance check proves nothing.'
        );
        $this->assertLessThan(
            4,
            $this->blueLean($zinc),
            'Zinc now reads as violet, so the hue check proves nothing.'
        );
    }

    /**
     * The scope block defines --bg-card, --bg-sidebar and --text-primary
     * FROM these tokens. Reading them back would be a custom-property
     * cycle, and a cycle computes to nothing rather than to the
     * fallback -- the nav would lose its colours entirely, silently.
     * This is the kind of tidy-up that looks like an improvement.
     */
    public function test_the_preset_defines_its_tokens_without_reading_the_ones_it_feeds(): void
    {
        foreach (['rail', 'panel'] as $area) {
            $block = $this->darkPreset($this->dashboard(), $area);

            $this->assertDoesNotMatchRegularExpression(
                '/var\(\s*--(bg-card|bg-sidebar|text-primary|text-muted)\b/',
                $block,
                "The {$area} Dark preset reads a token it defines: that is a cycle, and a cycle paints nothing."
            );
        }
    }
}
