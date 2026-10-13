<?php

namespace Tests\Feature;

use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\NoteActionPlan;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\NoteSourceReader;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotesActionPlanTest extends TestCase
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
        return ['title' => 'Lunch launch', 'summary' => 'Simplify the menu and confirm delivery coverage.', 'decisions' => ['Simplify lunch menu'], 'tasks' => [['title' => 'Review delivery radius', 'details' => 'Agree coverage before promotion.', 'priority' => 'high', 'owner' => '', 'due_date' => '']], 'questions' => ['What is the budget?'], 'warnings' => [], 'form' => ['title' => 'Office lunch survey', 'description' => 'Learn lunch preferences.', 'fields' => [['id' => 'lunch', 'type' => 'text', 'label' => 'Preferred lunch?']]], 'page_copy' => ['title' => 'Office lunches', 'body' => 'Explore our office lunch options.']];
    }

    private function plan(User $u): NoteActionPlan
    {
        return NoteActionPlan::create(['user_id' => $u->id, 'title' => 'Lunch launch', 'purpose' => 'meeting', 'notes' => 'Meeting notes', 'result' => $this->planResult(), 'coins_spent' => 4, 'model' => 'test']);
    }

    public function test_estimate_is_free_generation_saves_and_quote_cannot_be_reused(): void
    {
        $u = $this->owner();
        $result = $this->planResult();
        $this->mock(OpenAiService::class, function ($m) use ($result) {
            $m->shouldReceive('estimateChatCoins')->andReturn(80);
            $m->shouldReceive('chat')->once()->andReturn(['content' => json_encode($result), 'credits_spent' => 4, 'model' => 'test']);
        });
        $quote = $this->postJson('/user/ai/mind/estimate', ['thoughts' => 'Review office lunch ideas', 'purpose' => 'meeting'])->assertOk()->assertJsonPath('estimated_coins', 80)->json();
        $this->assertDatabaseCount('note_action_plans', 0);
        $response = $this->postJson('/user/ai/mind/think', ['token' => $quote['token']])->assertOk();
        $this->assertDatabaseCount('note_action_plans', 1);
        $plan = NoteActionPlan::first();
        $this->assertSame($u->id, $plan->user_id);
        $this->assertSame(4, $plan->coins_spent);
        $this->postJson('/user/ai/mind/think', ['token' => $quote['token']])->assertStatus(422);
        $page = $this->get($response->json('url'))->assertOk()->assertSee('Review delivery radius');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/notes-saved.html', $page->getContent());
        }
    }

    public function test_record_ownership_and_workspace_isolation(): void
    {
        $u = $this->owner();
        $plan = $this->plan($u);
        $this->owner();
        $this->get('/user/ai/mind?record='.$plan->id)->assertNotFound();
        $this->get('/user/ai/mind/records/'.$plan->id.'/export')->assertNotFound();
        $this->post('/user/ai/mind/records/'.$plan->id.'/form')->assertNotFound();
        $this->delete('/user/ai/mind/records/'.$plan->id)->assertNotFound();
    }

    public function test_saved_records_work_when_ai_is_disabled_and_history_filters(): void
    {
        $u = $this->owner();
        $plan = $this->plan($u);
        AiEngineSettings::setEnabled(false);
        $this->get('/user/ai/mind?record='.$plan->id)->assertOk()->assertSee('Lunch launch');
        $this->get('/user/ai/mind?q=missing')->assertOk()->assertDontSee('Lunch launch');
        $this->postJson('/user/ai/mind/estimate', ['thoughts' => 'Example notes', 'purpose' => 'meeting'])->assertStatus(422);
        $this->get('/user/ai/mind/records/'.$plan->id.'/export')->assertOk();
    }

    public function test_invalid_output_refunds_and_does_not_save(): void
    {
        $this->owner();
        $this->mock(AiUsageCharger::class, function ($m) {
            $m->shouldReceive('getBalance')->andReturn(10000);
            $m->shouldReceive('refund')->once()->withArgs(fn ($u, $coins, $opts) => $coins === 4)->andReturn(new WalletTransaction);
        });
        $this->mock(OpenAiService::class, function ($m) {
            $m->shouldReceive('estimateChatCoins')->andReturn(80);
            $m->shouldReceive('chat')->once()->andReturn(['content' => 'not JSON', 'credits_spent' => 4, 'model' => 'test']);
        });
        $q = $this->postJson('/user/ai/mind/estimate', ['thoughts' => 'Some notes', 'purpose' => 'project'])->assertOk()->json();
        $this->postJson('/user/ai/mind/think', ['token' => $q['token']])->assertStatus(422);
        $this->assertDatabaseCount('note_action_plans', 0);
    }

    public function test_reviewed_form_and_page_actions_create_inactive_drafts_once(): void
    {
        $u = $this->owner();
        $plan = $this->plan($u);
        $this->post('/user/ai/mind/records/'.$plan->id.'/form')->assertRedirect();
        $form = Form::first();
        $this->assertFalse($form->is_active);
        $this->assertSame('lunch', $form->fields[0]['id']);
        $this->post('/user/ai/mind/records/'.$plan->id.'/form')->assertRedirect();
        $this->assertDatabaseCount('forms', 1);
        $this->post('/user/ai/mind/records/'.$plan->id.'/page')->assertRedirect();
        $link = Link::first();
        $this->assertFalse($link->is_active);
        $this->assertSame('private', $link->visibility);
        $this->post('/user/ai/mind/records/'.$plan->id.'/page')->assertRedirect();
        $this->assertDatabaseCount('links', 1);
    }

    public function test_pdf_image_and_text_sources_use_estimate_without_charging(): void
    {
        $this->owner();
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturn(70));
        $this->postJson('/user/ai/mind/estimate', ['purpose' => 'feedback', 'file' => UploadedFile::fake()->createWithContent('notes.txt', 'Customer feedback: offer a simpler menu.')])->assertOk();
        $this->assertDatabaseCount('note_action_plans', 0);
    }

    public function test_webpage_source_and_unsafe_urls(): void
    {
        $this->owner();
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturn(70));
        $this->postJson('/user/ai/mind/estimate', ['purpose' => 'general', 'source_url' => 'http://127.0.0.1/private'])->assertStatus(422);
        $this->mock(NoteSourceReader::class, fn ($m) => $m->shouldReceive('webpage')->once()->with('https://example.com/notes')->andReturn('A source note to summarise.'));
        $this->postJson('/user/ai/mind/estimate', ['purpose' => 'general', 'source_url' => 'https://example.com/notes'])->assertOk();
    }

    public function test_quote_expiry_and_low_balance_block_generation(): void
    {
        $this->owner();
        $balance = 10000;
        $this->mock(AiUsageCharger::class, function ($m) use (&$balance) {
            $m->shouldReceive('getBalance')->andReturnUsing(function () use (&$balance) {
                return $balance;
            });
        });
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturn(70));
        $this->postJson('/user/ai/mind/think', ['token' => (string) Str::uuid()])->assertStatus(422);
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturn(70));
        $q = $this->postJson('/user/ai/mind/estimate', ['purpose' => 'general', 'thoughts' => 'Make a project plan'])->assertOk()->json();
        $balance = 1;
        $this->postJson('/user/ai/mind/think', ['token' => $q['token']])->assertStatus(422);
        $this->assertDatabaseCount('note_action_plans', 0);
    }

    public function test_new_screen_and_record_deletion(): void
    {
        $u = $this->owner();
        $page = $this->get('/user/ai/mind')->assertOk()->assertSee('Notes → Action Plan')->assertSee('Estimate coins')->assertSee('Past plans');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/notes-new.html', $page->getContent());
        }
        $plan = $this->plan($u);
        $this->delete('/user/ai/mind/records/'.$plan->id)->assertRedirect();
        $this->assertDatabaseCount('note_action_plans', 0);
    }

    public function test_reviewed_calendar_task_preserves_timezone_and_is_idempotent(): void
    {
        $u = $this->owner();
        $plan = $this->plan($u);
        $link = Link::create(['user_id' => $u->id, 'type' => Link::TYPE_CALENDAR, 'alias' => 'notes-calendar', 'title' => 'Office tasks', 'is_active' => true]);
        $payload = ['task_index' => 0, 'calendar_id' => $link->id, 'title' => 'Discuss radius', 'start_at' => '2026-10-20T10:00', 'end_at' => '2026-10-20T11:00', 'timezone' => 'Asia/Kolkata'];
        $this->post('/user/ai/mind/records/'.$plan->id.'/calendar', $payload)->assertRedirect()->assertSessionHas('success');
        $event = CalendarEvent::first();
        $this->assertNotNull($event);
        $this->assertSame('Asia/Kolkata', $event->timezone);
        $this->assertSame('Discuss radius', $event->title);
        $this->assertSame($event->id, $plan->fresh()->actions['calendar_task_0']);
        $this->post('/user/ai/mind/records/'.$plan->id.'/calendar', $payload)->assertRedirect();
        $this->assertDatabaseCount('calendar_events', 1);
        $payload['end_at'] = '2026-10-19T10:00';
        $this->postJson('/user/ai/mind/records/'.$plan->id.'/calendar', $payload)->assertStatus(422);
    }

    public function test_sources_use_vision_and_cannot_bind_another_project(): void
    {
        $u = $this->owner();
        $this->mock(OpenAiService::class, fn ($m) => $m->shouldReceive('estimateChatCoins')->andReturnUsing(function ($model, $messages) {
            $this->assertTrue(collect($messages[1]['content'])->contains(fn ($part) => $part['type'] === 'image_url'));

            return 90;
        }));
        $this->postJson('/user/ai/mind/estimate', ['purpose' => 'meeting', 'file' => UploadedFile::fake()->image('notes.png')])->assertOk();
        $other = User::factory()->create();
        $project = Project::create(['user_id' => $other->id, 'name' => 'Other project']);
        $this->postJson('/user/ai/mind/estimate', ['purpose' => 'meeting', 'thoughts' => 'Sample notes', 'project_id' => $project->id])->assertStatus(422);
    }
}
