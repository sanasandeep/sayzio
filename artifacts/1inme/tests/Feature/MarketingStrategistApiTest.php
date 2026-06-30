<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\MarketingStrategySuggestion;
use App\Modules\User\Models\Pixel;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Task #3066 — Sanctum REST parity for the Marketing Strategist safety net
 * (mirror of {@see MarketingStrategistTest} over `/api/v1/ai/...`):
 *
 *   - generate happy path → 201 with the strategy + persisted suggestions;
 *   - out-of-coins → 402 `insufficient_credits` and NOTHING saved;
 *   - plan quantity cap → 422 `plan_limit` before any spend;
 *   - unparseable model output → 503 `ai_unavailable`, nothing saved, and
 *     the metered charge auto-refunded (no silent loss);
 *   - each apply-suggestion type builds the right OWNED object, the apply
 *     requires an explicit `confirm` (409 without), and a non-owner is 404'd.
 *
 * Authenticated requests use a real personal access token, NOT
 * Sanctum::actingAs — that injects a Mockery mock the TouchSessionToken
 * middleware can't ->save(), so every authed request would 500 (see the
 * sanctum-api-tests convention).
 */
class MarketingStrategistApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{messages:array,opts:array}> */
    protected array $chatCalls = [];

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

    // ── fixtures ───────────────────────────────────────────────────────

    private function plan(int $maxStrategies = 25): Plan
    {
        return Plan::create([
            'name'          => 'Growth',
            'slug'          => 'growth-' . Str::random(6),
            'monthly_price' => 19,
            'annual_price'  => 190,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 5,
            'features'      => [
                'max_links'                => 100,
                'max_biolinks'             => 100,
                'max_marketing_strategies' => $maxStrategies,
            ],
        ]);
    }

    private function makeUser(?Plan $plan = null): User
    {
        $plan ??= $this->plan();
        $user = User::create([
            'name'     => 'Mktg ' . Str::random(4),
            'email'    => 'mktg-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $plan->id,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function wsId(User $user): ?int
    {
        return app(WorkspaceContext::class)->resolve($user)?->id;
    }

    private function strategyFor(User $user): MarketingStrategy
    {
        $strategy = new MarketingStrategy();
        $strategy->user_id      = $user->id;
        $strategy->workspace_id = $this->wsId($user);
        $strategy->title        = 'Test plan';
        $strategy->goal         = 'Grow my audience';
        $strategy->status       = 'ready';
        $strategy->sources      = ['links'];
        $strategy->parameters   = [];
        $strategy->strategy     = ['summary' => 'x', 'organic' => [], 'paid' => [], 'kpis' => []];
        $strategy->model        = 'gpt-4o-mini';
        $strategy->credits_spent = 5;
        $strategy->save();
        return $strategy;
    }

    private function suggestion(MarketingStrategy $strategy, string $type, array $payload): MarketingStrategySuggestion
    {
        return MarketingStrategySuggestion::create([
            'strategy_id' => $strategy->id,
            'type'        => $type,
            'title'       => 'A suggestion',
            'description' => null,
            'payload'     => $payload,
            'status'      => MarketingStrategySuggestion::STATUS_PENDING,
        ]);
    }

    private function biolink(User $user, string $alias): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->wsId($user),
            'type'         => 'biolink',
            'alias'        => $alias,
            'title'        => 'My page',
            'is_active'    => true,
        ]);
    }

    private function shortLink(User $user, string $alias): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->wsId($user),
            'type'         => 'short',
            'alias'        => $alias,
            'title'        => 'A link',
            'long_url'     => 'https://example.test',
            'is_active'    => true,
        ]);
    }

    private function pixel(User $user, string $name): Pixel
    {
        return Pixel::create([
            'user_id'  => $user->id,
            'name'     => $name,
            'type'     => 'facebook',
            'pixel_id' => '1234567890',
        ]);
    }

    private function validStrategyJson(): string
    {
        return json_encode([
            'title'   => 'Creator Growth Sprint',
            'summary' => 'A focused 30-day plan to grow your link clicks.',
            'organic' => [
                ['channel' => 'Link-in-Bio', 'title' => 'Refresh your bio', 'rationale' => 'Why', 'steps' => ['Do this'], 'sayzio_features' => ['Biolink']],
            ],
            'paid' => [
                ['channel' => 'Meta Ads', 'title' => 'Boost top link', 'budget_hint' => '$5-10/day', 'rationale' => 'Why', 'steps' => ['Run ad'], 'sayzio_features' => ['Pixels']],
            ],
            'kpis'        => ['Clicks', 'Followers'],
            'suggestions' => [
                ['type' => 'create_link', 'title' => 'Add a launch link', 'description' => 'd', 'payload' => ['long_url' => 'https://launch.test', 'title' => 'Launch']],
                ['type' => 'draft_post', 'title' => 'Announce it', 'description' => 'd', 'payload' => ['title' => 'Hi', 'body' => 'Body', 'schedule_in_days' => 2]],
            ],
        ]);
    }

    private function bindChat(string $content, int $creditsSpent): void
    {
        $calls =& $this->chatCalls;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts = []) use (&$calls, $content, $creditsSpent) {
                $calls[] = ['messages' => $messages, 'opts' => $opts];
                return [
                    'content'       => $content,
                    'tool_calls'    => [],
                    'finish_reason' => 'stop',
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => $creditsSpent,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    private function bindChatThrows(\Throwable $e): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')->andThrow($e);
        $this->app->instance(OpenAiService::class, $mock);
    }

    /** Mock the STREAMED chat path to blow up so we can assert the SSE error frame. */
    private function bindChatStreamThrows(\Throwable $e): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chatStream')->andThrow($e);
        $this->app->instance(OpenAiService::class, $mock);
    }

    private function spyCharger(): \Mockery\MockInterface
    {
        $charger = Mockery::spy(AiUsageCharger::class);
        $this->app->instance(AiUsageCharger::class, $charger);
        return $charger;
    }

    // ── generate happy path → 201 + persisted strategy + suggestions ──────

    public function test_api_generate_returns_201_and_persists(): void
    {
        $user = $this->makeUser();
        $this->bindChat($this->validStrategyJson(), 8);

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/ai/marketing-strategist', [
                'goal'    => 'Grow my audience and engagement',
                'sources' => ['links', 'analytics'],
            ]);

        $resp->assertStatus(201);
        $resp->assertJsonPath('data.strategy.title', 'Creator Growth Sprint');
        $resp->assertJsonPath('data.credits_spent', 8);

        $strategy = MarketingStrategy::where('user_id', $user->id)->first();
        $this->assertNotNull($strategy);
        $this->assertSame($this->wsId($user), $strategy->workspace_id);
        $this->assertSame(2, $strategy->suggestions()->count());
    }

    // ── out-of-coins → 402 and nothing saved ──────────────────────────────

    public function test_api_generate_out_of_coins_returns_402_and_saves_nothing(): void
    {
        $user = $this->makeUser();
        $this->bindChatThrows(new InsufficientCoinsForAiException(50, 2));

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/ai/marketing-strategist', ['goal' => 'Grow']);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'insufficient_credits');
        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
    }

    // ── plan quantity cap → 422 before any spend ──────────────────────────

    public function test_api_generate_blocked_by_quantity_cap_returns_422(): void
    {
        $user = $this->makeUser($this->plan(0));
        $this->bindChat($this->validStrategyJson(), 8);

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/ai/marketing-strategist', ['goal' => 'Grow']);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'plan_limit');
        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
        $this->assertCount(0, $this->chatCalls);
    }

    // ── unparseable output → 503, nothing saved, charge auto-refunded ─────

    public function test_api_generate_parse_failure_refunds_and_saves_nothing(): void
    {
        $user = $this->makeUser();
        $this->bindChat('totally not json', 11);
        $charger = $this->spyCharger();

        $resp = $this->withToken($this->token($user))
            ->postJson('/api/v1/ai/marketing-strategist', ['goal' => 'Grow']);

        $resp->assertStatus(503);
        $resp->assertJsonPath('error.code', 'ai_unavailable');
        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            11,
            Mockery::on(fn ($o) => is_array($o) && ($o['feature'] ?? null) === \App\Services\AI\MarketingStrategistService::FEATURE),
        );
    }

    // ── apply requires explicit confirm (409 without) ─────────────────────

    public function test_api_apply_requires_confirmation(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://x.test', 'title' => 'X',
        ]);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply");

        $resp->assertStatus(409);
        $resp->assertJsonPath('error.code', 'confirmation_required');
        $this->assertSame(MarketingStrategySuggestion::STATUS_PENDING, $s->fresh()->status);
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── apply: each suggestion type builds the right owned object ──────────

    public function test_api_apply_create_link_makes_a_link(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://applied-api.test',
            'title'    => 'Applied API link',
            'alias'    => 'apiapplied-' . Str::random(5),
        ]);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertOk();
        $resp->assertJsonPath('data.status', MarketingStrategySuggestion::STATUS_APPLIED);

        $s->refresh();
        $this->assertSame('link', $s->applied_ref_type);
        $link = Link::find($s->applied_ref_id);
        $this->assertNotNull($link);
        $this->assertSame($user->id, $link->user_id);
        $this->assertSame('short', $link->type);
        $this->assertSame($this->wsId($user), $link->workspace_id);
    }

    public function test_api_apply_add_block_makes_a_block(): void
    {
        $user = $this->makeUser();
        $bio  = $this->biolink($user, 'bio' . Str::lower(Str::random(6)));
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_ADD_BLOCK, [
            'target_alias' => $bio->alias,
            'block_type'   => 'text',
            'content'      => 'Hello from the API',
        ]);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertOk();
        $s->refresh();
        $this->assertSame('biolink_block', $s->applied_ref_type);
        $block = BiolinkBlock::find($s->applied_ref_id);
        $this->assertNotNull($block);
        $this->assertSame($bio->id, $block->link_id);
        $this->assertSame('paragraph', $block->type);
    }

    public function test_api_apply_attach_pixel_attaches_to_link(): void
    {
        $user = $this->makeUser();
        $link  = $this->shortLink($user, 'lnk' . Str::lower(Str::random(6)));
        $pixel = $this->pixel($user, 'API Pixel');
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_ATTACH_PIXEL, [
            'pixel_name'   => 'API Pixel',
            'target_alias' => $link->alias,
        ]);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertOk();
        $this->assertTrue($link->fresh()->pixels()->where('pixels.id', $pixel->id)->exists());
    }

    public function test_api_apply_draft_post_makes_a_scheduled_post(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_DRAFT_POST, [
            'title'            => 'API news',
            'body'            => 'Launched via API.',
            'schedule_in_days' => 4,
        ]);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertOk();
        $s->refresh();
        $this->assertSame('creator_post', $s->applied_ref_type);
        $post = CreatorPost::find($s->applied_ref_id);
        $this->assertNotNull($post);
        $this->assertSame($user->id, $post->user_id);
        $this->assertNotNull($post->scheduled_at);
    }

    // ── apply: a non-owner cannot apply someone else's suggestion ─────────

    public function test_api_apply_non_owner_is_404(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $strategy = $this->strategyFor($owner);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://x.test', 'title' => 'X',
        ]);

        $resp = $this->withToken($this->token($other))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertNotFound();
        $this->assertSame(MarketingStrategySuggestion::STATUS_PENDING, $s->fresh()->status);
        $this->assertSame(0, Link::where('user_id', $other->id)->count());
    }

    // ── re-applying an already-applied suggestion → 422 not_pending ───────

    public function test_api_apply_twice_is_rejected_and_creates_no_duplicate(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://once-api.test',
            'title'    => 'Once',
            'alias'    => 'onceapi-' . Str::random(5),
        ]);
        $token = $this->token($user);

        // First apply succeeds and creates exactly one link.
        $this->withToken($token)
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true])
            ->assertOk();
        $this->assertSame(MarketingStrategySuggestion::STATUS_APPLIED, $s->fresh()->status);
        $this->assertSame(1, Link::where('user_id', $user->id)->count());

        // Re-applying the same (now non-pending) suggestion is rejected with
        // `not_pending` and must NOT create a second link.
        $resp = $this->withToken($token)
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'not_pending');
        $resp->assertJsonPath('error.details.status', MarketingStrategySuggestion::STATUS_APPLIED);
        $this->assertSame(MarketingStrategySuggestion::STATUS_APPLIED, $s->fresh()->status);
        $this->assertSame(1, Link::where('user_id', $user->id)->count());
    }

    // ── an applier failure → 422 apply_failed, suggestion flipped to error ─

    public function test_api_apply_failure_flips_to_error_and_creates_nothing(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        // A create_link payload with NO destination URL → the applier throws.
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'title' => 'No destination',
        ]);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/suggestions/{$s->id}/apply", ['confirm' => true]);

        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'apply_failed');
        $resp->assertJsonPath('error.message', 'The suggested link needs a valid destination URL.');
        $resp->assertJsonPath('error.details.status', MarketingStrategySuggestion::STATUS_ERROR);

        $s->refresh();
        $this->assertSame(MarketingStrategySuggestion::STATUS_ERROR, $s->status);
        $this->assertSame('The suggested link needs a valid destination URL.', $s->error);
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── chat (non-streamed): persists the assistant turn with credits_spent ─

    public function test_api_chat_persists_assistant_message_with_credits(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChat('Here is a sharper plan.', 4);

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/{$strategy->id}/chat", [
                'message' => 'Make the headline punchier',
            ]);

        $resp->assertOk();
        $resp->assertJsonPath('data.message.role', 'assistant');
        $resp->assertJsonPath('data.message.content', 'Here is a sharper plan.');
        $resp->assertJsonPath('data.message.meta.credits_spent', 4);

        // The metered turn carried the chat meter feature, not the generate one.
        $this->assertCount(1, $this->chatCalls);
        $this->assertSame(
            \App\Services\AI\MarketingStrategistService::CHAT_FEATURE,
            $this->chatCalls[0]['opts']['feature'] ?? null,
        );

        // Both the user prompt and the assistant reply are persisted, and the
        // spend is recorded in the assistant turn's meta (no silent burn).
        $assistant = $strategy->messages()->where('role', 'assistant')->first();
        $this->assertNotNull($assistant);
        $this->assertSame(4, (int) ($assistant->meta['credits_spent'] ?? 0));
        $this->assertSame(1, $strategy->messages()->where('role', 'user')->count());
    }

    // ── chat out-of-coins → 402 and NO assistant turn persisted ────────────

    public function test_api_chat_out_of_coins_returns_402_and_persists_no_assistant(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChatThrows(new InsufficientCoinsForAiException(50, 2));

        $resp = $this->withToken($this->token($user))
            ->postJson("/api/v1/ai/marketing-strategist/{$strategy->id}/chat", [
                'message' => 'Refine this for me',
            ]);

        $resp->assertStatus(402);
        $resp->assertJsonPath('error.code', 'insufficient_credits');
        $this->assertSame(0, $strategy->messages()->where('role', 'assistant')->count());
    }

    // ── chat (streamed): emits an SSE error frame when OpenAI throws ────────

    public function test_api_chat_stream_emits_error_frame_when_ai_throws(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChatStreamThrows(new \RuntimeException('boom'));

        $resp = $this->withToken($this->token($user))
            ->post("/api/v1/ai/marketing-strategist/{$strategy->id}/chat", [
                'message' => 'Refine this for me (streamed)',
                'stream'  => true,
            ], ['Accept' => 'text/event-stream']);

        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('event: error', $body);
        $this->assertStringContainsString('ai_unavailable', $body);
        $this->assertSame(0, $strategy->messages()->where('role', 'assistant')->count());
    }

    public function test_api_chat_stream_emits_insufficient_credits_error_frame(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChatStreamThrows(new InsufficientCoinsForAiException(50, 2));

        $resp = $this->withToken($this->token($user))
            ->post("/api/v1/ai/marketing-strategist/{$strategy->id}/chat", [
                'message' => 'Refine this for me (streamed, broke)',
                'stream'  => true,
            ], ['Accept' => 'text/event-stream']);

        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('event: error', $body);
        $this->assertStringContainsString('insufficient_credits', $body);
        $this->assertSame(0, $strategy->messages()->where('role', 'assistant')->count());
    }
}
