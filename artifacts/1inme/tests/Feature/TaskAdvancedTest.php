<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function setupBoard(): array
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);
        $this->post('/user/tasks/boards', ['name' => 'Launch workflow', 'scope' => 'team'])->assertRedirect();
        $board = TaskBoard::where('name', 'Launch workflow')->firstOrFail();
        $this->postJson('/user/tasks/boards/'.$board->id.'/cards', ['title' => 'Design launch page', 'column_id' => $board->columns->first()->id])->assertOk();
        $card = $board->cards->first();

        return [$owner, $ws, $board, $card];
    }

    public function test_progress_and_completion_are_coherent_and_move_columns(): void
    {
        [$owner,$ws,$board,$card] = $this->setupBoard();
        $this->patchJson('/user/tasks/cards/'.$card->id, ['progress' => 35])->assertOk()->assertJsonPath('card.progress', 35);
        $this->patchJson('/user/tasks/cards/'.$card->id, ['progress' => '100'])->assertOk();
        $this->assertTrue($card->fresh()->column->is_done);
        $this->assertNotNull($card->fresh()->completed_at);
        $this->assertSame(100, $card->fresh()->progress);
        $this->patchJson('/user/tasks/cards/'.$card->id, ['progress' => 30])->assertUnprocessable();
        $this->postJson('/user/tasks/cards/'.$card->id.'/complete', ['completed' => false])->assertOk();
        $this->assertNull($card->fresh()->completed_at);
        $this->assertSame(0, $card->fresh()->progress);
    }

    public function test_completion_stops_the_timer_and_done_column_creation_is_complete(): void
    {
        [$owner, $ws, $board, $card] = $this->setupBoard();
        $this->travelTo(now()->startOfSecond());
        $this->postJson('/user/tasks/cards/'.$card->id.'/timer/start')->assertOk();
        $this->travel(7)->minutes();
        $this->postJson('/user/tasks/cards/'.$card->id.'/complete', ['completed' => true])->assertOk();
        $entry = TaskTimeEntry::firstOrFail();
        $this->assertNotNull($entry->ended_at);
        $this->assertSame(7, (int) $entry->minutes);
        $done = $board->columns()->where('is_done', true)->firstOrFail();
        $result = $this->postJson('/user/tasks/boards/'.$board->id.'/cards', ['title' => 'Already finished', 'column_id' => $done->id])->assertOk();
        $created = TaskCard::findOrFail($result->json('id'));
        $this->assertSame(100, $created->progress);
        $this->assertNotNull($created->completed_at);
    }

    public function test_multiple_routes_match_conditions_and_manual_drops_are_checked(): void
    {
        [$owner,$ws,$board,$card] = $this->setupBoard();
        [$todo,$doing,$done] = $board->columns->all();
        $rules = [['target_column_id' => $doing->id, 'trigger' => 'completed', 'condition' => 'priority', 'value' => 'urgent'], ['target_column_id' => $done->id, 'trigger' => 'completed', 'condition' => 'always', 'value' => ''], ['target_column_id' => $doing->id, 'trigger' => 'manual', 'condition' => 'always', 'value' => '']];
        $this->putJson('/user/tasks/columns/'.$todo->id.'/transitions', ['transitions' => $rules])->assertOk();
        $card->update(['priority' => 'urgent']);
        $this->postJson('/user/tasks/cards/'.$card->id.'/move', ['column_id' => $done->id, 'position' => 0])->assertUnprocessable();
        $this->postJson('/user/tasks/cards/'.$card->id.'/complete', ['completed' => true])->assertOk();
        $this->assertSame($doing->id, $card->fresh()->column_id);
        $this->assertSame(0, $card->fresh()->progress);
        $this->assertNull($card->fresh()->completed_at);
        $other = $board->replicate();
        $other->name = 'Other board';
        $other->save();
        $foreign = $other->columns()->create(['workspace_id' => $ws->id, 'name' => 'Foreign', 'position' => 0]);
        $this->putJson('/user/tasks/columns/'.$todo->id.'/transitions', ['transitions' => [['target_column_id' => $foreign->id, 'trigger' => 'completed', 'condition' => 'always']]])->assertUnprocessable();
    }

    public function test_related_picker_returns_accessible_lists_without_foreign_minds(): void
    {
        [$owner,$ws,$board] = $this->setupBoard();
        AiMind::create(['user_id' => $owner->id, 'name' => 'Launch knowledge', 'is_disabled' => false]);
        $foreign = User::factory()->create();
        AiMind::create(['user_id' => $foreign->id, 'name' => 'Private foreign Mind', 'is_disabled' => false]);
        $r = $this->getJson('/user/tasks/boards/'.$board->id.'/related-choices?kind=mind&q=Launch')->assertOk();
        $this->assertSame('Launch knowledge', $r->json('items.0.title'));
        $this->assertStringNotContainsString('Private foreign', $r->getContent());
    }

    public function test_follow_attendance_tracks_only_working_intervals_and_manual_time_is_independent(): void
    {
        [$owner,$ws,$board,$card] = $this->setupBoard();
        DB::table('attendance_policies')->insert(['workspace_id' => $ws->id, 'settings' => json_encode(['require_photo' => false]), 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/user/tasks/cards/'.$card->id.'/timer/start', ['follow_attendance' => true])->assertOk()->assertJsonPath('waiting', true);
        $this->assertDatabaseCount('task_time_entries', 0);
        $punch = function ($action) {
            $id = $this->postJson('/user/attendance/challenge', ['action' => $action])->assertOk()->json('id');
            $this->postJson('/user/attendance/punch', ['challenge_id' => $id])->assertOk();
        };
        $punch('in');
        $this->assertDatabaseCount('task_time_entries', 1);
        $this->travel(10)->minutes();
        $punch('break');
        $this->assertEquals(10, TaskTimeEntry::sum('minutes'));
        $this->travel(20)->minutes();
        $punch('resume');
        $this->travel(5)->minutes();
        $punch('out');
        $this->assertEquals(15, TaskTimeEntry::sum('minutes'));
        $this->postJson('/user/tasks/cards/'.$card->id.'/timer/stop')->assertOk();
        $this->assertDatabaseCount('task_tracking_preferences', 0);
        $this->postJson('/user/tasks/cards/'.$card->id.'/time-entries', ['minutes' => 12, 'note' => 'Offline planning'])->assertOk();
        $this->assertEquals(27, TaskTimeEntry::sum('minutes'));
    }

    public function test_workflow_track_and_export_permissions_can_be_revoked_separately(): void
    {
        [$owner,$ws,$board,$card] = $this->setupBoard();
        $staff = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $staff->id, 'role' => 'editor']);
        $matrix = WorkspaceRoleMatrix::forWorkspace($ws);
        $matrix['editor']['tasks.workflow'] = false;
        $matrix['editor']['tasks.track'] = false;
        $matrix['editor']['tasks.export'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->actingAs($staff)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        $this->putJson('/user/tasks/columns/'.$card->column_id.'/transitions', ['transitions' => []])->assertForbidden();
        $this->postJson('/user/tasks/cards/'.$card->id.'/timer/start')->assertForbidden();
        $this->getJson('/user/tasks/boards/'.$board->id.'/export?mode=board&format=svg')->assertForbidden();
    }

    public function test_column_people_groups_assignees_and_contributors_without_other_columns(): void
    {
        [$owner, $ws, $board, $card] = $this->setupBoard();
        $assigned = User::factory()->create(['name' => 'Assigned teammate', 'avatar' => 'https://example.com/teammate.png']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $assigned->id, 'role' => 'editor']);
        $card->assignees()->attach($assigned->id);
        \App\Modules\User\Models\TaskActivity::log($card->id, $assigned->id, 'commented', []);
        $page = $this->get('/user/tasks/boards/'.$board->id)->assertOk()->assertSee('Assigned teammate');
        $people = $page->viewData('columnPeople');
        $this->assertArrayHasKey($assigned->id, $people[$card->column_id]);
        $this->assertSame(['Assigned', 'Participated'], array_keys($people[$card->column_id][$assigned->id]['roles']));
        $this->assertCount(1, $people[$card->column_id][$assigned->id]['tasks']);
        $this->assertSame('https://example.com/teammate.png', $people[$card->column_id][$assigned->id]['avatar']);
        $this->assertSame([], $people[$board->columns[1]->id]);
        $card->update(['archived_at' => now()]);
        $page = $this->get('/user/tasks/boards/'.$board->id)->assertOk();
        $this->assertSame([], $page->viewData('columnPeople')[$card->column_id]);
    }

    public function test_related_link_type_is_available_and_duplicate_urls_are_deduplicated(): void
    {
        [$owner, $ws, $board, $card] = $this->setupBoard();
        $link = \App\Modules\User\Models\Link::create(['user_id' => $owner->id, 'title' => 'Campaign bio', 'alias' => 'campaign-bio', 'type' => 'biolink']);
        $choices = $this->getJson('/user/tasks/boards/'.$board->id.'/related-choices?kind=link&q=Campaign')->assertOk();
        $this->assertSame('biolink', $choices->json('items.0.link_type'));
        $item = ['title' => 'Campaign bio', 'type' => 'link', 'link_type' => 'biolink', 'url' => route('user.links.show', $link), 'note' => ''];
        $this->patchJson('/user/tasks/cards/'.$card->id, ['related_items' => [$item, array_merge($item, ['url' => $item['url'].'/#details'])]])->assertOk();
        $this->assertCount(1, $card->fresh()->related_items);
        $this->assertSame('biolink', $card->fresh()->related_items[0]['link_type']);
    }

    public function test_export_and_editor_render_with_escaped_titles(): void
    {
        [$owner,$ws,$board,$card] = $this->setupBoard();
        $card->update(['start_date' => '2026-10-24', 'due_date' => '2026-10-30', 'progress' => 35]);
        foreach (['Prepare content', 'Review copy', 'Publish launch'] as $i => $title) {
            $board->cards()->create(['workspace_id' => $ws->id, 'column_id' => $board->columns[$i % 3]->id, 'created_by_user_id' => $owner->id, 'title' => $title, 'start_date' => '2026-10-'.(25 + $i), 'due_date' => '2026-11-02', 'progress' => 20 + $i * 20, 'position' => $i + 1]);
        }
        $page = $this->get('/user/tasks/boards/'.$board->id)->assertOk()->assertSee('Task editor sections')->assertSee('Column connections')->assertSee('Export PDF')->assertDontSee('Attach from Cloud Files')->assertDontSee('cloudAttachPicker(', false);
        $files = $this->withSession(['files_last_tab' => 'cloud'])->get('/user/files')->assertOk()->assertDontSee('My Vault')->assertDontSee('Cloud Library');
        if (getenv('TASK_UI_FIXTURE')) file_put_contents('/tmp/sayzio-files-clean.html', $files->getContent());
        $svg = $this->get('/user/tasks/boards/'.$board->id.'/export?mode=gantt&format=svg&from=2026-10-24')->assertOk();
        $this->assertStringContainsString('<svg', $svg->getContent());
        $pdf = $this->get('/user/tasks/boards/'.$board->id.'/export?mode=gantt&format=pdf&from=2026-10-24')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        if (getenv('TASK_UI_FIXTURE')) {
            file_put_contents('/tmp/sayzio-task-advanced.html', $page->getContent());
            file_put_contents('/tmp/sayzio-task-card.json', json_encode($this->getJson('/user/tasks/cards/'.$card->id)->json()));
            file_put_contents('/tmp/sayzio-task-gantt.pdf', $pdf->getContent());
            file_put_contents('/tmp/sayzio-task-gantt.svg', $svg->getContent());
            file_put_contents('/tmp/sayzio-task-board.pdf',$this->get('/user/tasks/boards/'.$board->id.'/export?mode=board&format=pdf')->assertOk()->getContent());
        }
    }
}
