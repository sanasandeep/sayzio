<?php

namespace Tests\Feature;

use App\Jobs\IngestAiMindSourceJob;
use App\Jobs\RunAiPlanner;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\PlannerHandoff;
use App\Modules\User\Models\PlannerNote;
use App\Modules\User\Models\PlannerRun;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiStaffRuntime;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Billing\WalletService;
use App\Services\Planner\PlannerEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiPlannerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($u));
        app()->instance('workspace_owner', $u);
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $this->mock(AiUsageCharger::class, fn ($m) => $m->shouldReceive('getBalance')->andReturn(10000));

        return $u;
    }

    private function planResult(): array
    {
        return ['title' => 'Grow office lunches', 'summary' => 'Confirm delivery coverage.', 'decisions' => [], 'questions' => [], 'warnings' => [], 'tasks' => [['title' => 'Confirm delivery radius', 'details' => 'Review coverage before advertising.', 'priority' => 'high', 'owner' => '', 'due_date' => '', 'checklist' => ['Check delivery cost', 'Confirm service area']]], 'milestones' => [['title' => 'Ready to launch', 'details' => 'Coverage agreed']], 'page_copy' => ['title' => 'Lunch', 'body' => 'Office lunch options'], 'form' => null];
    }

    private function plan($u): NoteActionPlan
    {
        return NoteActionPlan::create(['user_id' => $u->id, 'title' => 'Lunch plan', 'purpose' => 'project', 'notes' => 'Prepare launch', 'result' => $this->planResult(), 'planner_settings' => $this->settings(), 'automation_enabled' => true, 'next_run_at' => now()->subMinute()]);
    }

    private function settings(): array
    {
        return ['goal' => 'Grow lunch orders', 'cadence' => 'daily', 'trigger' => 'schedule', 'time' => '09:00', 'timezone' => 'Asia/Kolkata', 'weekday' => 1, 'run_budget' => 100, 'daily_budget' => 500, 'monthly_budget' => 3000, 'resources' => []];
    }

    private function ai(int $coins = 20): void
    {
        $result = $this->planResult();
        $this->mock(OpenAiService::class, function ($m) use ($coins, $result) {
            $m->shouldReceive('estimateChatCoins')->andReturn($coins);
            $m->shouldReceive('chat')->once()->andReturn(['content' => json_encode($result), 'credits_spent' => 5, 'model' => 'test']);
        });
    }

    public function test_tasks_create_real_checklists_once_and_folder_contains_other_resources(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $folder = Project::create(['user_id' => $u->id, 'name' => 'Lunch']);
        $p->update(['project_id' => $folder->id]);
        $this->post('/user/ai/planner/plans/'.$p->id.'/tasks', ['indices' => [0], 'due_date' => '2026-10-21', 'assignee_id' => $u->id])->assertRedirect();
        $card = TaskCard::latest('id')->first();
        $this->assertSame(2, $card->subtasks()->count());
        $this->assertTrue($card->assignees()->where('user_id', $u->id)->exists());
        $this->post('/user/ai/planner/plans/'.$p->id.'/tasks', ['indices' => [0]])->assertRedirect();
        $this->assertDatabaseCount('task_cards', 1);
        $page = $this->get('/user/projects/'.$folder->id)->assertOk()->assertSee('Folder workspace')->assertSee('Confirm delivery radius');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/planner-folder.html', $page->getContent());
        }
    }

    public function test_folder_links_and_reports_share_the_workspace_shell(): void
    {
        $u = $this->owner();
        $folder = Project::create(['user_id' => $u->id, 'name' => 'Unified marketing']);
        Link::create(['user_id' => $u->id, 'project_id' => $folder->id, 'title' => 'Campaign launch', 'type' => 'biolink', 'alias' => 'unified-launch']);
        $other = Project::create(['user_id' => $u->id, 'name' => 'Other folder']);
        $outside = Link::create(['user_id' => $u->id, 'project_id' => $other->id, 'title' => 'Outside folder item', 'type' => 'biolink', 'alias' => 'outside-folder']);
        foreach (['overview', 'links', 'reports', 'tasks', 'resources'] as $tab) {
            $page = $this->get('/user/projects/'.$folder->id.'?tab='.$tab)->assertOk()->assertSee('Folder workspace')->assertSee('YOUR FOLDER WORKSPACE');
            $page->assertSee('aria-label="Folder workspace"', false);
            if ($tab === 'links') {
                $page->assertSee('Campaign launch')->assertDontSee('data-link-id="'.$outside->id.'"', false);
            }
            if ($tab === 'reports') {
                $page->assertSee('Task progress')->assertSee('Form submissions')->assertSee('rp-from');
            }
            if (getenv('FORMS_UI_FIXTURE')) {
                file_put_contents('/tmp/unified-folder-'.$tab.'.html', $page->getContent());
            }
        }
        $this->get('/user/links?project_id='.$folder->id)->assertOk()->assertSee('YOUR FOLDER WORKSPACE')->assertDontSee('Open links &amp; reports', false);
        $this->get('/user/links')->assertOk()->assertDontSee('YOUR FOLDER WORKSPACE');
    }

    public function test_notes_are_private_shared_explicitly_and_snooze_hides_context(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $link = Link::create(['user_id' => $u->id, 'title' => 'Menu', 'type' => 'biolink', 'alias' => 'notes-context', 'is_active' => true]);
        $this->post('/user/ai/planner/notes', ['body' => 'Confirm coverage', 'plan_id' => $p->id, 'resource' => 'link:'.$link->id, 'pinned' => 1])->assertRedirect();
        $note = PlannerNote::first();
        $this->assertFalse($note->shared);
        $this->get('/user/links/'.$link->id.'/blocks')->assertOk()->assertSee('Remember here')->assertSee('Confirm coverage');
        $this->post('/user/ai/planner/notes/'.$note->id, ['action' => 'snooze'])->assertRedirect();
        $this->get('/user/links/'.$link->id.'/blocks')->assertOk()->assertDontSee('Confirm coverage');
    }

    public function test_cross_workspace_resources_and_private_plans_are_rejected(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $foreign = User::factory()->create();
        $link = Link::create(['user_id' => $foreign->id, 'title' => 'Private', 'type' => 'biolink', 'alias' => 'private-source']);
        $this->postJson('/user/ai/planner/plans/'.$p->id.'/settings', array_replace($this->settings(), ['resources' => ['link:'.$link->id]]))->assertNotFound();
        $this->owner();
        $this->postJson('/user/ai/planner/plans/'.$p->id.'/estimate')->assertNotFound();
    }

    public function test_background_run_is_single_use_and_results_require_review(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $this->ai();
        $run = PlannerRun::create(['plan_id' => $p->id, 'run_key' => 'run-once', 'trigger' => 'scheduled']);
        (new RunAiPlanner($run->id))->handle();
        $this->assertSame('needs_review', $run->fresh()->status);
        $this->assertSame(5, $run->fresh()->coins_spent);
        $this->assertSame('Lunch plan', $p->fresh()->title);
        (new RunAiPlanner($run->id))->handle();
        $this->post('/user/ai/planner/plans/'.$p->id.'/runs/'.$run->id.'/apply')->assertRedirect();
        $this->assertSame('Grow office lunches', $p->fresh()->title);
    }

    public function test_budget_stops_generation_and_pauses_background_work(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $p->update(['planner_settings' => array_replace($this->settings(), ['run_budget' => 5])]);
        $this->mock(OpenAiService::class, function ($m) {
            $m->shouldReceive('estimateChatCoins')->andReturn(20);
            $m->shouldNotReceive('chat');
        });
        $run = PlannerRun::create(['plan_id' => $p->id, 'run_key' => 'budget', 'trigger' => 'scheduled']);
        (new RunAiPlanner($run->id))->handle();
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertFalse($p->fresh()->automation_enabled);
        $this->assertSame(0, $run->fresh()->coins_spent);
    }

    public function test_scheduler_deduplicates_and_respects_pause(): void
    {
        Queue::fake();
        $u = $this->owner();
        $p = $this->plan($u);
        $this->artisan('planner:dispatch')->assertSuccessful();
        $this->artisan('planner:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('planner_runs', 1);
        Queue::assertPushed(RunAiPlanner::class, 1);
        $this->assertNotNull(Cache::store('database')->get('planner:scheduler-heartbeat'));
        $this->post('/user/ai/planner/plans/'.$p->id.'/pause')->assertRedirect();
        $p->update(['next_run_at' => now()->subMinute()]);
        $this->artisan('planner:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('planner_runs', 1);
    }

    public function test_selected_form_triggers_review_without_sending_answers(): void
    {
        Queue::fake();
        $u = $this->owner();
        $p = $this->plan($u);
        $form = $u->forms()->create(['title' => 'Lunch survey', 'slug' => 'trigger-survey', 'fields' => []]);
        $p->update(['planner_settings' => array_replace($this->settings(), ['trigger' => 'form_submission', 'resources' => [['kind' => 'form', 'id' => $form->id]]])]);
        FormSubmission::create(['form_id' => $form->id, 'data' => ['private' => 'Do not expose this answer'], 'is_spam' => false]);
        $this->assertDatabaseCount('planner_runs', 1);
        Queue::assertPushed(RunAiPlanner::class);
    }

    public function test_ai_handoff_and_capture_keeps_results_on_plan(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $this->post('/user/ai/planner/plans/'.$p->id.'/handoff', ['tool' => 'coach', 'task_index' => 0])->assertRedirect(route('user.ai.ask-coach.show'));
        $h = PlannerHandoff::first();
        $this->assertStringContainsString('Confirm delivery radius', $h->brief);
        $this->post('/user/ai/planner/capture', ['handoff_id' => $h->id, 'result' => 'Review local office demand.'])->assertRedirect();
        $this->assertSame('Review local office demand.', $h->fresh()->result);
    }

    public function test_planner_tabs_and_folder_multiselect_render(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $page = $this->get('/user/ai/mind?record='.$p->id)->assertOk()->assertSee('Goal, context')->assertSee('AI Staff');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/planner-plan.html', $page->getContent());
        }
    }

    public function test_settings_keep_numeric_selections_and_manual_quote_is_single_use(): void
    {
        Queue::fake();
        $u = $this->owner();
        $p = $this->plan($u);
        $staff = AiStaff::create(['user_id' => $u->id, 'name' => 'Lunch specialist', 'domain' => 'general']);
        $this->post('/user/ai/planner/plans/'.$p->id.'/settings', array_replace($this->settings(), ['staff_id' => (string) $staff->id, 'weekday' => '3', 'run_budget' => '120', 'daily_budget' => '500', 'monthly_budget' => '3000']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $p->fresh()->planner_settings['staff_id']);
        $this->assertSame(3, $p->fresh()->planner_settings['weekday']);
        $p->refresh()->update(['planner_settings' => $this->settings()]);
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturn(20));
        $q = $this->postJson('/user/ai/planner/plans/'.$p->id.'/estimate')->assertOk()->json();
        $this->postJson('/user/ai/planner/plans/'.$p->id.'/run', ['token' => $q['token']])->assertOk();
        $this->postJson('/user/ai/planner/plans/'.$p->id.'/run', ['token' => $q['token']])->assertStatus(422);
        $this->assertDatabaseCount('planner_runs', 1);
    }

    public function test_background_daily_budget_and_disabled_staff_prevent_ai_calls(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $p->update(['planner_settings' => array_replace($this->settings(), ['run_budget' => 20, 'daily_budget' => 20])]);
        PlannerRun::create(['plan_id' => $p->id, 'run_key' => 'prior-spend', 'status' => 'needs_review', 'coins_spent' => 15]);
        $this->mock(OpenAiService::class, function ($m) {
            $m->shouldReceive('estimateChatCoins')->andReturn(20);
            $m->shouldNotReceive('chat');
        });
        $run = PlannerRun::create(['plan_id' => $p->id, 'run_key' => 'daily-stop', 'trigger' => 'scheduled']);
        (new RunAiPlanner($run->id))->handle();
        $this->assertSame('failed', $run->fresh()->status);
        $staff = AiStaff::create(['user_id' => $u->id, 'name' => 'Disabled', 'domain' => 'general', 'is_disabled' => true]);
        $p->update(['planner_settings' => array_replace($this->settings(), ['staff_id' => $staff->id])]);
        $this->postJson('/user/ai/planner/plans/'.$p->id.'/estimate')->assertStatus(422);
    }

    public function test_invalid_run_refunds_and_applied_revision_can_be_restored(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $this->mock(AiUsageCharger::class, function ($m) {
            $m->shouldReceive('getBalance')->andReturn(10000);
            $m->shouldReceive('refund')->once()->withArgs(fn ($u, $coins, $opts) => $coins === 5 && isset($opts['idempotency_key']))->andReturn(new WalletTransaction);
        });
        $this->mock(OpenAiService::class, function ($m) {
            $m->shouldReceive('estimateChatCoins')->andReturn(20);
            $m->shouldReceive('chat')->once()->andReturn(['content' => 'not JSON', 'credits_spent' => 5, 'model' => 'test']);
        });
        $run = PlannerRun::create(['plan_id' => $p->id, 'run_key' => 'refund', 'trigger' => 'scheduled']);
        (new RunAiPlanner($run->id))->handle();
        $this->assertSame(0, $run->fresh()->coins_spent);
        $this->assertSame('failed', $run->fresh()->status);
        $run = PlannerRun::create(['plan_id' => $p->id, 'run_key' => 'apply', 'status' => 'needs_review', 'result' => array_replace($this->planResult(), ['title' => 'Updated plan'])]);
        $this->post('/user/ai/planner/plans/'.$p->id.'/runs/'.$run->id.'/apply')->assertRedirect();
        $this->post('/user/ai/planner/plans/'.$p->id.'/runs/'.$run->id.'/revert')->assertRedirect();
        $this->assertSame('Lunch plan', $p->fresh()->title);
        $this->assertSame('reverted', $run->fresh()->status);
    }

    public function test_memory_draft_is_saved_without_learning_or_duplicate_sources(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $mind = AiMind::create(['user_id' => $u->id, 'name' => 'Lunch knowledge']);
        Queue::fake();
        $this->post('/user/ai/planner/plans/'.$p->id.'/memory', ['mind_id' => $mind->id])->assertRedirect();
        $this->post('/user/ai/planner/plans/'.$p->id.'/memory', ['mind_id' => $mind->id])->assertRedirect();
        $this->assertDatabaseCount('ai_mind_sources', 1);
        $this->assertSame('disabled', AiMindSource::first()->status);
        Queue::assertNotPushed(IngestAiMindSourceJob::class);
    }

    public function test_staff_review_uses_staff_identity_and_permissions(): void
    {
        $u = $this->owner();
        $plan = Plan::create(['name' => 'Staff enabled', 'slug' => 'staff-test', 'status' => 'active', 'features' => ['ai_staff_general' => true]]);
        $u->update(['plan_id' => $plan->id]);
        $u->unsetRelation('plan');
        $p = $this->plan($u);
        $staff = AiStaff::create(['user_id' => $u->id, 'name' => 'Lunch specialist', 'domain' => 'general']);
        $p->update(['planner_settings' => array_replace($this->settings(), ['staff_id' => $staff->id])]);
        $this->mock(AiStaffRuntime::class, fn ($m) => $m->shouldReceive('systemPrompt')->once()->withArgs(fn ($s) => $s->id === $staff->id)->andReturn('You are the lunch specialist.'));
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturn(20));
        $quote = app(PlannerEngine::class)->prepare($p, $u);
        $this->assertSame('ai_staff_general', $quote['feature']);
        $this->assertStringContainsString('lunch specialist', $quote['messages'][0]['content']);
    }

    public function test_shared_plan_and_reminders_respect_workspace_roles(): void
    {
        $owner = $this->owner();
        $ws = app('current_workspace');
        $p = $this->plan($owner);
        $private = PlannerNote::create(['user_id' => $owner->id, 'workspace_id' => $ws->id, 'plan_id' => $p->id, 'body' => 'Private detail']);
        $shared = PlannerNote::create(['user_id' => $owner->id, 'workspace_id' => $ws->id, 'plan_id' => $p->id, 'body' => 'Team detail', 'shared' => true]);
        $viewer = User::factory()->create();
        WorkspaceMember::create(['workspace_id' => $ws->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $this->actingAs($viewer);
        app(WorkspaceContext::class)->set($ws);
        $this->get('/user/ai/mind?record='.$p->id)->assertNotFound();
        $p->update(['shared' => true]);
        $this->get('/user/ai/mind?record='.$p->id)->assertOk()->assertSee('Team detail')->assertDontSee('Private detail');
        $this->postJson('/user/ai/planner/plans/'.$p->id.'/tasks', ['indices' => [0]])->assertForbidden();
        $this->postJson('/user/ai/planner/notes/'.$private->id, ['action' => 'done'])->assertForbidden();
    }

    public function test_native_ai_spend_is_attributed_only_to_matching_handoff(): void
    {
        $u = $this->owner();
        $p = $this->plan($u);
        $h = PlannerHandoff::create(['user_id' => $u->id, 'workspace_id' => $p->workspace_id, 'plan_id' => $p->id, 'tool' => 'coach', 'brief' => 'Lunch question']);
        $previous = app('request');
        $request = Request::create('/user/ai/ask-coach', 'POST');
        $request->setLaravelSession(session()->driver());
        $request->session()->put('planner_handoff', $h->id);
        app()->instance('request', $request);
        try {
            $wallet = app(WalletService::class);
            $wallet->credit($u, 100, ['reason' => 'test']);
            $charger = new AiUsageCharger($wallet);
            $tx = $charger->charge($u, 5, ['feature' => 'ask_coach']);
            $this->assertSame($h->id, $tx->meta['planner_handoff_id']);
            $tx = $charger->charge($u, 1, ['feature' => 'persona']);
            $this->assertArrayNotHasKey('planner_handoff_id', $tx->meta);
            $h->update(['closed_at' => now()]);
            $tx = $charger->charge($u, 1, ['feature' => 'ask_coach']);
            $this->assertArrayNotHasKey('planner_handoff_id', $tx->meta);
        } finally {
            app()->instance('request', $previous);
        }
    }
}
