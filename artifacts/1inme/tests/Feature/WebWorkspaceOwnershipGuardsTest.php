<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two sibling owner-only mutations on WorkspaceController share the exact same
 * server-side guard as the rename path (WebWorkspaceUpdateTest):
 *   abort_unless((int) $workspace->owner_user_id === $user->id, 403)
 *
 *   - destroy()           — DELETE /user/workspaces/{workspace}
 *   - updatePostApproval()— PUT    /user/workspaces/{workspace}/post-approval
 *
 * These cover the transition case for both: user A owns a team workspace, then
 * ownership is transferred to B and A is demoted to a plain admin member. A's
 * very next web request must be rejected 403 by the server regardless of what a
 * stale UI still shows — the workspace must NOT be deleted and its approval
 * config must be unchanged. This guards against a future refactor silently
 * dropping the guard on either path.
 */
class WebWorkspaceOwnershipGuardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The transition case for delete: A owns and could delete the workspace,
     * then ownership moves to B (A demoted to admin member). A's next DELETE is
     * rejected 403 and the workspace still exists. The new owner B can delete it.
     */
    public function test_demoted_owner_cannot_delete_a_workspace_they_lost(): void
    {
        // Each user is auto-provisioned a personal workspace on creation, so
        // owning the team workspace below never trips the "can't delete your
        // only workspace" guard for either owner.
        $userA = User::factory()->create()->fresh();
        $userB = User::factory()->create()->fresh();

        $team = $userA->ownedWorkspaces()->create([
            'name'        => 'Design Guild',
            'slug'        => 'design-guild-abcd',
            'is_personal' => false,
        ]);

        // Ownership is transferred away to B (A is demoted to a plain member).
        $team->update(['owner_user_id' => $userB->id]);
        $team->members()->create(['user_id' => $userA->id, 'role' => 'admin']);

        // A's next DELETE — mimicking a stale UI that hasn't re-fetched — is
        // rejected server-side and the workspace is untouched.
        $resp = $this->actingAs($userA)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->delete(route('user.workspaces.destroy', $team));

        $resp->assertForbidden();
        $this->assertDatabaseHas('workspaces', ['id' => $team->id]);
        $this->assertDatabaseHas('workspace_members', ['workspace_id' => $team->id, 'user_id' => $userA->id]);

        // Sanity: the NEW owner B can still delete it.
        $this->actingAs($userB)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->delete(route('user.workspaces.destroy', $team))
            ->assertRedirect();
        $this->assertDatabaseMissing('workspaces', ['id' => $team->id]);
    }

    /**
     * The transition case for post-approval: A turns the approval workflow on
     * while owning the workspace, then ownership moves to B (A demoted to admin
     * member). A's next PUT is rejected 403 and the config is unchanged. The new
     * owner B can still change it.
     */
    public function test_demoted_owner_cannot_reconfigure_post_approval_on_a_workspace_they_lost(): void
    {
        $userA = User::factory()->create()->fresh();
        $userB = User::factory()->create()->fresh();

        $team = $userA->ownedWorkspaces()->create([
            'name'        => 'Editorial Team',
            'slug'        => 'editorial-team-abcd',
            'is_personal' => false,
        ]);

        // While A owns it, A turns the approval workflow on with the editor role
        // as approver — this is the config a demoted A must not be able to flip.
        $this->actingAs($userA)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.post-approval.update', $team), [
                'enabled'        => '1',
                'approver_roles' => ['editor'],
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertTrue($team->postApprovalConfig()['enabled']);
        $this->assertSame(['editor'], $team->postApprovalConfig()['approver_roles']);

        // Ownership is transferred away to B (A is demoted to a plain member).
        $team->update(['owner_user_id' => $userB->id]);
        $team->members()->create(['user_id' => $userA->id, 'role' => 'admin']);

        // A's next PUT — mimicking a stale UI — is rejected server-side and the
        // approval config is unchanged (still on, still editor-only).
        $resp = $this->actingAs($userA)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.post-approval.update', $team), [
                'enabled'        => '0',
                'approver_roles' => ['viewer'],
            ]);

        $resp->assertForbidden();
        $team->refresh();
        $this->assertTrue($team->postApprovalConfig()['enabled'], 'config must be unchanged after a rejected request');
        $this->assertSame(['editor'], $team->postApprovalConfig()['approver_roles']);

        // Sanity: the NEW owner B can still change it.
        $this->actingAs($userB)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.post-approval.update', $team), [
                'enabled'        => '0',
                'approver_roles' => ['admin'],
            ])
            ->assertRedirect();

        $team->refresh();
        $this->assertFalse($team->postApprovalConfig()['enabled']);
    }
}
