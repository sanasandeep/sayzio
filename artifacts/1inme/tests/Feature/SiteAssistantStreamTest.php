<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SiteAssistantConversation;
use App\Modules\Common\Models\SiteAssistantMessage;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
