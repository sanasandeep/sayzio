<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use App\Services\AI\SiteAssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Coverage for the SSE turn served by `POST /assistant/stream`.
 *
 * The streamed reply path is wired controller → runtime → OpenAI
 * helper, and a regression in any link silently breaks the live
 * widget for every visitor (no token frames, no persisted message,
 * or a half-emitted SSE stream the JS client cannot parse).
 *
 * This test pins the contract end-to-end with a Mockery double for
 * OpenAiService (so no network call happens) that drives the
 * `$onChunk` callback the runtime wires through to the controller's
 * SSE emitter. We then assert:
 *
 *   - The HTTP response is a 200 SSE stream with the headers the
 *     widget relies on (text/event-stream, no buffering).
 *   - Every model delta is re-emitted as an `event: token` frame
 *     and the persisted assistant message lands in the final
 *     `event: done` frame.
 *   - The user + assistant rows are stored on the resolved
 *     SiteAssistantConversation so the transcript survives a reload.
 */
class SiteAssistantStreamTest extends TestCase
{
    use RefreshDatabase;

    protected array $callLog = ['chatStream' => 0, 'last_messages' => [], 'last_opts' => []];

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Stream Tester',
            'email'    => 'stream-' . Str::random(6) . '@example.com',
            'password' => bcrypt('x'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    /**
     * Replace OpenAiService with a double that records the request
     * and pretends the model produced `$deltas`, invoking the
     * caller-supplied chunk callback once per delta (this is what the
     * controller relies on to emit SSE `event: token` frames).
     */
    private function mockChatStream(array $deltas, int $creditsSpent = 4): void
    {
        $log =& $this->callLog;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chatStream')
            ->andReturnUsing(function ($user, $model, $messages, $opts, $onChunk) use (&$log, $deltas, $creditsSpent) {
                $log['chatStream']++;
                $log['last_messages'] = $messages;
                $log['last_opts']     = $opts;

                $content = '';
                foreach ($deltas as $d) {
                    $content .= $d;
                    $onChunk($d);
                }
                return [
                    'content'       => $content,
                    'tokens_in'     => 12,
                    'tokens_out'    => 7,
                    'credits_spent' => $creditsSpent,
                    'model'         => $model,
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    public function test_stream_emits_user_token_and_done_frames_and_persists_message(): void
    {
        $this->mockChatStream(['Hello', ', ', 'world!'], creditsSpent: 9);

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->call(
            'POST',
            route('site-assistant.stream'),
            [
                'visitor_token' => $token,
                'message'       => 'Say hi please.',
                'page'          => ['route' => 'home', 'path' => '/'],
            ]
        );

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));

        $body = $response->streamedContent();

        // Each model delta becomes its own SSE token frame in order.
        $this->assertStringContainsString('event: token', $body);
        $this->assertStringContainsString('"delta":"Hello"', $body);
        $this->assertStringContainsString('"delta":", "', $body);
        $this->assertStringContainsString('"delta":"world!"', $body);
        $this->assertSame(3, substr_count($body, 'event: token'));

        // The user-echo frame fires before the model is invoked, the
        // done frame after — both must be present exactly once.
        $this->assertSame(1, substr_count($body, 'event: user'));
        $this->assertSame(1, substr_count($body, 'event: done'));
        $this->assertStringNotContainsString('event: error', $body);

        // Token frames must precede the done frame so the JS client
        // can render word-by-word and only commit on done.
        $this->assertLessThan(strpos($body, 'event: done'), strpos($body, 'event: token'));

        // The OpenAI helper was actually called once for this turn.
        $this->assertSame(1, $this->callLog['chatStream']);
        $this->assertSame('site_assistant', $this->callLog['last_opts']['feature'] ?? null);

        // The conversation + both messages were persisted with the
        // model's credit cost rolled into the assistant row.
        $conv = SiteAssistantConversation::where('visitor_token', $token)->firstOrFail();
        $this->assertSame((int) $user->id, (int) $conv->bound_user_id);

        $messages = SiteAssistantMessage::where('conversation_id', $conv->id)->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('user',           $messages[0]->role);
        $this->assertSame('Say hi please.', $messages[0]->content);
        $this->assertSame('assistant',      $messages[1]->role);
        $this->assertSame('Hello, world!',  $messages[1]->content);
        $this->assertSame(9, (int) $messages[1]->credits_spent);

        // The done payload echoes the persisted assistant message id.
        $this->assertStringContainsString('"id":' . (int) $messages[1]->id, $body);

        // Conversation rollups bumped so admin reports stay accurate.
        $conv->refresh();
        $this->assertSame(1, (int) $conv->turns_count);
        $this->assertSame(9, (int) $conv->credits_spent);
    }

    public function test_stream_emits_error_frame_when_message_is_blank(): void
    {
        // Validation rejects this before we ever reach the runtime —
        // assert via standard 4xx so the JS client surfaces the error
        // rather than seeing an empty SSE stream.
        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)
            ->postJson(route('site-assistant.stream'), [
                'visitor_token' => $token,
                'message'       => '',
            ]);

        $response->assertStatus(422);
    }

    /**
     * Mock OpenAiService so chatStream throws the supplied exception
     * BEFORE any token is emitted. Used to exercise the runtime's
     * mid-call error branches (model crash / out-of-credits) without
     * touching the network.
     */
    private function mockChatStreamThrows(\Throwable $error): void
    {
        $log =& $this->callLog;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chatStream')
            ->andReturnUsing(function ($user, $model, $messages, $opts, $onChunk) use (&$log, $error) {
                $log['chatStream']++;
                $log['last_messages'] = $messages;
                $log['last_opts']     = $opts;
                throw $error;
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    /**
     * Decode an SSE response body into an ordered list of
     * ['event' => string, 'data' => array] frames so individual
     * branches can be asserted without brittle substring matching.
     *
     * @return array<int,array{event:string,data:array}>
     */
    private function parseSseFrames(string $body): array
    {
        $frames = [];
        foreach (preg_split("/\n\n/", trim($body)) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') continue;
            $event = null; $data = null;
            foreach (explode("\n", $chunk) as $line) {
                if (str_starts_with($line, 'event: ')) $event = substr($line, 7);
                elseif (str_starts_with($line, 'data: ')) $data = json_decode(substr($line, 6), true);
            }
            if ($event !== null) $frames[] = ['event' => $event, 'data' => $data ?? []];
        }
        return $frames;
    }

    /**
     * Common assertion helper: the stream emitted exactly one
     * `event: error` frame carrying $expectedMessage, no token frames,
     * and no done frame. A blank stream + no error frame is the bug
     * the chat widget cannot recover from.
     */
    private function assertSingleErrorFrame(string $body, string $expectedMessage): void
    {
        $frames = $this->parseSseFrames($body);
        $errors = array_values(array_filter($frames, fn ($f) => $f['event'] === 'error'));
        $tokens = array_values(array_filter($frames, fn ($f) => $f['event'] === 'token'));
        $dones  = array_values(array_filter($frames, fn ($f) => $f['event'] === 'done'));

        $this->assertCount(1, $errors, 'Expected exactly one event:error frame, got: ' . $body);
        $this->assertSame($expectedMessage, $errors[0]['data']['error'] ?? null);
        $this->assertCount(0, $tokens, 'No token frames should be emitted on an error branch.');
        $this->assertCount(0, $dones, 'No done frame should be emitted on an error branch.');
    }

    /**
     * Assert the conversation never received a successful (committed)
     * assistant row. Some branches DO record a marker row with
     * `meta.stream.status === 'failed'` for admin debugging — that is
     * not a user-visible reply, so we only forbid streamed-success
     * rows here.
     */
    private function assertNoSuccessfulAssistantRow(): void
    {
        $bad = SiteAssistantMessage::where('role', 'assistant')
            ->get()
            ->filter(function ($m) {
                $status = data_get($m->meta, 'stream.status');
                return $status === null || $status === 'streamed';
            });
        $this->assertCount(0, $bad, 'No successful assistant row should be persisted on an error branch.');
    }

    public function test_stream_emits_error_when_surface_is_disabled(): void
    {
        // Toggle BOTH surface flags off so neither an anonymous
        // visitor (marketing) nor a signed-in user (app) can drive a
        // turn — mirrors the admin "pause assistant everywhere" switch.
        SiteAssistantSettings::update(['enabled_marketing' => false, 'enabled_app' => false]);

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->call(
            'POST',
            route('site-assistant.stream'),
            [
                'visitor_token' => $token,
                'message'       => 'hi',
                'page'          => ['route' => 'home', 'path' => '/'],
            ]
        );

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertSingleErrorFrame($body, 'The assistant is currently disabled.');

        // Disabled-surface check fires before any DB writes — no
        // conversation, user row, or assistant row should exist.
        $this->assertSame(0, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::count());
        $this->assertSame(0, $this->callLog['chatStream']);
    }

    public function test_stream_emits_error_when_over_monthly_budget(): void
    {
        // Seed the SiteAssistantMessage credit-spend fallback that
        // SiteAssistantSettings::monthlySpend uses when the credit
        // ledger table is absent/empty in the test env.
        $seedConv = SiteAssistantConversation::create([
            'visitor_token' => 'sa_' . Str::random(28),
            'surface'       => 'app',
        ]);
        SiteAssistantMessage::create([
            'conversation_id' => $seedConv->id,
            'role'            => 'assistant',
            'content'         => 'prior turn',
            'credits_spent'   => 50,
        ]);
        SiteAssistantSettings::update(['monthly_budget_credits' => 10]);

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->call(
            'POST',
            route('site-assistant.stream'),
            [
                'visitor_token' => $token,
                'message'       => 'hi',
                'page'          => ['route' => 'home', 'path' => '/'],
            ]
        );

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertSingleErrorFrame($body, 'The assistant is temporarily unavailable.');

        // Budget tripped before the runtime touched the visitor's
        // conversation: no user/assistant row added beyond the seed.
        $this->assertSame(1, SiteAssistantConversation::count());
        $this->assertSame(0, SiteAssistantMessage::where('conversation_id', '!=', $seedConv->id)->count());
        $this->assertSame(0, $this->callLog['chatStream']);
    }

    public function test_stream_emits_error_when_session_rate_limit_hit(): void
    {
        // Pre-create the conversation so we know its id and can prime
        // the per-session rate-limit cache to the cap. This avoids
        // having to fire two real requests just to exhaust the bucket.
        SiteAssistantSettings::update(['session_rate_per_minute' => 1]);
        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);
        $conv = SiteAssistantConversation::create([
            'visitor_token' => $token,
            'surface'       => 'app',
            'user_id'       => $user->id,
            'bound_user_id' => $user->id,
        ]);
        Cache::put("siteasst-rl:{$conv->id}", 1, now()->addMinute());

        $response = $this->actingAs($user)->call(
            'POST',
            route('site-assistant.stream'),
            [
                'visitor_token' => $token,
                'message'       => 'hi again',
                'page'          => ['route' => 'home', 'path' => '/'],
            ]
        );

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertSingleErrorFrame($body, "You're sending messages too fast. Please wait a moment.");

        // Rate-limit guard fires before the user message is created
        // and before OpenAI is touched.
        $this->assertSame(0, SiteAssistantMessage::count());
        $this->assertSame(0, $this->callLog['chatStream']);
    }

    public function test_stream_emits_error_when_openai_throws(): void
    {
        $this->mockChatStreamThrows(new \RuntimeException('upstream blew up'));

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->call(
            'POST',
            route('site-assistant.stream'),
            [
                'visitor_token' => $token,
                'message'       => 'tell me a joke',
                'page'          => ['route' => 'home', 'path' => '/'],
            ]
        );

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertSingleErrorFrame($body, 'The assistant could not respond right now.');

        // OpenAI was actually invoked and threw — confirms the
        // mid-call branch (not the early budget/rate guards) handled it.
        $this->assertSame(1, $this->callLog['chatStream']);
        // No streamed-success assistant row should be committed; the
        // visitor sees the error frame in the widget instead.
        $this->assertNoSuccessfulAssistantRow();
    }

    public function test_stream_emits_error_when_insufficient_credits(): void
    {
        $this->mockChatStreamThrows(new InsufficientAiCreditsException(required: 100, balance: 1));

        $user  = $this->makeUser();
        $token = 'sa_' . Str::random(28);

        $response = $this->actingAs($user)->call(
            'POST',
            route('site-assistant.stream'),
            [
                'visitor_token' => $token,
                'message'       => 'help',
                'page'          => ['route' => 'home', 'path' => '/'],
            ]
        );

        $response->assertOk();
        $body = $response->streamedContent();
        $this->assertSingleErrorFrame($body, 'The assistant is temporarily out of capacity.');

        $this->assertSame(1, $this->callLog['chatStream']);
        $this->assertNoSuccessfulAssistantRow();
    }
}
