<?php

namespace Tests\Feature\Inbox;

use App\Modules\User\Services\Inbox\MailboxAiReplyDrafter;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientCoinsForAiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Modules\User\Models\User;

class MailboxReplyDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $bearerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->bearerToken = $this->user->createToken('test')->plainTextToken;
    }

    private function withAuth(): self
    {
        return $this->withToken($this->bearerToken);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'thread' => [
                'subject'      => 'Re: Project proposal',
                'participants' => ['alice@example.com', 'bob@example.com'],
                'messages'     => [
                    [
                        'role'   => 'inbound',
                        'sender' => 'alice@example.com',
                        'body'   => 'Hi, could you share the updated proposal by Friday?',
                    ],
                ],
            ],
        ], $overrides);
    }

    // ── Unauthenticated ──────────────────────────────────────────────────

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/mailbox/draft-reply', $this->validPayload())
            ->assertUnauthorized();
    }

    // ── Validation ───────────────────────────────────────────────────────

    public function test_missing_thread_messages_returns_422(): void
    {
        // Force the AI engine on so validation errors are not masked by a 403.
        AiEngineSettings::setEnabled(true);

        $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', ['thread' => ['subject' => 'Hi']])
            ->assertUnprocessable();
    }

    public function test_invalid_message_role_returns_422(): void
    {
        AiEngineSettings::setEnabled(true);

        $payload = $this->validPayload();
        $payload['thread']['messages'][0]['role'] = 'unknown_role';

        $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', $payload)
            ->assertUnprocessable();
    }

    public function test_too_many_knowledge_base_ids_returns_422(): void
    {
        AiEngineSettings::setEnabled(true);

        $payload = $this->validPayload(['knowledge_base_ids' => [1, 2, 3, 4, 5, 6]]);

        $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', $payload)
            ->assertUnprocessable();
    }

    // ── AI engine disabled ───────────────────────────────────────────────

    public function test_returns_403_when_ai_engine_disabled(): void
    {
        AiEngineSettings::setEnabled(false);

        $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ai_disabled');
    }

    // ── Successful draft ─────────────────────────────────────────────────

    public function test_returns_draft_on_success(): void
    {
        AiEngineSettings::setEnabled(true);

        $drafter = $this->createMock(MailboxAiReplyDrafter::class);
        $drafter->expects($this->once())
            ->method('draft')
            ->willReturn([
                'draft'         => 'Of course, I will send it over by Friday.',
                'citations'     => [],
                'credits_spent' => 5,
                'model'         => 'gpt-4o-mini',
            ]);

        $this->app->instance(MailboxAiReplyDrafter::class, $drafter);

        $response = $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', $this->validPayload());

        $response->assertOk()
            ->assertJsonPath('data.draft', 'Of course, I will send it over by Friday.')
            ->assertJsonPath('data.citations', [])
            ->assertJsonPath('data.credits_spent', 5)
            ->assertJsonPath('data.model', 'gpt-4o-mini');
    }

    public function test_passes_optional_fields_to_drafter(): void
    {
        AiEngineSettings::setEnabled(true);

        $captured = null;
        $drafter = $this->createMock(MailboxAiReplyDrafter::class);
        $drafter->expects($this->once())
            ->method('draft')
            ->with(
                $this->anything(),
                $this->anything(),
                [1, 2],
                false,
                'make it shorter',
            )
            ->willReturnCallback(function (...$args) use (&$captured) {
                $captured = $args;
                return [
                    'draft'         => 'Short reply',
                    'citations'     => [['id' => 1, 'name' => 'My KB']],
                    'credits_spent' => 8,
                    'model'         => 'gpt-4o-mini',
                ];
            });

        $this->app->instance(MailboxAiReplyDrafter::class, $drafter);

        $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', $this->validPayload([
                'knowledge_base_ids' => [1, 2],
                'include_links'      => false,
                'instruction'        => 'make it shorter',
            ]))
            ->assertOk()
            ->assertJsonPath('data.citations.0.name', 'My KB');
    }

    // ── Insufficient coins ───────────────────────────────────────────────

    public function test_returns_402_on_insufficient_coins(): void
    {
        AiEngineSettings::setEnabled(true);

        $drafter = $this->createMock(MailboxAiReplyDrafter::class);
        $drafter->expects($this->once())
            ->method('draft')
            ->willThrowException(new InsufficientCoinsForAiException(10, 2));

        $this->app->instance(MailboxAiReplyDrafter::class, $drafter);

        $response = $this->withAuth()
            ->postJson('/api/v1/mailbox/draft-reply', $this->validPayload());

        $response->assertStatus(402)
            ->assertJsonPath('error.code', 'insufficient_coins')
            ->assertJsonPath('error.details.required', 10)
            ->assertJsonPath('error.details.balance', 2);
    }

    // ── Scope isolation ──────────────────────────────────────────────────

    public function test_another_users_request_does_not_access_first_users_drafter(): void
    {
        AiEngineSettings::setEnabled(true);

        $other = User::factory()->create();
        $otherToken = $other->createToken('test2')->plainTextToken;

        $callCount = 0;
        $drafter = $this->createMock(MailboxAiReplyDrafter::class);
        $drafter->method('draft')
            ->willReturnCallback(function (...$args) use (&$callCount) {
                $callCount++;
                return ['draft' => 'ok', 'citations' => [], 'credits_spent' => 1, 'model' => 'gpt-4o-mini'];
            });

        $this->app->instance(MailboxAiReplyDrafter::class, $drafter);

        $this->withToken($otherToken)
            ->postJson('/api/v1/mailbox/draft-reply', $this->validPayload())
            ->assertOk();

        // The drafter is called exactly once for the second user's request.
        $this->assertSame(1, $callCount);
    }
}
