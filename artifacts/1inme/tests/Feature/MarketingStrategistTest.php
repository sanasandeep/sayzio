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
use App\Services\AI\MarketingStrategistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Task #3066 — guards that paid AI Marketing Strategist generations can
 * never silently fail to generate (lose money without a result) and that
 * one-click "apply suggestion" actions only ever create OWNED objects.
 *
 * This file covers the shared {@see MarketingStrategistService} pipeline
 * plus the WEB controller surface; {@see MarketingStrategistApiTest}
 * mirrors the same contract over the Sanctum REST API for parity.
 *
 * The contract under test here:
 *   1. a successful generation persists a strategy + its suggestions and
 *      leaves the metered charge standing (no refund), tagging the chat
 *      call with the `marketing_strategist` feature so OpenAiService meters
 *      the right spend;
 *   2. an unparseable model response refunds the EXACT credits charged
 *      against `marketing_strategist` and persists NOTHING (no silent loss);
 *   3. an out-of-coins generation surfaces an error and saves nothing — and
 *      never reaches a refund because the charge never landed;
 *   4. the per-plan `max_marketing_strategies` quantity cap blocks creation
 *      before any spend;
 *   5. each apply-suggestion type builds the correct owned object, and a
 *      non-owner is 404'd.
 *
 * OpenAiService::chat() is a Mockery double (no network); AiUsageCharger is
 * a Mockery spy where the refund branch must be asserted precisely.
 */
