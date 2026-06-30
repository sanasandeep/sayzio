<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\AiResourceShare;
use App\Modules\User\Models\AskCoachThread;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\AiResourceShareService;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\ElevenLabsService;
use App\Services\AI\OpenAiService;
use App\Services\AI\PersonaRuntime;
use App\Services\AI\WhisperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Off-boarding can NOT be drained through the *shared AI runtime* — the
 * two surfaces that consume an AiMind / AiPersonaAgent at request time
 * besides the builder endpoints already pinned by Task #2938:
 *
 *   1. Ask Coach send  (POST /user/ai/ask-coach/{thread}/send)
 *   2. Voice Assistant (POST /user/ai/voice/turn)
 *
 * Task #2938 proved a suspended teammate / detached-badge holder loses
 * the "Test this Mind" / "Test this Persona" runtime. This file pins the
 * remaining shared-resource consumers so a suspended seat or detached
 * badge can never make a teammate's Mind / Persona grind AI/coin spend
 * through them.
 *
 * What the production code does today (and what these tests lock in):
 *
 *   - Ask Coach's KB resolution (AskCoachController::resolveKb ->
 *     AiMindQueryService::resolveMindsForUser) is OWNER + PLATFORM only.
 *     A Mind merely *shared with* the asker is never resolved, so passing
 *     its id grounds nothing and embeds nothing — zero retrieval/coin
 *     spend against the owner's resource. The member's OWN Mind still
 *     grounds, proving the exclusion is specific (not a dead pipeline).
 *
 *   - The Voice turn (VoiceAssistantService::runTurn + VoiceToolRegistry)
 *     only ever STT -> LLM -> tool(navigation) -> TTS. It never touches
 *     AiMindQueryService or PersonaRuntime, so a shared Mind / Persona is
 *     never invoked and never billed during a turn.
 *
 * Because both surfaces are owner-scoped / non-shared by construction,
 * these assertions PASS today whether or not the seat is suspended. They
 * are therefore forward-looking tripwires: the day someone wires a
 * shared-Mind picker into Ask Coach, or a "query a shared Mind" tool into
 * Voice, WITHOUT a live access gate, the suspended-member / detached-badge
 * cases here will start grounding/invoking the shared resource and FAIL —
 * which is exactly the regression Task #2940 exists to catch.
 */
class AiSharedRuntimeOffboardingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Captured side-effects of the mocked OpenAiService for the most
     * recent request: how many embed/chat calls happened, and the
     * messages argument from the last chat call (where KB context is
     * spliced into the system prompt).
     */
    protected array $callLog = ['embed' => 0, 'chat' => 0, 'last_chat_messages' => []];

    protected function setUp(): void
    {
        parent::setUp();
        // Both runtime surfaces 404 / refuse unless the engine is on.
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setVoiceEnabled(true);
        $this->resetOpenAiMock();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Rebind a fresh OpenAiService double against a clean call log.
     * embed() returns the constant unit vector (cosine 1.0 vs. the
     * seeded chunk embeddings) so retrieval is deterministic; chat()
     * returns a final answer with no tool calls so the loops exit after
     * one round-trip and we can read the system prompt it was given.
     */
    protected function resetOpenAiMock(): void
    {
        $this->callLog = ['embed' => 0, 'chat' => 0, 'last_chat_messages' => []];
        $log =& $this->callLog;

        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('embed')
            ->andReturnUsing(function ($user, $model, $batch, $opts = []) use (&$log) {
                $log['embed']++;
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
                    'tool_calls'    => [],
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => 0,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });

        $this->app->instance(OpenAiService::class, $mock);
    }

    // ===================================================================
    // Fixture helpers (mirror AiSharedRuntimeRevocationTest +
    // AiPersonaCoachMindSelectionTest)
    // ===================================================================

    private function user(): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
        $u->ensureDefaultWorkspace();
        return $u->fresh();
    }

    private function team(User $owner): Workspace
    {
        return Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => 'Team ' . Str::random(4),
            'slug'          => 'team-' . Str::random(6),
            'is_personal'   => false,
        ]);
    }

    private function memberOf(Workspace $ws, User $user): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $user->id,
            'role'         => 'member',
        ]);
    }

    private function badge(): AccountBadge
    {
        return AccountBadge::create(['name' => 'b' . Str::random(5), 'color' => '#3b82f6']);
    }

    /**
     * A Mind with one ready source + one chunk whose embedding is the
     * constant unit vector, so it is always the top retrieval hit and we
     * can assert its body landed (or did not land) in the system prompt
     * verbatim.
     */
    private function mindWithSource(int $userId, string $name, string $sourceTitle, string $body): AiMind
    {
        $mind = AiMind::create(['user_id' => $userId, 'name' => $name]);
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

    private function persona(User $owner): AiPersonaAgent
    {
        return AiPersonaAgent::create([
            'user_id'       => $owner->id,
            'name'          => 'Persona ' . Str::random(4),
            'system_prompt' => 'You are helpful.',
            'model'         => 'gpt-4o-mini',
        ]);
    }

    private function share(User $owner, string $type, int $resourceId, string $audienceType, int $audienceId): AiResourceShare
    {
        return app(AiResourceShareService::class)->share(
            $owner, $type, $resourceId, $audienceType, $audienceId, AiResourceShare::ACCESS_USE
        );
    }

    /**
     * Create a fresh Ask Coach thread for $user via the real store
     * endpoint so its workspace scoping matches what send() will look
     * up, then return it.
     */
    private function newThread(User $user): AskCoachThread
    {
        $this->actingAs($user)->post(route('user.ai.ask-coach.store'))->assertRedirect();
        return AskCoachThread::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function askCoachSend(User $user, AskCoachThread $thread, array $mindIds)
    {
        return $this->actingAs($user)->post(
            route('user.ai.ask-coach.send', $thread->id),
            ['message' => 'How are my links doing?', 'mind_ids' => $mindIds]
        );
    }

    private function lastSystemPrompt(): string
    {
        return (string) ($this->callLog['last_chat_messages'][0]['content'] ?? '');
    }

    // ===================================================================
    // Ask Coach send path — shared Mind is silently excluded
    // ===================================================================

    public function test_suspended_member_cannot_ground_ask_coach_on_a_shared_mind(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $sharedMind = $this->mindWithSource($owner->id, 'Owner KB', 'Owner playbook', 'OWNER-SHARED-SECRET');
        $this->share($owner, AiResourceShare::RESOURCE_MIND, $sharedMind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);

        // Off-board the teammate. The share row is untouched — only LIVE
        // membership resolution changes.
        $ms->forceFill(['suspended_at' => now()])->save();
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $sharedMind->id,
        ]);

        // Selecting ONLY the shared Mind grounds nothing: no embedding
        // call (no retrieval) and the secret never reaches the prompt —
        // i.e. zero AI/coin spend against the owner's resource.
        $thread = $this->newThread($member);
        $this->askCoachSend($member->fresh(), $thread, [$sharedMind->id])->assertRedirect();

        $this->assertSame(0, $this->callLog['embed'], 'A shared Mind must not be embedded/retrieved by an off-boarded member.');
        $this->assertStringNotContainsString('OWNER-SHARED-SECRET', $this->lastSystemPrompt());
        $this->assertStringNotContainsString('Knowledge Base context', $this->lastSystemPrompt());

        // Positive control: the member's OWN Mind still grounds, proving
        // the retrieval pipeline is live and the exclusion above is
        // specific to the (now-revoked) shared resource — not a globally
        // dead path that would pass vacuously.
        $ownMind = $this->mindWithSource($member->id, 'My KB', 'My playbook', 'MEMBER-OWN-FACT');
        $this->resetOpenAiMock();
        $this->askCoachSend($member->fresh(), $thread, [$ownMind->id])->assertRedirect();

        $this->assertSame(1, $this->callLog['embed'], 'The member\'s own Mind must still be retrieved.');
        $this->assertStringContainsString('MEMBER-OWN-FACT', $this->lastSystemPrompt());
    }

    public function test_detached_badge_holder_cannot_ground_ask_coach_on_a_shared_mind(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        // Owner must hold the badge to share into it; the recipient holds it too.
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $sharedMind = $this->mindWithSource($owner->id, 'Owner KB', 'Owner playbook', 'OWNER-BADGE-SECRET');
        $this->share($owner, AiResourceShare::RESOURCE_MIND, $sharedMind->id, AiResourceShare::AUDIENCE_BADGE, $badge->id);

        // Detach the badge from the recipient — share row untouched.
        $holder->accountBadges()->detach($badge->id);
        $this->assertDatabaseHas('ai_resource_shares', [
            'resource_type' => AiResourceShare::RESOURCE_MIND,
            'resource_id'   => $sharedMind->id,
            'audience_type' => AiResourceShare::AUDIENCE_BADGE,
            'audience_id'   => $badge->id,
        ]);

        $thread = $this->newThread($holder);
        $this->askCoachSend($holder->fresh(), $thread, [$sharedMind->id])->assertRedirect();

        $this->assertSame(0, $this->callLog['embed'], 'A shared Mind must not be embedded/retrieved by a detached-badge holder.');
        $this->assertStringNotContainsString('OWNER-BADGE-SECRET', $this->lastSystemPrompt());
        $this->assertStringNotContainsString('Knowledge Base context', $this->lastSystemPrompt());

        // Positive control: the holder's own Mind still grounds.
        $ownMind = $this->mindWithSource($holder->id, 'My KB', 'My playbook', 'HOLDER-OWN-FACT');
        $this->resetOpenAiMock();
        $this->askCoachSend($holder->fresh(), $thread, [$ownMind->id])->assertRedirect();

        $this->assertSame(1, $this->callLog['embed']);
        $this->assertStringContainsString('HOLDER-OWN-FACT', $this->lastSystemPrompt());
    }

    // ===================================================================
    // Voice turn path — never reaches a shared Mind / Persona runtime
    // ===================================================================

    /**
     * Bind the voice sub-services as zero-network doubles and install
     * SPIES for the two shared-resource runtimes. If a voice turn ever
     * invokes a shared Mind / Persona, the spy assertions will fail.
     *
     * @return array{mind:\Mockery\MockInterface,persona:\Mockery\MockInterface}
     */
    private function bindVoiceDoubles(): array
    {
        $whisper = Mockery::mock(WhisperService::class);
        $whisper->shouldReceive('transcribe')->andReturn([
            'text'          => 'open my minds',
            'credits_spent' => 2,
        ]);
        $this->app->instance(WhisperService::class, $whisper);

        $eleven = Mockery::mock(ElevenLabsService::class);
        $eleven->shouldReceive('speak')->andReturn([
            'audio'         => 'rawbytes',
            'credits_spent' => 1,
        ]);
        $this->app->instance(ElevenLabsService::class, $eleven);

        // Positive wallet balance so the turn is not short-circuited by
        // the InsufficientCoins guard; ignore any other charger calls.
        $charger = Mockery::mock(AiUsageCharger::class);
        $charger->shouldReceive('getBalance')->andReturn(100);
        $charger->shouldIgnoreMissing();
        $this->app->instance(AiUsageCharger::class, $charger);

        // The tripwire: a voice turn must NEVER reach these.
        $mindSpy = Mockery::spy(AiMindQueryService::class);
        $this->app->instance(AiMindQueryService::class, $mindSpy);

        $personaSpy = Mockery::spy(PersonaRuntime::class);
        $this->app->instance(PersonaRuntime::class, $personaSpy);

        return ['mind' => $mindSpy, 'persona' => $personaSpy];
    }

    private function voiceTurn(User $user)
    {
        return $this->actingAs($user)->post(route('user.ai.voice.turn'), [
            'audio'   => UploadedFile::fake()->create('voice.webm', 10, 'audio/webm'),
            'context' => '{}',
        ]);
    }

    public function test_suspended_member_voice_turn_never_invokes_a_shared_mind_or_persona(): void
    {
        $owner  = $this->user();
        $member = $this->user();
        $team   = $this->team($owner);
        $ms     = $this->memberOf($team, $member);

        $mind    = $this->mindWithSource($owner->id, 'Owner KB', 'Owner playbook', 'OWNER-VOICE-SECRET');
        $persona = $this->persona($owner);
        $this->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);
        $this->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_WORKSPACE, $team->id);

        $ms->forceFill(['suspended_at' => now()])->save();

        $spies = $this->bindVoiceDoubles();

        $resp = $this->voiceTurn($member->fresh());
        $resp->assertOk();

        // The shared owner's Mind / Persona runtime was never touched, so
        // no AI/coin spend could have been billed against either of them.
        $spies['mind']->shouldNotHaveReceived('ask');
        $spies['mind']->shouldNotHaveReceived('retrieveContext');
        $spies['mind']->shouldNotHaveReceived('resolveMindsForUser');
        $spies['persona']->shouldNotHaveReceived('turn');

        // Spend is bounded to the three first-party stages (STT+LLM+TTS),
        // never an extra shared-resource line item.
        $credits = $resp->json('credits');
        $this->assertSame(2, $credits['stt']);
        $this->assertSame(0, $credits['llm']);
        $this->assertSame(1, $credits['tts']);
        $this->assertSame(3, $credits['total']);
    }

    public function test_detached_badge_holder_voice_turn_never_invokes_a_shared_mind_or_persona(): void
    {
        $owner  = $this->user();
        $holder = $this->user();
        $badge  = $this->badge();
        $owner->accountBadges()->attach($badge->id);
        $holder->accountBadges()->attach($badge->id);

        $mind    = $this->mindWithSource($owner->id, 'Owner KB', 'Owner playbook', 'OWNER-VOICE-BADGE-SECRET');
        $persona = $this->persona($owner);
        $this->share($owner, AiResourceShare::RESOURCE_MIND, $mind->id, AiResourceShare::AUDIENCE_BADGE, $badge->id);
        $this->share($owner, AiResourceShare::RESOURCE_PERSONA, $persona->id, AiResourceShare::AUDIENCE_BADGE, $badge->id);

        $holder->accountBadges()->detach($badge->id);

        $spies = $this->bindVoiceDoubles();

        $resp = $this->voiceTurn($holder->fresh());
        $resp->assertOk();

        $spies['mind']->shouldNotHaveReceived('ask');
        $spies['mind']->shouldNotHaveReceived('retrieveContext');
        $spies['mind']->shouldNotHaveReceived('resolveMindsForUser');
        $spies['persona']->shouldNotHaveReceived('turn');

        $credits = $resp->json('credits');
        $this->assertSame(3, $credits['total']);
    }
}
