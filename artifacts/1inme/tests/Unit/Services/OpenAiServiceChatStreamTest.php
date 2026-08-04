<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\User;
use App\Services\AI\AiUsageCharger;
use App\Services\Billing\WalletService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pin the SSE-parsing + usage-accounting contract of
 * {@see OpenAiService::chatStream()}.
 *
 * The helper is the only place that:
 *   - splits the streaming HTTP body into SSE frames,
 *   - extracts each `delta.content` token and re-emits it via
 *     `$onChunk`,
 *   - merges the final `usage` frame into our credit ledger so the
 *     spend report reflects the true OpenAI billing instead of our
 *     pre-call worst-case estimate.
 *
 * A regression here would either drop tokens (the widget would render
 * a truncated reply) or under/over-charge the user — both silent and
 * easy to ship. The test feeds a canned SSE body via Http::fake so we
 * can assert the parser end-to-end without touching the network.
 */
class OpenAiServiceChatStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-key');
        // Single test model with non-zero rates so we can prove the
        // ledger entry was written from the parsed usage frame.
        AiEngineSettings::setModels([
            ['name' => 'gpt-test', 'kind' => 'chat', 'enabled' => true,
             'in_coins_per_1k' => 1000, 'out_coins_per_1k' => 1000],
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'name' => 'Streamer',
            'role' => 'user',
        ]);
    }

    public function test_chat_stream_parses_sse_body_and_records_usage(): void
    {
        $user = $this->makeUser();
        // Fund the user so the worst-case prepay gate clears even
        // though our test rates are deliberately generous.
        app(WalletService::class)->credit($user, 5_000, ['reason' => 'test seed']);

        // Canned SSE body exercising the realistic shape OpenAI sends:
        //   - multiple delta frames whose content concatenates into the
        //     full assistant reply,
        //   - an empty delta + finish_reason,
        //   - a final usage-only frame (stream_options.include_usage),
        //   - the [DONE] sentinel.
        // CRLF is intentional on one frame to prove the parser
        // normalises line endings before splitting on \n\n.
        $sse  = "data: {\"id\":\"chatcmpl-stream-1\",\"choices\":[{\"delta\":{\"role\":\"assistant\"}}]}\n\n";
        $sse .= "data: {\"id\":\"chatcmpl-stream-1\",\"choices\":[{\"delta\":{\"content\":\"Hello\"}}]}\n\n";
        $sse .= "data: {\"id\":\"chatcmpl-stream-1\",\"choices\":[{\"delta\":{\"content\":\", \"}}]}\r\n\r\n";
        $sse .= "data: {\"id\":\"chatcmpl-stream-1\",\"choices\":[{\"delta\":{\"content\":\"world!\"}}]}\n\n";
        $sse .= "data: {\"id\":\"chatcmpl-stream-1\",\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}]}\n\n";
        $sse .= "data: {\"id\":\"chatcmpl-stream-1\",\"choices\":[],\"usage\":{\"prompt_tokens\":42,\"completion_tokens\":17,\"total_tokens\":59}}\n\n";
        $sse .= "data: [DONE]\n\n";

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(
                $sse,
                200,
                ['Content-Type' => 'text/event-stream']
            ),
        ]);

        $deltas = [];
        $svc = app(OpenAiService::class);

        $result = $svc->chatStream(
            $user,
            'gpt-test',
            [['role' => 'user', 'content' => 'hi']],
            ['feature' => 'unit_test', 'reason' => 'unit stream'],
            function (string $delta) use (&$deltas) { $deltas[] = $delta; }
        );

        // The chunk callback received only the non-empty content
        // deltas, in order, so the widget renders the reply token by
        // token without leaking the role-only or finish-only frames.
        $this->assertSame(['Hello', ', ', 'world!'], $deltas);

        // Final return value reconstructs the full assistant message
        // from those same deltas — this is what the runtime persists
        // into the assistant row.
        $this->assertSame('Hello, world!', $result['content']);

        // Usage came from the trailing usage frame, NOT from the
        // pre-call worst-case estimate. If the parser ever stops
        // reading that frame we'd silently overcharge users by the
        // estimate instead of the truth.
        $this->assertSame(42, $result['tokens_in']);
        $this->assertSame(17, $result['tokens_out']);

        // Cost = (42 + 17) tokens * 1000 credits / 1000 = 59 credits,
        // and the ledger row was actually written under our feature tag.
        $this->assertSame(59, $result['credits_spent']);
        $this->assertSame('gpt-test', $result['model']);

        $tx = \App\Modules\User\Models\WalletTransaction::where('user_id', $user->id)
            ->where('type', 'spend')
            ->where('meta->ai', true)
            ->where('meta->feature', 'unit_test')
            ->latest('id')->first();
        $this->assertNotNull($tx);
        $this->assertSame(-59, (int) $tx->delta_coins);
        $this->assertSame(42,  (int) ($tx->meta['tokens_in'] ?? null));
        $this->assertSame(17,  (int) ($tx->meta['tokens_out'] ?? null));
        // The streamed call_id from the SSE frames must round-trip
        // into meta so support can correlate ledger rows with OpenAI
        // dashboard entries when investigating disputes.
        $this->assertSame('chatcmpl-stream-1', $tx->meta['call_id'] ?? null);
        $this->assertTrue((bool) ($tx->meta['streamed'] ?? false));
    }

    public function test_chat_stream_falls_back_to_estimates_when_usage_frame_is_missing(): void
    {
        $user = $this->makeUser();
        app(WalletService::class)->credit($user, 5_000, ['reason' => 'test seed']);

        // No usage frame here — older OpenAI compat servers and some
        // proxies omit it. The helper must fall back to its own
        // estimator so we still charge *something* and don't ship a
        // free-of-charge regression.
        $sse  = "data: {\"id\":\"x\",\"choices\":[{\"delta\":{\"content\":\"abcd\"}}]}\n\n";
        $sse .= "data: [DONE]\n\n";

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response(
                $sse, 200, ['Content-Type' => 'text/event-stream']
            ),
        ]);

        $svc = app(OpenAiService::class);
        $result = $svc->chatStream(
            $user, 'gpt-test',
            [['role' => 'user', 'content' => 'hello there']],
            [],
            function (string $delta) {}
        );

        $this->assertSame('abcd', $result['content']);
        $this->assertGreaterThan(0, $result['tokens_in']);
        $this->assertGreaterThan(0, $result['tokens_out']);
        $this->assertGreaterThan(0, $result['credits_spent']);
    }
}