class MarketingStrategistTest extends TestCase
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
        // A PAID plan (price > 0, non-'free' slug) so the availability gate
        // (`marketing_strategist` => !isOnFreePlan()) unlocks the feature.
        return Plan::create([
            'name'          => 'Growth',
            'slug'          => 'growth-' . Str::random(6),
            'monthly_price' => 19,
            'annual_price'  => 190,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 5,
            'features'      => [
                'max_links'                 => 100,
                'max_biolinks'              => 100,
                'max_marketing_strategies'  => $maxStrategies,
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
        $mock = Mockery::mock(\App\Services\AI\OpenAiService::class);
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
        $this->app->instance(\App\Services\AI\OpenAiService::class, $mock);
    }

    private function bindChatThrows(\Throwable $e): void
    {
        $mock = Mockery::mock(\App\Services\AI\OpenAiService::class);
        $mock->shouldReceive('chat')->andThrow($e);
        $this->app->instance(\App\Services\AI\OpenAiService::class, $mock);
    }

    /** Mock the STREAMED chat path: stream one chunk, then return the turn. */
    private function bindChatStream(string $content, int $creditsSpent): void
    {
        $mock = Mockery::mock(\App\Services\AI\OpenAiService::class);
        $mock->shouldReceive('chatStream')
            ->andReturnUsing(function ($user, $model, $messages, $opts, $onChunk) use ($content, $creditsSpent) {
                $onChunk($content);
                return [
                    'content'       => $content,
                    'credits_spent' => $creditsSpent,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });
        $this->app->instance(\App\Services\AI\OpenAiService::class, $mock);
    }

    /** Mock the STREAMED chat path to blow up so we can assert the SSE error frame. */
    private function bindChatStreamThrows(\Throwable $e): void
    {
        $mock = Mockery::mock(\App\Services\AI\OpenAiService::class);
        $mock->shouldReceive('chatStream')->andThrow($e);
        $this->app->instance(\App\Services\AI\OpenAiService::class, $mock);
    }

    private function spyCharger(): \Mockery\MockInterface
    {
        $charger = Mockery::spy(AiUsageCharger::class);
        $this->app->instance(AiUsageCharger::class, $charger);
        return $charger;
    }

    private function service(): MarketingStrategistService
    {
        return app(MarketingStrategistService::class);
    }

    // ── 1. service: success persists strategy + suggestions, charge stands ─

    public function test_generate_persists_strategy_and_keeps_charge(): void
    {
        $user = $this->makeUser();
        $this->bindChat($this->validStrategyJson(), 9);
        $charger = $this->spyCharger();

        $result = $this->service()->generate($user, 'Grow my audience', [], ['links'], $this->wsId($user));

        // The chat call carried the right meter feature.
        $this->assertCount(1, $this->chatCalls);
        $this->assertSame(MarketingStrategistService::FEATURE, $this->chatCalls[0]['opts']['feature'] ?? null);

        $this->assertSame(9, $result['credits_spent']);

        $strategy = $result['strategy'];
        $this->assertInstanceOf(MarketingStrategy::class, $strategy);
        $this->assertTrue($strategy->exists);
        $this->assertSame($user->id, $strategy->user_id);
        $this->assertSame('Creator Growth Sprint', $strategy->title);
        $this->assertSame(1, MarketingStrategy::where('user_id', $user->id)->count());
        $this->assertSame(2, $strategy->suggestions()->count());

        // Success ⇒ no refund.
        $charger->shouldNotHaveReceived('refund');
    }

    // ── 2. service: unparseable response → exact refund, nothing saved ────

    public function test_generate_refunds_credits_when_response_unparseable(): void
    {
        $user = $this->makeUser();
        $this->bindChat('this is definitely not json', 12);
        $charger = $this->spyCharger();

        try {
            $this->service()->generate($user, 'Grow', [], ['links'], $this->wsId($user));
            $this->fail('Expected generate() to throw on unparseable output.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
        $this->assertSame(0, MarketingStrategySuggestion::count());
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            12,
            Mockery::on(fn ($o) => is_array($o) && ($o['feature'] ?? null) === MarketingStrategistService::FEATURE),
        );
    }

    // ── 3. service: out-of-coins → no charge, no save, no refund ──────────

    public function test_generate_propagates_insufficient_coins_without_saving(): void
    {
        $user = $this->makeUser();
        $this->bindChatThrows(new InsufficientCoinsForAiException(50, 3));
        $charger = $this->spyCharger();

        try {
            $this->service()->generate($user, 'Grow', [], ['links'], $this->wsId($user));
            $this->fail('Expected generate() to surface InsufficientCoinsForAiException.');
        } catch (InsufficientCoinsForAiException $e) {
            $this->assertSame(402, $e->getCode());
        }

        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
        // The charge never landed, so there is nothing to refund.
        $charger->shouldNotHaveReceived('refund');
    }

    // ── 4. web: store happy path persists + redirects to the strategy ─────

    public function test_web_store_generates_and_redirects(): void
    {
        $user = $this->makeUser();
        $this->bindChat($this->validStrategyJson(), 6);

        $resp = $this->actingAs($user, 'web')->post(route('user.ai.marketing-strategist.store'), [
            'goal'    => 'Grow my audience and engagement',
            'sources' => ['links', 'analytics'],
        ]);

        $strategy = MarketingStrategy::where('user_id', $user->id)->first();
        $this->assertNotNull($strategy);
        $resp->assertRedirect(route('user.ai.marketing-strategist.show', $strategy->id));
        $this->assertSame(2, $strategy->suggestions()->count());
    }

    // ── 5. web: out-of-coins surfaces an error and saves nothing ──────────

    public function test_web_store_out_of_coins_shows_error_and_saves_nothing(): void
    {
        $user = $this->makeUser();
        $this->bindChatThrows(new InsufficientCoinsForAiException(50, 1));

        $resp = $this->actingAs($user, 'web')
            ->from(route('user.ai.marketing-strategist.create'))
            ->post(route('user.ai.marketing-strategist.store'), ['goal' => 'Grow']);

        $resp->assertRedirect(route('user.ai.marketing-strategist.create'));
        $resp->assertSessionHas('error');
        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
    }

    // ── 6. web: plan quantity cap blocks before any spend ─────────────────

    public function test_web_store_blocked_by_quantity_cap(): void
    {
        $user = $this->makeUser($this->plan(0));
        // Even if the model would answer, the cap must short-circuit first.
        $this->bindChat($this->validStrategyJson(), 6);

        $resp = $this->actingAs($user, 'web')
            ->from(route('user.ai.marketing-strategist.create'))
            ->post(route('user.ai.marketing-strategist.store'), ['goal' => 'Grow']);

        $resp->assertRedirect(route('user.ai.marketing-strategist.create'));
        $resp->assertSessionHas('error');
        $this->assertSame(0, MarketingStrategy::where('user_id', $user->id)->count());
        // The cap short-circuited before the model was ever called.
        $this->assertCount(0, $this->chatCalls);
    }

    // ── 7. web apply: each suggestion type builds the right owned object ───

    public function test_web_apply_create_link_makes_a_link(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://applied-link.test',
            'title'    => 'Applied link',
            'alias'    => 'applied-' . Str::random(5),
        ]);

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertOk();
        $resp->assertJsonPath('status', MarketingStrategySuggestion::STATUS_APPLIED);

        $s->refresh();
        $this->assertSame('link', $s->applied_ref_type);
        $link = Link::find($s->applied_ref_id);
        $this->assertNotNull($link);
        $this->assertSame($user->id, $link->user_id);
        $this->assertSame('short', $link->type);
        $this->assertSame('https://applied-link.test', $link->long_url);
        $this->assertSame($this->wsId($user), $link->workspace_id);
    }

    public function test_web_apply_add_block_makes_a_block(): void
    {
        $user = $this->makeUser();
        $bio  = $this->biolink($user, 'bio' . Str::lower(Str::random(6)));
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_ADD_BLOCK, [
            'target_alias' => $bio->alias,
            'block_type'   => 'heading',
            'content'      => 'Welcome!',
        ]);

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertOk();
        $s->refresh();
        $this->assertSame('biolink_block', $s->applied_ref_type);
        $block = BiolinkBlock::find($s->applied_ref_id);
        $this->assertNotNull($block);
        $this->assertSame($bio->id, $block->link_id);
        $this->assertSame('heading', $block->type);
    }

    public function test_web_apply_attach_pixel_attaches_to_link(): void
    {
        $user = $this->makeUser();
        $link  = $this->shortLink($user, 'lnk' . Str::lower(Str::random(6)));
        $pixel = $this->pixel($user, 'My Meta Pixel');
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_ATTACH_PIXEL, [
            'pixel_name'   => 'My Meta Pixel',
            'target_alias' => $link->alias,
        ]);

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertOk();
        $s->refresh();
        $this->assertSame('link', $s->applied_ref_type);
        $this->assertTrue($link->fresh()->pixels()->where('pixels.id', $pixel->id)->exists());
    }

    public function test_web_apply_draft_post_makes_a_scheduled_post(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_DRAFT_POST, [
            'title'            => 'Big news',
            'body'            => 'We just launched.',
            'schedule_in_days' => 5,
        ]);

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertOk();
        $s->refresh();
        $this->assertSame('creator_post', $s->applied_ref_type);
        $post = CreatorPost::find($s->applied_ref_id);
        $this->assertNotNull($post);
        $this->assertSame($user->id, $post->user_id);
        $this->assertNotNull($post->scheduled_at);
        $this->assertNull($post->published_at);
    }

    // ── 8. web apply: a non-owner cannot apply someone else's suggestion ──

    public function test_web_apply_non_owner_is_404(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $strategy = $this->strategyFor($owner);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://x.test', 'title' => 'X',
        ]);

        $resp = $this->actingAs($other, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertNotFound();
        $s->refresh();
        $this->assertSame(MarketingStrategySuggestion::STATUS_PENDING, $s->status);
        $this->assertSame(0, Link::where('user_id', $other->id)->count());
    }

    // ── 9. web apply: re-applying an already-applied suggestion is rejected ─

    public function test_web_apply_twice_is_rejected_and_creates_no_duplicate(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'long_url' => 'https://once.test',
            'title'    => 'Once',
            'alias'    => 'once-' . Str::random(5),
        ]);

        // First apply succeeds and creates exactly one link.
        $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id))
            ->assertOk();
        $this->assertSame(MarketingStrategySuggestion::STATUS_APPLIED, $s->fresh()->status);
        $this->assertSame(1, Link::where('user_id', $user->id)->count());

        // Re-applying the same (now non-pending) suggestion is rejected and
        // must NOT create a second link.
        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertStatus(422);
        $this->assertSame(MarketingStrategySuggestion::STATUS_APPLIED, $s->fresh()->status);
        $this->assertSame(1, Link::where('user_id', $user->id)->count());
    }

    // ── 10. web apply: an applier failure flips the suggestion to `error` ──

    public function test_web_apply_failure_flips_to_error_and_creates_nothing(): void
    {
        $user = $this->makeUser();
        $strategy = $this->strategyFor($user);
        // A create_link payload with NO destination URL → the applier throws.
        $s = $this->suggestion($strategy, MarketingStrategySuggestion::TYPE_CREATE_LINK, [
            'title' => 'No destination',
        ]);

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.suggestions.apply', $s->id));

        $resp->assertStatus(422);
        $resp->assertJsonPath('status', MarketingStrategySuggestion::STATUS_ERROR);
        $resp->assertJsonPath('error.message', 'The suggested link needs a valid destination URL.');

        $s->refresh();
        $this->assertSame(MarketingStrategySuggestion::STATUS_ERROR, $s->status);
        $this->assertSame('The suggested link needs a valid destination URL.', $s->error);
        $this->assertSame(0, Link::where('user_id', $user->id)->count());
    }

    // ── 11. web chat (streamed): happy path persists the assistant turn ─────

    public function test_web_chat_stream_persists_assistant_with_credits(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChatStream('A sharper refinement.', 3);

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.chat', $strategy->id), [
                'message' => 'Make it punchier',
            ]);

        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('event: done', $body);

        $assistant = $strategy->messages()->where('role', 'assistant')->first();
        $this->assertNotNull($assistant);
        $this->assertSame(3, (int) ($assistant->meta['credits_spent'] ?? 0));
        $this->assertTrue((bool) ($assistant->meta['streamed'] ?? false));
        $this->assertSame(1, $strategy->messages()->where('role', 'user')->count());
    }

    // ── 10. web chat out-of-coins → SSE error frame, no assistant turn ─────

    public function test_web_chat_out_of_coins_streams_error_and_saves_no_assistant(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChatStreamThrows(new InsufficientCoinsForAiException(50, 1));

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.chat', $strategy->id), [
                'message' => 'Refine this for me',
            ]);

        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('event: error', $body);
        $this->assertStringContainsString('insufficient_credits', $body);
        $this->assertSame(0, $strategy->messages()->where('role', 'assistant')->count());
    }

    // ── 11. web chat: SSE error frame when OpenAI throws ──────────────────

    public function test_web_chat_stream_emits_error_frame_when_ai_throws(): void
    {
        $user     = $this->makeUser();
        $strategy = $this->strategyFor($user);
        $this->bindChatStreamThrows(new \RuntimeException('boom'));

        $resp = $this->actingAs($user, 'web')
            ->post(route('user.ai.marketing-strategist.chat', $strategy->id), [
                'message' => 'Refine this for me',
            ]);

        $resp->assertOk();
        $body = $resp->streamedContent();
        $this->assertStringContainsString('event: error', $body);
        $this->assertStringContainsString('ai_unavailable', $body);
        $this->assertSame(0, $strategy->messages()->where('role', 'assistant')->count());
    }
}
