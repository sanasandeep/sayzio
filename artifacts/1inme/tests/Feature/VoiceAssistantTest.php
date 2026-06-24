<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Services\AI\AiUsageCharger;
use App\Services\Billing\WalletService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\ElevenLabsService;
use App\Services\AI\OpenAiService;
use App\Services\AI\WhisperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end coverage for the Voice Assistant turn endpoint.
 *
 * The voice flow (mic → STT → LLM tool-loop → TTS → ledger) was added
 * without an automated suite. These tests guard the regressions that
 * would silently break the product:
 *
 *   1. Plan gate — users whose plan is not on the voice allow-list
 *      must hit a 403 *before* any STT credits are charged.
 *   2. Credit gate — a user with zero balance must get 402, not a
 *      half-spent silent failure.
 *   3. Happy path — STT/LLM/TTS each contribute their own credit line
 *      to the ledger, audio is base64-encoded back to the client, and
 *      the transcript + reply are returned for the UI to render.
 *   4. Destructive tool calls returned by GPT must come back as
 *      `confirm_required` payloads — the orchestrator must NEVER run
 *      them on the first pass.
 *   5. When the client re-sends with `confirmed_tools[name] = true`,
 *      the same tool must execute and the LLM must continue past it
 *      to a final spoken reply.
 *
 * WhisperService / OpenAiService / ElevenLabsService are all replaced
 * with Mockery doubles so no network call happens and assertions on
 * credit breakdown / tool flow are deterministic.
 */
class VoiceAssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Successive responses returned by the mocked OpenAI chat call
     * (drained in order across the tool-iteration loop).
     *
     * @var array<int, array<string,mixed>>
     */
    protected array $llmResponses = [];

    /** @var int Number of times the mocked OpenAi::chat was invoked. */
    protected int $llmCalls = 0;

    /** @var int Number of times the mocked WhisperService::transcribe was invoked. */
    protected int $sttCalls = 0;

    /** @var int Number of times the mocked ElevenLabsService::speak was invoked. */
    protected int $ttsCalls = 0;

    /** @var string|null System prompt captured from the first LLM call. */
    protected ?string $capturedSystemPrompt = null;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setVoiceEnabled(true);
        // Empty allow-list = every plan is allowed by default.
        AiEngineSettings::setVoiceEnabledPlans([]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(string $tag = 'v'): User
    {
        return User::create([
            'name'     => 'Voice ' . $tag,
            'email'    => $tag . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    private function fakeAudio(): UploadedFile
    {
        // Pin the mime so the controller's `mimetypes:` validation
        // passes (audio/webm is in the allow-list). createWithContent
        // alone falls back to application/octet-stream which is also
        // accepted, but webm matches what the browser actually sends.
        return UploadedFile::fake()->createWithContent(
            'clip.webm',
            'fake-audio-bytes'
        )->mimeType('audio/webm');
    }

    /**
     * Bind mocked Whisper / OpenAI / ElevenLabs services into the
     * container before the request resolves VoiceAssistantService.
     *
     * @param  array<int, array<string,mixed>>  $llmResponses Successive
     *         OpenAi::chat() return shapes. Each entry should contain
     *         `content`, optional `tool_calls`, and `credits_spent`.
     */
    protected function mockVoiceServices(array $llmResponses): void
    {
        $this->llmResponses = $llmResponses;
        $this->llmCalls     = 0;
        $this->sttCalls     = 0;
        $this->ttsCalls     = 0;

        $whisper = Mockery::mock(WhisperService::class);
        $whisper->shouldReceive('transcribe')->andReturnUsing(function () {
            $this->sttCalls++;
            return [
                'text'             => 'hello assistant',
                'duration_seconds' => 2.0,
                'credits_spent'    => 5,
                'model'            => 'whisper-1',
            ];
        });

        $openai = Mockery::mock(OpenAiService::class);
        $openai->shouldReceive('chat')->andReturnUsing(
            function ($user, $model, $messages, $opts = []) {
                if ($this->llmCalls === 0 && isset($messages[0]['content'])) {
                    $this->capturedSystemPrompt = (string) $messages[0]['content'];
                }
                $idx = $this->llmCalls++;
                $resp = $this->llmResponses[$idx]
                    ?? ['content' => 'Done.', 'tool_calls' => [], 'credits_spent' => 0];
                return [
                    'content'       => $resp['content'] ?? '',
                    'tool_calls'    => $resp['tool_calls'] ?? [],
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => $resp['credits_spent'] ?? 7,
                    'model'         => $model,
                    'raw'           => [],
                ];
            }
        );

        $eleven = Mockery::mock(ElevenLabsService::class);
        $eleven->shouldReceive('speak')->andReturnUsing(function () {
            $this->ttsCalls++;
            return [
                'audio'         => 'mp3-bytes',
                'mime'          => 'audio/mpeg',
                'characters'    => 32,
                'credits_spent' => 3,
                'model'         => 'eleven_test',
                'voice_id'      => 'voice-x',
            ];
        });

        $this->app->instance(WhisperService::class, $whisper);
        $this->app->instance(OpenAiService::class, $openai);
        $this->app->instance(ElevenLabsService::class, $eleven);
    }

    // ── 1) plan gate ──────────────────────────────────────────────────────────

    public function test_voice_endpoint_returns_403_when_user_plan_is_not_allow_listed(): void
    {
        // Restrict to a plan slug the test user does not have. The
        // user's effective plan slug falls back to "free", which is
        // not in the allow-list.
        AiEngineSettings::setVoiceEnabledPlans(['premium']);

        $user = $this->makeUser('p1');
        // Even with full balance and working services, plan gate must win first.
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);
        $this->mockVoiceServices([
            ['content' => 'Should never speak', 'tool_calls' => [], 'credits_spent' => 7],
        ]);

        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertStatus(403);
        $this->assertSame(0, $this->sttCalls, 'STT must not run for a plan-blocked user (no credits should be charged).');
        $this->assertSame(0, $this->llmCalls, 'LLM must not run for a plan-blocked user.');
        $this->assertSame(0, $this->ttsCalls, 'TTS must not run for a plan-blocked user.');
        // Balance must be untouched — refusal happens before any meter charge.
        $this->assertSame(500, app(AiUsageCharger::class)->getBalance($user));
    }

    // ── 2) credit gate ────────────────────────────────────────────────────────

    public function test_voice_endpoint_returns_402_when_user_has_no_credits(): void
    {
        $user = $this->makeUser('c1');
        // Deliberately no grant — balance is 0.
        $this->mockVoiceServices([
            ['content' => 'Should never speak', 'tool_calls' => [], 'credits_spent' => 7],
        ]);

        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertStatus(402);
        $resp->assertJsonStructure(['error', 'balance', 'required']);
        $this->assertSame(0, $this->sttCalls, 'STT must not run for a broke user (would charge credits we do not have).');
        $this->assertSame(0, $this->llmCalls, 'LLM must not run for a broke user.');
        $this->assertSame(0, $this->ttsCalls, 'TTS must not run for a broke user.');
        // Balance stays at zero — the pre-flight balance check refuses
        // the turn before any service is invoked, so the ledger is untouched.
        $this->assertSame(0, app(AiUsageCharger::class)->getBalance($user));
    }

    // ── 3) happy path ─────────────────────────────────────────────────────────

    public function test_happy_path_returns_transcript_audio_and_per_stage_credit_breakdown(): void
    {
        $user = $this->makeUser('h1');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);

        $this->mockVoiceServices([
            ['content' => 'Hi there!', 'tool_calls' => [], 'credits_spent' => 7],
        ]);

        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('transcript', 'hello assistant');
        $resp->assertJsonPath('reply', 'Hi there!');
        $resp->assertJsonPath('credits.stt', 5);
        $resp->assertJsonPath('credits.llm', 7);
        $resp->assertJsonPath('credits.tts', 3);
        $resp->assertJsonPath('credits.total', 15);
        $resp->assertJsonPath('pending_confirmations', []);

        // TTS audio comes back as base64 of the synthetic mp3 bytes.
        $this->assertSame(base64_encode('mp3-bytes'), $resp->json('audio_base64'));

        // History is sanitised + bounded; the new turn appears at the tail.
        $messages = $resp->json('messages');
        $this->assertNotEmpty($messages);
        $this->assertSame('user', $messages[count($messages) - 2]['role']);
        $this->assertSame('hello assistant', $messages[count($messages) - 2]['content']);
        $this->assertSame('assistant', $messages[count($messages) - 1]['role']);
        $this->assertSame('Hi there!', $messages[count($messages) - 1]['content']);
    }

    // ── 3b) surface context reaches the system prompt ─────────────────────────
    //
    // Surfaces tell the service which screen the user is driving so GPT
    // prefers the right tools. The web sends `surface` as an object
    // ({name:'wizard'}); mobile sends it as a bare string ('wizard').
    // Both MUST produce the same surface hint — a regression here silently
    // de-targets every mobile voice turn.

    public function test_string_surface_context_is_honored_in_prompt(): void
    {
        $user = $this->makeUser('s1');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);
        $this->mockVoiceServices([
            ['content' => 'On it.', 'tool_calls' => [], 'credits_spent' => 7],
        ]);

        // Mobile contract: surface is a plain string.
        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio'   => $this->fakeAudio(),
            'context' => json_encode(['surface' => 'wizard']),
        ]);

        $resp->assertOk();
        $this->assertNotNull($this->capturedSystemPrompt);
        $this->assertStringContainsString(
            'biolink creation wizard',
            (string) $this->capturedSystemPrompt,
            'A string surface must still inject the wizard hint into the prompt.'
        );
    }

    public function test_object_surface_context_is_honored_in_prompt(): void
    {
        $user = $this->makeUser('s2');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);
        $this->mockVoiceServices([
            ['content' => 'On it.', 'tool_calls' => [], 'credits_spent' => 7],
        ]);

        // Web contract: surface is an object with a name (+ optional extra).
        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio'   => $this->fakeAudio(),
            'context' => json_encode(['surface' => ['name' => 'create_link']]),
        ]);

        $resp->assertOk();
        $this->assertNotNull($this->capturedSystemPrompt);
        $this->assertStringContainsString(
            'Create Link type picker',
            (string) $this->capturedSystemPrompt,
            'An object surface must inject the create-link hint into the prompt.'
        );
    }

    // ── 3c) dictation helper is always defined ────────────────────────────────
    //
    // Surfaces mount `x-data="voiceDictation(...)"` on always-rendered
    // containers (header search box, companion composer). The reusable
    // voiceDictation() control MUST therefore be defined for every
    // authenticated user — even one whose plan blocks voice — or Alpine
    // throws an init error and breaks the host component's other behaviour
    // (e.g. the header search Enter handler). The full turn widget stays
    // gated; only the dictation helper is unconditional.

    public function test_voice_dictation_helper_is_defined_even_for_plan_blocked_user(): void
    {
        // Free user + a premium-only allow-list ⇒ voice is unavailable.
        AiEngineSettings::setVoiceEnabledPlans(['premium']);

        $user = $this->makeUser('vd');
        $this->actingAs($user);

        $html = view('partials.voice-assistant')->render();

        // The reusable dictation control is defined regardless of plan…
        $this->assertStringContainsString(
            'window.voiceDictation',
            $html,
            'voiceDictation() must be defined for plan-blocked users so surface mics never reference an undefined function.'
        );
        // …but the full turn widget must NOT render for a blocked user.
        $this->assertStringNotContainsString(
            'x-data="voiceAssistant(',
            $html,
            'The turn widget must stay gated behind the voice allow-list.'
        );
    }

    // ── 4) destructive tool returns confirm_required ──────────────────────────

    public function test_destructive_tool_call_is_short_circuited_into_confirm_required(): void
    {
        $user = $this->makeUser('d1');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);

        // The model asks to switch_plan (destructive). Without the
        // user's confirmation the registry MUST refuse to execute and
        // the orchestrator must stop the loop at this point.
        $this->mockVoiceServices([
            [
                'content'    => '',
                'credits_spent' => 7,
                'tool_calls' => [[
                    'id'       => 'call_1',
                    'type'     => 'function',
                    'function' => [
                        'name'      => 'switch_plan',
                        'arguments' => '{"plan_slug":"premium"}',
                    ],
                ]],
            ],
        ]);

        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('pending_confirmations.0.confirm_required', true);
        $resp->assertJsonPath('pending_confirmations.0.tool', 'switch_plan');
        $resp->assertJsonPath('tool_results.0.tool', 'switch_plan');
        $resp->assertJsonPath('tool_results.0.result.confirm_required', true);

        // Reply text is the synthesised confirm prompt, not a tool result.
        $this->assertStringContainsString('switch_plan', $resp->json('reply'));

        // Critical: only ONE LLM iteration happened — the loop stopped
        // as soon as we collected the pending confirmation, otherwise
        // we'd be wasting credits asking the model to keep planning
        // around an action the user hasn't approved yet.
        $this->assertSame(1, $this->llmCalls);
    }

    // ── 5) confirmed re-run executes the tool ────────────────────────────────

    public function test_confirmed_destructive_tool_executes_on_re_run_and_loop_continues(): void
    {
        $user = $this->makeUser('d2');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);

        // Two LLM calls this time: first asks for the destructive tool,
        // second (after the tool result is fed back) emits the final
        // spoken reply with no further tool calls.
        $this->mockVoiceServices([
            [
                'content'       => '',
                'credits_spent' => 7,
                'tool_calls'    => [[
                    'id'       => 'call_1',
                    'type'     => 'function',
                    'function' => [
                        'name'      => 'switch_plan',
                        'arguments' => '{"plan_slug":"premium"}',
                    ],
                ]],
            ],
            [
                'content'       => 'Plan switch started.',
                'credits_spent' => 4,
                'tool_calls'    => [],
            ],
        ]);

        $context = json_encode(['confirmed_tools' => ['switch_plan' => true]]);

        $resp = $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio'   => $this->fakeAudio(),
            'context' => $context,
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('reply', 'Plan switch started.');
        $resp->assertJsonPath('pending_confirmations', []);
        $resp->assertJsonPath('tool_results.0.tool', 'switch_plan');

        // Result should be the actual handler payload — NOT a
        // confirm_required envelope (which is what the unconfirmed
        // path returns).
        $result = $resp->json('tool_results.0.result');
        $this->assertArrayNotHasKey('confirm_required', $result ?? []);
        $this->assertArrayHasKey('summary', $result);

        // Both LLM iterations ran (initial + post-tool continuation),
        // and both contributed credits to the ledger total.
        $this->assertSame(2, $this->llmCalls);
        $this->assertSame(7 + 4, $resp->json('credits.llm'));
        $this->assertSame(5 + (7 + 4) + 3, $resp->json('credits.total'));
    }

    // ── transcribe (dictation-only STT) ────────────────────────────
    //
    // The dictation endpoint is STT-only: it reuses the same plan gate
    // and meter as a full turn but never calls the LLM or TTS. It backs
    // the reusable `voiceDictation()` control on the companion composer
    // (and mobile). These tests pin the gate and the happy-path shape.

    public function test_transcribe_returns_403_when_plan_is_not_allow_listed(): void
    {
        AiEngineSettings::setVoiceEnabledPlans(['premium']);

        $user = $this->makeUser('tr-gate');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);
        $this->mockVoiceServices([]); // services bound but must never run

        $resp = $this->actingAs($user)->post(route('user.ai.voice.transcribe'), [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertStatus(403);
        $this->assertSame(0, $this->sttCalls, 'STT must not run for a plan-blocked user.');
        $this->assertSame(0, $this->llmCalls, 'Dictation must never invoke the LLM.');
        $this->assertSame(0, $this->ttsCalls, 'Dictation must never invoke TTS.');
        $this->assertSame(500, app(AiUsageCharger::class)->getBalance($user));
    }

    public function test_transcribe_returns_text_without_calling_llm_or_tts(): void
    {
        $user = $this->makeUser('tr-ok');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);
        $this->mockVoiceServices([]);

        $resp = $this->actingAs($user)->post(route('user.ai.voice.transcribe'), [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('text', 'hello assistant');
        $this->assertSame(1, $this->sttCalls, 'Dictation must run STT exactly once.');
        $this->assertSame(0, $this->llmCalls, 'Dictation must never invoke the LLM.');
        $this->assertSame(0, $this->ttsCalls, 'Dictation must never invoke TTS.');
    }
}
