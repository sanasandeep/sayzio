<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Web/app links parity: the API links index and dashboard must scope to the
 * caller's ACTIVE workspace using the same rule as the web "My Links" page
 * (owner = workspace owner, workspace_id = active workspace), and the mobile
 * switcher's POST /workspaces/{id}/activate must persist the pointer that
 * both surfaces resolve.
 */
class ApiLinksWorkspaceParityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    /** Link has no factory; workspace_id isn't fillable — set directly. */
    private function makeLink(User $owner, Workspace $ws, string $alias): Link
    {
        $link = new Link([
            'type'      => 'link',
            'alias'     => $alias,
            'long_url'  => 'https://example.com/' . $alias,
            'title'     => $alias,
            'is_active' => true,
        ]);
        $link->user_id      = $owner->id;
        $link->workspace_id = $ws->id;
        $link->save();

        return $link;
    }

    public function test_links_index_is_scoped_to_the_active_workspace(): void
    {
        $user = $this->makeUser();
        $personal = $user->ownedWorkspaces()->first();
        $team = $user->ownedWorkspaces()->create(['name' => 'Team', 'slug' => 'team-' . $user->id]);

        $this->makeLink($user, $personal, 'p-one-' . $user->id);
        $this->makeLink($user, $personal, 'p-two-' . $user->id);
        $this->makeLink($user, $team, 't-one-' . $user->id);

        $this->withToken($user->createToken('t')->plainTextToken);

        // Default active workspace = personal (first accessible).
        $resp = $this->getJson('/api/v1/links');
        $resp->assertStatus(200);
        $aliases = collect($resp->json('data.items'))->pluck('alias')->sort()->values()->all();
        $this->assertSame(['p-one-' . $user->id, 'p-two-' . $user->id], $aliases);

        // Switch to the team workspace via the mobile activate endpoint.
        $this->postJson('/api/v1/workspaces/' . $team->id . '/activate')->assertStatus(200);
        $this->assertSame($team->id, (int) $user->fresh()->active_workspace_id);

        $resp = $this->getJson('/api/v1/links');
        $resp->assertStatus(200);
        $aliases = collect($resp->json('data.items'))->pluck('alias')->values()->all();
        $this->assertSame(['t-one-' . $user->id], $aliases);
    }

    public function test_dashboard_totals_follow_the_active_workspace(): void
    {
        $user = $this->makeUser();
        $personal = $user->ownedWorkspaces()->first();
        $team = $user->ownedWorkspaces()->create(['name' => 'Team', 'slug' => 'dteam-' . $user->id]);
        $this->makeLink($user, $personal, 'dp-one-' . $user->id);
        $this->makeLink($user, $team, 'dt-one-' . $user->id);
        $this->makeLink($user, $team, 'dt-two-' . $user->id);

        $this->withToken($user->createToken('t')->plainTextToken);

        $this->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.totals.links', 1);

        $this->postJson('/api/v1/workspaces/' . $team->id . '/activate')->assertStatus(200);

        $this->getJson('/api/v1/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('data.totals.links', 2);
    }

    public function test_activate_rejects_workspaces_the_caller_cannot_access(): void
    {
        $user  = $this->makeUser();
        $other = $this->makeUser();
        $otherWs = $other->ownedWorkspaces()->first();

        $this->withToken($user->createToken('t')->plainTextToken);
        $this->postJson('/api/v1/workspaces/' . $otherWs->id . '/activate')->assertStatus(404);
        $this->assertNull($user->fresh()->active_workspace_id);

        $this->postJson('/api/v1/workspaces/999999/activate')->assertStatus(404);
    }

    public function test_web_session_resolver_honours_the_persisted_pointer(): void
    {
        $user = $this->makeUser();
        $team = $user->ownedWorkspaces()->create(['name' => 'Team', 'slug' => 'wteam-' . $user->id]);

        // App switched workspaces via the API…
        $this->withToken($user->createToken('t')->plainTextToken);
        $this->postJson('/api/v1/workspaces/' . $team->id . '/activate')->assertStatus(200);
        $this->flushHeaders();

        // …then a FRESH web session resolves the same workspace instead of
        // falling back to the first accessible one.
        $ctx = app(\App\Modules\User\Services\WorkspaceContext::class);
        $this->be($user->fresh(), 'web');
        $this->assertSame($team->id, $ctx->resolve($user->fresh())->id);
    }
}
