<?php

namespace Tests\Feature;

use App\Modules\User\Models\Project;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspaceRoleMatrix;
use App\Services\Planner\FolderChartExport;
use App\Services\Planner\FolderWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_roster_excludes_suspended_and_denied_members_and_counts_recorded_work(): void
    {
        $owner = User::factory()->create();
        $ws = $owner->ownedWorkspaces()->first();
        $this->actingAs($owner)->withSession([WorkspaceContext::SESSION_KEY => $ws->id]);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $owner);
        $project = Project::create(['user_id' => $owner->id, 'name' => 'Launch & learn']);
        $member = User::factory()->create(['name' => 'Active reviewer']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $member->id, 'role' => 'editor']);
        $suspended = User::factory()->create(['name' => 'Suspended reviewer']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $suspended->id, 'role' => 'editor', 'suspended_at' => now()]);
        $denied = User::factory()->create(['name' => 'Denied viewer']);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $denied->id, 'role' => 'viewer']);
        $matrix = WorkspaceRoleMatrix::forWorkspace($ws);
        $matrix['viewer']['projects.view_others'] = false;
        WorkspaceRoleMatrix::save($ws, $matrix, $owner);
        $this->post('/user/ai/folders/'.$project->id.'/tasks', ['title' => 'Prepare campaign', 'priority' => 'normal', 'assignee_id' => $member->id])->assertRedirect()->assertSessionHasNoErrors();
        $task = TaskCard::firstOrFail();
        TaskTimeEntry::create(['workspace_id' => $ws->id, 'card_id' => $task->id, 'user_id' => $member->id, 'source' => 'manual', 'minutes' => 45]);
        TaskTimeEntry::create(['workspace_id' => $ws->id, 'card_id' => $task->id, 'user_id' => $member->id, 'source' => 'timer', 'minutes' => 20, 'started_at' => now()]);
        $data = app(FolderWorkspace::class)->data($project, $owner);
        $people = collect($data['peopleData']['people']);
        $this->assertCount(2, $people);
        $this->assertFalse($people->contains('name', 'Denied viewer'));
        $this->assertFalse($people->contains('name', 'Suspended reviewer'));
        $active = $people->firstWhere('id', $member->id);
        $this->assertSame(1, $active['open']);
        $this->assertSame(45, $active['minutes']);
        foreach (['hub', 'flow', 'people'] as $mode) {
            $svg = FolderChartExport::render($mode, $project->name, $data['resources'], $people->all());
            $xml = simplexml_load_string($svg);
            $this->assertNotFalse($xml);
            $this->assertStringContainsString('Launch &amp; learn', $svg);
            $this->assertStringNotContainsString('Suspended reviewer', $svg);
        }
        $page = $this->get('/user/projects/'.$project->id)->assertOk()->assertSee('People / Process')->assertSee('Active reviewer')->assertDontSee('Suspended reviewer');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/hub-people.html', $page->getContent());
        }
        $report = $this->get('/user/projects/'.$project->id.'?tab=reports')->assertOk()->assertSee('Member performance');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/hub-people-reports.html', $report->getContent());
        }
        $team = $this->get('/user/team')->assertOk()->assertSee('Workspace team');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/workspace-team.html',$team->getContent());
        }
    }
}
