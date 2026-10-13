<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Controllers\FormAiController;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\User;
use App\Modules\User\Services\FormResponseAnalytics;
use App\Modules\User\Services\FormStyleCatalog;
use App\Modules\User\Services\FormVisitTracker;
use App\Modules\User\Services\FormWebhookDispatcher;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormsExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $plan = Plan::create(['name' => 'Reports', 'slug' => 'reports-'.Str::random(8), 'status' => 'active', 'monthly_price' => 0, 'annual_price' => 0, 'features' => ['form_analytics_advanced' => true]]);
        $u = User::factory()->create(['plan_id' => $plan->id]);
        $this->actingAs($u);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($u));
        app()->instance('workspace_owner', $u);

        return $u;
    }

    private function form(User $u): Form
    {
        $f = new Form(['title' => 'Club survey', 'slug' => 'survey-'.$u->id, 'fields' => [['id' => 'join', 'type' => 'radio', 'label' => 'Join?', 'options' => ['Yes', 'No']], ['id' => 'reason', 'type' => 'text', 'label' => 'Why?', 'required' => true, 'show_if' => ['field' => 'join', 'operator' => 'equals', 'value' => 'Yes']], ['id' => 'rating', 'type' => 'number', 'label' => 'Score']], 'settings' => Form::defaultSettings(), 'design' => Form::defaultDesign(), 'notifications' => Form::defaultNotifications(), 'is_active' => true]);
        $f->user_id = $u->id;
        $f->save();

        return $f;
    }

    public function test_editor_tabs_and_real_preview_render_without_saving(): void
    {
        $f = $this->form($this->owner());
        foreach (['', '/builder', '/design', '/notifications', '/payment', '/settings', '/embed', '/reports'] as $suffix) {
            $response = $this->get('/user/forms/'.$f->id.$suffix)->assertOk();
            if (getenv('FORMS_UI_FIXTURE')) {
                file_put_contents('/tmp/forms-'.($suffix ? trim($suffix, '/') : 'overview').'.html', $response->getContent());
            }
        }
        $preview = $this->post('/user/forms/'.$f->id.'/preview', ['title' => 'Unsaved title', 'fields' => [['id' => 'choice', 'type' => 'radio', 'label' => 'Choose a color', 'options' => ['Blue', 'Red']]], 'design' => ['accent' => '#db2777']])->assertOk()->assertSee('Choose a color')->assertSee('Blue')->assertSee('Preview only');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/forms-preview.html', $preview->getContent());
        }
        $this->assertEquals('Club survey', $f->fresh()->title);
        $this->assertEquals(0, $f->fresh()->total_views);
    }

    public function test_report_filters_numeric_values_and_excludes_spam_and_pending(): void
    {
        $f = $this->form($this->owner());
        foreach ([['Yes', 4, false, 'none'], ['No', 8, false, 'none'], ['Yes', 90, true, 'none'], ['Yes', 100, false, 'pending']] as [$join,$rating,$spam,$payment]) {
            $f->submissions()->create(['data' => compact('join', 'rating'), 'is_spam' => $spam, 'payment_status' => $payment, 'created_at' => now()]);
        }$a = app(FormResponseAnalytics::class)->build($f, now()->timezone($f->user->timezone)->subDay()->toDateString(), now()->timezone($f->user->timezone)->toDateString(), [], ['join' => 'Yes']);
        $this->assertEquals(1, $a['total']);
        $this->assertEquals(4, collect($a['reports'])->firstWhere('id', 'rating')['numeric']['sum']);
        $this->get('/user/forms/'.$f->id.'/reports?export=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_foreign_form_cannot_be_previewed_or_reported(): void
    {
        $u = $this->owner();
        $f = $this->form(User::factory()->create());
        $this->post('/user/forms/'.$f->id.'/preview', [])->assertForbidden();
        $this->get('/user/forms/'.$f->id.'/reports')->assertForbidden();
    }

    public function test_report_layout_round_trip(): void
    {
        $f = $this->form($this->owner());
        $this->post('/user/forms/'.$f->id.'/reports', ['name' => 'Scores', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString(), 'chart' => 'table', 'fields' => ['rating']])->assertRedirect();
        $this->assertEquals(['rating'], $f->fresh()->settings['saved_reports'][0]['fields']);
    }

    public function test_conditional_required_answers_and_schema_preservation(): void
    {
        Queue::fake();
        $f = $this->form($this->owner());
        $fields = $f->fields;
        $this->postJson('/f/'.$f->slug, ['join' => 'No', 'reason' => 'Hidden forged value', 'rating' => 3])->assertOk();
        $this->assertEquals($fields, $f->fresh()->fields);
        $this->assertArrayNotHasKey('reason', $f->submissions()->first()->data);
        $this->postJson('/f/'.$f->slug, ['join' => 'Yes', 'rating' => 3])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_signed_visits_scope_completion_and_bot_exclusion(): void
    {
        $f = $this->form($this->owner());
        $tracker = app(FormVisitTracker::class);
        $r = Request::create('/f/'.$f->slug);
        $r->headers->set('User-Agent', 'Mozilla/5.0');
        $token = $tracker->create($f, $r);
        $this->assertNotNull($token);
        $this->postJson('/f/'.$f->slug.'/event', ['token' => $token, 'page' => 2])->assertOk();
        $tracker->complete($f, $token);
        $tracker->complete($f, $token);
        $this->assertEquals(1, DB::table('form_visits')->whereNotNull('completed_at')->count());
        $foreign = $this->form(User::factory()->create());
        $this->assertNull($tracker->resolve($foreign, $token));
        $this->assertNull($tracker->resolve($f, $token.'x'));
        $r->headers->set('User-Agent', 'Googlebot');
        $this->assertNull($tracker->create($f, $r));
    }

    public function test_ai_edits_preserve_existing_metadata(): void
    {
        $existing = [['id' => 'join', 'type' => 'radio', 'label' => 'Join', 'options' => ['Yes', 'No'], 'option_prices' => [100, 200], 'required' => true, 'show_if' => ['field' => 'x', 'value' => 'Y']]];
        $a = app(FormAiController::class)->normalize(['fields' => [['id' => 'join', 'type' => 'radio', 'label' => 'Ready to join?']]], $existing);
        $this->assertEquals([100, 200], $a['fields'][0]['option_prices']);
        $this->assertEquals(['Yes', 'No'], $a['fields'][0]['options']);
        $this->assertTrue($a['fields'][0]['required']);
        $existing[] = ['id' => 'keep', 'type' => 'text', 'label' => 'Keep this'];
        $a = app(FormAiController::class)->normalize(['fields' => [['id' => 'join', 'type' => 'radio', 'label' => 'New label']]], $existing);
        $this->assertEquals('keep', $a['fields'][1]['id']);
    }

    public function test_webhook_rejects_private_endpoint_and_records_failure(): void
    {
        $f = $this->form($this->owner());
        Http::fake();
        $result = app(FormWebhookDispatcher::class)->send($f, ['url' => 'http://127.0.0.1/private'], [], 0, true);
        $this->assertFalse($result['success']);
        Http::assertNothingSent();
    }

    public function test_ai_estimates_are_bound_single_use_and_do_not_publish(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $f = $this->form($this->owner());
        $this->mock(AiUsageCharger::class, function ($mock) {
            $mock->shouldReceive('getBalance')->andReturn(1000);
        });
        $this->mock(OpenAiService::class, function ($mock) {
            $mock->shouldReceive('estimateChatCoins')->andReturn(20);
            $mock->shouldReceive('chat')->once()->andReturn(['content' => json_encode(['title' => 'AI draft', 'fields' => [['id' => 'join', 'type' => 'radio', 'label' => 'Ready?', 'options' => ['Yes', 'No']]]]), 'credits_spent' => 2]);
        });
        $quote = $this->postJson('/user/forms/'.$f->id.'/ai/estimate', ['instruction' => 'Improve these questions', 'mode' => 'edit'])->assertOk()->assertJsonPath('estimated_coins', 20)->json('token');
        $this->postJson('/user/forms/'.$f->id.'/ai/generate', ['token' => $quote])->assertOk()->assertJsonPath('title', 'AI draft')->assertJsonPath('coins_spent', 2);
        $this->assertEquals('Club survey', $f->fresh()->title);
        $this->postJson('/user/forms/'.$f->id.'/ai/generate', ['token' => $quote])->assertUnprocessable();
    }

    public function test_repeated_answers_have_response_completion_and_entry_totals(): void
    {
        $f = $this->form($this->owner());
        $f->update(['fields' => [['id' => 'guests', 'type' => 'section', 'repeatable' => true], ['id' => 'age', 'parent' => 'guests', 'type' => 'number', 'label' => 'Age']]]);
        $f->submissions()->create(['data' => ['guests' => ['_repeatable_group' => true, 'copies' => [['age' => 10], ['age' => 20]]]], 'is_spam' => false, 'payment_status' => 'none']);
        $a = app(FormResponseAnalytics::class)->build($f, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $field = $a['reports'][0];
        $this->assertEquals(100, $field['rate']);
        $this->assertEquals(2, $field['entries']);
        $this->assertEquals(30, $field['numeric']['sum']);
    }

    public function test_translated_labels_and_success_keep_answer_values(): void
    {
        Queue::fake();
        $f = $this->form($this->owner());
        $this->put('/user/forms/'.$f->id.'/settings', ['captcha_provider' => 'honeypot', 'allow_resume' => true, 'translations_json' => json_encode(['hi' => ['title' => 'Registration', 'button_label' => 'Send', 'success_message' => 'Welcome', 'fields' => ['join' => ['label' => 'Join the club']]]])])->assertRedirect();
        $f->refresh();
        $this->assertTrue($f->settings['allow_resume']);
        $this->get('/f/'.$f->slug.'?lang=hi')->assertOk()->assertSee('Join the club')->assertSee('Save my answers');
        $this->postJson('/f/'.$f->slug, ['_locale' => 'hi', 'join' => 'No'])->assertOk()->assertJsonPath('message', 'Welcome');
        $this->assertEquals('No', $f->submissions()->first()->data['join']);
        $this->put('/user/forms/'.$f->id.'/settings', ['captcha_provider' => 'honeypot', 'translations_json' => '123'])->assertSessionHasErrors('translations_json');
    }

    public function test_preview_choice_cards_and_report_layout_removal(): void
    {
        $f = $this->form($this->owner());
        $this->post('/user/forms/'.$f->id.'/preview', ['design' => ['choice_style' => 'cards']])->assertOk()->assertSee('label:has(input:checked)', false);
        $this->post('/user/forms/'.$f->id.'/reports', ['name' => 'Remove me', 'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString(), 'chart' => 'bars'])->assertRedirect();
        $this->delete('/user/forms/'.$f->id.'/reports/0')->assertRedirect();
        $this->assertEquals([], $f->fresh()->settings['saved_reports']);
    }

    public function test_detailed_reports_preserve_existing_plan_gate(): void
    {
        $u = $this->owner();
        $f = $this->form($u);
        $u->update(['plan_id' => null]);
        $u->unsetRelation('plan');
        app()->instance('workspace_owner', $u->fresh());
        $this->get('/user/forms/'.$f->id.'/reports')->assertRedirect(route('user.upgrade'));
        $this->post('/user/forms/'.$f->id.'/reports', ['name' => 'Blocked'])->assertForbidden();
    }

    public function test_composite_empty_answers_and_rank_positions_are_measured_correctly(): void
    {
        $f = $this->form($this->owner());
        $f->update(['fields' => [['id' => 'name', 'type' => 'full_name', 'label' => 'Name'], ['id' => 'rank', 'type' => 'ranking', 'label' => 'Preference']]]);
        $f->submissions()->create(['data' => ['name' => ['first' => '', 'last' => ''], 'rank' => ['A', 'B']], 'payment_status' => 'none', 'is_spam' => false]);
        $f->submissions()->create(['data' => ['name' => ['first' => 'Alex', 'last' => ''], 'rank' => ['B', 'A']], 'payment_status' => 'none', 'is_spam' => false]);
        $a = app(FormResponseAnalytics::class)->build($f, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertEquals(50, $a['reports'][0]['rate']);
        $this->assertEquals(1.5, $a['reports'][1]['ranking']['A']);
    }

    public function test_image_estimate_uses_vision_without_a_generation_call(): void
    {
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
        $f = $this->form($this->owner());
        $this->mock(AiUsageCharger::class, fn ($mock) => $mock->shouldReceive('getBalance')->andReturn(1000));
        $this->mock(OpenAiService::class, function ($mock) {
            $mock->shouldReceive('estimateChatCoins')->withArgs(function ($model, $messages, $max, $actor) {
                $this->assertStringStartsWith('data:image/jpeg;base64,', $messages[1]['content'][1]['image_url']['url']);

                return true;
            })->andReturn(20);
            $mock->shouldNotReceive('chat');
        });
        $this->postJson('/user/forms/'.$f->id.'/ai/estimate', ['instruction' => 'Read the handwritten questions', 'mode' => 'create', 'file' => UploadedFile::fake()->image('questions.png', 100, 200)])->assertOk();
    }

    public function test_style_catalog_and_new_design_controls_round_trip(): void
    {
        $f = $this->form($this->owner());
        $this->assertGreaterThanOrEqual(25, count(FormStyleCatalog::all()));
        $data = array_merge(Form::defaultDesign(), ['surface_style' => 'liquid', 'label_style' => 'floating', 'character_boxes' => true, 'layout' => 'grid3']);
        $this->put('/user/forms/'.$f->id.'/design', $data)->assertSessionHasNoErrors();
        $this->assertEquals('liquid', $f->fresh()->design['surface_style']);
        $this->assertEquals('grid3', $f->fresh()->design['layout']);
        $preview = $this->post('/user/forms/'.$f->id.'/preview', ['design' => $data])->assertOk();
        $preview->assertSee('surface-liquid')->assertSee('data-label-style="floating"', false)->assertSee('data-character-boxes="1"', false)->assertSee('layout-grid3');
        if (getenv('FORMS_UI_FIXTURE')) {
            file_put_contents('/tmp/forms-style-preview.html', $preview->getContent());
        }
    }

    public function test_submission_exports_share_filters_and_preserve_nested_json(): void
    {
        $f = $this->form($this->owner());
        $yes = $f->submissions()->create(['data' => ['join' => 'Yes', 'reason' => '', 'rating' => 7], 'payment_status' => 'none']);
        $f->submissions()->create(['data' => ['join' => 'No', 'reason' => 'filled', 'rating' => 9], 'payment_status' => 'none']);
        $f->submissions()->create(['data' => ['join' => 'Yes', 'rating' => 99], 'payment_status' => 'none', 'is_spam' => true]);
        $base = '/user/forms/'.$f->id.'/submissions';
        $filter = ['answer_field' => 'reason', 'answer_state' => 'empty', 'payment' => 'unpaid'];
        $this->get($base.'?'.http_build_query($filter))->assertOk()->assertViewHas('submissions', fn ($rows) => $rows->count() === 1 && $rows->first()->id === $yes->id);
        $response = $this->get($base.'/export?'.http_build_query($filter + ['format' => 'json', 'columns' => ['join']]))->assertOk();
        $rows = json_decode($response->streamedContent(), true);
        $this->assertCount(1, $rows);
        $this->assertEquals(['join' => 'Yes'], $rows[0]['data']);
        $this->get($base.'/export?'.http_build_query(['format' => 'long_csv', 'answer_field' => 'join', 'answer_state' => 'equals', 'answer_value' => 'No']))->assertOk();
        $this->get($base.'?q=filled')->assertOk()->assertViewHas('submissions', fn ($rows) => $rows->count() === 1);
        $this->getJson($base.'/export?columns[]=not_a_field')->assertStatus(422);
        $this->getJson($base.'?answer_field=not_a_field&answer_state=empty')->assertStatus(422);
    }

    public function test_selected_repeatable_child_columns_export_each_copy(): void
    {
        $f = $this->form($this->owner());
        $f->update(['fields' => [['id' => 'people', 'type' => 'section', 'repeatable' => true, 'label' => 'People'], ['id' => 'person', 'type' => 'text', 'parent' => 'people', 'label' => 'Name']]]);
        $f->submissions()->create(['payment_status' => 'none', 'data' => ['people' => ['_repeatable_group' => true, 'copies' => [['person' => 'One'], ['person' => 'Two']]]]]);
        $result = $this->get('/user/forms/'.$f->id.'/submissions/export?columns[]=person')->assertOk()->streamedContent();
        $this->assertStringContainsString('person', $result);
        $this->assertStringContainsString('One', $result);
        $this->assertStringContainsString('Two', $result);
        $json=$this->get('/user/forms/'.$f->id.'/submissions/export?columns[]=person&format=json')->assertOk()->streamedContent();
        $this->assertEquals(['One','Two'],json_decode($json,true)[0]['data']['person']);
    }
}
