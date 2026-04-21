<?php

namespace Tests\Feature;

use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $tag = 'u'): User
    {
        return User::create([
            'name'     => $tag . ' ' . Str::random(4),
            'email'    => $tag . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function bindWorkspace(User $user): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $ws;
    }

    public function test_index_auto_seeds_personal_board_for_first_visit(): void
    {
        $user = $this->makeUser('alice');
        $this->actingAs($user)
            ->get('/user/tasks')
            ->assertStatus(200)
            ->assertSee('Personal Boards');

        $this->bindWorkspace($user);
        $this->assertSame(1, TaskBoard::query()
            ->where('scope', 'personal')->where('owner_user_id', $user->id)->count());
    }

    public function test_owner_can_create_team_board_with_starter_columns(): void
    {
        $user = $this->makeUser('alice');
        $this->actingAs($user)
            ->post('/user/tasks/boards', ['name' => 'Sprint 1', 'scope' => 'team'])
            ->assertRedirect();

        $this->bindWorkspace($user);
        $board = TaskBoard::where('name', 'Sprint 1')->first();
        $this->assertNotNull($board);
        $this->assertSame('team', $board->scope);
        $this->assertSame(4, $board->columns()->count());
        $this->assertTrue($board->columns()->where('is_done', true)->exists());
    }

    public function test_create_card_and_move_between_columns_updates_completion(): void
    {
        $user = $this->makeUser('alice');
        $this->bindWorkspace($user);
        // Create board via service path so columns exist.
        $this->actingAs($user)->post('/user/tasks/boards', ['name' => 'B', 'scope' => 'team']);
        $board = TaskBoard::where('name', 'B')->first();
        $first = $board->columns()->orderBy('position')->first();
        $done  = $board->columns()->where('is_done', true)->first();

        $this->actingAs($user)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $first->id, 'title' => 'Ship feature']);
        $card = TaskCard::where('title', 'Ship feature')->first();
        $this->assertNotNull($card);
        $this->assertNull($card->completed_at);

        // Move to Done column → auto-complete
        $this->actingAs($user)
            ->post("/user/tasks/cards/{$card->id}/move", ['column_id' => $done->id, 'position' => 0])
            ->assertOk();
        $this->assertNotNull($card->fresh()->completed_at);

        // Move back to first column → reopen
        $this->actingAs($user)
            ->post("/user/tasks/cards/{$card->id}/move", ['column_id' => $first->id, 'position' => 0])
            ->assertOk();
        $this->assertNull($card->fresh()->completed_at);
    }

    public function test_personal_board_is_invisible_to_other_workspace_members(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'editor']);

        // Alice creates a personal board in her workspace.
        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Alice Private', 'scope' => 'personal']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')
            ->where('name', 'Alice Private')->first();
        $this->assertNotNull($board);

        // Bob (editor in Alice's workspace) opens it — must 404.
        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($bob)
            ->get("/user/tasks/boards/{$board->id}")
            ->assertNotFound();
    }

    public function test_viewer_cannot_create_board(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'viewer']);

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($bob)
            ->post('/user/tasks/boards', ['name' => 'Hacky', 'scope' => 'team'])
            ->assertForbidden();
    }

    public function test_assignee_receives_notification(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $aliceWs = $alice->ensureDefaultWorkspace();
        WorkspaceMember::create(['workspace_id' => $aliceWs->id, 'user_id' => $bob->id, 'role' => 'editor']);

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Team', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Team')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'Help bob']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Help bob')->first();

        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/assign", ['user_id' => $bob->id])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bob->id,
            'type'    => 'task_assigned',
        ]);
    }

    public function test_cannot_assign_user_outside_workspace(): void
    {
        $alice   = $this->makeUser('alice');
        $outsider = $this->makeUser('out');
        $aliceWs = $alice->ensureDefaultWorkspace();

        session(['active_workspace_id' => $aliceWs->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'X', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'X')->first();
        $col = $board->columns()->orderBy('position')->first();
        $this->actingAs($alice)
            ->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $col->id, 'title' => 'T']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'T')->first();

        $this->actingAs($alice)
            ->post("/user/tasks/cards/{$card->id}/assign", ['user_id' => $outsider->id])
            ->assertStatus(422);
    }

    public function test_deleting_column_moves_cards_to_fallback_column(): void
    {
        $alice = $this->makeUser('alice');
        session(['active_workspace_id' => $alice->ensureDefaultWorkspace()->id]);
        $this->actingAs($alice)->post('/user/tasks/boards', ['name' => 'Move', 'scope' => 'team']);
        $board = TaskBoard::query()->withoutGlobalScope('workspace')->where('name', 'Move')->first();
        $first  = $board->columns()->orderBy('position')->first();
        $second = $board->columns()->orderBy('position')->skip(1)->first();
        $this->actingAs($alice)->post("/user/tasks/boards/{$board->id}/cards", ['column_id' => $first->id, 'title' => 'Keep me']);
        $card = TaskCard::query()->withoutGlobalScope('workspace')->where('title', 'Keep me')->first();

        $this->actingAs($alice)
            ->delete("/user/tasks/columns/{$first->id}")
            ->assertRedirect();

        $this->assertSame($second->id, $card->fresh()->column_id);
    }
}
