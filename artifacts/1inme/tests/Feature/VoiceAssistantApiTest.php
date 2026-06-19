<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiCreditService;
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
 * End-to-end coverage for the mobile-side Voice Assistant controller
 * (`POST /api/v1/ai/voice/turn` + `GET /api/v1/ai/voice/capabilities`).
 *
 * The mobile controller is its own class — it injects
 * `client_kind = 'mobile'` into the orchestrator context so mobile-only
 * tools (currently `write_nfc_tag`) are exposed, and accepts a wider
 * mime allow-list for iOS/Android recorders. It still funnels through
 * the same VoiceAssistantService used by the web client, so the
 * STT/LLM/TTS credit ledger contract MUST stay identical:
 *
 *   1. Happy path — STT/LLM/TTS each contribute their own credit row,
 *      audio comes back base64-encoded, and the per-stage breakdown
 *      sums to `credits.total`.
 *   2. `write_nfc_tag` is hidden from the web capabilities endpoint
 *      (no mobile flag) and visible from the mobile capabilities
 *      endpoint — otherwise the LLM would offer NFC writes on web
 *      where the JS runtime cannot fulfil them.
 *   3. A destructive tool returned by GPT must come back as
 *      `confirm_required` until the client re-sends with
 *      `confirmed_tools[name] = true`, matching the web behaviour.
 *
 * WhisperService / OpenAiService / ElevenLabsService are mocked so
 * no network call happens and credit assertions are deterministic.
 */
class VoiceAssistantApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array<string,mixed>> Successive OpenAi::chat() responses. */
    protected array $llmResponses = [];

    protected int $llmCalls = 0;
    protected int $sttCalls = 0;
    protected int $ttsCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setVoiceEnabled(true);
        // Empty allow-list = every plan is allowed.
        AiEngineSettings::setVoiceEnabledPlans([]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(string $tag = 'm'): User
    {
        $plan = Plan::create([
            'name'          => 'p' . Str::random(4),
            'slug'          => 'p' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'features'      => [],
        ]);
        return User::create([
            'name'     => 'Voice ' . $tag,
            'email'    => $tag . '-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $plan->id,
        ]);
    }

    /** Bind the user's workspace so permission-gated tools (links.create) can resolve. */
    private function bindWorkspace(User $user): void
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
    }

    private function fakeAudio(): UploadedFile
    {
        // m4a matches what the iOS recorder uploads; the mobile
        // controller's mimetypes: list explicitly accepts it.
        return UploadedFile::fake()->createWithContent(
            'clip.m4a',
            'fake-mobile-audio'
        )->mimeType('audio/x-m4a');
    }

    /**
     * Bind mocked Whisper / OpenAI / ElevenLabs services so the run
     * is deterministic and no network IO happens.
     *
     * @param  array<int, array<string,mixed>>  $llmResponses
     */
    protected function mockVoiceServices(array $llmResponses): void
    {
        $this->llmResponses = $llmResponses;
        $this->llmCalls = 0;
        $this->sttCalls = 0;
        $this->ttsCalls = 0;

        $whisper = Mockery::mock(WhisperService::class);
        $whisper->shouldReceive('transcribe')->andReturnUsing(function () {
            $this->sttCalls++;
            return [
                'text'             => 'hey from mobile',
                'duration_seconds' => 2.0,
                'credits_spent'    => 6,
                'model'            => 'whisper-1',
            ];
        });

        $openai = Mockery::mock(OpenAiService::class);
        $openai->shouldReceive('chat')->andReturnUsing(
            function ($user, $model, $messages, $opts = []) {
                $idx = $this->llmCalls++;
                $resp = $this->llmResponses[$idx]
                    ?? ['content' => 'Done.', 'tool_calls' => [], 'credits_spent' => 0];
                return [
                    'content'       => $resp['content'] ?? '',
                    'tool_calls'    => $resp['tool_calls'] ?? [],
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => $resp['credits_spent'] ?? 8,
                    'model'         => $model,
                    'raw'           => [],
                ];
            }
        );

        $eleven = Mockery::mock(ElevenLabsService::class);
        $eleven->shouldReceive('speak')->andReturnUsing(function () {
            $this->ttsCalls++;
            return [
                'audio'         => 'mp3-mobile-bytes',
                'mime'          => 'audio/mpeg',
                'characters'    => 24,
                'credits_spent' => 4,
                'model'         => 'eleven_test',
                'voice_id'      => 'voice-x',
            ];
        });

        $this->app->instance(WhisperService::class, $whisper);
        $this->app->instance(OpenAiService::class, $openai);
        $this->app->instance(ElevenLabsService::class, $eleven);
    }

    // ── 1) /api/v1/ai/voice/turn happy path charges per stage ────────────────

    public function test_mobile_turn_endpoint_returns_per_stage_credit_breakdown(): void
    {
        $user = $this->makeUser('h');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);

        $this->mockVoiceServices([
            ['content' => 'Hello from mobile.', 'tool_calls' => [], 'credits_spent' => 8],
        ]);

        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/ai/voice/turn', [
            'audio' => $this->fakeAudio(),
        ]);

        $resp->assertOk();
        $resp->assertJsonPath('transcript', 'hey from mobile');
        $resp->assertJsonPath('reply', 'Hello from mobile.');

        // Each pipeline stage charges its own credit row; the controller
        // returns the breakdown so the mobile UI can show "you spent X".
        $resp->assertJsonPath('credits.stt', 6);
        $resp->assertJsonPath('credits.llm', 8);
        $resp->assertJsonPath('credits.tts', 4);
        $resp->assertJsonPath('credits.total', 18);

        // Spoken reply is base64-encoded mp3 bytes from the TTS mock.
        $this->assertSame(base64_encode('mp3-mobile-bytes'), $resp->json('audio_base64'));

        // Each stage was invoked exactly once.
        $this->assertSame(1, $this->sttCalls, 'STT must run exactly once per turn.');
        $this->assertSame(1, $this->llmCalls, 'LLM must run exactly once when no tool calls are emitted.');
        $this->assertSame(1, $this->ttsCalls, 'TTS must run exactly once to produce the spoken reply.');
    }

    // ── 2) write_nfc_tag visibility flips on client_kind ─────────────────────

    public function test_write_nfc_tag_is_visible_to_mobile_callers_and_hidden_from_web_callers(): void
    {
        $user = $this->makeUser('nfc');
        // links.create permission requires a bound workspace; without
        // it the mobile caller would also be blocked and we'd be
        // measuring the wrong gate.
        $this->bindWorkspace($user);

        // Mobile capabilities — controller passes isMobile=true to the
        // registry, so the mobile-only NFC tool MUST surface.
        $this->withToken($user->createToken('test')->plainTextToken);
        $mobile = $this->getJson('/api/v1/ai/voice/capabilities');
        $mobile->assertOk();

        $mobileNames = $this->toolNames($mobile->json('tools') ?: []);
        $this->assertContains(
            'write_nfc_tag',
            $mobileNames,
            'write_nfc_tag must be advertised to mobile callers — the Expo app is the only client that can fulfil an NFC write.'
        );

        // Web capabilities — same user, but the web controller does NOT
        // set client_kind=mobile, so the registry must hide NFC.
        $web = $this->actingAs($user)->getJson(route('user.ai.voice.capabilities'));
        $web->assertOk();

        $webNames = $this->toolNames($web->json('tools') ?: []);
        $this->assertNotContains(
            'write_nfc_tag',
            $webNames,
            'write_nfc_tag must be hidden from web callers — the browser cannot write to a physical tag, so offering it would lie to the user.'
        );
    }

    // ── 3) destructive tool gates on confirmed_tools ─────────────────────────

    public function test_destructive_tool_returns_confirm_required_until_confirmed_tools_is_set(): void
    {
        $user = $this->makeUser('d');
        app(WalletService::class)->credit($user, 500, ['reason' => 'test seed']);

        // First request: model asks for switch_plan (destructive). The
        // orchestrator must short-circuit into a confirm_required
        // payload rather than executing the handler.
        $this->mockVoiceServices([
            [
                'content'       => '',
                'credits_spent' => 8,
                'tool_calls'    => [[
                    'id'       => 'call_1',
                    'type'     => 'function',
                    'function' => [
                        'name'      => 'switch_plan',
                        'arguments' => '{"plan_slug":"premium"}',
                    ],
                ]],
            ],
        ]);

        $this->withToken($user->createToken('test')->plainTextToken);

        $first = $this->postJson('/api/v1/ai/voice/turn', [
            'audio' => $this->fakeAudio(),
        ]);

        $first->assertOk();
        $first->assertJsonPath('pending_confirmations.0.confirm_required', true);
        $first->assertJsonPath('pending_confirmations.0.tool', 'switch_plan');
        $first->assertJsonPath('tool_results.0.tool', 'switch_plan');
        $first->assertJsonPath('tool_results.0.result.confirm_required', true);
        // Loop must stop on the very first iteration — anything else
        // burns LLM credits planning around an unconfirmed action.
        $this->assertSame(1, $this->llmCalls);

        // Second request: same destructive tool, but this time the
        // client passes confirmed_tools so the registry is allowed to
        // execute the real handler. The model then emits a final
        // spoken reply with no further tool calls.
        $this->mockVoiceServices([
            [
                'content'       => '',
                'credits_spent' => 8,
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
                'credits_spent' => 5,
                'tool_calls'    => [],
            ],
        ]);

        $second = $this->postJson('/api/v1/ai/voice/turn', [
            'audio'   => $this->fakeAudio(),
            'context' => json_encode(['confirmed_tools' => ['switch_plan' => true]]),
        ]);

        $second->assertOk();
        $second->assertJsonPath('reply', 'Plan switch started.');
        $second->assertJsonPath('pending_confirmations', []);
        $second->assertJsonPath('tool_results.0.tool', 'switch_plan');

        // Confirmed run returns the real handler payload, NOT a
        // confirm_required envelope.
        $result = $second->json('tool_results.0.result');
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('confirm_required', $result);
        $this->assertArrayHasKey('summary', $result);

        // Both LLM iterations ran on the confirmed re-call (initial +
        // post-tool continuation), and both contribute to the ledger.
        $this->assertSame(2, $this->llmCalls);
        $this->assertSame(8 + 5, $second->json('credits.llm'));
    }

    /**
     * Flatten the grouped `tools` payload into a flat list of tool
     * names for membership assertions.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $grouped
     * @return array<int, string>
     */
    private function toolNames(array $grouped): array
    {
        $names = [];
        foreach ($grouped as $entries) {
            foreach ($entries as $entry) {
                if (isset($entry['name'])) {
                    $names[] = $entry['name'];
                }
            }
        }
        return $names;
    }
}
