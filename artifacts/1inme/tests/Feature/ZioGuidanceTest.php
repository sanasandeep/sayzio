<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AssistantActionChat;
use App\Services\AI\ElevenLabsService;
use App\Services\AI\OpenAiService;
use App\Services\AI\Providers\AnthropicDriver;
use App\Services\AI\SiteAssistantSettings;
use App\Services\AI\Voice\VoiceAssistantService;
use App\Services\AI\Voice\VoiceToolRegistry;
use App\Services\AI\WhisperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ZioGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_uses_the_same_question_tool_as_voice_and_stops_before_another_paid_call(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $llm = Mockery::mock(OpenAiService::class);
        $llm->shouldReceive('chat')->once()->withArgs(function ($payer, $model, $messages, $opts) {
            $names = array_column(array_column($opts['tools'], 'function'), 'name');
            $this->assertContains('guide_control', $names);
            $this->assertContains('ask_question', $names);
            $this->assertNotContains('delete_biolink', $names);

            return true;
        })->andReturn(['content' => '', 'credits_spent' => 2, 'tool_calls' => [[
            'id' => 'question', 'function' => ['name' => 'ask_question', 'arguments' => json_encode([
                'question' => 'Where is your image?', 'mode' => 'single', 'options' => ['Upload', 'URL', 'My Files'],
            ])],
        ]]]);
        $result = (new AssistantActionChat($llm, app(VoiceToolRegistry::class)))->run($user, $user, 'test', [['role' => 'user', 'content' => 'Add an image']], []);
        $this->assertSame(2, $result['credits_spent']);
        $this->assertSame('question', $result['tool_results'][0]['result']['blocks'][0]['type']);
        $this->assertSame(['Upload', 'URL', 'My Files'], $result['tool_results'][0]['result']['blocks'][0]['options']);
    }

    public function test_questions_support_multiple_and_typed_answers_and_limit_choices(): void
    {
        $user = User::factory()->create();
        $registry = app(VoiceToolRegistry::class);
        foreach (['multiple', 'text'] as $mode) {
            $r = $registry->execute($user, false, 'ask_question', ['question' => 'What do you need?', 'mode' => $mode, 'options' => ['One', 'Two', 'Three', 'Four', 'Five']]);
            $this->assertSame($mode, $r['blocks'][0]['mode']);
            $this->assertCount(4, $r['blocks'][0]['options']);
        }
    }

    public function test_guidance_is_a_label_pointer_and_never_a_click_or_arbitrary_selector(): void
    {
        $user = User::factory()->create();
        $r = app(VoiceToolRegistry::class)->execute($user, false, 'guide_control', ['label' => 'Upload', 'instruction' => 'Choose an image from your device.', 'selector' => 'body', 'click' => true]);
        $this->assertSame(['type' => 'zio_guide', 'label' => 'Upload', 'instruction' => 'Choose an image from your device.'], $r['client_action']);
    }

    public function test_open_biolink_refuses_another_users_page_and_asks_when_ambiguous(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        $workspace = app(WorkspaceContext::class)->resolve($user);
        $ids = [];
        foreach ([$user, $user, $other] as $i => $owner) {
            $ids[] = DB::table('links')->insertGetId(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'alias' => 'zio-'.$i, 'title' => 'Page '.$i, 'type' => 'biolink', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $registry = app(VoiceToolRegistry::class);
        $r = $registry->execute($user, false, 'open_biolink', ['mode' => 'view', 'link_id' => $ids[2]]);
        $this->assertArrayNotHasKey('navigate_to', $r);
        $r = $registry->execute($user, false, 'open_biolink', ['mode' => 'view']);
        $this->assertArrayNotHasKey('error', $r, json_encode($r));
        $this->assertCount(2, $r['blocks'][0]['options']);
        $r = $registry->execute($user, false, 'open_biolink', ['mode' => 'view', 'link_id' => $ids[0]]);
        $this->assertSame(url('/zio-0'), $r['navigate_to']);
    }

    public function test_guided_stream_delivers_actions_and_persists_question_for_the_next_answer(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = User::factory()->create();
        $this->actingAs($user);
        $llm = Mockery::mock(OpenAiService::class);
        $llm->shouldReceive('chat')->once()->andReturn(['content' => '', 'credits_spent' => 2, 'tool_calls' => [[
            'id' => 'question', 'function' => ['name' => 'ask_question', 'arguments' => json_encode([
                'question' => 'Which sources?', 'mode' => 'multiple', 'options' => ['Upload', 'URL', 'My Files'],
            ])],
        ]]]);
        $this->app->instance(OpenAiService::class, $llm);
        $response = $this->post(route('site-assistant.stream'), [
            'visitor_token' => 'sa_'.Str::random(28), 'surface' => 'app', 'message' => 'Help me add an image',
            'page' => ['title' => 'QR builder', 'controls' => ['Upload', 'URL', 'My Files']],
        ]);
        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('event: done', $body);
        $this->assertStringContainsString('"tool_results"', $body);
        $this->assertStringContainsString('"mode":"multiple"', $body);
        $this->assertStringNotContainsString('event: error', $body);
        $message = SiteAssistantMessage::where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('question', $message->blocks[0]['type']);
        $this->assertSame('Which sources?', $message->content);
    }

    public function test_widget_renders_shared_guidance_runtime_and_interactive_questions(): void
    {
        AiEngineSettings::setEnabled(true);
        SiteAssistantSettings::update(['enabled_app' => true]);
        $this->actingAs(User::factory()->create());
        $html = view('common.partials.site-assistant', ['surface' => 'app'])->render();
        $this->assertStringContainsString('zio_guide', $html);
        $this->assertStringContainsString('Submit selections', $html);
        $this->assertStringContainsString('Type a different answer', $html);
        file_put_contents('/tmp/zio-guidance-widget.html', $html);
    }

    public function test_voice_returns_the_same_interactive_question_and_speaks_it(): void
    {
        AiEngineSettings::setVoiceEnabled(true);
        AiEngineSettings::setVoiceEnabledPlans([]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $whisper = Mockery::mock(WhisperService::class);
        $whisper->shouldReceive('transcribe')->once()->andReturn(['text' => 'Help me add an image', 'credits_spent' => 1]);
        $llm = Mockery::mock(OpenAiService::class);
        $llm->shouldReceive('chat')->once()->andReturn(['content' => '', 'credits_spent' => 2, 'tool_calls' => [[
            'id' => 'question', 'function' => ['name' => 'ask_question', 'arguments' => json_encode([
                'question' => 'Where is your image?', 'mode' => 'single', 'options' => ['Upload', 'URL', 'My Files'],
            ])],
        ]]]);
        $speech = Mockery::mock(ElevenLabsService::class);
        $speech->shouldReceive('speak')->once()->with($user, 'Where is your image?')->andReturn(['audio' => 'test', 'credits_spent' => 3]);
        $credits = Mockery::mock(AiUsageCharger::class);
        $credits->shouldReceive('getBalance')->andReturn(100);
        $voice = new VoiceAssistantService($whisper, $llm, $speech, app(VoiceToolRegistry::class), $credits);
        $result = $voice->runTurn($user, false, null, ['surface' => ['name' => 'app', 'controls' => ['Upload', 'URL', 'My Files']]]);
        $this->assertSame('Where is your image?', $result['reply']);
        $this->assertSame('question', $result['tool_results'][0]['result']['blocks'][0]['type']);
        $this->assertSame(6, $result['credits']['total']);
        $this->assertSame(base64_encode('test'), $result['audio_base64']);
    }

    public function test_explicit_open_request_uses_the_navigation_tool_even_without_browser_control_metadata(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = User::factory()->create();
        $this->actingAs($user);
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        $id = DB::table('links')->insertGetId(['workspace_id' => $ws->id, 'user_id' => $user->id, 'alias' => 'sana-bio', 'title' => 'Sana Sandeep', 'type' => 'biolink', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $llm = Mockery::mock(OpenAiService::class);
        $llm->shouldReceive('chat')->once()->withArgs(function ($payer, $model, $messages, $opts) {
            $this->assertSame(['type' => 'function', 'function' => ['name' => 'open_biolink']], $opts['tool_choice']);

            return true;
        })->andReturn(['content' => '', 'credits_spent' => 2, 'tool_calls' => [[
            'id' => 'open', 'function' => ['name' => 'open_biolink', 'arguments' => json_encode(['mode' => 'view', 'link_id' => $id])],
        ]]]);
        $this->app->instance(OpenAiService::class, $llm);
        $response = $this->post(route('site-assistant.stream'), [
            'visitor_token' => 'sa_'.Str::random(28), 'surface' => 'app',
            'message' => 'can u open Sana Sandeep link in bio page?', 'page' => ['title' => 'My Links'],
        ]);
        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertStringContainsString('"navigate_to"', $body);
        $this->assertStringContainsString('sana-bio', $body);
        $this->assertStringNotContainsString('event: error', $body);
    }

    public function test_claude_provider_preserves_forced_navigation_tool_selection(): void
    {
        $driver = new AnthropicDriver;
        $method = new \ReflectionMethod($driver, 'payload');
        $payload = $method->invoke($driver, 'claude-test', [['role' => 'user', 'content' => 'Open my Link in Bio']], [
            'tools' => [['type' => 'function', 'function' => ['name' => 'open_biolink', 'description' => 'Open a page', 'parameters' => ['type' => 'object', 'properties' => []]]]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'open_biolink']],
        ]);
        $this->assertSame(['type' => 'tool', 'name' => 'open_biolink'], $payload['tool_choice']);
    }

    public function test_directions_and_negative_requests_do_not_force_navigation(): void
    {
        $this->assertTrue(AssistantActionChat::isBiolinkNavigationRequest('can u open Sana Sandeep link in bio page?'));
        foreach (['Do not open my Link in Bio', "Don't open my Link in Bio", 'How can I open my Link in Bio?'] as $text) {
            $this->assertFalse(AssistantActionChat::isBiolinkNavigationRequest($text));
        }
    }
}
