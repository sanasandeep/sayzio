<?php

namespace Tests\Feature;

use App\Modules\User\Models\Project;
use App\Modules\User\Models\ProjectItem;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Planner\PlannerResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskWorkspaceUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function setupBoard(): array
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);
        $project = Project::create(['workspace_id' => $ws->id, 'user_id' => $owner->id, 'created_by_user_id' => $owner->id, 'name' => 'Task launch']);
        $this->post('/user/tasks/boards', ['name' => "Team's launch", 'scope' => 'team', 'project_id' => $project->id])->assertRedirect()->assertSessionHasNoErrors();
        $board = TaskBoard::where('scope', 'team')->firstOrFail();
        $this->postJson('/user/tasks/boards/'.$board->id.'/cards', ['title' => 'Prepare launch', 'column_id' => $board->columns->first()->id])->assertOk();

        return [$owner, $ws, $project, $board, $board->cards->first()];
    }

    public function test_column_save_returns_json_and_done_moves_are_shared(): void
    {
        [$owner,$ws,$project,$board,$card] = $this->setupBoard();
        $column = $board->columns->first();
        $this->putJson('/user/tasks/columns/'.$column->id, ['name' => 'Working', 'is_done' => false, 'wip_limit' => 2, 'color' => '#3d6bff'])->assertOk()->assertJsonPath('column.wip_limit', 2);
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $revision = $this->getJson('/user/tasks/boards/'.$board->id.'/sync')->assertOk()->json('revision');
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $done = $board->columns->firstWhere('is_done', true);
        $this->postJson('/user/tasks/cards/'.$card->id.'/move', ['column_id' => $done->id, 'position' => 0])->assertOk();
        $this->assertNotNull($card->fresh()->completed_at);
        $next = $this->getJson('/user/tasks/boards/'.$board->id.'/sync')->assertOk()->json('revision');
        $this->assertNotSame($revision, $next);
        $this->postJson('/user/tasks/cards/'.$card->id.'/move', ['column_id' => $column->id, 'position' => 0])->assertOk();
        $this->assertNull($card->fresh()->completed_at);
    }

    public function test_project_board_tasks_feed_folder_planner_and_gantt_dates_are_validated(): void
    {
        [$owner,$ws,$project,$board,$card] = $this->setupBoard();
        $items = app(PlannerResources::class)->folder($project, $owner);
        $this->assertTrue(collect($items)->contains(fn ($item) => $item['kind'] === 'task' && $item['row']->id === $card->id));
        $this->patchJson('/user/tasks/cards/'.$card->id, ['start_date' => '2026-10-14', 'due_date' => '2026-10-20'])->assertOk();
        $this->patchJson('/user/tasks/cards/'.$card->id, ['due_date' => '2026-10-10'])->assertUnprocessable();
        $this->assertSame('2026-10-20', $card->fresh()->due_date->toDateString());
        $page = $this->get('/user/tasks/boards/'.$board->id)->assertOk()->assertSee('Gantt timeline')->assertSee('Task limit (0 = unlimited)')->assertDontSee('$root.saveColumn', false);
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/task-board-upgrade.html', $page->getContent());
            file_put_contents('/tmp/task-upgrade-card.json', json_encode($this->getJson('/user/tasks/cards/'.$card->id)->json()));
        }
        $index = $this->get('/user/tasks')->assertOk()->assertSee('Optional project')->assertSee('Find boards');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/task-dashboard-upgrade.html', $index->getContent());
        }
        $before = $this->getJson('/user/tasks/boards/'.$board->id.'/sync')->json('revision');
        $this->assertSame($before, $this->getJson('/user/tasks/boards/'.$board->id.'/sync')->json('revision'));
        $this->put('/user/tasks/boards/'.$board->id, ['project_id' => null])->assertRedirect();
        $this->assertNotSame($before, $this->getJson('/user/tasks/boards/'.$board->id.'/sync')->json('revision'));
        $this->assertFalse(ProjectItem::where('kind', 'board')->where('resource_id', $board->id)->exists());
    }

    public function test_stale_task_edits_are_rejected_and_private_sync_is_hidden(): void
    {
        [$owner,$ws,$project,$board,$card] = $this->setupBoard();
        $revision = $this->getJson('/user/tasks/cards/'.$card->id)->assertOk()->json('card.revision');
        $this->patchJson('/user/tasks/cards/'.$card->id, ['title' => 'Reviewed title', 'expected_revision' => $revision])->assertOk();
        $this->patchJson('/user/tasks/cards/'.$card->id, ['title' => 'Stale title', 'expected_revision' => $revision])->assertStatus(409);
        $this->assertSame('Reviewed title', $card->fresh()->title);
        $private = TaskBoard::create(['workspace_id' => $ws->id, 'name' => 'Private', 'scope' => 'personal', 'owner_user_id' => $owner->id]);
        $member = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $this->actingAs($member)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->getJson('/user/tasks/boards/'.$private->id.'/sync')->assertNotFound();
    }
    public function test_related_items_save_remove_and_reject_unsafe_urls(): void
    {
        [$owner,$ws,$project,$board,$card] = $this->setupBoard();
        $item = ['title' => 'Campaign Mind', 'type' => 'mind', 'url' => url('/user/minds/42'), 'note' => 'Review the campaign brief'];
        $revision = $this->getJson('/user/tasks/cards/'.$card->id)->json('card.revision');
        $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => [$item], 'expected_revision' => $revision])->assertOk();
        $this->getJson('/user/tasks/cards/'.$card->id)->assertOk()->assertJsonPath('card.related_items.0.note', $item['note']);
        $this->assertCount(1, $card->fresh()->related_items);
        foreach (['javascript:alert(1)', 'https://outside.example/user/minds/42', 'https://sayzio.app@evil.example/'] as $url) {
            $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => [array_merge($item, ['url' => $url])]])->assertUnprocessable();
        }
        $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => array_fill(0,21,$item)])->assertUnprocessable();
        $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => [], 'expected_revision' => $revision])->assertStatus(409);
        $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => []])->assertOk();
        $this->assertSame([], $card->fresh()->related_items);
        $viewer = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $this->actingAs($viewer)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => [$item]])->assertForbidden();
        $this->assertSame([], $card->fresh()->related_items);
        $this->actingAs($owner);
        $page = $this->get('/user/tasks/boards/'.$board->id)->assertOk()->assertSee('Related Sayzio items');
        if (getenv('FORMS_UI_FIXTURE')) file_put_contents('/tmp/task-context-board.html', $page->getContent());
    }

}
