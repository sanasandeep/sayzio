<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PATCH /api/v1/workspaces/{id} is owner-only. The controller decides ownership
 * server-side on every request from the live `owner_user_id`, so a client that
 * only *thinks* it still owns a workspace (a stale switcher that hasn't
 * re-fetched after ownership was transferred/downgraded) can never rename it.
 * These cover the happy path, a plain non-owner, and — the important one — the
 * transition case where a user WAS the owner but ownership has since moved away.
 */
class ApiWorkspaceUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_owner_can_rename_their_workspace(): void
    {
        $owner = User::factory()->create()->fresh();
        $team  = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Design Guild',
            'is_personal'   => false,
        ]);

        $resp = $this->withToken($this->bearer($owner))
            ->patchJson('/api/v1/workspaces/' . $team->id, ['name' => 'Design League']);

        $resp->assertStatus(200);
        $this->assertSame('Design League', $resp->json('data.item.name'));
        $this->assertTrue($resp->json('data.item.is_owner'));
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'Design League']);
    }

    public function test_non_owner_member_cannot_rename_workspace(): void
    {
        $owner  = User::factory()->create()->fresh();
        $member = User::factory()->create()->fresh();

        $team = Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Shared Team',
            'is_personal'   => false,
        ]);
        $team->members()->create(['user_id' => $member->id, 'role' => 'editor']);

        $resp = $this->withToken($this->bearer($member))
            ->patchJson('/api/v1/workspaces/' . $team->id, ['name' => 'Hijacked']);

        $resp->assertStatus(403);
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'Shared Team']);
    }

    /**
     * The transition case: user A creates + owns a workspace (and can rename it),
     * then ownership is transferred/downgraded away to user B. A's very next
     * PATCH must be rejected 403 by the server regardless of what a stale client
     * still shows, and A must NOT be able to rename it anymore.
     */
    public function test_demoted_owner_immediately_loses_the_ability_to_rename(): void
    {
        $userA = User::factory()->create()->fresh();
        $userB = User::factory()->create()->fresh();

        $team = Workspace::create([
            'owner_user_id' => $userA->id,
            'name'          => 'Original Name',
            'is_personal'   => false,
        ]);

        $tokenA = $this->bearer($userA);

        // While A owns it, the rename succeeds.
        $this->withToken($tokenA)
            ->patchJson('/api/v1/workspaces/' . $team->id, ['name' => 'A Renamed It'])
            ->assertStatus(200);

        // Ownership is transferred away to B (A is demoted to a plain member).
        $team->update(['owner_user_id' => $userB->id]);
        $team->members()->create(['user_id' => $userA->id, 'role' => 'admin']);

        // A's next PATCH — with the SAME token, mimicking a stale client that
        // hasn't re-fetched the workspaces list — is rejected server-side.
        $resp = $this->withToken($tokenA)
            ->patchJson('/api/v1/workspaces/' . $team->id, ['name' => 'A Tried Again']);

        $resp->assertStatus(403);
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'A Renamed It']);

        // Sanity: the NEW owner B can still rename it.
        $this->withToken($this->bearer($userB))
            ->patchJson('/api/v1/workspaces/' . $team->id, ['name' => 'B Owns It Now'])
            ->assertStatus(200);
        $this->assertDatabaseHas('workspaces', ['id' => $team->id, 'name' => 'B Owns It Now']);
    }

    /**
     * The list endpoint that drives the mobile switcher must also report
     * is_owner=false for the demoted user on its next refetch, so the client
     * drops the edit gear (see the mobile source-driven test).
     */
    public function test_index_reports_is_owner_false_for_demoted_user_on_refetch(): void
    {
        $userA = User::factory()->create()->fresh();
        $userB = User::factory()->create()->fresh();

        $team = Workspace::create([
            'owner_user_id' => $userA->id,
            'name'          => 'Team',
            'is_personal'   => false,
        ]);

        $tokenA = $this->bearer($userA);

        $before = collect($this->withToken($tokenA)->getJson('/api/v1/workspaces')->json('data.items'))
            ->firstWhere('id', $team->id);
        $this->assertNotNull($before);
        $this->assertTrue($before['is_owner']);

        // Transfer ownership away and keep A as a member so the row still lists.
        $team->update(['owner_user_id' => $userB->id]);
        $team->members()->create(['user_id' => $userA->id, 'role' => 'admin']);

        $after = collect($this->withToken($tokenA)->getJson('/api/v1/workspaces')->json('data.items'))
            ->firstWhere('id', $team->id);
        $this->assertNotNull($after, 'the demoted user should still see the workspace as a member');
        $this->assertFalse($after['is_owner'], 'a refetch must report is_owner=false once ownership moves away');
    }
}
