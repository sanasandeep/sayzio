<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile owners can now create and delete team workspaces natively via the
 * sanctum API (previously the app bounced them to the web). These endpoints
 * must mirror the web {@see \App\Modules\User\Controllers\WorkspaceController}:
 * enforce the plan's `max_workspaces` cap on create, and protect the personal
 * workspace and the owner's last workspace on delete.
 */
class ApiWorkspaceCreateDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** Assign the user a plan whose `max_workspaces` cap is exactly $max. */
    private function planWithMaxWorkspaces(User $user, int $max): void
    {
        $plan = Plan::firstOrCreate(
            ['slug' => 'test-ws-cap-' . $max],
            ['name' => "Test WS Cap {$max}", 'price' => 0, 'currency' => 'USD', 'is_active' => true,
             'features' => ['max_workspaces' => $max]]
        );
        $plan->features = array_merge((array) $plan->features, ['max_workspaces' => $max]);
        $plan->save();
        $user->plan_id = $plan->id;
        $user->save();
        $user->refresh()->load('plan');
    }

    public function test_owner_can_create_a_team_workspace_via_api(): void
    {
        $user = $this->makeUser();
        // The default free plan caps workspaces at 1 (the auto-created personal
        // one already fills it), so grant headroom to exercise the happy path.
        $this->planWithMaxWorkspaces($user, 5);
        $icon = array_key_first(Workspace::ICON_CHOICES);
        $color = Workspace::COLOR_CHOICES[0];

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/workspaces', [
            'name'  => 'Team Alpha',
            'icon'  => $icon,
            'color' => $color,
        ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.item.name', 'Team Alpha');
        $resp->assertJsonPath('data.item.is_personal', false);
        $resp->assertJsonPath('data.item.is_owner', true);

        $id = $resp->json('data.item.id');
        $ws = Workspace::find($id);
        $this->assertNotNull($ws);
        $this->assertFalse((bool) $ws->is_personal);
        $this->assertSame($user->id, (int) $ws->owner_user_id);
        $this->assertSame($icon, $ws->settings['appearance']['icon'] ?? null);
        $this->assertSame($color, $ws->settings['appearance']['color'] ?? null);
    }

    public function test_create_is_blocked_by_plan_max_workspaces_cap(): void
    {
        $user = $this->makeUser();
        // The personal workspace already counts toward the cap; a plan limit of
        // 1 means a second workspace must be rejected.
        $this->planWithMaxWorkspaces($user, 1);

        $this->withToken($this->token($user));
        $resp = $this->postJson('/api/v1/workspaces', ['name' => 'Over The Cap']);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'plan_upgrade_required');
        $this->assertSame(1, $user->ownedWorkspaces()->count());
    }

    public function test_owner_can_delete_a_team_workspace_via_api(): void
    {
        $user = $this->makeUser();
        $ws = $user->ownedWorkspaces()->create([
            'name'        => 'Disposable',
            'slug'        => 'disposable-' . uniqid(),
            'is_personal' => false,
        ]);

        $this->withToken($this->token($user));
        $resp = $this->deleteJson("/api/v1/workspaces/{$ws->id}");

        $resp->assertStatus(200);
        $this->assertNull(Workspace::find($ws->id));

        $ids = collect($resp->json('data.items'))->pluck('id')->all();
        $this->assertNotContains($ws->id, $ids);
    }

    public function test_personal_workspace_cannot_be_deleted_via_api(): void
    {
        $user = $this->makeUser();
        $personal = $user->ownedWorkspaces()->where('is_personal', true)->first();
        $this->assertNotNull($personal);

        $this->withToken($this->token($user));
        $resp = $this->deleteJson("/api/v1/workspaces/{$personal->id}");

        $resp->assertStatus(422);
        $this->assertNotNull(Workspace::find($personal->id));
    }

    public function test_last_workspace_cannot_be_deleted_via_api(): void
    {
        $user = $this->makeUser();
        // Only the auto-created personal workspace exists — already guarded as
        // personal, so make a lone team workspace the sole owned one instead.
        $user->ownedWorkspaces()->where('is_personal', true)->delete();
        $ws = $user->ownedWorkspaces()->create([
            'name'        => 'Only One',
            'slug'        => 'only-one-' . uniqid(),
            'is_personal' => false,
        ]);
        $this->assertSame(1, $user->ownedWorkspaces()->count());

        $this->withToken($this->token($user));
        $resp = $this->deleteJson("/api/v1/workspaces/{$ws->id}");

        $resp->assertStatus(422);
        $this->assertNotNull(Workspace::find($ws->id));
    }

    public function test_non_owner_cannot_delete_someone_elses_workspace(): void
    {
        $owner = $this->makeUser();
        $ws = $owner->ownedWorkspaces()->create([
            'name'        => 'Not Yours',
            'slug'        => 'not-yours-' . uniqid(),
            'is_personal' => false,
        ]);

        $stranger = $this->makeUser();
        $this->withToken($this->token($stranger));
        $resp = $this->deleteJson("/api/v1/workspaces/{$ws->id}");

        $resp->assertStatus(403);
        $this->assertNotNull(Workspace::find($ws->id));
    }
}
