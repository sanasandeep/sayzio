<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Feature coverage for the Persona and Coach Mind-selection paths.
 *
 * Persona/Coach now accept a list of Mind ids plus a separate
 * `include_platform` toggle, resolve them through AiMindQueryService,
 * fetch context, and splice citations + KB facts into the response.
 *
 * These tests guard against silent regressions to that behaviour:
 *   - cross-tenant Mind leak (asking-user can never query someone
 *     else's Mind, even if they pass its id),
 *   - the platform default Mind being on by default (must stay
 *     opt-in to avoid an unintended global "house style" bleed),
 *   - the citation list / minds_used echo going missing once
 *     context was actually retrieved,
 *   - disabled Minds being silently honoured.
 *
 * The OpenAiService is replaced with a Mockery double so no network
 * calls occur and we can capture the system prompt the controllers
 * built — that is where Mind context is spliced in, so it is the
 * source of truth for "did the KB context land in the chat call".
 */
class AiPersonaCoachMindSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captured side-effects of the mocked OpenAiService for the most
     * recent request: how many embed/chat calls, and the messages
     * argument from the last chat call.
     */
    protected array $callLog = ['embed' => 0, 'chat' => 0, 'last_chat_messages' => []];

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
        $this->resetCallLogAndMock();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Reset the captured call log AND rebind a fresh OpenAiService
     * mock against it. Used between back-to-back requests in a single
     * test (e.g. opt-out then opt-in) so the second assertion only
     * sees what the second request did.
     */
    protected function resetCallLogAndMock(): void
    {
        $this->callLog = ['embed' => 0, 'chat' => 0, 'last_chat_messages' => []];
        $log =& $this->callLog;

        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('embed')
            ->andReturnUsing(function ($user, $model, $batch, $opts = []) use (&$log) {
                $log['embed']++;
                // Return a constant unit vector for every input — paired
                // with the constant-vector chunk embeddings created in
                // makeMindWithSource() this gives cosine = 1.0, so the
                // top-K retrieval always finds the seeded chunk and we
                // can assert on the resulting citation deterministically.
                return [
                    'vectors'       => array_map(fn () => [1.0], $batch),
                    'tokens_in'     => 0,
                    'credits_spent' => 0,
                    'model'         => $model,
                ];
            });
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts = []) use (&$log) {
                $log['chat']++;
                $log['last_chat_messages'] = $messages;
                return [
                    'content'       => 'GENERATED-OUTPUT',
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => 0,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });

        $this->app->instance(OpenAiService::class, $mock);
    }

    protected function makeUser(string $tag = 'u'): User
    {
        return User::factory()->create([
            'name' => "Test $tag",
            'email' => $tag . '-' . Str::random(8) . '@example.com',
            'role' => 'user',
        ]);
    }

    /**
     * Create a mind with a single source + a single chunk whose
     * embedding is the constant unit vector. The body is also used as
     * the chunk content so we can assert it landed in the system
     * prompt verbatim.
     *
     * For platform minds (user_id null + is_default true) we reuse any
     * mind already auto-provisioned by User::created so we never end
     * up with two "platform default" rows — Persona/Coach pick the
     * first one and a duplicate would mask the assertion.
     */
    protected function makeMindWithSource(
        ?int $userId,
        string $name,
        string $sourceTitle,
        string $body,
        bool $isDisabled = false,
        bool $isDefault = false,
    ): AiMind {
        $mind = null;
        if ($userId === null && $isDefault) {
            $mind = AiMind::whereNull('user_id')->where('is_default', true)->first();
            if ($mind) {
                $mind->update([
                    'name'        => $name,
                    'is_disabled' => $isDisabled,
                ]);
            }
        }
        if (!$mind) {
            $mind = AiMind::create([
                'user_id'     => $userId,
                'name'        => $name,
                'is_default'  => $isDefault,
                'is_disabled' => $isDisabled,
            ]);
        }
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => $sourceTitle,
            'body'    => $body,
            'status'  => AiMindSource::STATUS_READY,
        ]);
        AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            'content'   => $body,
            'tokens'    => 5,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);
        return $mind;
    }

    protected function makeLink(User $user, string $title = 'Test link'): Link
    {
        // Link uses BelongsToWorkspace which auto-scopes reads by the
        // currently-bound workspace. Test seed runs outside an HTTP
        // request so we resolve & assign workspace_id explicitly,
        // otherwise the controller's later scoped query would filter
        // it out and Coach would 404.
        $ws = app(\App\Modules\User\Services\WorkspaceContext::class)->resolve($user);
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $ws?->id,
            'type'         => 'short',
            'alias'        => Str::random(7),
            'title'        => $title,
            'long_url'     => 'https://example.com/x',
            'is_active'    => true,
        ]);
    }

    // ---------- Persona ----------

    public function test_persona_only_resolves_minds_owned_by_requesting_user(): void
    {
        $alice = $this->makeUser('alice');
        $bob   = $this->makeUser('bob');
        $bobMind = $this->makeMindWithSource(
            $bob->id, "Bob's Mind", 'Bob secret notes', 'BOB-TOP-SECRET-RECIPE',
        );

        $this->actingAs($alice)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Solo founders launching their first SaaS',
                'mind_ids' => [$bobMind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $result = session('ai.persona.result');
        $this->assertNotNull($result);
        $this->assertSame('GENERATED-OUTPUT', $result['content']);
        $this->assertSame([], $result['minds_used'], "Bob's mind must not resolve for Alice");
        $this->assertSame([], $result['citations']);
        // No embedding call because nothing resolved → no retrieval.
        $this->assertSame(0, $this->callLog['embed']);

        $system = $this->callLog['last_chat_messages'][0]['content'] ?? '';
        $this->assertStringNotContainsString('BOB-TOP-SECRET-RECIPE', $system);
        $this->assertStringNotContainsString('Knowledge Base context', $system);
    }

    public function test_persona_skips_platform_mind_unless_opted_in(): void
    {
        $user     = $this->makeUser('p1');
        $platform = $this->makeMindWithSource(
            null, 'Platform', 'Platform default', 'PLATFORM-DEFAULT-FACTS',
            isDefault: true,
        );

        // include_platform omitted → platform mind must NOT participate.
        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Indie creators',
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $result = session('ai.persona.result');
        $this->assertSame([], $result['minds_used']);
        $this->assertSame(0, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringNotContainsString('PLATFORM-DEFAULT-FACTS', $system);

        // Now opt in → resolves, retrieves, cites.
        $this->resetCallLogAndMock();
        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience'         => 'Indie creators',
                'include_platform' => '1',
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $result = session('ai.persona.result');
        $this->assertCount(1, $result['minds_used']);
        $this->assertSame((int) $platform->id, $result['minds_used'][0]['id']);
        $this->assertTrue($result['minds_used'][0]['is_platform']);
        $this->assertNotEmpty($result['citations']);
        $this->assertSame('Platform default', $result['citations'][0]['title']);
        $this->assertSame(1, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringContainsString('Knowledge Base context', $system);
        $this->assertStringContainsString('PLATFORM-DEFAULT-FACTS', $system);
    }

    public function test_persona_renders_citations_when_user_mind_returns_context(): void
    {
        $user = $this->makeUser('p2');
        $mind = $this->makeMindWithSource(
            $user->id, 'My Mind', 'Brand voice guide', 'BRAND-VOICE-GUIDE-CONTENT',
        );

        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Yoga teachers building a studio brand',
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $result = session('ai.persona.result');
        $this->assertCount(1, $result['minds_used']);
        $this->assertSame((int) $mind->id, $result['minds_used'][0]['id']);
        $this->assertFalse($result['minds_used'][0]['is_platform']);
        $this->assertCount(1, $result['citations']);
        $this->assertSame('Brand voice guide', $result['citations'][0]['title']);
        $this->assertSame((int) $mind->id, $result['citations'][0]['mind_id']);

        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringContainsString('Knowledge Base context', $system);
        $this->assertStringContainsString('BRAND-VOICE-GUIDE-CONTENT', $system);
    }

    public function test_persona_skips_disabled_minds(): void
    {
        $user = $this->makeUser('p3');
        $mind = $this->makeMindWithSource(
            $user->id, 'Old Mind', 'Stale guide', 'OLD-DISABLED-CONTENT',
            isDisabled: true,
        );

        $this->actingAs($user)
            ->post(route('user.ai.persona.generate'), [
                'audience' => 'Backend engineers',
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.persona.show'));

        $result = session('ai.persona.result');
        $this->assertSame([], $result['minds_used']);
        $this->assertSame([], $result['citations']);
        $this->assertSame(0, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringNotContainsString('OLD-DISABLED-CONTENT', $system);
    }

    // ---------- Coach ----------

    public function test_coach_only_resolves_minds_owned_by_requesting_user(): void
    {
        $alice = $this->makeUser('ca');
        $bob   = $this->makeUser('cb');
        $bobMind = $this->makeMindWithSource(
            $bob->id, "Bob's Mind", 'Bob coach playbook', 'BOB-TOP-SECRET-COACH-DATA',
        );
        $link = $this->makeLink($alice);

        $this->actingAs($alice)
            ->post(route('user.ai.coach.suggest'), [
                'link_id'  => $link->id,
                'mind_ids' => [$bobMind->id],
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $result = session('ai.coach.result');
        $this->assertNotNull($result);
        $this->assertSame('GENERATED-OUTPUT', $result['content']);
        $this->assertSame([], $result['minds_used']);
        $this->assertSame([], $result['citations']);
        $this->assertSame(0, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringNotContainsString('BOB-TOP-SECRET-COACH-DATA', $system);
    }

    public function test_coach_skips_platform_mind_unless_opted_in(): void
    {
        $user     = $this->makeUser('cp');
        $platform = $this->makeMindWithSource(
            null, 'Platform', 'Platform coach defaults', 'PLATFORM-COACH-FACTS',
            isDefault: true,
        );
        $link = $this->makeLink($user);

        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id' => $link->id,
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $result = session('ai.coach.result');
        $this->assertSame([], $result['minds_used']);
        $this->assertSame(0, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringNotContainsString('PLATFORM-COACH-FACTS', $system);

        // Opt in → must resolve, retrieve, cite.
        $this->resetCallLogAndMock();
        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id'          => $link->id,
                'include_platform' => '1',
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $result = session('ai.coach.result');
        $this->assertCount(1, $result['minds_used']);
        $this->assertSame((int) $platform->id, $result['minds_used'][0]['id']);
        $this->assertTrue($result['minds_used'][0]['is_platform']);
        $this->assertNotEmpty($result['citations']);
        $this->assertSame('Platform coach defaults', $result['citations'][0]['title']);
        $this->assertSame(1, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringContainsString('Knowledge Base context', $system);
        $this->assertStringContainsString('PLATFORM-COACH-FACTS', $system);
    }

    public function test_coach_renders_citations_when_user_mind_returns_context(): void
    {
        $user = $this->makeUser('cu');
        $mind = $this->makeMindWithSource(
            $user->id, 'My KB', 'Coach playbook', 'COACH-PLAYBOOK-FACTS',
        );
        $link = $this->makeLink($user);

        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id'  => $link->id,
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $result = session('ai.coach.result');
        $this->assertCount(1, $result['minds_used']);
        $this->assertSame((int) $mind->id, $result['minds_used'][0]['id']);
        $this->assertFalse($result['minds_used'][0]['is_platform']);
        $this->assertCount(1, $result['citations']);
        $this->assertSame('Coach playbook', $result['citations'][0]['title']);
        $this->assertSame((int) $mind->id, $result['citations'][0]['mind_id']);

        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringContainsString('Knowledge Base context', $system);
        $this->assertStringContainsString('COACH-PLAYBOOK-FACTS', $system);
    }

    public function test_coach_skips_disabled_minds(): void
    {
        $user = $this->makeUser('cd');
        $mind = $this->makeMindWithSource(
            $user->id, 'Dead', 'Old playbook', 'DEAD-COACH-CONTENT',
            isDisabled: true,
        );
        $link = $this->makeLink($user);

        $this->actingAs($user)
            ->post(route('user.ai.coach.suggest'), [
                'link_id'  => $link->id,
                'mind_ids' => [$mind->id],
            ])
            ->assertRedirect(route('user.ai.coach.show'));

        $result = session('ai.coach.result');
        $this->assertSame([], $result['minds_used']);
        $this->assertSame([], $result['citations']);
        $this->assertSame(0, $this->callLog['embed']);
        $system = $this->callLog['last_chat_messages'][0]['content'];
        $this->assertStringNotContainsString('DEAD-COACH-CONTENT', $system);
    }
}
