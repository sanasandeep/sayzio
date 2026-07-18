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
 * Coverage for the Sanctum-authenticated SSE mirror at
 * `POST /api/v1/assistant/stream`, added for the Zio Browser desktop
 * client. The web widget uses the session-authenticated
 * `/assistant/stream`; native clients hold only a bearer token, so
 * the API group re-exposes the same controller action behind
 * `auth:sanctum`. This test pins:
 *
 *   - A real bearer token drives a full streamed turn (user → token
 *     frames → done) and the conversation binds to the token's user.
 *   - An unauthenticated request is rejected with 401 (no SSE body),
 *     which the desktop client maps to its "Sign in to use Zio AI"
 *     state.
 */
class SiteAssistantApiStreamTest extends TestCase
{
    use RefreshDatabase;

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

    private function mockChatStream(array $deltas): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chatStream')
            ->andReturnUsing(function ($user, $model, $messages, $opts, $onChunk) use ($deltas) {
                $content = '';
                foreach ($deltas as $d) {
                    $content .= $d;
                    $onChunk($d);
                }
                return [
                    'content'       => $content,
                    'tokens_in'     => 5,
                    'tokens_out'    => 3,
                    'credits_spent' => 2,
                    'model'         => $model,
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    public function test_bearer_token_streams_a_full_turn(): void
    {
        $this->mockChatStream(['Hi', ' there']);

        $user  = User::factory()->create(['role' => 'user']);
        $plain = $user->createToken('zio-browser')->plainTextToken;
        $vt    = 'sa_' . Str::random(28);

        $response = $this->withToken($plain)->post(
            '/api/v1/assistant/stream',
            [
                'visitor_token' => $vt,
                'surface'       => 'app',
                'message'       => 'Summarize this page.',
                'page'          => ['route' => 'zio-browser', 'path' => '/', 'title' => 'Example', 'url' => 'https://example.com'],
            ],
        );

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertSame(1, substr_count($body, 'event: user'));
        $this->assertSame(2, substr_count($body, 'event: token'));
        $this->assertSame(1, substr_count($body, 'event: done'));
        $this->assertStringContainsString('"delta":"Hi"', $body);
        $this->assertStringNotContainsString('event: error', $body);

        $conv = SiteAssistantConversation::where('visitor_token', $vt)->firstOrFail();
        $this->assertSame((int) $user->id, (int) $conv->bound_user_id);
        $this->assertSame(2, SiteAssistantMessage::where('conversation_id', $conv->id)->count());
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/assistant/stream', [
            'visitor_token' => 'sa_' . Str::random(28),
            'surface'       => 'app',
            'message'       => 'hello',
        ]);

        $response->assertStatus(401);
    }
}
