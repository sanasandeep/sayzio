<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Models\WorkspaceRolePermission;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Complements UserSidebarMenuGatingTest by exercising the `$__can[...]`
 * workspace-permission gates in `resources/views/user/layouts/app.blade.php`
 * from the perspective of a NON-OWNER workspace member.
 *
 * The owner-based test cannot actually exercise those gates: a workspace
 * owner passes every `WorkspacePermissions::userCan()` check, so the gated
 * nav items render regardless of the underlying permission. Here user A
 * owns the workspace and user B is added as a member with a restricted
 * role, so B's role × action matrix genuinely drives what shows.
 *
 * Workspace permissions are role-based and apply uniformly across every
 * resource (the `feature.` prefix in slugs like `links.view` is ignored),
 * so the sidebar's many `*_view` items all resolve to the single `view`
 * action, and "Create Link" resolves to `create`. The scenarios below pin
 * down both gates:
 *   - viewer (default matrix): holds `view`, lacks `create` → view items
 *     show, "Create Link" is hidden.
 *   - editor (default matrix): also holds `create` → "Create Link" shows.
 *   - a custom matrix revoking `view` from the member's role → every
 *     workspace-scoped nav item disappears.
 *
 * Every assertion checks BOTH the desktop `<aside>` and the mobile drawer
 * so the two copies can't drift apart.
 */
class UserSidebarMemberRoleGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@ex.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            // Skip the onboarding redirect so the dashboard actually renders.
            'onboarded_at' => now(),
        ]);
    }

    /** A workspace owned by $owner. */
    private function makeWorkspace(User $owner): Workspace
    {
        return Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'WS ' . Str::random(4),
            'slug'          => 'ws-' . Str::random(8),
        ]);
    }

    /** Add $member to $ws with the given role. */
    private function addMember(Workspace $ws, User $member, string $role): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => $role,
        ]);
    }

    /**
     * Render the dashboard for $user with $ws bound as the active workspace,
     * and split out the desktop + mobile nav regions so each can be asserted
     * independently.
     *
     * @return array{0:string,1:string} [desktopNavHtml, mobileNavHtml]
     */
    private function navRegions(User $user, Workspace $ws): array
    {
        $resp = $this->actingAs($user)
            ->withSession([WorkspaceContext::SESSION_KEY => $ws->id])
            ->get('/user/dashboard');
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

    /** Relative path for a named route, e.g. "/user/links". */
    private function path(string $route): string
    {
        return route($route, [], false);
    }

    /**
     * Assert that the nav item for $route is present / absent in BOTH the
     * desktop and mobile nav blocks rendered for $user in $ws.
     */
    private function assertNavItem(User $user, Workspace $ws, string $route, bool $shouldShow): void
    {
        [$desktop, $mobile] = $this->navRegions($user, $ws);
        // The layout renders absolute hrefs, so match on the path followed
        // by the closing quote — robust against the request host and against
        // longer paths sharing a prefix.
        $needle = $this->path($route) . '"';

        if ($shouldShow) {
            $this->assertStringContainsString($needle, $desktop, "Desktop nav missing {$route}");
            $this->assertStringContainsString($needle, $mobile, "Mobile nav missing {$route}");
        } else {
            $this->assertStringNotContainsString($needle, $desktop, "Desktop nav leaked {$route}");
            $this->assertStringNotContainsString($needle, $mobile, "Mobile nav leaked {$route}");
        }
    }

    // ===== Viewer — holds `view`, lacks `create` =====

    public function test_viewer_member_sees_view_items_but_not_create(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $ws     = $this->makeWorkspace($owner);
        $this->addMember($ws, $member, 'viewer');

        // Dashboard is ungated and always renders.
        $this->assertNavItem($member, $ws, 'user.dashboard', true);

        // `view`-gated items the viewer holds — present.
        $this->assertNavItem($member, $ws, 'user.links.index', true);
        $this->assertNavItem($member, $ws, 'user.inbox.unified.index', true);

        // `create`-gated item the viewer lacks — absent.
        $this->assertNavItem($member, $ws, 'user.links.create', false);
    }

    // ===== Editor — also holds `create` =====

    public function test_editor_member_also_sees_create_item(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $ws     = $this->makeWorkspace($owner);
        $this->addMember($ws, $member, 'editor');

        // Editors keep the view items…
        $this->assertNavItem($member, $ws, 'user.links.index', true);
        // …and, unlike a viewer, gain "Create Link".
        $this->assertNavItem($member, $ws, 'user.links.create', true);
    }

    // ===== Custom matrix revoking `view` — items vanish =====

    public function test_member_without_view_permission_sees_no_workspace_nav(): void
    {
        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $ws     = $this->makeWorkspace($owner);
        $this->addMember($ws, $member, 'viewer');

        // Override this workspace's matrix so the viewer role loses every
        // action — including `view`. (Only the Admin row's `view` is
        // locked on, so revoking the viewer's view is honoured.)
        WorkspaceRolePermission::create([
            'workspace_id' => $ws->id,
            'matrix'       => [
                'viewer' => [
                    'view'   => false,
                    'create' => false,
                    'edit'   => false,
                    'delete' => false,
                    'reply'  => false,
                ],
            ],
        ]);

        // Dashboard still renders (ungated).
        $this->assertNavItem($member, $ws, 'user.dashboard', true);

        // Every workspace-scoped item is now gated out for this member.
        $this->assertNavItem($member, $ws, 'user.links.index', false);
        $this->assertNavItem($member, $ws, 'user.links.create', false);
        $this->assertNavItem($member, $ws, 'user.inbox.unified.index', false);
    }

    // ===== AI per-item gates still apply when the engine is off =====

    /**
     * The AI nav group always renders now (Task #1999), but its two per-item
     * gates must keep working with the engine off: Minds needs settings_view;
     * Ask Coach needs askCoachAllowedFor(). A member who lacks `view` must see
     * the ungated AI items (Mind, Coach, …) and Ask Coach (plan-gated, open
     * allow-list) but NOT Minds.
     */
    public function test_ai_per_item_gates_apply_with_engine_off(): void
    {
        AiEngineSettings::setEnabled(false);
        AiEngineSettings::setAskCoachEnabledPlans([]); // every plan allowed

        $owner  = $this->makeUser();
        $member = $this->makeUser();
        $ws     = $this->makeWorkspace($owner);
        $this->addMember($ws, $member, 'viewer');

        // Strip the viewer role's `view` action so settings_view resolves false.
        WorkspaceRolePermission::create([
            'workspace_id' => $ws->id,
            'matrix'       => [
                'viewer' => [
                    'view'   => false,
                    'create' => false,
                    'edit'   => false,
                    'delete' => false,
                    'reply'  => false,
                ],
            ],
        ]);

        // Ungated AI items render even with the engine off and `view` revoked.
        $this->assertNavItem($member, $ws, 'user.ai.mind.show', true);
        $this->assertNavItem($member, $ws, 'user.ai.coach.show', true);
        // Minds is hidden because this member lacks settings_view…
        $this->assertNavItem($member, $ws, 'user.minds.index', false);
        // …while Ask Coach (plan-gated, empty allow-list) still shows.
        $this->assertNavItem($member, $ws, 'user.ai.ask-coach.show', true);
    }
}
