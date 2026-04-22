<?php

namespace Tests\Feature;

use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * End-to-end coverage for the Ask Coach chatbot.
 *
 * Ask Coach is the data-aware self-support chat: each turn fans out to
 * the AskCoachToolRegistry, splices snapshots into the system prompt,
 * then persists the assistant turn (with credits_spent + citations +
 * actions in `meta`) so the renderer can redraw it after a reload.
 *
 * Until now every part of that pipeline was only manually verified.
 * This file guards three regressions that would silently break the
 * product if they came back:
 *
 *   1. credit-deduction + citation persistence — assistant rows must
 *      record what the model spent and which tool snapshots backed
 *      the answer, otherwise the admin spend report and the "Coach
 *      knew this" panel both go blank.
 *   2. per-plan kill switch — a user whose plan is not on the
 *      Ask Coach allow-list must hit a 403 on the page itself, not
 *      just be hidden in the nav. Otherwise downgraded users keep
 *      burning credits.
 *   3. thumbs-down feedback storage — the optional note must land on
 *      the assistant message (not the thread), and `feedback=down`
 *      must persist `feedback_note` (whereas thumbs-up clears it).
 *
 * OpenAiService is a Mockery double so no network call happens and we
 * can stamp a known `credits_spent` value into the assistant row.
 */
class AskCoachTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{chat:int,last_messages:array,last_opts:array} */
    protected array $callLog = ['chat' => 0, 'last_messages' => [], 'last_opts' => []];

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
        // Default = empty allow-list, i.e. every plan can use Ask Coach.
        // Plan-toggle test overrides this to gate a specific plan.
        AiEngineSettings::setAskCoachEnabledPlans([]);
        $this->mockOpenAi(creditsSpent: 7);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Replace the shared OpenAiService with a double that records what
     * the controller asked for and returns a fixed assistant body plus
     * the supplied credits_spent value (so the persistence assertion
     * is deterministic).
     */
    protected function mockOpenAi(int $creditsSpent): void
    {
        $log =& $this->callLog;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts = []) use (&$log, $creditsSpent) {
                $log['chat']++;
                $log['last_messages'] = $messages;
                $log['last_opts']     = $opts;
                return [
                    'content'       => 'Here is what your data shows.',
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => $creditsSpent,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    protected function makeUser(string $tag = 'u', ?int $planId = null): User
    {
        return User::create([
            'name'     => "Test $tag",
            'email'    => $tag . '-' . Str::random(8) . '@example.com',
            'password' => bcrypt('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $planId,
        ]);
    }

    /**
     * Resolve the workspace the controller will see when this user
     * makes a request. Tests that pre-create AskCoachThread rows
     * outside an HTTP request need to stamp the same workspace_id, or
     * the controller's scoped lookup will 404 on them.
     */
    protected function workspaceIdFor(User $user): ?int
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        return $ws?->id;
    }

    // ── 1) thread create → send → assistant persisted with citations + credits ──

    public function test_send_persists_assistant_message_with_citations_and_credits(): void
    {
        $user = $this->makeUser('s1');

        // Step 1: create a fresh thread (POST /ask-coach).
        $this->actingAs($user)
            ->post(route('user.ai.ask-coach.store'))
            ->assertRedirect();

        $thread = AskCoachThread::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('New chat', $thread->title);

        // Step 2: send a question that the keyword router maps to the
        // 'account' tool — guarantees at least one citation is appended
        // by AskCoachToolRegistry so the persistence assertion is real.
        $question = 'How many AI credits do I have left on my plan?';
        $this->actingAs($user)
            ->post(route('user.ai.ask-coach.send', $thread->id), ['message' => $question])
            ->assertRedirect(route('user.ai.ask-coach.thread', $thread->id));

        // OpenAI was called exactly once and the snapshot block landed
        // in the system prompt — confirms the tool fan-out actually ran.
        $this->assertSame(1, $this->callLog['chat']);
        $system = $this->callLog['last_messages'][0]['content'] ?? '';
        $this->assertStringContainsString('Snapshots from the user\'s data', $system);
        $this->assertStringContainsString('[account]', $system);
        $this->assertSame('ask_coach.chat', $this->callLog['last_opts']['feature'] ?? null);

        // Two rows persisted: the user turn and the assistant turn.
        $messages = AskCoachMessage::where('thread_id', $thread->id)
            ->orderBy('id')->get();
        $this->assertCount(2, $messages);
        $this->assertSame('user',      $messages[0]->role);
        $this->assertSame($question,   $messages[0]->content);

        $assistant = $messages[1];
        $this->assertSame('assistant', $assistant->role);
        $this->assertSame('Here is what your data shows.', $assistant->content);

        // Credits the model spent must round-trip into meta so the
        // admin spend report and the "this turn cost X" UI both work.
        $meta = $assistant->meta;
        $this->assertIsArray($meta);
        $this->assertSame(7, $meta['credits_spent']);
        $this->assertContains('account', $meta['tools_used']);

        // At least one citation must be persisted — the renderer relies
        // on this to draw the "Coach knew this when answering" panel
        // after a page reload, even though the original tool output is
        // not re-run on read.
        $this->assertNotEmpty($meta['citations']);
        $sources = array_column($meta['citations'], 'source');
        $this->assertContains('account', $sources);

        // Thread should be auto-titled from the first user question and
        // its last_message_at bumped so it sorts to the top of the list.
        $thread->refresh();
        $this->assertNotSame('New chat', $thread->title);
        $this->assertNotNull($thread->last_message_at);
    }

    // ── 2) per-plan toggle blocks the page with 403 ───────────────────────────

    public function test_plan_toggle_blocks_disallowed_user_with_403(): void
    {
        // Allow-list has entries but the asker's plan slug ('free' for
        // a planless account) is not on it → must 403, not just hide.
        AiEngineSettings::setAskCoachEnabledPlans(['premium']);

        $user = $this->makeUser('p1');

        $this->actingAs($user)
            ->get(route('user.ai.ask-coach.show'))
            ->assertStatus(403);

        // Send and feedback endpoints share the same gate — if the
        // page 403s, the write endpoints must too, otherwise a blocked
        // user could still POST and burn credits / leave feedback.
        $thread = AskCoachThread::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->workspaceIdFor($user),
            'title'        => 'New chat',
        ]);
        $this->actingAs($user)
            ->post(route('user.ai.ask-coach.send', $thread->id), ['message' => 'hi'])
            ->assertStatus(403);

        // No OpenAI call should have happened while the user was blocked.
        $this->assertSame(0, $this->callLog['chat']);
    }

    // ── 3) thumbs-down feedback persists with note ────────────────────────────

    public function test_thumbs_down_feedback_saves_note_on_assistant_message(): void
    {
        $user = $this->makeUser('f1');

        $thread = AskCoachThread::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->workspaceIdFor($user),
            'title'        => 'Existing chat',
        ]);
        $assistant = AskCoachMessage::create([
            'thread_id'  => $thread->id,
            'role'       => 'assistant',
            'content'    => 'Original answer',
            'meta'       => ['credits_spent' => 3, 'citations' => []],
            'created_at' => now(),
        ]);

        // Thumbs-down with a note → both fields stored verbatim.
        $this->actingAs($user)
            ->post(route('user.ai.ask-coach.feedback', $assistant->id), [
                'feedback' => 'down',
                'note'     => 'Numbers were stale.',
            ])
            ->assertRedirect();

        $assistant->refresh();
        $this->assertSame('down', $assistant->feedback);
        $this->assertSame('Numbers were stale.', $assistant->feedback_note);

        // Thumbs-up later overwrites the down vote AND clears the note —
        // a stale negative comment must not stick to a now-positive vote.
        $this->actingAs($user)
            ->post(route('user.ai.ask-coach.feedback', $assistant->id), ['feedback' => 'up'])
            ->assertRedirect();
        $assistant->refresh();
        $this->assertSame('up', $assistant->feedback);
        $this->assertNull($assistant->feedback_note);

        // Another user can't move the needle on someone else's message —
        // feedback is scoped through the thread's owning user.
        $stranger = $this->makeUser('f2');
        $this->actingAs($stranger)
            ->post(route('user.ai.ask-coach.feedback', $assistant->id), ['feedback' => 'down', 'note' => 'evil'])
            ->assertStatus(404);
        $assistant->refresh();
        $this->assertSame('up', $assistant->feedback);
    }
}
