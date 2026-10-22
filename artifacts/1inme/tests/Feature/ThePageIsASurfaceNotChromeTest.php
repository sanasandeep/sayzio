<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\NavigationAppearance;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page background is a fourth area, and it is not chrome.
 *
 * "admin must be able to change backgrounds for sidebar, page, top bar,
 * side panel" (Sana). The first three were already areas; the page is
 * new, and it is the one that cannot take the same treatment as the
 * others.
 *
 * TWO WAYS THE OBVIOUS IMPLEMENTATION BREAKS THINGS
 *
 * 1. The token block. Every other area's rule redefines --bg-card,
 *    --text-primary and the rest for its own subtree, so the controls
 *    inside that column follow the preset. The page's scope is `body`,
 *    so the same block would redefine them for the whole document and
 *    repaint every card on every screen. Choosing a page background is
 *    not choosing a card colour.
 *
 * 2. Inheritance. Custom properties inherit, and nav-rail reads
 *    `var(--nav-surface, var(--bg-card))` in three places -- the account
 *    block, the presence dot and the minimised flyout -- each relying on
 *    that fallback when no preset is chosen. Setting --nav-surface on
 *    body would quietly hand them the page's colour, so a light page
 *    would repaint the flyout inside a dark rail. Nothing would error.
 *
 * So the page emits a background and no custom properties at all, and
 * the test below is written against that second failure specifically,
 * because it is invisible until someone combines the two settings.
 */
class ThePageIsASurfaceNotChromeTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): string
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        return (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    /** The declarations of one area's rule for one preset. */
    private function rule(string $html, string $area, string $scope, string $preset): string
    {
        $ok = preg_match(
            '/html\[data-' . $area . '-appearance="' . preg_quote($preset, '/') . '"\]\s+'
                . preg_quote($scope, '/') . '\s*\{([^}]*)\}/',
            $html,
            $m
        );
        $this->assertSame(1, $ok, "No {$preset} rule for the {$area}.");

        return $m[1];
    }

    public function test_the_page_is_an_area_the_pickers_will_offer(): void
    {
        $this->assertArrayHasKey('page', NavigationAppearance::AREAS);
        $this->assertContains('page', NavigationAppearance::SURFACE_ONLY_AREAS);

        // Validation builds itself from the catalogue, so a new area is
        // accepted without anyone editing the controller.
        $this->assertStringContainsString('dark', NavigationAppearance::rule());
    }

    public function test_every_preset_paints_the_page(): void
    {
        $html = $this->dashboard();

        foreach (NavigationAppearance::PALETTES as $preset => $palette) {
            $body = $this->rule($html, 'page', 'body', $preset);

            $this->assertStringContainsString(
                'background:' . $palette['surface'],
                $body,
                "The page's {$preset} rule does not paint the palette's surface."
            );
        }
    }

    /**
     * The leak. A page preset must not put a --nav-* property on body,
     * because three things inside the rail read one with a fallback.
     */
    public function test_the_page_rule_sets_no_custom_property(): void
    {
        $html = $this->dashboard();
        $leaked = [];

        foreach (array_keys(NavigationAppearance::PALETTES) as $preset) {
            $body = $this->rule($html, 'page', 'body', $preset);

            if (preg_match_all('/(--[a-z-]+)\s*:/', $body, $m)) {
                $leaked = array_merge($leaked, $m[1]);
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($leaked)),
            'The page sets custom properties on body; they inherit into the rail: ' . implode(', ', array_unique($leaked))
        );

        // The control. The chrome areas DO set them, so the search above
        // is capable of finding one.
        $rail = $this->rule($html, 'rail', '.navrail', 'dark');
        $this->assertMatchesRegularExpression(
            '/--nav-surface\s*:/',
            $rail,
            'No area sets a custom property, so the check above proves nothing.'
        );
    }

    /**
     * The rail and the panel inherit the old single `sidebar` choice.
     * The page must not: an account that once chose a dark sidebar would
     * otherwise wake up with a dark page it never asked for.
     */
    public function test_the_legacy_sidebar_choice_does_not_reach_the_page(): void
    {
        AppSetting::put(NavigationAppearance::SETTING, [NavigationAppearance::LEGACY_AREA => 'dark']);

        $defaults = NavigationAppearance::defaults();

        $this->assertSame('dark', $defaults['rail'], 'The rail should inherit the old sidebar choice.');
        $this->assertSame('dark', $defaults['panel'], 'The panel should inherit the old sidebar choice.');
        $this->assertSame('theme', $defaults['page'], 'A dark sidebar silently became a dark page.');
        $this->assertSame('theme', $defaults['topbar'], 'A dark sidebar silently became a dark top bar.');
    }

    public function test_an_explicit_page_choice_is_kept(): void
    {
        AppSetting::put(NavigationAppearance::SETTING, [
            NavigationAppearance::LEGACY_AREA => 'dark',
            'page' => 'gradient-light',
        ]);

        $this->assertSame('gradient-light', NavigationAppearance::defaults()['page']);
    }
}
