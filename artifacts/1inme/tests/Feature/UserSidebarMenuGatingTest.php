<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the per-plan / per-permission gating of the user sidebar menu
 * (`resources/views/user/layouts/app.blade.php`).
 *
 * The menu is rendered TWICE per page — once in the desktop `<aside>` and
 * once in the mobile drawer (`x-show="mobileMenu"`). A regression could
 * either leak a gated item (e.g. show "API keys" to a user without the
 * `api_access` plan feature) or let the two copies drift apart. Every
 * assertion here therefore checks BOTH the desktop and mobile nav blocks.
 *
 * Items exercised:
 *   - AI section        — always rendered now (Task #1999); the engine state
 *                         no longer hides it. Per-item gates still apply
 *                         (Minds → settings_view).
 *   - "Ask Zio"         — ONE item with a ternary href: Ask Coach when
 *                         `askCoachAllowedFor()` passes, the companion page
 *                         when it does not. It never disappears.
 *   - "API keys"        — no longer in the sidebar at all (moved into the
 *                         Settings hub); asserted absent so it does not
 *                         return by accident.
 *   - Administration    — removed from the user sidebar by Task #3805;
 *                         asserted absent even for someone holding every
 *                         permission it used to key off.
 *
 * Three of the items above were, until this file was corrected, asserted
 * as PRESENT long after they had been deliberately removed. The pattern
 * is worth naming because it is easy to recreate: when a nav item goes
 * away, its "hidden" test keeps passing for the wrong reason while its
 * "shown" test fails, and the pair stops describing anything. Both halves
 * were replaced with the invariant that actually survives the removal —
 * that the item stays gone.
 */
class UserSidebarMenuGatingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], ?string $slug = null): Plan
    {
        $slug = $slug ?: ('p' . Str::random(6));

        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'plan_id'      => $plan?->id,
            // Skip the onboarding redirect so the dashboard actually renders.
            //
            // now() stopped being enough. RedirectToOnboarding fires its
            // one-time steps -- creator profile, WhatsApp, contact privacy --
            // for anyone onboarded within the last 14 days, and a fresh test
            // user has none of them stamped as shown, so /user/dashboard
            // answered 302 to /user/onboarding/creator-profile and all ten
            // of these read the redirect instead of the sidebar.
            //
            // An older date rather than stamping each step: all three are
            // gated on the same recency window, so this keeps working when a
            // fourth step is added. These tests are about an established
            // account's menu, not about onboarding.
            'onboarded_at' => now()->subMonths(6),
        ]);
    }

    /** Attach a web-guard role granting the given permission slugs to $user. */
    private function grantPermissions(User $user, array $slugs): void
    {
        $role = Role::create([
            'name'  => 'R ' . Str::random(4),
            'slug'  => 'r-' . Str::random(8),
            'guard' => 'web',
        ]);
        foreach ($slugs as $slug) {
            $perm = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => explode('.', $slug)[0] ?? 'misc']
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->flushPermissionCache();
    }

    /**
     * Render the dashboard for $user and split out the desktop + mobile nav
     * regions so each can be asserted against independently.
     *
     * @return array{0:string,1:string} [desktopNavHtml, mobileNavHtml]
     */
    private function navRegions(User $user): array
    {
        $resp = $this->actingAs($user)->get('/user/dashboard');
        $resp->assertStatus(200);
        $html = (string) $resp->getContent();

        // Desktop sidebar: the <aside ... sidebar-v2 sidebar-shell ...> block.
        $dStart = strpos($html, 'sidebar-v2 sidebar-shell');
        $this->assertNotFalse($dStart, 'desktop sidebar <aside> not found in layout');
        $dEnd = strpos($html, '</aside>', $dStart);
        $this->assertNotFalse($dEnd, 'desktop sidebar end not found');
        $desktop = substr($html, $dStart, $dEnd - $dStart);

        // Mobile drawer: the x-show="mobileMenu" block up to <main>.
        $mStart = strpos($html, 'x-show="mobileMenu"');
        $this->assertNotFalse($mStart, 'mobile drawer not found in layout');
        $mEnd = strpos($html, '<main', $mStart);
        $this->assertNotFalse($mEnd, 'mobile drawer end (<main>) not found');
        $mobile = substr($html, $mStart, $mEnd - $mStart);

        return [$desktop, $mobile];
    }

    /** Relative path for a named route, e.g. "/user/api-keys". */
    private function path(string $route): string
    {
        return route($route, [], false);
    }

    private function assertNavItem(User $user, string $route, bool $shouldShow): void
    {
        [$desktop, $mobile] = $this->navRegions($user);
        // The layout renders absolute hrefs (e.g. http://localhost/user/plans),
        // so match on the path followed by the closing quote — robust against
        // the request host and against longer paths sharing a prefix.
        $needle = $this->path($route) . '"';

        if ($shouldShow) {
            $this->assertStringContainsString($needle, $desktop, "Desktop nav missing {$route}");
            $this->assertStringContainsString($needle, $mobile, "Mobile nav missing {$route}");
        } else {
            $this->assertStringNotContainsString($needle, $desktop, "Desktop nav leaked {$route}");
            $this->assertStringNotContainsString($needle, $mobile, "Mobile nav leaked {$route}");
        }
    }

    // ===== Baseline — always-on items render in both nav blocks =====

    public function test_core_items_render_in_both_nav_blocks(): void
    {
        $u = $this->user($this->plan());
        // Dashboard + All Links are visible to every workspace owner.
        $this->assertNavItem($u, 'user.dashboard', true);
        $this->assertNavItem($u, 'user.links.index', true);
    }

    // ===== API keys — no longer a sidebar item at all =====

    public function test_api_keys_is_not_a_sidebar_item_any_more(): void
    {
        // Commit 973125935 ("Consolidate ~10 user-settings surfaces into one
        // Settings hub") moved API keys into Settings. The route still
        // exists and the page still works -- it is the NAV ENTRY that is
        // gone, so the old pair of tests asserted a plan gate on an item
        // that cannot appear either way. The "hidden" half passed for the
        // wrong reason and the "shown" half had been failing ever since.
        //
        // What is still worth holding: the item does not come back by
        // accident, under either plan.
        $this->assertNavItem($this->user($this->plan(['api_access' => false])), 'user.api-keys.index', false);
        $this->assertNavItem($this->user($this->plan(['api_access' => true])),  'user.api-keys.index', false);
    }

    // NOTE: nothing here covers the api_access gate where it now lives, in
    // the Settings hub. That is a gap, not a decision -- it belongs in a
    // test about Settings, not about the sidebar.

    // ===== AI section — always rendered regardless of engine state =====
    //
    // The AI nav group is no longer wrapped in an AiEngineSettings::isEnabled()
    // gate (Task #1999): when the engine is off the menu must stay visible so
    // users land on the informative "turned off" page instead of a vanished
    // group. These tests guard against the @if wrapper being reintroduced in
    // either the desktop or mobile nav copy.

    /**
     * AI items with no per-item gate — always visible to a workspace owner.
     *
     * `user.ai.companion.show` is deliberately NOT in this list. "Ask Zio"
     * is a single nav item whose href is a ternary: ask-coach when the
     * plan is allowed, companion when it is not. Asserting both present at
     * once is impossible, which is why the two tests below had been
     * failing -- the layout collapsed two items into one and the list was
     * never updated.
     */
    private const ALWAYS_ON_AI_ROUTES = [
        'user.ai.mind.show',
        'user.ai.persona.show',
        'user.ai-personas.index',
        'user.ai-companions.index',
    ];

    public function test_all_ai_items_shown_when_engine_disabled(): void
    {
        AiEngineSettings::setEnabled(false);
        // Empty allow-list keeps the per-plan Ask Coach gate open.
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $u = $this->user($this->plan([], 'aioffplan'));

        foreach (self::ALWAYS_ON_AI_ROUTES as $route) {
            $this->assertNavItem($u, $route, true);
        }
        // Minds is gated on settings_view, which a workspace owner always holds.
        $this->assertNavItem($u, 'user.minds.index', true);
        // Ask Coach is gated on askCoachAllowedFor() only — engine state no
        // longer factors in, so an allowed plan still sees it with AI off.
        $this->assertNavItem($u, 'user.ai.ask-coach.show', true);
    }

    public function test_all_ai_items_shown_when_engine_enabled(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $u = $this->user($this->plan([], 'aionplan'));

        foreach (self::ALWAYS_ON_AI_ROUTES as $route) {
            $this->assertNavItem($u, $route, true);
        }
        $this->assertNavItem($u, 'user.minds.index', true);
        $this->assertNavItem($u, 'user.ai.ask-coach.show', true);
    }

    // ===== Ask Coach — per-plan allow-list gate (independent of engine) =====

    public function test_ask_zio_falls_back_to_companion_when_the_plan_is_not_allowed(): void
    {
        // The old name said "hidden", which is not what happens. Ask Zio is
        // one item and it stays visible -- only its destination changes,
        // from Ask Coach to the companion page. A test that only checked
        // ask-coach was absent would also pass if the whole item vanished,
        // which is the regression anyone would actually care about.
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans(['some-other-plan']);
        $u = $this->user($this->plan([], 'notcoachon'));

        $this->assertNavItem($u, 'user.ai.mind.show', true);
        $this->assertNavItem($u, 'user.ai.ask-coach.show', false);
        $this->assertNavItem($u, 'user.ai.companion.show', true);
    }

    public function test_the_fallback_holds_with_the_engine_off_too(): void
    {
        // Turning AI off neither reveals nor hides Ask Zio beyond the plan
        // rule: same item, same fallback destination.
        AiEngineSettings::setEnabled(false);
        AiEngineSettings::setAskCoachEnabledPlans(['some-other-plan']);
        $u = $this->user($this->plan([], 'notcoachoff'));

        $this->assertNavItem($u, 'user.ai.mind.show', true);
        $this->assertNavItem($u, 'user.ai.ask-coach.show', false);
        $this->assertNavItem($u, 'user.ai.companion.show', true);
    }

    // ===== Administration — removed from the user sidebar on purpose =====

    /** The three links the Administration section used to carry. */
    private const RETIRED_ADMIN_ROUTES = [
        'user.plans.index',
        'user.verification.admin',
        'user.access.users.index',
    ];

    public function test_the_admin_menu_stays_out_of_the_user_sidebar(): void
    {
        // Task #3805 removed the Administration section from both nav
        // blocks. The pages and their permission gates are untouched --
        // only the sidebar entry went.
        //
        // This replaces three tests that no longer described anything:
        // "hidden_for_plain_user" passed because the items can never
        // appear, and the two that asserted they DO appear for a permitted
        // admin had been failing since #3805 landed. Between them they
        // could not have caught the section being reinstated, which is the
        // one thing worth guarding.
        //
        // So: grant every permission the section used to key off, and
        // assert it still does not render. Someone who genuinely wants it
        // back has to change this test, deliberately.
        $u = $this->user($this->plan());
        $this->grantPermissions($u, [
            'user.plans.manage',
            'user.verifications.review',
            'user.roles.manage',
        ]);

        foreach (self::RETIRED_ADMIN_ROUTES as $route) {
            $this->assertNavItem($u, $route, false);
        }
    }

    public function test_a_plain_user_does_not_see_them_either(): void
    {
        $u = $this->user($this->plan());

        foreach (self::RETIRED_ADMIN_ROUTES as $route) {
            $this->assertNavItem($u, $route, false);
        }
    }
}
