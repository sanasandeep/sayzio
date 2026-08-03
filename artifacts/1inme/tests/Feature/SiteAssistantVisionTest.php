<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Ask Zio vision tier (Task #6654): an optional page screenshot on the
 * assistant message endpoint routes the turn to a vision-capable model with
 * multimodal user content, gated by AiPlanAccess ('site_assistant_vision').
 *
 * Pins:
 *  - Paid user + valid screenshot → OpenAiService receives a multimodal
 *    user message (text + image_url) and the response carries
 *    vision.used=true; the screenshot itself is never persisted.
 *  - Free user + screenshot → text-only content plus a friendly notice
 *    (vision.used=false).
 *  - Oversized screenshot → dropped with a notice; malformed screenshot →
 *    silently ignored (no vision key at all).
 */
class SiteAssistantVisionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, mixed>|null Messages captured from the mocked chat() call. */
    private ?array $capturedMessages = null;
    private ?string $capturedModel = null;

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

    private function mockChat(): void
    {
        $this->capturedMessages = null;
        $this->capturedModel = null;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts) {
                $this->capturedModel = $model;
                $this->capturedMessages = $messages;
                return [
                    'content'       => 'The image shows a bridge.',
                    'tokens_in'     => 10,
                    'tokens_out'    => 5,
                    'credits_spent' => 3,
                    'model'         => $model,
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    private function paidUser(): User
    {
        $plan = Plan::create([
            'name'   => 'Vision '.Str::random(5),
            'slug'   => 'vision-'.Str::lower(Str::random(6)),
            'status' => 1,
        ]);
        return User::factory()->create(['role' => 'user', 'plan_id' => $plan->id]);
    }

    private function freeUser(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function send(User $user, array $extra = []): \Illuminate\Testing\TestResponse
    {
        $plain = $user->createToken('zio-browser')->plainTextToken;
        return $this->withToken($plain)->postJson('/api/v1/assistant/message', array_merge([
            'visitor_token' => 'sa_'.Str::random(28),
            'surface'       => 'app',
            'message'       => 'What does the image on this page show?',
            'page'          => ['title' => 'Example', 'url' => 'https://example.com'],
        ], $extra));
    }

    private function validScreenshot(): string
    {
        // A tiny but real-looking JPEG data URL.
        return 'data:image/jpeg;base64,'.base64_encode(str_repeat('x', 600));
    }

    public function test_paid_user_screenshot_yields_multimodal_vision_turn(): void
    {
        $this->mockChat();
        $shot = $this->validScreenshot();

        $res = $this->send($this->paidUser(), ['screenshot' => $shot]);

        $res->assertOk()->assertJsonPath('vision.used', true)->assertJsonPath('vision.notice', null);

        // The final user message must be a multimodal part list carrying the image.
        $last = end($this->capturedMessages);
        $this->assertIsArray($last['content']);
        $this->assertSame('text', $last['content'][0]['type']);
        $this->assertSame('image_url', $last['content'][1]['type']);
        $this->assertSame($shot, $last['content'][1]['image_url']['url']);

        // The runtime appends the vision directive to the multimodal
        // message so a prior in-history refusal can't poison this turn.
        $this->assertSame('text', $last['content'][2]['type']);
        $this->assertSame(\App\Services\AI\SiteAssistantRuntime::VISION_DIRECTIVE, $last['content'][2]['text']);

        // A vision-capable model was selected.
        $this->assertTrue(AiEngineSettings::modelSupportsVision($this->capturedModel));

        // The screenshot is never persisted — only a boolean flag in meta.
        $this->assertDatabaseMissing('site_assistant_messages', ['content' => $shot]);
        $userMsgMeta = $res->json('user_message.meta') ?? [];
        $this->assertStringNotContainsString('base64', json_encode($userMsgMeta));
    }

    public function test_free_user_screenshot_degrades_to_text_with_notice(): void
    {
        $this->mockChat();

        $res = $this->send($this->freeUser(), ['screenshot' => $this->validScreenshot()]);

        $res->assertOk()->assertJsonPath('vision.used', false);
        $this->assertStringContainsString('plan', (string) $res->json('vision.notice'));

        $last = end($this->capturedMessages);
        $this->assertIsString($last['content']);
    }

    public function test_oversized_screenshot_dropped_with_notice(): void
    {
        $this->mockChat();
        // > 1.5MB decoded (runtime cap) but under the 2.2MB validation cap.
        $big = 'data:image/png;base64,'.base64_encode(str_repeat('a', 1_600_000));

        $res = $this->send($this->paidUser(), ['screenshot' => $big]);

        $res->assertOk()->assertJsonPath('vision.used', false);
        $this->assertStringContainsString('too large', (string) $res->json('vision.notice'));
        $last = end($this->capturedMessages);
        $this->assertIsString($last['content']);
    }

    public function test_malformed_screenshot_silently_ignored(): void
    {
        $this->mockChat();

        $res = $this->send($this->paidUser(), ['screenshot' => 'data:text/html;base64,PGI+aGk8L2I+']);

        $res->assertOk();
        $this->assertNull($res->json('vision'));
        $last = end($this->capturedMessages);
        $this->assertIsString($last['content']);
    }

    public function test_no_screenshot_keeps_legacy_response_shape(): void
    {
        $this->mockChat();

        $res = $this->send($this->paidUser());

        $res->assertOk();
        $this->assertNull($res->json('vision'));
    }
}
