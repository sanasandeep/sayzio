<?php

namespace Tests\Feature;

use App\Modules\User\Models\ConversationFlow;
use App\Modules\User\Models\ConversationSession;
use App\Modules\User\Models\ConversationStep;
use App\Modules\User\Models\ConversationStepEvent;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the conversational chat features:
 *  - Editor save endpoint (valid + invalid payloads).
 *  - All six new step kinds end-to-end (multi_select, media, file_upload,
 *    rating, datetime, ai_freetext).
 *  - Branching/conditions, validation errors, analytics rows, AI
 *    fallback routing.
 */
class ConversationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Public /cv/* endpoints are throttled (e.g. /cv/{alias}/start is
        // capped at 30/min). Tests blow past that quickly — disable
        // throttling so test order doesn't matter.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Conv ' . Str::random(4),
            'email'    => 'conv' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function makeLink(User $user): Link
    {
        // Resolve (and bind) the user's active workspace so that the
        // BelongsToWorkspace global scope on Link doesn't filter the row
        // out when the controller does route-model binding on subsequent
        // authed requests in the same test.
        $ws = app(WorkspaceContext::class)->resolve($user);
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => $ws?->id,
            'type'         => 'biolink',
            'alias'        => Link::generateAlias(),
            'title'        => 'Bio',
            'is_active'    => true,
        ]);
    }

    /** Minimum-viable valid steps payload (overridable per test). */
    private function baseSteps(array $overrides = []): array
    {
        $steps = [
            [
                'key'           => 'start',
                'kind'          => ConversationStep::KIND_MESSAGE,
                'message_text'  => 'Hi!',
                'is_entry'      => true,
                'next_step_key' => 'done',
                'choices'       => [],
            ],
            [
                'key'          => 'done',
                'kind'         => ConversationStep::KIND_END,
                'message_text' => 'Bye!',
                'choices'      => [],
            ],
        ];
        return array_replace_recursive($steps, $overrides);
    }

    private function postSave(User $user, Link $link, array $payload)
    {
        return $this->actingAs($user)
            ->postJson("/user/links/{$link->id}/conversational", $payload);
    }

    // ───────────────────────── Editor save: valid full flow ─────────────────────────

    public function test_save_persists_full_flow_with_all_six_new_step_kinds(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $payload = [
            'name'          => 'Big Flow',
            'intro_message' => 'Welcome {{name}}',
            'is_published'  => true,
            'settings'      => ['default_typing_ms' => 400],
            'actions'       => [],
            'steps' => [
                // Multi-select question.
                [
                    'key' => 'topics', 'kind' => 'question', 'is_entry' => true,
                    'message_text' => 'Pick topics', 'next_step_key' => 'pic',
                    'settings' => ['multi_select' => true, 'min_choices' => 1, 'max_choices' => 2],
                    'choices' => [
                        ['label' => 'A', 'value' => 'a'],
                        ['label' => 'B', 'value' => 'b'],
                        ['label' => 'C', 'value' => 'c'],
                    ],
                ],
                // Media bubble.
                [
                    'key' => 'pic', 'kind' => 'media', 'message_text' => 'Look',
                    'next_step_key' => 'doc',
                    'settings' => ['media' => ['url' => 'https://example.com/x.png', 'kind' => 'image']],
                    'choices' => [],
                ],
                // File upload.
                [
                    'key' => 'doc', 'kind' => 'file_upload', 'message_text' => 'Upload',
                    'next_step_key' => 'rate',
                    'settings' => ['file' => ['max_mb' => 5, 'accept' => 'pdf,png']],
                    'choices' => [],
                ],
                // Rating.
                [
                    'key' => 'rate', 'kind' => 'rating', 'message_text' => 'Score?',
                    'next_step_key' => 'when',
                    'settings' => ['rating' => ['scale' => 'star', 'min' => 1, 'max' => 5]],
                    'choices' => [],
                ],
                // Datetime.
                [
                    'key' => 'when', 'kind' => 'datetime', 'message_text' => 'When?',
                    'next_step_key' => 'free',
                    'settings' => ['datetime' => ['mode' => 'date']],
                    'choices' => [],
                ],
                // AI free-text.
                [
                    'key' => 'free', 'kind' => 'ai_freetext', 'message_text' => 'Tell me',
                    'next_step_key' => 'bye',
                    'settings' => ['ai' => [
                        'min_confidence' => 0.4,
                        'fallback_step_key' => 'bye',
                        'intents' => [
                            ['value' => 'sales', 'label' => 'Sales',   'next_step_key' => 'bye'],
                            ['value' => 'help',  'label' => 'Support', 'next_step_key' => 'bye'],
                        ],
                    ]],
                    'choices' => [],
                ],
                ['key' => 'bye', 'kind' => 'end', 'message_text' => 'Cheers', 'choices' => []],
            ],
        ];

        $resp = $this->postSave($user, $link, $payload);
        $resp->assertOk()->assertJson(['ok' => true]);

        $flow = ConversationFlow::where('link_id', $link->id)->firstOrFail();
        $this->assertSame(7, $flow->steps()->count());
        $this->assertTrue($flow->steps()->where('kind', 'ai_freetext')->exists());
    }

    // ───────────────────────── Editor save: invalid payloads ─────────────────────────

    public function test_save_rejects_invalid_regex(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $resp = $this->postSave($user, $link, [
            'steps' => [
                [
                    'key' => 'ask', 'kind' => 'input', 'is_entry' => true,
                    'message_text' => 'Email',
                    'settings' => ['input_kind' => 'text', 'validation' => ['regex' => '[unclosed']],
                    'choices' => [],
                ],
            ],
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('invalid regex', strtolower($resp->json('error') ?? ''));
    }

    public function test_save_rejects_dangling_next_step_key(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $resp = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true,
                    'message_text' => 'Hi', 'next_step_key' => 'ghost', 'choices' => []],
            ],
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('ghost', $resp->json('error') ?? '');
    }

    public function test_save_rejects_dangling_choice_next_step_key(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $resp = $this->postSave($user, $link, [
            'steps' => [
                [
                    'key' => 'q', 'kind' => 'question', 'is_entry' => true,
                    'message_text' => 'Pick',
                    'choices' => [
                        ['label' => 'X', 'value' => 'x', 'next_step_key' => 'nowhere'],
                    ],
                ],
            ],
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('nowhere', $resp->json('error') ?? '');
    }

    public function test_save_rejects_malformed_ai_intents(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // (a) Intent missing label.
        $resp = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'ai_freetext', 'is_entry' => true,
                    'message_text' => 'Hi', 'next_step_key' => 'b',
                    'settings' => ['ai' => [
                        'fallback_step_key' => 'b',
                        'intents' => [['value' => 'x']],
                    ]],
                    'choices' => []],
                ['key' => 'b', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
            ],
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('intent', strtolower($resp->json('error') ?? ''));

        // (b) Missing fallback step.
        $resp2 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'ai_freetext', 'is_entry' => true,
                    'message_text' => 'Hi', 'next_step_key' => 'b',
                    'settings' => ['ai' => [
                        'intents' => [['value' => 'x', 'label' => 'X']],
                    ]],
                    'choices' => []],
                ['key' => 'b', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
            ],
        ]);
        $resp2->assertStatus(422);
        $this->assertStringContainsString('fallback', strtolower($resp2->json('error') ?? ''));

        // (c) AI fallback references missing step.
        $resp3 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'ai_freetext', 'is_entry' => true,
                    'message_text' => 'Hi', 'next_step_key' => 'b',
                    'settings' => ['ai' => [
                        'fallback_step_key' => 'missing',
                        'intents' => [['value' => 'x', 'label' => 'X']],
                    ]],
                    'choices' => []],
                ['key' => 'b', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
            ],
        ]);
        $resp3->assertStatus(422);
        $this->assertStringContainsString('missing', strtolower($resp3->json('error') ?? ''));
    }

    public function test_save_rejects_out_of_range_typing_delays(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Per-step typing_delay_ms over 8000.
        $resp = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true,
                    'message_text' => 'Hi', 'next_step_key' => 'b',
                    'settings' => ['typing_delay_ms' => 9999], 'choices' => []],
                ['key' => 'b', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
            ],
        ]);
        $resp->assertStatus(422);
        $this->assertStringContainsString('typing delay', strtolower($resp->json('error') ?? ''));

        // Flow-level default_typing_ms over 5000 (caught by validator rules).
        $resp2 = $this->postSave($user, $link, [
            'settings' => ['default_typing_ms' => 6000],
            'steps'    => $this->baseSteps(),
        ]);
        $resp2->assertStatus(422);
    }

    public function test_save_rejects_broken_merge_tags(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Unbalanced.
        $r1 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true,
                    'message_text' => 'Hi {{name', 'choices' => []],
            ],
        ]);
        $r1->assertStatus(422);
        $this->assertStringContainsString('merge tag', strtolower($r1->json('error') ?? ''));

        // Empty {{ }}.
        $r2 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true,
                    'message_text' => 'Hi {{   }}', 'choices' => []],
            ],
        ]);
        $r2->assertStatus(422);

        // Disallowed characters inside tag.
        $r3 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true,
                    'message_text' => 'Hi {{bad-name}}', 'choices' => []],
            ],
        ]);
        $r3->assertStatus(422);
    }

    public function test_save_rejects_file_size_out_of_range(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // > 50 MB.
        $r1 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'file_upload', 'is_entry' => true,
                    'message_text' => 'Up', 'settings' => ['file' => ['max_mb' => 999]],
                    'choices' => []],
            ],
        ]);
        $r1->assertStatus(422);
        $this->assertStringContainsString('max_mb', strtolower($r1->json('error') ?? ''));

        // < 1 MB.
        $r2 = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'file_upload', 'is_entry' => true,
                    'message_text' => 'Up', 'settings' => ['file' => ['max_mb' => 0]],
                    'choices' => []],
            ],
        ]);
        $r2->assertStatus(422);
    }

    public function test_save_rejects_duplicate_step_keys_and_multiple_entries(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $dup = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true, 'message_text' => 'Hi', 'choices' => []],
                ['key' => 'a', 'kind' => 'end',     'message_text' => 'k', 'choices' => []],
            ],
        ]);
        $dup->assertStatus(422);
        $this->assertStringContainsString('unique', strtolower($dup->json('error') ?? ''));

        $multi = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'a', 'kind' => 'message', 'is_entry' => true, 'message_text' => 'Hi', 'next_step_key' => 'b', 'choices' => []],
                ['key' => 'b', 'kind' => 'end',     'is_entry' => true, 'message_text' => 'k', 'choices' => []],
            ],
        ]);
        $multi->assertStatus(422);
        $this->assertStringContainsString('one', strtolower($multi->json('error') ?? ''));
    }

    public function test_save_rejects_invalid_step_key_format(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $resp = $this->postSave($user, $link, [
            'steps' => [
                ['key' => 'BadKey!', 'kind' => 'message', 'is_entry' => true,
                    'message_text' => 'Hi', 'choices' => []],
            ],
        ]);
        $resp->assertStatus(422);
    }

    public function test_save_requires_link_owner(): void
    {
        $owner   = $this->makeUser();
        $other   = $this->makeUser();
        $link    = $this->makeLink($owner);

        $resp = $this->actingAs($other)
            ->postJson("/user/links/{$link->id}/conversational", ['steps' => $this->baseSteps()]);
        $resp->assertStatus(403);
    }

    // ───────────────────────── Public runtime ─────────────────────────

    /**
     * Build a published flow with the requested steps via the save endpoint
     * so we exercise the same code path the editor does.
     */
    private function publishFlow(User $user, Link $link, array $steps, array $extra = []): ConversationFlow
    {
        $payload = array_merge([
            'is_published' => true,
            'steps'        => $steps,
        ], $extra);
        $this->postSave($user, $link, $payload)->assertOk();
        return ConversationFlow::where('link_id', $link->id)->firstOrFail();
    }

    protected function beginVisitorSession(Link $link): array
    {
        $resp = $this->postJson("/cv/{$link->alias}/start");
        $resp->assertOk();
        return $resp->json();
    }

    public function test_multi_select_question_validates_min_max_and_advances(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            [
                'key' => 'topics', 'kind' => 'question', 'is_entry' => true,
                'message_text' => 'Pick',
                'next_step_key' => 'bye', 'answer_field' => 'topics',
                'settings' => ['multi_select' => true, 'min_choices' => 2, 'max_choices' => 3],
                'choices' => [
                    ['label' => 'A', 'value' => 'a'],
                    ['label' => 'B', 'value' => 'b'],
                    ['label' => 'C', 'value' => 'c'],
                    ['label' => 'D', 'value' => 'd'],
                ],
            ],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'Cheers', 'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $sessionId = $start['session']['id'];
        $this->assertTrue($start['step']['multi_select']);

        // Too few — validation_failed event recorded.
        $r1 = $this->postJson("/cv/{$sessionId}/answer", ['choice_values' => ['a']]);
        $r1->assertStatus(422);
        $this->assertSame(1, ConversationStepEvent::where('event', 'validation_failed')
            ->where('step_key', 'topics')->count());

        // Too many.
        $r2 = $this->postJson("/cv/{$sessionId}/answer",
            ['choice_values' => ['a', 'b', 'c', 'd']]);
        $r2->assertStatus(422);
        $this->assertSame(2, ConversationStepEvent::where('event', 'validation_failed')
            ->where('step_key', 'topics')->count());

        // Valid pick — advances to the END step (which itself needs one
        // more answer call to fire the terminal action).
        $r3 = $this->postJson("/cv/{$sessionId}/answer", ['choice_values' => ['a', 'b']]);
        $r3->assertOk()->assertJson(['done' => false, 'step' => ['key' => 'bye']]);
        $r4 = $this->postJson("/cv/{$sessionId}/answer", []);
        $r4->assertOk()->assertJson(['done' => true]);

        $session = ConversationSession::where('public_id', $sessionId)->firstOrFail();
        $this->assertSame(['a', 'b'], $session->answers['topics']);
        // ANSWERED row stores comma-joined choices.
        $answered = ConversationStepEvent::where('event', 'answered')
            ->where('step_key', 'topics')->first();
        $this->assertSame('a,b', $answered->choice_value);
    }

    public function test_media_bubble_payload_is_emitted_to_visitor(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'pic', 'kind' => 'media', 'is_entry' => true,
                'message_text' => 'Photo',
                'settings' => ['media' => ['url' => 'https://example.com/img.png', 'kind' => 'image']],
                'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $this->assertSame('media', $start['step']['kind']);
        $this->assertSame('https://example.com/img.png', $start['step']['media']['url']);
        $this->assertSame('image', $start['step']['media']['kind']);
    }

    public function test_file_upload_enforces_size_and_extension_then_advances(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'doc', 'kind' => 'file_upload', 'is_entry' => true,
                'message_text' => 'Send file', 'next_step_key' => 'bye',
                'answer_field' => 'doc',
                'settings' => ['file' => ['max_mb' => 1, 'accept' => 'pdf,png']],
                'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'Got it', 'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $sessionId = $start['session']['id'];

        // Wrong extension.
        $bad = UploadedFile::fake()->create('evil.exe', 50);
        $r1 = $this->postJson("/cv/{$sessionId}/upload", ['file' => $bad]);
        $r1->assertStatus(422);

        // Oversize (>1 MB → 2 MB sample).
        $big = UploadedFile::fake()->create('big.pdf', 2048);
        $r2 = $this->postJson("/cv/{$sessionId}/upload", ['file' => $big]);
        $r2->assertStatus(422);

        $this->assertSame(2, ConversationStepEvent::where('event', 'validation_failed')
            ->where('step_key', 'doc')->count());

        // Good file: small PDF.
        $good = UploadedFile::fake()->create('resume.pdf', 50);
        $up = $this->postJson("/cv/{$sessionId}/upload", ['file' => $good]);
        $up->assertOk()->assertJson(['ok' => true, 'name' => 'resume.pdf']);

        // Following answer call advances using the stored upload pointer
        // (lands on the END step, which then needs one more advance call).
        $ans = $this->postJson("/cv/{$sessionId}/answer", []);
        $ans->assertOk()->assertJson(['done' => false, 'step' => ['key' => 'bye']]);
        $this->postJson("/cv/{$sessionId}/answer", [])->assertOk()->assertJson(['done' => true]);

        $session = ConversationSession::where('public_id', $sessionId)->firstOrFail();
        $this->assertArrayHasKey('doc_url', $session->answers);
        $this->assertSame('resume.pdf', $session->answers['doc']);
    }

    public function test_rating_step_validates_range_and_records_value(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'rate', 'kind' => 'rating', 'is_entry' => true,
                'message_text' => 'Stars?', 'next_step_key' => 'bye',
                'answer_field' => 'stars',
                'settings' => ['rating' => ['scale' => 'star', 'min' => 1, 'max' => 5]],
                'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'Thanks', 'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $sid = $start['session']['id'];

        // Out of range.
        $bad = $this->postJson("/cv/{$sid}/answer", ['rating_value' => 99]);
        $bad->assertStatus(422);
        $this->assertSame(1, ConversationStepEvent::where('event', 'validation_failed')
            ->where('step_key', 'rate')->count());

        // Valid.
        $ok = $this->postJson("/cv/{$sid}/answer", ['rating_value' => 4]);
        $ok->assertOk()->assertJson(['done' => false, 'step' => ['key' => 'bye']]);
        $this->postJson("/cv/{$sid}/answer", [])->assertOk()->assertJson(['done' => true]);

        $session = ConversationSession::where('public_id', $sid)->firstOrFail();
        $this->assertSame(4.0, (float) $session->answers['stars']);

        $row = ConversationStepEvent::where('event', 'answered')
            ->where('step_key', 'rate')->first();
        $this->assertSame('4', $row->choice_value);
    }

    public function test_datetime_step_validates_format(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'when', 'kind' => 'datetime', 'is_entry' => true,
                'message_text' => 'When?', 'next_step_key' => 'bye',
                'answer_field' => 'when',
                'settings' => ['datetime' => ['mode' => 'date']],
                'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $sid = $start['session']['id'];

        $bad = $this->postJson("/cv/{$sid}/answer", ['datetime_value' => 'not-a-date']);
        $bad->assertStatus(422);

        $ok = $this->postJson("/cv/{$sid}/answer", ['datetime_value' => '2026-12-25']);
        $ok->assertOk()->assertJson(['done' => false, 'step' => ['key' => 'bye']]);
        $this->postJson("/cv/{$sid}/answer", [])->assertOk()->assertJson(['done' => true]);
    }

    public function test_ai_freetext_falls_back_when_no_openai_key(): void
    {
        // No OPENAI key in test env -> classifyAi returns __none__ -> route to fallback.
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'free', 'kind' => 'ai_freetext', 'is_entry' => true,
                'message_text' => 'Tell me', 'next_step_key' => 'fb',
                'answer_field' => 'reply',
                'settings' => ['ai' => [
                    'min_confidence'    => 0.4,
                    'fallback_step_key' => 'fb',
                    'intents' => [
                        ['value' => 'sales', 'label' => 'Sales', 'next_step_key' => 'sales_step'],
                        ['value' => 'help',  'label' => 'Help',  'next_step_key' => 'help_step'],
                    ],
                ]],
                'choices' => []],
            ['key' => 'sales_step', 'kind' => 'message', 'message_text' => 'Sales',
                'next_step_key' => 'bye', 'choices' => []],
            ['key' => 'help_step',  'kind' => 'message', 'message_text' => 'Help',
                'next_step_key' => 'bye', 'choices' => []],
            ['key' => 'fb',  'kind' => 'message', 'message_text' => 'Fallback',
                'next_step_key' => 'bye', 'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $sid = $start['session']['id'];

        $resp = $this->postJson("/cv/{$sid}/answer", ['input_value' => 'hello world']);
        $resp->assertOk();

        // ai_classified row tagged with __fallback__.
        $aiRow = ConversationStepEvent::where('event', 'ai_classified')
            ->where('step_key', 'free')->first();
        $this->assertNotNull($aiRow);
        $this->assertSame('__fallback__', $aiRow->choice_value);

        $session = ConversationSession::where('public_id', $sid)->firstOrFail();
        $this->assertSame('__fallback__', $session->answers['reply_intent']);
        // Path must include the fallback bubble, not sales/help.
        $this->assertContains('fb', $session->path);
        $this->assertNotContains('sales_step', $session->path);
    }

    public function test_step_level_condition_branches_on_answer(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'pick', 'kind' => 'question', 'is_entry' => true,
                'message_text' => 'Budget?', 'next_step_key' => 'low',
                'answer_field' => 'budget',
                'settings' => ['conditions' => [
                    ['op' => 'eq', 'field' => 'budget', 'value' => 'high', 'goto' => 'high'],
                ]],
                'choices' => [
                    ['label' => 'High', 'value' => 'high'],
                    ['label' => 'Low',  'value' => 'low'],
                ]],
            ['key' => 'high', 'kind' => 'message', 'message_text' => 'Premium',
                'next_step_key' => 'bye', 'choices' => []],
            ['key' => 'low',  'kind' => 'message', 'message_text' => 'Demo',
                'next_step_key' => 'bye', 'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
        ]);

        // Answer "high" → routed to 'high' branch via condition.
        $start = $this->beginVisitorSession($link);
        $sid = $start['session']['id'];
        $this->postJson("/cv/{$sid}/answer", ['choice_value' => 'high'])
            ->assertOk();
        $session = ConversationSession::where('public_id', $sid)->firstOrFail();
        $this->assertContains('high', $session->path);
        $this->assertNotContains('low', $session->path);

        // Answer "low" → falls through to step's default next_step_key 'low'.
        $start2 = $this->beginVisitorSession($link);
        $sid2 = $start2['session']['id'];
        $this->postJson("/cv/{$sid2}/answer", ['choice_value' => 'low'])
            ->assertOk();
        $s2 = ConversationSession::where('public_id', $sid2)->firstOrFail();
        $this->assertContains('low', $s2->path);
        $this->assertNotContains('high', $s2->path);
    }

    public function test_input_step_regex_validation_logs_failure(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'em', 'kind' => 'input', 'is_entry' => true,
                'message_text' => 'Email?', 'next_step_key' => 'bye',
                'answer_field' => 'email',
                'settings' => ['input_kind' => 'email'],
                'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
        ]);

        $start = $this->beginVisitorSession($link);
        $sid = $start['session']['id'];

        $bad = $this->postJson("/cv/{$sid}/answer", ['input_value' => 'not-an-email']);
        $bad->assertStatus(422);
        $this->assertSame(1, ConversationStepEvent::where('event', 'validation_failed')
            ->where('step_key', 'em')->count());

        $ok = $this->postJson("/cv/{$sid}/answer", ['input_value' => 'hi@example.com']);
        $ok->assertOk()->assertJson(['done' => false, 'step' => ['key' => 'bye']]);
        $this->postJson("/cv/{$sid}/answer", [])->assertOk()->assertJson(['done' => true]);
    }

    public function test_drop_endpoint_records_drop_event(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, $this->baseSteps());

        $start = $this->beginVisitorSession($link);
        $sid = $start['session']['id'];

        $this->postJson("/cv/{$sid}/drop")->assertOk();
        $this->assertTrue(ConversationStepEvent::where('event', 'dropped')->exists());
    }

    // ───────────────────────── Analytics ─────────────────────────

    public function test_analytics_endpoint_returns_funnel_with_new_kinds(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $this->publishFlow($user, $link, [
            ['key' => 'rate', 'kind' => 'rating', 'is_entry' => true,
                'message_text' => 'Stars', 'next_step_key' => 'free',
                'answer_field' => 'stars',
                'settings' => ['rating' => ['scale' => 'star', 'min' => 1, 'max' => 5]],
                'choices' => []],
            ['key' => 'free', 'kind' => 'ai_freetext',
                'message_text' => 'Why?', 'next_step_key' => 'fb',
                'answer_field' => 'reply',
                'settings' => ['ai' => [
                    'fallback_step_key' => 'fb',
                    'intents' => [['value' => 'sales', 'label' => 'Sales']],
                ]],
                'choices' => []],
            ['key' => 'fb',  'kind' => 'message', 'message_text' => 'fb',
                'next_step_key' => 'bye', 'choices' => []],
            ['key' => 'bye', 'kind' => 'end', 'message_text' => 'k', 'choices' => []],
        ]);

        // Drive two visitors through. Each non-END step needs its own
        // answer call; the END step itself completes on a final call.
        foreach ([5, 3] as $stars) {
            $start = $this->beginVisitorSession($link);
            $sid = $start['session']['id'];
            $this->postJson("/cv/{$sid}/answer", ['rating_value' => $stars])->assertOk();
            $this->postJson("/cv/{$sid}/answer", ['input_value' => 'hello'])->assertOk();
            $this->postJson("/cv/{$sid}/answer", [])->assertOk(); // fb (message)
            $this->postJson("/cv/{$sid}/answer", [])->assertOk()->assertJson(['done' => true]); // bye (end)
        }

        $resp = $this->actingAs($user)
            ->getJson("/user/links/{$link->id}/conversational/analytics.json");
        $resp->assertOk();
        $body = $resp->json();

        $this->assertSame(2, $body['total_sessions']);
        $this->assertSame(2, $body['completed']);

        $byKey = collect($body['funnel'])->keyBy('key');
        $this->assertEquals(4.0, $byKey['rate']['rating']['avg']);
        $this->assertSame(2, $byKey['rate']['rating']['count']);
        // AI step: both classifications fell back (no OpenAI key).
        $this->assertSame(2, $byKey['free']['ai']['fallback']);
        $this->assertEquals(100.0, $byKey['free']['ai']['fallback_pct']);
    }
}
