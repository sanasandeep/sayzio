<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\PlannerNote;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\SocialProof;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Planner\PlannerResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FolderProjectHubTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($u));
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function folder($u): Project
    {
        return Project::create(['user_id' => $u->id, 'name' => 'Launch']);
    }

    public function test_folder_maps_team_marketing_notes_and_mobile_sections_render(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/hub-marketing-empty.html', $this->get('/user/projects/'.$p->id.'?tab=marketing')->assertOk()->getContent());
        }
        $mind = AiMind::create(['user_id' => $u->id, 'name' => 'Launch knowledge']);
        app(PlannerResources::class)->attach($p, 'mind', $mind->id, $u);
        $buzz = SocialProof::create(['user_id' => $u->id, 'name' => 'Launch proof', 'type' => 'recent_activity', 'impressions' => 12, 'clicks' => 3, 'conversions' => 1]);
        app(PlannerResources::class)->attach($p, 'buzz', $buzz->id, $u);
        PlannerNote::create(['user_id' => $u->id, 'workspace_id' => $p->workspace_id, 'project_id' => $p->id, 'body' => 'Ready for launch', 'completed_at' => now()]);
        foreach (['overview', 'team', 'marketing', 'resources', 'tasks', 'remember', 'activity'] as $tab) {
            $page = $this->get('/user/projects/'.$p->id.'?tab='.$tab)->assertOk()->assertSee('Project section')->assertSee('Suggested workflow')->assertSee('Launch knowledge')->assertSee('Launch proof')->assertSee('Ready for launch');
            if ($tab === 'resources') {
                $page->assertSee('Select visible')->assertDontSee("@include('user.planner.chooser", false);
            }
            if (getenv('FORMS_UI_FIXTURE')) {
                file_put_contents('/tmp/hub-'.$tab.'.html', $page->getContent());
            }
        }
        $this->get('/user/projects/'.$p->id.'/edit')->assertOk()->assertSee('Delete folder, keep contents');
    }

    public function test_direct_task_creation_and_completion_keep_checklists_and_folder_assignment(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $this->post('/user/ai/folders/'.$p->id.'/tasks', ['title' => 'Prepare launch', 'description' => 'Check content', 'priority' => 'high', 'due_date' => '2026-10-20', 'assignee_id' => $u->id, 'checklist' => "Proofread\nPublish draft"])->assertRedirect()->assertSessionHasNoErrors();
        $card = TaskCard::firstOrFail();
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/hub-tasks-populated.html', $this->get('/user/projects/'.$p->id.'?tab=tasks')->assertOk()->getContent());
        }$this->assertSame(2, $card->subtasks()->count());
        $this->assertTrue($card->assignees()->where('user_id', $u->id)->exists());
        $this->assertDatabaseHas('project_items', ['project_id' => $p->id, 'kind' => 'task', 'resource_id' => $card->id]);
        $this->post('/user/ai/folders/'.$p->id.'/tasks/'.$card->id, ['action' => 'complete'])->assertRedirect();
        $this->assertNotNull($card->fresh()->completed_at);
        $this->post('/user/ai/folders/'.$p->id.'/tasks/'.$card->id, ['action' => 'reopen'])->assertRedirect();
        $this->assertNull($card->fresh()->completed_at);
    }

    public function test_direct_tasks_obey_task_board_plan_limits(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $plan = Plan::create(['name' => 'No task boards', 'slug' => 'no-task-boards', 'status' => 'active', 'features' => ['tasks' => true, 'max_task_boards' => 0]]);
        $u->update(['plan_id' => $plan->id]);
        $u->unsetRelation('plan');
        $this->postJson('/user/ai/folders/'.$p->id.'/tasks', ['title' => 'Cannot create', 'priority' => 'normal'])->assertUnprocessable();
        $this->assertDatabaseCount('task_cards', 0);
    }

    public function test_relationships_require_assigned_visible_resources(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $a = Link::create(['user_id' => $u->id, 'project_id' => $p->id, 'title' => 'Launch', 'type' => 'biolink', 'alias' => 'launch-relationship']);
        $b = Link::create(['user_id' => $u->id, 'title' => 'Unassigned', 'type' => 'url', 'alias' => 'unassigned-relationship']);
        $payload = ['source' => 'link:'.$a->id, 'target' => 'link:'.$b->id, 'label' => 'Supports'];
        $this->postJson('/user/ai/folders/'.$p->id.'/connections', $payload)->assertUnprocessable();
        app(PlannerResources::class)->attach($p, 'link', $b->id, $u);
        $this->post('/user/ai/folders/'.$p->id.'/connections', $payload)->assertRedirect();
        $this->post('/user/ai/folders/'.$p->id.'/connections', $payload)->assertRedirect();
        $this->assertDatabaseCount('folder_relationships', 1);
        $this->delete('/user/ai/folders/'.$p->id.'/connections/'.DB::table('folder_relationships')->value('id'))->assertRedirect();
        $this->assertDatabaseCount('folder_relationships', 0);
        $this->assertDatabaseCount('links', 2);
    }

    public function test_delete_keeps_contents_and_pauses_automation(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $link = Link::create(['user_id' => $u->id, 'project_id' => $p->id, 'title' => 'Live content', 'type' => 'biolink', 'alias' => 'kept-content']);
        $plan = NoteActionPlan::create(['user_id' => $u->id, 'project_id' => $p->id, 'title' => 'Launch plan', 'purpose' => 'project', 'notes' => 'Launch notes', 'result' => [], 'automation_enabled' => true, 'next_run_at' => now()->addHour()]);
        $note = PlannerNote::create(['user_id' => $u->id, 'workspace_id' => $p->workspace_id, 'project_id' => $p->id, 'body' => 'Keep this note']);
        $mind = AiMind::create(['user_id' => $u->id, 'name' => 'Keep knowledge']);
        app(PlannerResources::class)->attach($p, 'mind', $mind->id, $u);
        $this->delete('/user/projects/'.$p->id)->assertRedirect();
        $this->assertNotNull($mind->fresh());
        $this->assertDatabaseMissing('project_items', ['project_id' => $p->id]);
        $this->assertNull($link->fresh()->project_id);
        $this->assertNull($plan->fresh()->project_id);
        $this->assertFalse($plan->fresh()->automation_enabled);
        $this->assertNull($plan->fresh()->next_run_at);
        $this->assertNull($note->fresh()->project_id);
        $this->assertDatabaseMissing('projects', ['id' => $p->id]);
        $destination = Project::create(['user_id' => $u->id, 'name' => 'Recovered']);
        $this->get('/user/projects/'.$destination->id.'?tab=remember')->assertOk()->assertSee('Unassigned notes')->assertSee('Keep this note');
        $this->post('/user/ai/planner/notes/'.$note->id, ['action' => 'assign', 'project_id' => $destination->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($destination->id, $note->fresh()->project_id);
    }

    public function test_move_contents_preserves_resources_and_schedules(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $dest = Project::create(['user_id' => $u->id, 'name' => 'Next campaign']);
        $link = Link::create(['user_id' => $u->id, 'project_id' => $p->id, 'title' => 'Launch', 'type' => 'url', 'alias' => 'move-content']);
        $this->post('/user/ai/folders/'.$p->id.'/move', ['destination' => $dest->id])->assertRedirect();
        $this->assertSame($dest->id, $link->fresh()->project_id);
        $this->assertDatabaseCount('projects', 2);
    }

    public function test_foreign_folders_and_viewer_mutations_are_rejected(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $other = User::factory()->create();
        $this->actingAs($other);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($other));
        app()->instance('workspace_owner', $other);
        $this->postJson('/user/ai/folders/'.$p->id.'/tasks', ['title' => 'Unauthorized', 'priority' => 'normal'])->assertNotFound();
        $this->actingAs($u);
        $ws = app(WorkspaceContext::class)->resolve($u);
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $other->id, 'role' => 'viewer']);
        $this->actingAs($other);
        app(WorkspaceContext::class)->set($ws);
        $this->postJson('/user/ai/folders/'.$p->id.'/tasks', ['title' => 'Unauthorized', 'priority' => 'normal'])->assertForbidden();
    }

    public function test_my_links_has_no_reports_tab_but_folder_reports_keep_native_analytics(): void
    {
        $u = $this->owner();
        $p = $this->folder($u);
        $this->get('/user/links')->assertOk()->assertDontSee('aria-label="Link views"', false);
        $page = $this->get('/user/projects/'.$p->id.'?tab=reports')->assertOk()->assertSee('Project health')->assertSee('Buzz conversions')->assertSee('rp-from');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/hub-reports.html', $page->getContent());
        }
    }
    public function test_campaign_and_forecast_native_assignments_move_and_delete_without_destroying_content(): void
    {
        $u=$this->owner(); $p=$this->folder($u); $target=Project::create(['user_id'=>$u->id,'name'=>'Next']);
        $campaign=\App\Modules\User\Models\MarketingCampaign::create(['user_id'=>$u->id,'project_id'=>$p->id,'name'=>'Studio launch','path'=>'quick','brief'=>[]]);
        $forecast=\App\Modules\User\Models\MarketingPlanCalc::create(['user_id'=>$u->id,'workspace_id'=>$p->workspace_id,'name'=>'Launch forecast','payload'=>['project_id'=>$p->id,'annual_budget'=>12000]]);
        app(PlannerResources::class)->attach($p,'budget',$forecast->id,$u);
        $items=collect(app(PlannerResources::class)->folder($p,$u));
        $this->assertSame(1,$items->where('kind','budget')->count());
        $this->assertSame(1,$items->where('kind','campaign')->count());
        $this->get('/user/projects/'.$p->id.'?tab=budget')->assertOk()->assertSee('Launch forecast')->assertSee('Modelled cost:');
        $this->post('/user/ai/folders/'.$p->id.'/connections',['source'=>'campaign:'.$campaign->id,'target'=>'budget:'.$forecast->id,'label'=>'Funds'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/user/ai/folders/'.$p->id.'/move',['destination'=>$target->id])->assertRedirect();
        $this->assertSame($target->id,$campaign->fresh()->project_id);
        $this->assertSame($target->id,$forecast->fresh()->payload['project_id']);
        $this->assertCount(0,app(PlannerResources::class)->folder($p,$u));
        $this->delete('/user/projects/'.$target->id)->assertRedirect();
        $this->assertNull($campaign->fresh()->project_id);
        $this->assertNull($forecast->fresh()->payload['project_id']);
    }

    public function test_studio_creation_carries_authorized_folder_context(): void
    {
        $u=$this->owner(); $p=$this->folder($u);
        $this->get('/user/marketing-studio/create?project_id='.$p->id)->assertOk()->assertSee('Folder: Launch');
        $brand=\App\Modules\User\Models\BrandKit::create(['user_id'=>$u->id,'name'=>'Folder identity']);
        $this->put('/user/projects/'.$p->id,['name'=>$p->name,'default_brand_kit_id'=>$brand->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals($brand->id,$p->fresh()->default_brand_kit_id);
        $this->get('/user/marketing-studio/create?project_id='.$p->id)->assertOk()->assertSee('Folder identity');
        $data=['name'=>'Folder campaign','path'=>'quick','goal'=>'Launch store','project_id'=>$p->id];
        $this->post('/user/marketing-studio',$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('marketing_campaigns',['name'=>'Folder campaign','project_id'=>$p->id]);
        $other=User::factory()->create();
        $foreign=Project::create(['user_id'=>$other->id,'name'=>'Foreign']);
        $data['project_id']=$foreign->id;
        $this->post('/user/marketing-studio',$data)->assertNotFound();
        $this->get('/user/marketing-studio/create?project_id='.$foreign->id)->assertNotFound();
    }

}
