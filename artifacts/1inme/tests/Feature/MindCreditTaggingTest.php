<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\User;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindIngestor;
use App\Services\AI\AiMindQueryService;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Producer-side coverage for per-Mind credit accounting.
 *
 * MindCreditUsageServiceTest verifies the aggregation reads. This
 * test verifies the producers — the real ingest and query services —
 * actually write the rows the aggregator depends on. If a future
 * refactor drops `feature='mind'`, `meta.mind_id`, `meta.kind`, or
 * `related_id`, the per-Mind dashboards will silently zero out and
 * the aggregator tests will keep passing. These tests catch that.
 *
 * Strategy: enable the real AI engine, fake the OpenAI HTTP layer,
 * and exercise the real AiMindIngestor / AiMindQueryService so the
 * tags flow through OpenAiService → AiCreditService → ledger row
 * exactly the way they would in production.
 */
class MindCreditTaggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable the AI engine and store a fake key so OpenAiService::guard
        // lets the call through. The HTTP layer is faked below so the key
        // is never actually sent to OpenAI.
        AiEngineSettings::setEnabled(true);
        AiEngineSettings::setOpenAiKey('sk-test-fake-key');
        AiEngineSettings::setModels(AiEngineSettings::defaultModels());
    }

    protected function makeUser(): User
    {
        return User::create([
            'name'     => 'Mind Tag User '.Str::random(4),
            'email'    => 'tag'.Str::random(6).'@example.com',
            'password' => bcrypt('secret'),
            'status'   => 'active',
            'role'     => 'user',
        ]);
    }

    protected function makeMind(User $user): AiMind
    {
        return AiMind::create([
            'user_id'     => $user->id,
            'name'        => 'Tag Mind '.Str::random(4),
            'slug'        => 'tag-mind-'.Str::random(6),
            'description' => null,
            'is_default'  => false,
        ]);
    }

    protected function makeTextSource(AiMind $mind): AiMindSource
    {
        return AiMindSource::create([
            'mind_id' => $mind->id,
            'type'    => AiMindSource::TYPE_TEXT,
            'title'   => 'Inline note',
            'body'    => str_repeat('The quick brown fox jumps over the lazy dog. ', 30),
            'status'  => AiMindSource::STATUS_QUEUED,
        ]);
    }

    /**
     * Build a fake embeddings response. `data` length must match the
     * number of inputs the service sent so the OpenAiService::embed
     * vector mapping doesn't desync.
     */
    protected function fakeEmbeddingResponse(int $inputs, int $tokens = 100): array
    {
        return [
            'object' => 'list',
            'data'   => array_map(
                fn ($i) => ['object' => 'embedding', 'index' => $i, 'embedding' => array_fill(0, 8, 0.1)],
                range(0, max(0, $inputs - 1))
            ),
            'model'  => 'text-embedding-3-small',
            'usage'  => ['prompt_tokens' => $tokens, 'total_tokens' => $tokens],
        ];
    }

    protected function fakeChatResponse(string $content = 'A focused, grounded answer.'): array
    {
        return [
            'id'      => 'chatcmpl-fake-'.Str::random(8),
            'object'  => 'chat.completion',
            'choices' => [[
                'index'         => 0,
                'message'       => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage'   => ['prompt_tokens' => 200, 'completion_tokens' => 50, 'total_tokens' => 250],
            'model'   => 'gpt-4o-mini',
        ];
    }

    public function test_real_ingest_path_tags_charge_with_feature_mind_kind_ingest_and_source_id(): void
    {
        $user   = $this->makeUser();
        $mind   = $this->makeMind($user);
        $source = $this->makeTextSource($mind);

        // Enough headroom for the embed charge to land.
        app(AiCreditService::class)->grant($user, 10_000);

        // Fake the embeddings endpoint. Inspect the inbound payload so
        // we can return the matching number of vectors regardless of
        // chunk count.
        Http::fake([
            'api.openai.com/v1/embeddings' => function ($request) {
                $body = $request->data();
                $inputs = is_array($body['input'] ?? null) ? count($body['input']) : 1;
                return Http::response($this->fakeEmbeddingResponse($inputs));
            },
        ]);

        app(AiMindIngestor::class)->ingest($source);

        $source->refresh();
        $this->assertSame(
            AiMindSource::STATUS_READY,
            $source->status,
            "Ingest should succeed end-to-end. status_message: {$source->status_message}"
        );

        $spend = AiCreditTransaction::where('user_id', $user->id)
            ->where('type', 'spend')
            ->orderBy('id')
            ->get();

        $this->assertGreaterThan(
            0,
            $spend->count(),
            'Ingest should have written at least one spend row.'
        );

        foreach ($spend as $tx) {
            $this->assertSame('mind', $tx->feature, "feature must be 'mind'");
            $this->assertSame((int) $source->id, (int) $tx->related_id, 'related_id must point to the source');
            $this->assertIsArray($tx->meta);
            $this->assertSame('ingest', $tx->meta['kind'] ?? null, "meta.kind must be 'ingest'");
            $this->assertSame((int) $mind->id, (int) ($tx->meta['mind_id'] ?? null), 'meta.mind_id must point to the mind');
            $this->assertSame((int) $source->id, (int) ($tx->meta['source_id'] ?? null), 'meta.source_id must point to the source');
        }
    }

    public function test_real_query_path_tags_embed_and_chat_charges_with_feature_mind_kind_query_and_mind_id(): void
    {
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        app(AiCreditService::class)->grant($user, 10_000);

        Http::fake([
            'api.openai.com/v1/embeddings'      => function ($request) {
                $body = $request->data();
                $inputs = is_array($body['input'] ?? null) ? count($body['input']) : 1;
                return Http::response($this->fakeEmbeddingResponse($inputs));
            },
            'api.openai.com/v1/chat/completions' => Http::response($this->fakeChatResponse()),
        ]);

        $result = app(AiMindQueryService::class)->ask($user, [$mind], 'What does this Mind know?');
        $this->assertNotSame('', $result['answer']);

        $spend = AiCreditTransaction::where('user_id', $user->id)
            ->where('type', 'spend')
            ->orderBy('id')
            ->get();

        $this->assertGreaterThanOrEqual(
            2,
            $spend->count(),
            'Ask should produce both an embedding spend and a chat spend.'
        );

        foreach ($spend as $tx) {
            $this->assertSame('mind', $tx->feature, "feature must be 'mind'");
            $this->assertIsArray($tx->meta);
            $this->assertSame('query', $tx->meta['kind'] ?? null, "meta.kind must be 'query'");
            $this->assertSame(
                (int) $mind->id,
                (int) ($tx->meta['mind_id'] ?? null),
                'meta.mind_id must be the focused Mind'
            );
            // Both embed and chat in the query path use the focused Mind
            // as related_id so the row groups under that Mind.
            $this->assertSame((int) $mind->id, (int) $tx->related_id, 'related_id must be the focused Mind id');
        }

        // At least one spend row must be the chat (carries token_out > 0)
        // and at least one must be the embedding (token_out == 0). Proves
        // both producer code paths emit the right tags, not just one.
        $this->assertTrue(
            $spend->contains(fn ($tx) => (int) $tx->tokens_out > 0),
            'Expected a chat spend row from the ask() chat call.'
        );
        $this->assertTrue(
            $spend->contains(fn ($tx) => (int) $tx->tokens_out === 0),
            'Expected an embedding spend row from the ask() context retrieval.'
        );
    }

    public function test_failed_query_chat_call_still_leaves_correctly_tagged_embed_charge(): void
    {
        // When the chat call blows up after the retrieval embedding has
        // already been charged, the embedding's spend row must still
        // carry the same per-Mind tags so the dashboard accurately
        // reflects credits actually spent on that Mind.
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        app(AiCreditService::class)->grant($user, 10_000);

        Http::fake([
            'api.openai.com/v1/embeddings'       => function ($request) {
                $body = $request->data();
                $inputs = is_array($body['input'] ?? null) ? count($body['input']) : 1;
                return Http::response($this->fakeEmbeddingResponse($inputs));
            },
            'api.openai.com/v1/chat/completions' => Http::response('upstream boom', 500),
        ]);

        try {
            app(AiMindQueryService::class)->ask($user, [$mind], 'Will this fail?');
            $this->fail('Expected the chat call to bubble up as a RuntimeException.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $spend = AiCreditTransaction::where('user_id', $user->id)
            ->where('type', 'spend')
            ->get();

        $this->assertGreaterThan(
            0,
            $spend->count(),
            'The embedding spend row must persist even when the chat call later fails.'
        );

        foreach ($spend as $tx) {
            $this->assertSame('mind', $tx->feature);
            $this->assertSame('query', $tx->meta['kind'] ?? null);
            $this->assertSame((int) $mind->id, (int) ($tx->meta['mind_id'] ?? null));
            $this->assertSame((int) $mind->id, (int) $tx->related_id);
            // No completion tokens — these are all retrieval embeddings.
            $this->assertSame(0, (int) $tx->tokens_out);
        }
    }

    public function test_refund_using_the_same_tag_shape_round_trips_through_the_ledger(): void
    {
        // The current ingest/query code does not refund failed runs —
        // failed embeddings throw before the charge is written, and
        // failed downstream steps leave the prior charge in place.
        // This test pins the *contract* that any future "refund on
        // failure" path must follow: when callers pass the same
        // feature/related_id/meta shape the producers already use,
        // the ledger row preserves them so per-Mind analytics stay
        // consistent across spends and refunds.
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        $credits = app(AiCreditService::class);
        $credits->grant($user, 100);
        $charge = $credits->charge($user, 25, [
            'feature'    => 'mind',
            'related_id' => $mind->id,
            'reason'     => 'Mind query',
            'meta'       => ['kind' => 'query', 'mind_id' => (int) $mind->id],
        ]);

        $refund = $credits->refund($user, 25, [
            'feature'    => 'mind',
            'related_id' => $mind->id,
            'reason'     => 'reverse: '.$charge->id,
            'meta'       => ['kind' => 'query', 'mind_id' => (int) $mind->id, 'reverses' => $charge->id],
        ]);

        $this->assertSame('refund', $refund->type);
        $this->assertSame('mind', $refund->feature);
        $this->assertSame((int) $mind->id, (int) $refund->related_id);
        $this->assertSame('query', $refund->meta['kind'] ?? null);
        $this->assertSame((int) $mind->id, (int) ($refund->meta['mind_id'] ?? null));
    }
}
