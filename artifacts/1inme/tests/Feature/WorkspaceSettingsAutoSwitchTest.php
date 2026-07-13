<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workspace settings page is reachable by direct URL, so an owner can
 * land on a workspace's settings while a *different* workspace is active in
 * the sidebar. Visiting the page auto-switches the active context to the
 * workspace being edited and confirms the switch with a banner.
 */
class WorkspaceSettingsAutoSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_visiting_settings_switches_active_workspace_and_shows_banner(): void
    {
        $user = User::factory()->create()->fresh();
        $active = $user->ensureDefaultWorkspace();
        $other  = $user->ownedWorkspaces()->create([
            'name'        => 'Marketing team',
            'slug'        => 'marketing-team-abcd',
            'is_personal' => false,
        ]);

        // Make the personal workspace the active one, then open the OTHER
        // workspace's settings by direct URL.
        $this->withSession([WorkspaceContext::SESSION_KEY => $active->id])
            ->actingAs($user)
            ->get(route('user.workspaces.settings', $other))
            ->assertOk()
            ->assertSee('now editing')
            ->assertSee('Marketing team');

        $this->assertSame($other->id, session(WorkspaceContext::SESSION_KEY));
    }

    public function test_visiting_settings_for_active_workspace_shows_no_banner(): void
    {
        $user = User::factory()->create()->fresh();
        $active = $user->ensureDefaultWorkspace();

        $this->withSession([WorkspaceContext::SESSION_KEY => $active->id])
            ->actingAs($user)
            ->get(route('user.workspaces.settings', $active))
            ->assertOk()
            ->assertDontSee('now editing');

        $this->assertSame($active->id, session(WorkspaceContext::SESSION_KEY));
    }

    public function test_non_owner_cannot_reach_settings(): void
    {
        $owner = User::factory()->create()->fresh();
        $ws = $owner->ensureDefaultWorkspace();
        $intruder = User::factory()->create()->fresh();

        $this->actingAs($intruder)
            ->get(route('user.workspaces.settings', $ws))
            ->assertForbidden();
    }
}
