<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindChunk;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Modules\User\Models\CompanionMessage;
use App\Modules\User\Models\CompanionThread;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Citations rendered inside Ask Coach and Companion message bubbles must
 * link to the corresponding Mind source detail page when both `mind_id`
 * and source `id` are present, and fall back to plain text otherwise.
 *
 * The Persona/Coach result page already renders linkable citations
 * (covered by MindCitationLinkTest); this test guards the same behavior
 * inside the chat threads so creators can verify answers mid-conversation.
 */
class AiChatThreadCitationLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setAskCoachEnabledPlans([]);

        // Stub OpenAiService so Companion's send() path doesn't make a
        // network call when retrieveContext + chat fire.
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('embed')->andReturnUsing(
            fn ($user, $model, $batch, $opts = []) => [
                'vectors'       => array_map(fn () => [1.0], $batch),
                'tokens_in'     => 0,
                'credits_spent' => 0,
                'model'         => $model,
            ],
        );
        $mock->shouldReceive('chat')->andReturnUsing(
            fn ($user, $model, $messages, $opts = []) => [
                'content'       => 'Stubbed assistant reply.',
                'tokens_in'     => 0,
                'tokens_out'    => 0,
                'credits_spent' => 0,
                'model'         => $model,
                'raw'           => [],
            ],
        );
        $this->app->instance(OpenAiService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Cite Chat Tester',
            'role' => 'user',
        ]);
    }

    protected function seedMindWithSource(int $userId): array
    {
        $mind = AiMind::create([
            'user_id'     => $userId,
            'name'        => 'Chat Verifiable Mind',
            'is_default'  => false,
            'is_disabled' => false,
        ]);
        $src = AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => 'Chat verifiable source title',
            'body'    => 'CHAT-VERIFIABLE-BODY',
            'status'  => AiMindSource::STATUS_READY,
        ]);
        AiMindChunk::create([
            'mind_id'   => $mind->id,
            'source_id' => $src->id,
            'ord'       => 0,
            'content'   => 'CHAT-VERIFIABLE-BODY',
            'tokens'    => 5,
            'embedding' => [1.0],
            'model'     => 'text-embedding-3-small',
        ]);
        return [$mind, $src];
    }

    protected function workspaceIdFor(User $user): ?int
    {
        return app(WorkspaceContext::class)->resolve($user)?->id;
    }

    public function test_ask_coach_thread_renders_mind_citation_as_link(): void
    {
        $user = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($user->id);

        // Seed a thread + assistant message whose meta has a citation
        // shaped like the Mind retrieval output (id + mind_id + title).
        $thread = AskCoachThread::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->workspaceIdFor($user),
            'title'        => 'Cite chat',
        ]);
        AskCoachMessage::create([
            'thread_id'  => $thread->id,
            'role'       => 'user',
            'content'    => 'What does my mind say?',
            'created_at' => now(),
        ]);
        AskCoachMessage::create([
            'thread_id'  => $thread->id,
            'role'       => 'assistant',
            'content'    => 'Per your Mind…',
            'meta'       => [
                'credits_spent' => 1,
                'citations'     => [
                    [
                        'id'      => $src->id,
                        'mind_id' => $mind->id,
                        'title'   => 'Chat verifiable source title',
                        'type'    => 'text',
                        'score'   => 0.9,
                    ],
                    // Legacy tool citation — must fall back to plain text.
                    ['label' => 'Your account', 'source' => 'account'],
                ],
            ],
            'created_at' => now(),
        ]);

        $href = route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id]);
        $expectedAttr = 'href="' . e($href) . '"';

        $this->actingAs($user)
            ->get(route('user.ai.ask-coach.thread', $thread->id))
            ->assertOk()
            ->assertSee($expectedAttr, false)
            ->assertSee('Chat verifiable source title')
            // Legacy tool citation still displays its label as plain text.
            ->assertSee('Your account');
    }

    public function test_companion_thread_renders_mind_citation_as_link(): void
    {
        $user = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($user->id);

        $thread = CompanionThread::create([
            'user_id'          => $user->id,
            'workspace_id'     => $this->workspaceIdFor($user),
            'title'            => 'Companion cite chat',
            'mind_ids'         => [$mind->id],
            'include_platform' => false,
        ]);
        CompanionMessage::create([
            'thread_id'  => $thread->id,
            'role'       => 'user',
            'content'    => 'Reference my mind please.',
            'created_at' => now(),
        ]);
        CompanionMessage::create([
            'thread_id'  => $thread->id,
            'role'       => 'assistant',
            'content'    => 'Sure — see source below.',
            'meta'       => [
                'credits_spent' => 2,
                'citations'     => [
                    [
                        'id'      => $src->id,
                        'mind_id' => $mind->id,
                        'title'   => 'Chat verifiable source title',
                        'type'    => 'text',
                        'score'   => 0.88,
                    ],
                ],
            ],
            'created_at' => now(),
        ]);

        $href = route('user.minds.sources.show', ['mind' => $mind->id, 'source' => $src->id]);
        $expectedAttr = 'href="' . e($href) . '"';

        $this->actingAs($user)
            ->get(route('user.ai.companion.thread', $thread->id))
            ->assertOk()
            ->assertSee($expectedAttr, false)
            ->assertSee('Chat verifiable source title');
    }

    public function test_companion_send_persists_mind_citations_in_meta(): void
    {
        $user = $this->makeUser();
        [$mind, $src] = $this->seedMindWithSource($user->id);

        $thread = CompanionThread::create([
            'user_id'          => $user->id,
            'workspace_id'     => $this->workspaceIdFor($user),
            'title'            => 'Persisted cite',
            'mind_ids'         => [$mind->id],
            'include_platform' => false,
        ]);

        $this->actingAs($user)
            ->post(route('user.ai.companion.send', $thread->id), ['message' => 'hello'])
            ->assertRedirect(route('user.ai.companion.thread', $thread->id));

        $assistant = CompanionMessage::query()
            ->where('thread_id', $thread->id)
            ->where('role', 'assistant')
            ->latest('id')
            ->firstOrFail();

        $this->assertIsArray($assistant->meta);
        $this->assertArrayHasKey('citations', $assistant->meta);
        $this->assertNotEmpty($assistant->meta['citations']);
        $first = $assistant->meta['citations'][0];
        $this->assertSame($src->id, (int) $first['id']);
        $this->assertSame($mind->id, (int) $first['mind_id']);
    }
}
