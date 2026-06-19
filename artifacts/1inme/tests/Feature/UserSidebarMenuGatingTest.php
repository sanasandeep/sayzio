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
 *   - "API keys"        — plan feature gate (`api_access`).
 *   - AI section        — AI-engine gate (`AiEngineSettings::isEnabled()`).
 *   - "Ask Coach"       — AI-engine + per-plan allow-list gate.
 *   - Administration    — back-office permission gates
 *                         (`user.plans.manage`, `user.verifications.review`,
 *                          `user.roles.manage`).
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
            'onboarded_at' => now(),
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

    // ===== API keys — plan feature gate (api_access) =====

    public function test_api_keys_hidden_without_plan_feature(): void
    {
        $u = $this->user($this->plan(['api_access' => false]));
        $this->assertNavItem($u, 'user.api-keys.index', false);
    }

    public function test_api_keys_shown_with_plan_feature(): void
    {
        $u = $this->user($this->plan(['api_access' => true]));
        $this->assertNavItem($u, 'user.api-keys.index', true);
    }

    // ===== AI section — AI-engine gate =====

    public function test_ai_section_hidden_when_engine_disabled(): void
    {
        AiEngineSettings::setEnabled(false);
        $u = $this->user($this->plan());
        $this->assertNavItem($u, 'user.ai.mind.show', false);
    }

    public function test_ai_section_shown_when_engine_enabled(): void
    {
        AiEngineSettings::setEnabled(true);
        $u = $this->user($this->plan());
        $this->assertNavItem($u, 'user.ai.mind.show', true);
    }

    // ===== Ask Coach — AI-engine + per-plan allow-list gate =====

    public function test_ask_coach_shown_when_engine_on_and_plan_allowed(): void
    {
        AiEngineSettings::setEnabled(true);
        // Empty allow-list = every plan is allowed.
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $u = $this->user($this->plan([], 'coachplan'));
        $this->assertNavItem($u, 'user.ai.ask-coach.show', true);
    }

    public function test_ask_coach_hidden_when_plan_not_allowed(): void
    {
        AiEngineSettings::setEnabled(true);
        // Restrict to a plan the user is NOT on.
        AiEngineSettings::setAskCoachEnabledPlans(['some-other-plan']);
        $u = $this->user($this->plan([], 'notcoach'));
        // The rest of the AI section still renders…
        $this->assertNavItem($u, 'user.ai.mind.show', true);
        // …but Ask Coach is gated out.
        $this->assertNavItem($u, 'user.ai.ask-coach.show', false);
    }

    public function test_ask_coach_hidden_when_engine_disabled(): void
    {
        AiEngineSettings::setEnabled(false);
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $u = $this->user($this->plan());
        $this->assertNavItem($u, 'user.ai.ask-coach.show', false);
    }

    // ===== Administration — back-office permission gates =====

    public function test_administration_hidden_for_plain_user(): void
    {
        $u = $this->user($this->plan());
        $this->assertNavItem($u, 'user.plans.index', false);
        $this->assertNavItem($u, 'user.verification.admin', false);
        $this->assertNavItem($u, 'user.access.users.index', false);
    }

    public function test_administration_shown_for_admin_with_all_permissions(): void
    {
        $u = $this->user($this->plan());
        $this->grantPermissions($u, [
            'user.plans.manage',
            'user.verifications.review',
            'user.roles.manage',
        ]);
        $this->assertNavItem($u, 'user.plans.index', true);
        $this->assertNavItem($u, 'user.verification.admin', true);
        $this->assertNavItem($u, 'user.access.users.index', true);
    }

    public function test_administration_items_track_their_individual_permissions(): void
    {
        // Only the plans-manage permission — the other two admin links stay hidden.
        $u = $this->user($this->plan());
        $this->grantPermissions($u, ['user.plans.manage']);
        $this->assertNavItem($u, 'user.plans.index', true);
        $this->assertNavItem($u, 'user.verification.admin', false);
        $this->assertNavItem($u, 'user.access.users.index', false);
    }
}
