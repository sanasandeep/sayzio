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

    /**
     * Two facts that were MEASURED on the rendered page and can only be
     * kept by being written down, because nothing about reading the
     * stylesheet would tell you either one.
     */
    public function test_the_minimised_card_and_the_plan_row_keep_what_was_measured(): void
    {
        $css = $this->desktopSidebar($this->owner());

        // The rail is 56px, and a 56px card's content box is 54px, so a
        // rail left at its fixed width hangs 2px past the border it is
        // supposed to sit inside. It takes the card's width instead.
        $this->assertMatchesRegularExpression(
            '/\.rail-card \.navrail-wrap\[data-rail-min="1"\] \.navrail \{[^}]*width: 100%/s',
            $css,
            'The minimised rail must take the card width, or it overhangs the card border by 2px.'
        );

        // Label and status side by side in a 218px menu wrapped BOTH
        // halves onto two lines. They stack.
        // The account block measured 135px tall; the panel's bottom
        // padding has to clear it or the last link sits underneath.
        $this->assertMatchesRegularExpression(
            '/\\.navrail-panel-scroll \\{[^}]*padding: 6px 10px 144px/s',
            $css,
            'The panel must leave room for the account block floating over its foot.'
        );

        $this->assertStringContainsString('navrail-account-stack', $css);
        $this->assertMatchesRegularExpression(
            '/\.navrail-account-stack \{[^}]*flex-direction: column/s',
            $css,
            'The plan row stacks its label and status; side by side they both wrap.'
        );
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
     * Items 2 and 3: minimised, the rail has to carry what the panel
     * carried. The avatar sits at its foot "just like logo on top",
     * and the workspace and folder lists get tiles of their own rather
     * than being two clicks inside a section nobody chose.
     */
    public function test_the_minimised_rail_carries_the_avatar_and_two_utility_tiles(): void
    {
        $sidebar = $this->desktopSidebar($this->owner());

        // The tiles are server-rendered and hidden by Alpine, not the
        // other way round, so they are in the markup to be found.
        // Expanded, the switcher is the panel's head row and Folders is
        // the first group in Home, so a tile for either would be the
        // same destination twice: both are gated on railMin.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*x-show="railMin"[^>]*title="Folders"/s',
            $sidebar,
            'The folders tile must exist and must be minimised-only.'
        );
        $this->assertStringContainsString('utility(\'folders\')', $sidebar);

        // The workspace tile only exists where a workspace does, so it
        // is pinned to the switcher rather than asserted outright --
        // otherwise this fails for a reason that has nothing to do with
        // the rail.
        if (str_contains($sidebar, 'id="workspace-switcher"')) {
            $this->assertMatchesRegularExpression(
                '/<button[^>]*x-show="railMin"[^>]*title="Workspace: /s',
                $sidebar,
                'A workspace renders, so its rail tile must too.'
            );
            $this->assertStringContainsString('utility(\'workspace\')', $sidebar);
        }

        // The avatar moves with the block rather than being copied, so
        // there is still exactly one of it.
        $this->assertSame(1, substr_count($sidebar, 'navrail-account-avatar'));
        $this->assertMatchesRegularExpression(
            '/\.navrail-wrap\[data-rail-min="1"\] \.navrail-account \{[^}]*left: 0/s',
            $sidebar,
            'Minimised, the account block must move to the rail.'
        );
    }

    /**
     * The reason the utility tiles open the panel instead of carrying
     * their own flyouts: the workspace switcher is a component with an
     * id, and the folder list is one Alpine root. A second copy of
     * either beside the rail would be a duplicate id and two states
     * that drift apart.
     */
    public function test_a_utility_tile_opens_the_one_component_rather_than_a_second_copy(): void
    {
        $sidebar = $this->desktopSidebar($this->owner());

        $this->assertSame(
            1,
            substr_count($sidebar, 'id="workspace-switcher"'),
            'Two #workspace-switcher elements on one page is a broken document.'
        );
        $this->assertStringContainsString('open-workspace-switcher', $sidebar);
        $this->assertStringContainsString('open-nav-folders', $sidebar);

        // Both listeners have to be on the components themselves, or
        // the events above are shouted at nobody.
        $switcher = file_get_contents(resource_path('views/user/partials/workspace-switcher.blade.php'));
        $home = file_get_contents(resource_path('views/user/partials/nav/_home.blade.php'));
        $this->assertStringContainsString('@open-workspace-switcher.window', $switcher);
        $this->assertStringContainsString('@open-nav-folders.window', $home);
    }

    /**
     * Item 4. The plan is on the surface when there is width for it and
     * in the menu when there is not, and never in both places at once.
     */
    public function test_the_plan_is_on_the_strip_or_in_the_menu_but_never_both(): void
    {
        $sidebar = $this->desktopSidebar($this->owner());

        $this->assertMatchesRegularExpression(
            '/<div class="navrail-plan" x-show="!railMin"/s',
            $sidebar,
            'The plan strip is the expanded-width form.'
        );
        $this->assertMatchesRegularExpression(
            '/class="navrail-account-row-item"[^>]*x-show="railMin"|x-show="railMin"[^>]*class="navrail-account-row-item"/s',
            $sidebar,
            'The menu plan row is the minimised form.'
        );
        // Whichever form is on screen, the route out of it is the same.
        $this->assertStringContainsString(route('user.upgrade'), $sidebar);
    }

    /**
     * The account block is a child of the WRAP, not of either column:
     * it has to appear at the foot of the panel in one state and at the
     * foot of the rail in the other, and nothing inside one column can
     * do that. Asserted structurally, because the CSS only works if the
     * markup is actually shaped this way.
     */
    public function test_the_account_block_hangs_off_the_wrap_not_the_panel(): void
    {
        $sidebar = $this->desktopSidebar($this->owner());

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?><div>' . $sidebar . '</div>');
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query("//div[contains(@class,'navrail-account')]");
        $this->assertNotNull($nodes);
        $this->assertSame(1, $nodes->length, 'There must be exactly one account block.');

        $parent = $nodes->item(0)->parentNode;
        $this->assertStringContainsString(
            'navrail-wrap',
            (string) $parent->getAttribute('class'),
            'The account block must be a sibling of the two columns, not inside one of them.'
        );

        // And it has to outrank the flyout, or opening the menu while
        // minimised paints it behind the panel. Caught by asking the
        // document what was on top at the menu, not by reading this.
        $this->assertMatchesRegularExpression(
            '/\.navrail-account \{[^}]*z-index: 70/s',
            $sidebar
        );
        $this->assertMatchesRegularExpression(
            '/\.navrail-wrap\[data-rail-min="1"\] \.navrail-panel \{[^}]*z-index: 60/s',
            $sidebar
        );
    }

    /**
     * The account menu's two loaded rows say so at rest. A menu whose
     * four rows are all the same grey makes you read four labels to
     * find the one you came for, and the one you came for is sometimes
     * Log out.
     */
    public function test_logging_out_reads_as_destructive_without_a_hover(): void
    {
        $css = $this->desktopSidebar($this->owner());

        $this->assertMatchesRegularExpression(
            '/\\.navrail-account-menu \\.navrail-account-out \\{[^}]*color: var\\(--c-danger\\)/s',
            $css,
            'Log out must carry the danger colour at rest, not only on hover.'
        );
        $this->assertMatchesRegularExpression(
            '/\\.navrail-account-menu \\.navrail-account-admin \\{[^}]*color: var\\(--accent\\)/s',
            $css,
            'Switch to admin is an elevation and must not share the logout colour.'
        );
        // Both are tokens, so they follow the theme. A literal here
        // would be a hole in exactly the mechanism the appearance
        // presets work through.
        $this->assertDoesNotMatchRegularExpression(
            '/\\.navrail-account-(out|admin)[^{]*\\{[^}]*#[0-9a-fA-F]{3}/s',
            $css
        );
    }

    /**
     * Items 2 and 3, both measured against the reference layout rather
     * than guessed at: its header has no surface of its own, and its
     * collapse control sits in the header rather than on the sidebar.
     */
    public function test_the_top_bar_is_flush_and_owns_the_collapse_control(): void
    {
        $railHtml = (string) $this->actingAs($this->owner())
            ->get('/user/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('header-flush', $railHtml);
        $this->assertMatchesRegularExpression(
            '/\\.header-v2\\.header-flush \\{[^}]*background: transparent/s',
            $railHtml,
            'The top bar must share the page surface.'
        );
        // Aurora sets its own 1px rule with !important, so the flush
        // treatment has to answer it in the same terms. Selectors that
        // out-specify it on paper were measured losing to it.
        $this->assertStringContainsString(
            'html.aurora header.header-v2.header-flush { border-bottom: 0 !important; }',
            $railHtml
        );

        // The edge handle is gone and the top bar has the control.
        $railAside = $this->desktopSidebar($this->owner());
        $this->assertStringNotContainsString('sidebar-edge-toggle', $railAside);
        $this->assertStringContainsString('aria-label="Toggle sidebar"', $railHtml);

        // The control. Both survive on the classic sidebar, which keeps
        // its own edge handle and its own glass header.
        $classicHtml = (string) $this->actingAs($this->owner(['nav_pack' => 'classic']))
            ->get('/user/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('sidebar-edge-toggle', $classicHtml);
        $this->assertStringNotContainsString('header-flush', $classicHtml);
    }

    /**
     * Item 4. The first version of the two utility tiles made the
     * workspace one a filled colour block and left folders a bare
     * glyph, so the pair matched neither each other nor the seven
     * tiles below them.
     */
    public function test_the_utility_tiles_look_like_the_tiles_around_them(): void
    {
        $css = $this->desktopSidebar($this->owner());

        // The workspace colour is a dot on the corner, not the tile.
        $this->assertMatchesRegularExpression(
            '/\\.navrail-util-dot \\{[^}]*position: absolute[^}]*width: 8px/s',
            $css,
            'The workspace colour belongs on a dot, not on a filled tile.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\\.navrail-util-dot \\{[^}]*width: 20px/s',
            $css
        );
    }

    /**
     * "when expanded mode, minimize icon should be shown in panel and
     * hidden in topbar... it will be visible only on topbar and hidden
     * in panel when minimized" (Sana). One control, in whichever
     * surface is on screen -- never two of them at once, and never one
     * sitting on a surface that is not there.
     */
    public function test_the_collapse_control_follows_the_surface_that_is_on_screen(): void
    {
        $html = (string) $this->actingAs($this->owner())
            ->get('/user/dashboard')->assertOk()->getContent();

        // In the panel while the panel is open.
        $this->assertMatchesRegularExpression(
            '/class="navrail-panel-toggle"[^>]*x-show="!railMin"/s',
            $html,
            'The panel must carry the control while it is open.'
        );
        // In the top bar only once it is not.
        $this->assertMatchesRegularExpression(
            '/header-icon-btn[^>]*x-show="sidebarMode !== \'full\'"/s',
            $html,
            'The top bar control must be hidden while the panel is open.'
        );
        // And the panel's own copy cannot read a shadowed sidebarMode:
        // the switcher wrapper forces it to 'full', so a control inside
        // that wrapper would be reading a lie.
        $panelToggle = strpos($html, 'navrail-panel-toggle');
        $shadow = strpos($html, "x-data=\"{ sidebarMode: 'full' }\"");
        $this->assertNotFalse($panelToggle);
        $this->assertNotFalse($shadow);
        $this->assertLessThan($shadow, $panelToggle, 'The panel toggle must sit outside the shadowing wrapper.');
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
