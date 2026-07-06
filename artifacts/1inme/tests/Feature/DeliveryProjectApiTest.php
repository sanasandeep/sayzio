<?php

namespace Tests\Feature;

use App\Modules\User\Models\DeliveryProject;
use App\Modules\User\Models\DeliveryProjectTask;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #3567 — Sanctum Bearer-token access to Delivery Projects.
 *
 * The API path never runs SetActiveWorkspace, so projects are resolved
 * across the caller's accessible workspaces (reads need tasks.view,
 * mutations need tasks.edit). These tests use a REAL bearer token —
 * Sanctum::actingAs would break the TouchSessionToken middleware — and
 * assert the unified {data} / {error} envelope.
 */
class DeliveryProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u'): User
    {
        return User::factory()->create([
            'name' => $tag . ' ' . Str::random(4),
            'email' => $tag . Str::random(8) . '@ex.com',
        ])->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function memberOf(Workspace $ws, User $user, string $role): void
    {
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => $role,
        ]);
    }

    private function makeProject(Workspace $ws, array $attrs = []): DeliveryProject
    {
        return DeliveryProject::create(array_merge([
            'workspace_id'       => $ws->id,
            'created_by_user_id' => $ws->owner_user_id,
            'title'              => 'Proj ' . Str::random(4),
            'status'             => DeliveryProject::STATUS_ACTIVE,
        ], $attrs));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/delivery-projects')->assertStatus(401);
    }

    public function test_index_lists_projects_for_workspace_member(): void
    {
        $owner  = $this->makeUser('owner');
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser('mem');
        $this->memberOf($ws, $member, 'editor');

        $project = $this->makeProject($ws, ['title' => 'Kitchen remodel']);

        $resp = $this->withToken($this->token($member))
            ->getJson('/api/v1/delivery-projects')
            ->assertOk()
            ->assertJsonStructure(['data' => ['items' => [['id', 'title', 'status', 'progress']]]]);

        $ids = collect($resp->json('data.items'))->pluck('id')->all();
        $this->assertContains($project->id, $ids);
    }

    public function test_index_excludes_projects_from_inaccessible_workspaces(): void
    {
        $owner    = $this->makeUser('owner');
        $ws       = $owner->ownedWorkspaces()->first();
        $this->makeProject($ws, ['title' => 'Private project']);

        // An unrelated user with no membership must see nothing.
        $outsider = $this->makeUser('out');

        $resp = $this->withToken($this->token($outsider))
            ->getJson('/api/v1/delivery-projects')
            ->assertOk();

        $this->assertSame([], $resp->json('data.items'));
    }

    public function test_show_returns_project_tasks_members_and_share_url(): void
    {
        $owner  = $this->makeUser('owner');
        $ws     = $owner->ownedWorkspaces()->first();
        $member = $this->makeUser('mem');
        $this->memberOf($ws, $member, 'editor');
        $project = $this->makeProject($ws);

        $this->withToken($this->token($owner))
            ->getJson("/api/v1/delivery-projects/{$project->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'project' => ['id', 'title', 'status', 'progress', 'tasks'],
                    'members',
                    'statuses',
                    'share_url',
                ],
            ])
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonFragment(['share_url' => route('delivery-project.share', $project->share_token)]);
    }

    public function test_show_returns_error_envelope_for_non_member(): void
    {
        $owner    = $this->makeUser('owner');
        $ws       = $owner->ownedWorkspaces()->first();
        $project  = $this->makeProject($ws);
        $outsider = $this->makeUser('out');

        $this->withToken($this->token($outsider))
            ->getJson("/api/v1/delivery-projects/{$project->id}")
            ->assertStatus(404)
            ->assertJsonStructure(['error' => ['message']]);
    }

    public function test_store_task_requires_tasks_edit(): void
    {
        $owner  = $this->makeUser('owner');
        $ws     = $owner->ownedWorkspaces()->first();
        $viewer = $this->makeUser('vw');
        $this->memberOf($ws, $viewer, 'viewer'); // view-only, no tasks.edit
        $project = $this->makeProject($ws);

        // A viewer lacks tasks.edit, so the project is not resolvable for a
        // mutation → 404 with the error envelope (never a silent success).
        $this->withToken($this->token($viewer))
            ->postJson("/api/v1/delivery-projects/{$project->id}/tasks", ['title' => 'Hack'])
            ->assertStatus(404)
            ->assertJsonStructure(['error' => ['message']]);

        $this->assertSame(0, DeliveryProjectTask::query()->withoutGlobalScope('workspace')->count());
    }

    public function test_task_crud_updates_overall_progress(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $token   = $this->token($owner);
        $project = $this->makeProject($ws);

        // Add first task → project progress 0.
        $taskA = $this->withToken($token)
            ->postJson("/api/v1/delivery-projects/{$project->id}/tasks", ['title' => 'Design'])
            ->assertStatus(201)
            ->assertJsonPath('data.task.status', 'todo')
            ->assertJsonPath('data.task.progress', 0)
            ->json('data.task.id');

        $this->withToken($token)
            ->getJson("/api/v1/delivery-projects/{$project->id}")
            ->assertJsonPath('data.project.progress', 0);

        // Mark first task done → progress syncs to 100 (single task = 100%).
        $this->withToken($token)
            ->patchJson("/api/v1/delivery-projects/tasks/{$taskA}", ['status' => 'done'])
            ->assertOk()
            ->assertJsonPath('data.task.status', 'done')
            ->assertJsonPath('data.task.progress', 100);

        $this->withToken($token)
            ->getJson("/api/v1/delivery-projects/{$project->id}")
            ->assertJsonPath('data.project.progress', 100);

        // Add a second, empty task → average of (100 + 0) = 50.
        $this->withToken($token)
            ->postJson("/api/v1/delivery-projects/{$project->id}/tasks", ['title' => 'Build'])
            ->assertStatus(201);

        $this->withToken($token)
            ->getJson("/api/v1/delivery-projects/{$project->id}")
            ->assertJsonPath('data.project.progress', 50);
    }

    public function test_update_task_progress_syncs_status(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $token   = $this->token($owner);
        $project = $this->makeProject($ws);

        $taskId = $this->withToken($token)
            ->postJson("/api/v1/delivery-projects/{$project->id}/tasks", ['title' => 'T'])
            ->json('data.task.id');

        // Partial progress promotes a todo task to in_progress.
        $this->withToken($token)
            ->patchJson("/api/v1/delivery-projects/tasks/{$taskId}", ['progress' => 40])
            ->assertOk()
            ->assertJsonPath('data.task.status', 'in_progress')
            ->assertJsonPath('data.task.progress', 40);
    }

    public function test_destroy_task_removes_it(): void
    {
        $owner   = $this->makeUser('owner');
        $ws      = $owner->ownedWorkspaces()->first();
        $token   = $this->token($owner);
        $project = $this->makeProject($ws);

        $taskId = $this->withToken($token)
            ->postJson("/api/v1/delivery-projects/{$project->id}/tasks", ['title' => 'Doomed'])
            ->json('data.task.id');

        $this->withToken($token)
            ->deleteJson("/api/v1/delivery-projects/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('delivery_project_tasks', ['id' => $taskId]);
    }
}
