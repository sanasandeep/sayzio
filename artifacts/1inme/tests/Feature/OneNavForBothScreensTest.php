<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\NavRailSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The rail navigation: ten groups became seven sections, and the
 * desktop and mobile navs became one partial.
 *
 * THE RISK THIS FILE EXISTS FOR
 *
 * A regroup can silently lose a destination. 53 links moved; if one
 * dropped out, nothing crashes and no page 404s -- there is simply no
 * longer a way to reach it from the nav, and nobody notices until a
 * user asks where something went. So the first tests here read the
 * section partials off disk and compare what they contain against what
 * NavRailSections claims to cover.
 *
 * That also pins the one piece of deliberate duplication in the
 * feature. Each classic group computes its own `$grpFooActive` for its
 * collapse state; NavRailSections keeps a second copy of the route
 * patterns to decide which rail icon is lit. A route renamed in one
 * place and not the other lights the wrong icon, which is the kind of
 * wrong that survives a long time. test_every_route_in_a_section_partial
 * is what makes it loud.
 *
 * WHAT THESE TESTS CANNOT DO
 *
 * None of them can see the rail. Whether 72px is the right width,
 * whether the panel reads as a second zone against the glass, whether
 * the drawer's sideways rail is usable with a thumb: all of that is a
 * look at a real page, and no assertion in this file substitutes for
 * it.
 */
class OneNavForBothScreensTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that legitimately appear under two sections.
     *
     * user.links.index is "All Links" under Home and, with ?type=ics on
     * it, "My Events" under Events. Same route name, two destinations,
     * which is exactly why NavRailSections::activeKey() settles the
     * query string before it does anything else.
     */
    private const SHARED_ROUTES = ['user.links.index'];

    private function partialPath(string $key): string
    {
        $partial = NavRailSections::SECTIONS[$key]['partial'];

        return resource_path('views/' . str_replace('.', '/', $partial) . '.blade.php');
    }

    /** @return array<int, string> route names named in a section's partial */
    private function routesIn(string $key): array
    {
        $source = file_get_contents($this->partialPath($key));

        preg_match_all("/route\(\s*'([^']+)'/", (string) $source, $m);

        return array_values(array_unique($m[1]));
    }

    private function host(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    // ── The map matches the markup ───────────────────────────────────

    public function test_every_section_has_a_partial_on_disk(): void
    {
        foreach (NavRailSections::SECTIONS as $key => $section) {
            $this->assertFileExists($this->partialPath($key), "section {$key} has no partial");
            $this->assertNotSame('', trim((string) file_get_contents($this->partialPath($key))),
                "section {$key} has an empty partial");
        }
    }

    public function test_every_route_in_a_section_partial_is_matched_by_that_sections_patterns(): void
    {
        // The drift guard. If a nav link's route is renamed and the
        // pattern list here is not, the rail lights the wrong icon and
        // nothing else complains.
        foreach (NavRailSections::SECTIONS as $key => $section) {
            foreach ($this->routesIn($key) as $route) {
                if (in_array($route, self::SHARED_ROUTES, true)) {
                    continue;
                }

                $matched = false;
                foreach ($section['routes'] as $pattern) {
                    if (Str::is($pattern, $route)) {
                        $matched = true;
                        break;
                    }
                }

                $this->assertTrue($matched,
                    "{$route} is linked in the {$key} panel but no {$key} pattern matches it, "
                    . 'so standing on that page lights a different rail icon');
            }
        }
    }

    public function test_no_destination_sits_in_two_sections(): void
    {
        $seen = [];

        foreach (array_keys(NavRailSections::SECTIONS) as $key) {
            foreach ($this->routesIn($key) as $route) {
                if (in_array($route, self::SHARED_ROUTES, true)) {
                    continue;
                }

                $this->assertArrayNotHasKey($route, $seen,
                    "{$route} is in both the {$seen[$route]} and {$key} panels");
                $seen[$route] = $key;
            }
        }

        // Sanity: the scan found the nav, not an empty set. Without
        // this the loop above passes just as happily on nothing.
        $this->assertGreaterThan(40, count($seen),
            'the partial scan found almost no routes, so the assertions above proved nothing');
    }

    public function test_the_two_pages_that_only_mobile_could_reach_are_now_in_the_nav(): void
    {
        // Creator Profile and Linked identifiers were in the mobile
        // drawer and nowhere in the desktop sidebar. One shared partial
        // is what fixes that; this is the test that says so out loud.
        $this->assertContains('user.creator-profile.edit', $this->routesIn('audience'));
        $this->assertContains('user.identifiers.index', $this->routesIn('settings'));
    }

    public function test_the_rail_lists_every_section_exactly_once(): void
    {
        $order = NavRailSections::order();

        $this->assertSame(
            collect(array_keys(NavRailSections::SECTIONS))->sort()->values()->all(),
            collect($order)->sort()->values()->all(),
            'the rail order and the section map disagree about which sections exist'
        );
        $this->assertSame(count($order), count(array_unique($order)));
        $this->assertSame('home', $order[0], 'the rail should open on Home');
    }

    // ── Which icon is lit ────────────────────────────────────────────

    public function test_all_links_is_home_and_my_events_is_events_on_the_same_route(): void
    {
        // The one real ambiguity in the map.
        $this->get(route('user.links.index'));
        $this->assertSame('home', NavRailSections::activeKey());

        $this->get(route('user.links.index', ['type' => 'ics']));
        $this->assertSame('events', NavRailSections::activeKey());
    }

    public function test_a_route_nobody_mapped_falls_back_to_home(): void
    {
        // A mild wrong answer beats a sidebar with no panel open, which
        // reads as broken rather than as unmapped.
        $this->get('/');

        $this->assertSame('home', NavRailSections::activeKey());
    }

    // ── The flag ─────────────────────────────────────────────────────

    public function test_the_rail_is_off_until_an_account_opts_in(): void
    {
        $user = $this->host();

        $this->assertFalse($user->usesRailNav(), 'the rail was on for an account that never asked');

        $this->actingAs($user)->get('/user/appearance/nav/rail');
        $this->assertTrue($user->fresh()->usesRailNav());

        $this->actingAs($user)->get('/user/appearance/nav/classic');
        $this->assertFalse($user->fresh()->usesRailNav(), 'switching back did not switch back');
    }

    public function test_the_nav_flag_and_the_aurora_flag_do_not_touch_each_other(): void
    {
        // Both live in the same settings array. A write to one that
        // clobbered the other would only show up as someone's theme
        // quietly reverting.
        $user = $this->host();

        $this->actingAs($user)->get('/user/appearance/aurora');
        $this->actingAs($user)->get('/user/appearance/nav/rail');

        $user = $user->fresh();
        $this->assertTrue($user->usesAuroraUi(), 'opting into the rail turned Aurora off');
        $this->assertTrue($user->usesRailNav());
    }

    public function test_an_unknown_pack_is_not_a_route(): void
    {
        $this->actingAs($this->host())->get('/user/appearance/nav/metronic')->assertNotFound();
    }

    // ── The gates survived the move ──────────────────────────────────

    public function test_a_panel_renders_only_the_links_the_permissions_allow(): void
    {
        // The moved markup kept its @if($__can[...]) wrappers. This
        // renders the rail with those flags forced off and asserts the
        // links really are absent, rather than trusting that a copy
        // carried its gate.
        $this->actingAs($this->host());

        $html = view('user.partials.nav-rail', [
            '__can' => array_fill_keys([
                'tasks_view', 'vault_view', 'files_view', 'links_view', 'forms_view',
                'qr_view', 'links_create', 'inbox_view', 'posts_view', 'followers_view',
                'stats_view', 'settings_view', 'referrals_view',
            ], false),
        ])->render();

        $this->assertStringNotContainsString('All Links', $html);
        $this->assertStringNotContainsString('Create Link', $html);
        $this->assertStringNotContainsString('Linked identifiers', $html);

        // The control. With the gates open the same render must show
        // them, or the assertions above pass because nothing rendered
        // at all.
        $open = view('user.partials.nav-rail', [
            '__can' => array_fill_keys([
                'tasks_view', 'vault_view', 'files_view', 'links_view', 'forms_view',
                'qr_view', 'links_create', 'inbox_view', 'posts_view', 'followers_view',
                'stats_view', 'settings_view', 'referrals_view',
            ], true),
        ])->render();

        $this->assertStringContainsString('All Links', $open);
        $this->assertStringContainsString('Linked identifiers', $open);
    }

    public function test_the_rail_renders_all_seven_tabs_and_opens_on_one_panel(): void
    {
        $this->actingAs($this->host());

        $html = view('user.partials.nav-rail', ['railSurface' => 'desktop'])->render();

        foreach (NavRailSections::order() as $key) {
            $this->assertStringContainsString("navrail-tab-desktop-{$key}", $html,
                "the {$key} rail tab is missing");
            $this->assertStringContainsString("navrail-panel-desktop-{$key}", $html);
        }

        // Exactly one panel is visible in the first painted frame: six
        // carry the server-set display:none, the active one does not.
        $this->assertSame(6, substr_count($html, 'style="display:none"'),
            'the panel that opens on load is not exactly one');
    }

    public function test_the_drawer_gets_its_own_ids_so_two_rails_can_coexist(): void
    {
        // The same partial renders twice on one page, once per surface.
        // Duplicate element ids would break the tab/panel wiring for
        // whichever copy came second.
        $this->actingAs($this->host());

        $mobile = view('user.partials.nav-rail', ['railSurface' => 'mobile'])->render();

        $this->assertStringContainsString('navrail-mobile', $mobile);
        $this->assertStringContainsString('navrail-tab-mobile-home', $mobile);
        $this->assertStringNotContainsString('navrail-tab-desktop-', $mobile);
    }

    public function test_the_rail_adds_no_colour_literals_of_its_own(): void
    {
        // The sidebar's light/dark options work by overriding the
        // --sidebar-* tokens. A hex or rgba() in the rail's stylesheet
        // is a spot those options cannot reach, which is how the
        // classic sidebar ended up with 36 of them.
        $css = file_get_contents(resource_path('views/user/partials/nav-rail.blade.php'));

        preg_match_all('/<style>(.*?)<\/style>/s', (string) $css, $m);
        $this->assertNotEmpty($m[1], 'no style block found, so this test checked nothing');

        foreach ($m[1] as $block) {
            $this->assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $block),
                'the rail stylesheet has a colour literal in it; use a --sidebar-* token');
        }
    }
}
