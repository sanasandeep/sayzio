<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PUT /user/workspaces/{workspace} (the web workspace-settings rename) is
 * owner-only. The controller decides ownership server-side on every request
 * from the live `owner_user_id`, so a demoted owner whose UI hasn't caught up
 * can never rename a workspace they no longer own.
 *
 * This mirrors ApiWorkspaceUpdateTest for the web flow, guarding against a
 * future refactor of WorkspaceController::update() (or its route middleware)
 * silently dropping the `abort_unless(owner_user_id === user->id, 403)` guard.
 */
class WebWorkspaceUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_their_workspace(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = $owner->ownedWorkspaces()->create([
            'name'        => 'Design Guild',
            'slug'        => 'design-guild-abcd',
            'is_personal' => false,
        ]);

        $resp = $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.update', $team), ['name' => 'Design League']);

        $resp->assertRedirect();
        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'Design League']);
    }

    public function test_non_owner_member_cannot_rename_workspace(): void
    {
        $owner  = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();

        $team = $owner->ownedWorkspaces()->create([
            'name'        => 'Shared Team',
            'slug'        => 'shared-team-abcd',
            'is_personal' => false,
        ]);
        $team->members()->create(['user_id' => $member->id, 'role' => 'editor']);

        $resp = $this->actingAs($member)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.update', $team), ['name' => 'Hijacked']);

        $resp->assertForbidden();
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'Shared Team']);
    }

    /**
     * The transition case: user A creates + owns a workspace (and can rename
     * it), then ownership is transferred/downgraded away to user B. A's very
     * next web PUT must be rejected 403 by the server regardless of what a
     * stale UI still shows, and the name must NOT change. The new owner B can
     * still rename it.
     */
    public function test_demoted_owner_immediately_loses_the_ability_to_rename_on_web(): void
    {
        $userA = User::factory()->create()->fresh();
        $userB = User::factory()->create()->fresh();

        $team = $userA->ownedWorkspaces()->create([
            'name'        => 'Original Name',
            'slug'        => 'original-name-abcd',
            'is_personal' => false,
        ]);

        // While A owns it, the rename succeeds.
        $this->actingAs($userA)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.update', $team), ['name' => 'A Renamed It'])
            ->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'A Renamed It']);

        // Ownership is transferred away to B (A is demoted to a plain member).
        $team->update(['owner_user_id' => $userB->id]);
        $team->members()->create(['user_id' => $userA->id, 'role' => 'admin']);

        // A's next PUT — mimicking a stale UI that hasn't re-fetched — is
        // rejected server-side and the name is unchanged.
        $resp = $this->actingAs($userA)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.update', $team), ['name' => 'A Tried Again']);

        $resp->assertForbidden();
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'A Renamed It']);

        // Sanity: the NEW owner B can still rename it.
        $this->actingAs($userB)
            ->withSession([WorkspaceContext::SESSION_KEY => $team->id])
            ->from(route('user.workspaces.settings', $team))
            ->put(route('user.workspaces.update', $team), ['name' => 'B Owns It Now'])
            ->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'B Owns It Now']);
    }
}
