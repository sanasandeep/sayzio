<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
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
 *   2. per-plan gate — a user whose plan is not on the Ask Coach
 *      allow-list sees a self-serve "upgrade your plan" page (HTTP 200,
 *      not a dead-end 403) on the page itself, while the write endpoints
 *      still hard 403 so downgraded users can't keep burning credits.
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

    // ── 2) per-plan toggle: page self-serves, write endpoints 403 ─────────────

    public function test_plan_toggle_shows_self_serve_upgrade_page_but_blocks_writes(): void
    {
        // Engine is ON, but the allow-list excludes the asker's plan slug
        // ('free' for a planless account). The page must NOT dead-end —
        // it shows the plan-gated self-serve upgrade page (HTTP 200),
        // pointing at the cheapest plan that unlocks Ask Coach.
        AiEngineSettings::setAskCoachEnabledPlans(['premium']);
        Plan::create([
            'name'          => 'Premium',
            'slug'          => 'premium',
            'status'        => 'active',
            'is_archived'   => false,
            'monthly_price' => 9.00,
        ]);

        $user = $this->makeUser('p1');

        $page = $this->actingAs($user)->get(route('user.ai.ask-coach.show'));
        $page->assertOk();
        $page->assertViewIs('user.ai.disabled');
        // Plan-gated copy (engine on), not the admin-controlled "engine off"
        // copy — and a concrete self-serve upgrade CTA to the cheapest plan.
        $page->assertSee('How AI is billed');
        $page->assertSee('Upgrade to Premium');
        $page->assertDontSee('AI features are currently turned off');
        $page->assertDontSee('Request access');

        // Read-only page self-serves, but the WRITE endpoints must still hard
        // 403 — otherwise a blocked user could POST and burn credits / leave
        // feedback before upgrading.
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

    // ── 4) native tool-calling threads event_lookup's query argument ──────────

    /**
     * Task #3613 — when the model asks to call `event_lookup`, the
     * controller must decode the JSON `arguments` it supplied and pass the
     * `query` through to the tool, so the tool response fed back to the
     * model is that specific event's real stats (not a blank/whole-list
     * answer). Guards the arg-threading in the native tool loop.
     */
    public function test_native_tool_call_threads_event_lookup_query(): void
    {
        $user = $this->makeUser('ev1');

        // One ticketed event the model will "ask about" by name.
        $link = \App\Modules\User\Models\Link::create([
            'user_id'   => $user->id,
            'type'      => 'ics',
            'alias'     => 'ev' . Str::random(4),
            'title'     => 'Product Launch Party',
            'is_active' => true,
        ]);
        \App\Modules\User\Models\IcsData::create([
            'link_id'    => $link->id,
            'event_name' => 'Product Launch Party',
            'location'   => 'Main Stage',
            'start_date' => now()->addDays(5),
            'end_date'   => now()->addDays(5)->addHours(3),
            'timezone'   => 'UTC',
        ]);
        $tier = \App\Modules\User\Models\EventTicketTier::create([
            'link_id' => $link->id, 'name' => 'General', 'capacity' => 100,
        ]);
        \App\Modules\User\Models\EventTicket::create([
            'tier_id' => $tier->id, 'link_id' => $link->id,
            'attendee_name' => 'Alice', 'attendee_email' => 'alice@example.test',
            'quantity' => 3, 'code' => \App\Modules\User\Models\EventTicket::generateCode(),
            'status' => \App\Modules\User\Models\EventTicket::STATUS_VALID,
        ]);

        // Mock: first chat() emits a tool_call for event_lookup with a
        // JSON `arguments` string; second chat() returns the final answer.
        $calls = [];
        $step = 0;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($u, $model, $messages, $opts = []) use (&$calls, &$step) {
                $calls[] = $messages;
                $step++;
                if ($step === 1) {
                    return [
                        'content'    => '',
                        'tool_calls' => [[
                            'id'       => 'call_1',
                            'type'     => 'function',
                            'function' => [
                                'name'      => 'event_lookup',
                                'arguments' => json_encode(['query' => 'Product Launch Party']),
                            ],
                        ]],
                        'credits_spent' => 2, 'model' => $model, 'raw' => [],
                    ];
                }
                return [
                    'content' => 'Your launch party has sold 3 tickets.',
                    'tool_calls' => [], 'credits_spent' => 1, 'model' => $model, 'raw' => [],
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);

        $thread = AskCoachThread::create([
            'user_id'      => $user->id,
            'workspace_id' => $this->workspaceIdFor($user),
            'title'        => 'New chat',
        ]);
        $this->actingAs($user)
            ->post(route('user.ai.ask-coach.send', $thread->id), ['message' => 'How did the Product Launch Party do?'])
            ->assertRedirect(route('user.ai.ask-coach.thread', $thread->id));

        // The second call must carry a tool message with the resolved
        // event stats — proof the query argument was decoded and used.
        $this->assertGreaterThanOrEqual(2, count($calls));
        $toolMessages = array_values(array_filter(
            $calls[1],
            fn ($m) => ($m['role'] ?? '') === 'tool'
        ));
        $this->assertNotEmpty($toolMessages);
        $toolContent = implode("\n", array_column($toolMessages, 'content'));
        $this->assertStringContainsString('Product Launch Party', $toolContent);
        $this->assertStringContainsString('3 sold of 100 capacity', $toolContent);
        // Never leak attendee PII into the tool response.
        $this->assertStringNotContainsString('alice@example.test', $toolContent);

        // The event_lookup tool must be recorded as used on the turn.
        $assistant = AskCoachMessage::where('thread_id', $thread->id)
            ->where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertContains('event_lookup', $assistant->meta['tools_used']);
    }
}
