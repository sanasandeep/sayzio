<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rail sidebar is ONE CARD, and its account row is the only way out.
 *
 * Direction A of the shell boards. Three of Sana's complaints turned out
 * to be one question -- what the sidebar is as an object -- so they are
 * tested together here rather than as three unrelated patches:
 *
 *   "side bar is like a card... so i need that way"   (the card)
 *   "badges and setting menu can be remove... its repeate"  (the clear-out)
 *   "logout, plan update settings... like dropdown menu to name row"
 *
 * WHY THESE RENDER THE PAGE INSTEAD OF READING THE BLADE
 *
 * The last rail bug shipped green: every rail colour rule was scoped to
 * `.navrail-rail`, a class the markup has never had, and the tests
 * passed because they only ever asked what the STYLESHEET said. So the
 * assertions below go through an actual request and read the html that
 * came back. The one source-level check that remains -- the class
 * coverage test -- compares the stylesheet against that same rendered
 * html, not against the Blade.
 *
 * EVERY REMOVAL HAS A CONTROL
 *
 * Asserting that something is absent passes just as well when the whole
 * sidebar failed to render, so each absence is paired with a classic-nav
 * render where the same markup must still be present.
 */
class TheRailShellIsOneCardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An established owner. Owners pass every WorkspacePermissions check,
     * and an older onboarded_at keeps the onboarding gate from answering
     * 302 before the sidebar is ever rendered.
     */
    private function owner(array $settings = []): User
    {
        return User::factory()->create([
            'onboarded_at' => now()->subMonths(6),
            'settings'     => $settings,
        ])->fresh();
    }

    /** The desktop sidebar only: the mobile drawer repeats much of it. */
    private function desktopSidebar(User $user): string
    {
        $html = (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();

        $start = strpos($html, 'sidebar-v2 sidebar-shell');
        $this->assertNotFalse($start, 'The desktop sidebar did not render at all.');
        $end = strpos($html, '</aside>', $start);
        $this->assertNotFalse($end, 'The desktop sidebar never closed.');

        return substr($html, $start, $end - $start);
    }

    public function test_the_rail_sidebar_is_a_card_and_the_classic_one_is_not(): void
    {
        $this->assertStringContainsString('rail-card', $this->desktopSidebar($this->owner()));

        // The control. `rail-card` must come from the rail branch, not
        // from the class list being unconditional.
        $this->assertStringNotContainsString(
            'rail-card',
            $this->desktopSidebar($this->owner(['nav_pack' => 'classic'])),
            'The classic sidebar is flush against the edge; it must not take the card.'
        );
    }

    public function test_the_card_is_inset_and_keeps_its_corners(): void
    {
        $css = $this->desktopSidebar($this->owner());

        // Inset on three sides: a card pinned to any window edge is a
        // slab with rounded corners, which is what this replaces.
        $this->assertMatchesRegularExpression(
            '/\.sidebar-shell\.rail-card \{[^}]*top: 14px[^}]*bottom: 14px[^}]*left: 14px/s',
            $css,
            'The card must be inset from the window on three sides.'
        );

        // The shell cannot clip, because the flyout is a child of it.
        // This is the rule that made the flyout work; overflow:hidden
        // here would delete it on screen while every test still passed.
        $this->assertDoesNotMatchRegularExpression(
            '/\.sidebar-shell\.rail-card \{[^}]*overflow:\s*hidden/s',
            $css,
            'Clipping the shell would swallow the minimised flyout.'
        );

        // So the corners are cut by the two columns instead.
        $this->assertStringContainsString('.rail-card .navrail { border-radius: 15px 0 0 15px; }', $css);
        $this->assertStringContainsString('.rail-card .navrail-panel { border-radius: 0 15px 15px 0; }', $css);
    }

    public function test_the_account_row_carries_all_four_destinations(): void
    {
        $sidebar = $this->desktopSidebar($this->owner());

        $start = strpos($sidebar, 'navrail-account-menu');
        $this->assertNotFalse($start, 'The account menu did not render.');
        $menu = substr($sidebar, $start);

        foreach ([
            'the plan'      => '/user/upgrade',
            'settings'      => 'data-account-settings-link',
            'logging out'   => '/user/logout',
        ] as $what => $needle) {
            $this->assertStringContainsString(
                $needle, $menu, "The account menu lost its route to {$what}."
            );
        }

        // It opens upward: the row sits at the foot of a full-height
        // card, so a menu hanging below it would be off the window.
        $this->assertMatchesRegularExpression(
            '/\.navrail-account-menu \{[^}]*bottom: \d+px/s',
            $sidebar,
            'The account menu must be anchored to its bottom edge.'
        );
    }

    public function test_the_three_stacked_footer_blocks_are_gone_from_the_rail(): void
    {
        $rail = $this->desktopSidebar($this->owner());

        foreach ([
            'the plan card'          => 'upgrade-card',
            'the admin switch strip' => 'admin-switch-btn',
            'the avatar logout row'  => 'logout-btn',
        ] as $what => $needle) {
            $this->assertStringNotContainsString(
                $needle, $rail, "{$what} is still stacked at the foot of the rail sidebar."
            );
        }

        // The control: all three are still the classic sidebar's.
        $classic = $this->desktopSidebar($this->owner(['nav_pack' => 'classic']));
        foreach (['upgrade-card', 'admin-switch-btn', 'logout-btn'] as $needle) {
            $this->assertStringContainsString(
                $needle, $classic,
                "{$needle} vanished from the classic sidebar too, so the test above proves nothing."
            );
        }
    }

    public function test_the_duplicate_settings_link_and_the_badge_chips_are_out(): void
    {
        $badge = AccountBadge::create(['name' => 'Beta tester', 'color' => '#3b82f6']);

        $railUser = $this->owner();
        $railUser->accountBadges()->attach($badge->id);
        $rail = $this->desktopSidebar($railUser->fresh());

        $this->assertStringNotContainsString('Your badges', $rail);
        $this->assertStringNotContainsString('Beta tester', $rail);
        $this->assertSame(
            1,
            substr_count($rail, 'data-account-settings-link'),
            'Settings is a rail section AND a menu row; it must not also be a link in the list.'
        );

        // The control: the user really does have a badge, and the
        // classic sidebar really does still show it.
        $classicUser = $this->owner(['nav_pack' => 'classic']);
        $classicUser->accountBadges()->attach($badge->id);
        $classic = $this->desktopSidebar($classicUser->fresh());
        $this->assertStringContainsString('Your badges', $classic);
        $this->assertStringContainsString('Beta tester', $classic);
    }

    public function test_the_active_tile_is_a_gradient_built_from_the_accent(): void
    {
        $css = $this->desktopSidebar($this->owner());

        $this->assertMatchesRegularExpression(
            '/\.navrail-btn\.is-active \{[^}]*background: linear-gradient\([^)]*var\(--accent\)/s',
            $css,
            'The active tile must be a gradient, and it must be built from the accent token.'
        );
        // A literal here would be a hole in the appearance presets, which
        // work by redefining --accent and nothing else.
        $this->assertDoesNotMatchRegularExpression(
            '/\.navrail-btn\.is-active \{[^}]*background: linear-gradient\([^)]*#[0-9a-fA-F]{3,8}/s',
            $css,
            'A hex in the active tile would not follow the theme or the appearance presets.'
        );
    }

    /**
     * The generalised form of the .navrail-rail bug: a rule whose
     * selector names a class that nothing renders. Here the comparison
     * is against the page that actually came back, both navs included,
     * so an interpolated class name resolves itself.
     */
    public function test_every_class_the_rail_stylesheet_styles_exists_on_the_page(): void
    {
        $html = (string) $this->actingAs($this->owner())->get('/user/dashboard')->assertOk()->getContent();

        $sheet = resource_path('views/user/partials/nav-rail.blade.php');
        $src = file_get_contents($sheet);
        $css = substr($src, strpos($src, '<style>'), strpos($src, '</style>') - strpos($src, '<style>'));

        preg_match_all('/\.(navrail[\w-]*|rail-card)\b/', $css, $found);
        $styled = array_unique($found[1]);
        $this->assertGreaterThan(10, count($styled), 'Too few classes found; the sheet was not read.');

        $orphans = [];
        foreach ($styled as $class) {
            if (!preg_match('/class="[^"]*\b' . preg_quote($class, '/') . '\b/', $html)) {
                $orphans[] = $class;
            }
        }
        $this->assertSame([], $orphans, 'Styled but never rendered: ' . implode(', ', $orphans));

        // The control. The search has to be able to fail, or a typo in
        // the regex above would report everything as covered.
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*\bnavrail-not-a-real-class\b/',
            $html,
            'The coverage search matches anything, so it proves nothing.'
        );
    }
}
