<?php

namespace Tests\Feature;

use App\Modules\User\Models\AiCreditBalance;
use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\User;
use App\Services\AI\MindCreditUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end coverage for the per-Mind credit analytics. Exercises the
 * three public surfaces of MindCreditUsageService against a hand-built
 * ledger so a future refactor of the ingest/query tagging or the
 * aggregation can't silently zero out the numbers users see.
 */
class MindCreditUsageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MindCreditUsageService $usage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usage = app(MindCreditUsageService::class);
    }

    protected function makeUser(): User
    {
        return User::create([
            'name'     => 'Mind User '.Str::random(4),
            'email'    => 'mind'.Str::random(6).'@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    protected function makeMind(User $user, string $name = 'Mind'): AiMind
    {
        return AiMind::create([
            'user_id'     => $user->id,
            'name'        => $name.' '.Str::random(4),
            'description' => null,
            'is_default'  => false,
        ]);
    }

    /**
     * Direct ledger writer so each row's kind / mind_id / related_id /
     * created_at is exactly what the test wants. We deliberately
     * bypass AiCreditService::charge here so we can backdate rows and
     * assert the 30-day window is respected.
     */
    protected function writeSpend(
        User $user,
        int $amount,
        array $meta,
        ?int $relatedId = null,
        ?Carbon $at = null,
        string $feature = 'mind',
    ): AiCreditTransaction {
        $balance = AiCreditBalance::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0],
        );

        return AiCreditTransaction::create([
            'balance_id'    => $balance->id,
            'user_id'       => $user->id,
            'type'          => 'spend',
            'delta_credits' => -$amount,
            'balance_after' => 0,
            'feature'       => $feature,
            'related_id'    => $relatedId,
            'meta'          => $meta,
            'created_at'    => $at ?? Carbon::now(),
        ]);
    }

    public function test_usage_for_mind_splits_ingest_and_query_inside_window(): void
    {
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        $this->writeSpend($user, 10, ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 42);
        $this->writeSpend($user, 5,  ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 42);
        $this->writeSpend($user, 7,  ['mind_id' => $mind->id, 'kind' => 'query'],  relatedId: $mind->id);
        $this->writeSpend($user, 3,  ['mind_id' => $mind->id, 'kind' => 'query'],  relatedId: $mind->id);

        $out = $this->usage->usageForMind($mind->id);

        $this->assertSame(15, $out['ingest']);
        $this->assertSame(10, $out['query']);
        $this->assertSame(25, $out['total']);
        $this->assertSame(MindCreditUsageService::DEFAULT_WINDOW_DAYS, $out['days']);
    }

    public function test_usage_for_mind_excludes_rows_outside_window(): void
    {
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        // Inside the 30-day window.
        $this->writeSpend($user, 8, ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 1);
        $this->writeSpend($user, 4, ['mind_id' => $mind->id, 'kind' => 'query'],  relatedId: $mind->id);

        // Outside the window — must be ignored.
        $this->writeSpend(
            $user, 100,
            ['mind_id' => $mind->id, 'kind' => 'ingest'],
            relatedId: 1,
            at: Carbon::now()->subDays(45),
        );
        $this->writeSpend(
            $user, 200,
            ['mind_id' => $mind->id, 'kind' => 'query'],
            relatedId: $mind->id,
            at: Carbon::now()->subDays(31),
        );

        $out = $this->usage->usageForMind($mind->id, days: 30);
        $this->assertSame(8, $out['ingest']);
        $this->assertSame(4, $out['query']);
        $this->assertSame(12, $out['total']);
    }

    public function test_usage_for_mind_does_not_leak_other_minds_or_users(): void
    {
        $user      = $this->makeUser();
        $otherUser = $this->makeUser();

        $mind      = $this->makeMind($user, 'Target');
        $otherMind = $this->makeMind($user, 'Sibling');
        $foreign   = $this->makeMind($otherUser, 'Foreign');

        // The Mind under test.
        $this->writeSpend($user, 9, ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 11);
        $this->writeSpend($user, 6, ['mind_id' => $mind->id, 'kind' => 'query'],  relatedId: $mind->id);

        // Same user, different Mind — must not leak.
        $this->writeSpend($user, 50, ['mind_id' => $otherMind->id, 'kind' => 'ingest'], relatedId: 12);
        $this->writeSpend($user, 50, ['mind_id' => $otherMind->id, 'kind' => 'query'],  relatedId: $otherMind->id);

        // Different user, different Mind — must not leak.
        $this->writeSpend($otherUser, 70, ['mind_id' => $foreign->id, 'kind' => 'ingest'], relatedId: 13);
        $this->writeSpend($otherUser, 70, ['mind_id' => $foreign->id, 'kind' => 'query'],  relatedId: $foreign->id);

        // Non-mind feature spend on the same user — must not leak either.
        $this->writeSpend($user, 999, ['mind_id' => $mind->id, 'kind' => 'query'], relatedId: $mind->id, feature: 'persona');

        $out = $this->usage->usageForMind($mind->id);
        $this->assertSame(9, $out['ingest']);
        $this->assertSame(6, $out['query']);
        $this->assertSame(15, $out['total']);
    }

    public function test_ingestion_by_source_aggregates_per_source_only(): void
    {
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        // Two sources, multiple ingest rows each, plus query rows that
        // must be ignored even when they share related_id values.
        $this->writeSpend($user, 4,  ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 100);
        $this->writeSpend($user, 6,  ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 100);
        $this->writeSpend($user, 11, ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: 200);

        // Query spend — must not appear in ingestionBySource even though
        // its related_id is the mind id.
        $this->writeSpend($user, 99, ['mind_id' => $mind->id, 'kind' => 'query'], relatedId: $mind->id);

        // Outside window — must be ignored.
        $this->writeSpend(
            $user, 500,
            ['mind_id' => $mind->id, 'kind' => 'ingest'],
            relatedId: 100,
            at: Carbon::now()->subDays(40),
        );

        // Ingest row missing related_id — must be skipped.
        $this->writeSpend($user, 7, ['mind_id' => $mind->id, 'kind' => 'ingest'], relatedId: null);

        // Different Mind's ingest — must not leak.
        $otherMind = $this->makeMind($user, 'Other');
        $this->writeSpend($user, 1234, ['mind_id' => $otherMind->id, 'kind' => 'ingest'], relatedId: 100);

        $bySource = $this->usage->ingestionBySource($mind->id);

        $this->assertSame([100 => 10, 200 => 11], $bySource);
    }

    public function test_top_minds_sorts_by_total_and_returns_correct_splits(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $big    = $this->makeMind($userA, 'Big');
        $medium = $this->makeMind($userA, 'Medium');
        $small  = $this->makeMind($userB, 'Small');

        // Big: 30 ingest + 50 query = 80
        $this->writeSpend($userA, 30, ['mind_id' => $big->id,    'kind' => 'ingest'], relatedId: 1);
        $this->writeSpend($userA, 50, ['mind_id' => $big->id,    'kind' => 'query'],  relatedId: $big->id);

        // Medium: 20 ingest + 20 query = 40
        $this->writeSpend($userA, 20, ['mind_id' => $medium->id, 'kind' => 'ingest'], relatedId: 2);
        $this->writeSpend($userA, 20, ['mind_id' => $medium->id, 'kind' => 'query'],  relatedId: $medium->id);

        // Small: 5 ingest + 10 query = 15
        $this->writeSpend($userB, 5,  ['mind_id' => $small->id,  'kind' => 'ingest'], relatedId: 3);
        $this->writeSpend($userB, 10, ['mind_id' => $small->id,  'kind' => 'query'],  relatedId: $small->id);

        // Outside-window spend on the lowest mind — should not promote it.
        $this->writeSpend(
            $userB, 10_000,
            ['mind_id' => $small->id, 'kind' => 'query'],
            relatedId: $small->id,
            at: Carbon::now()->subDays(60),
        );

        // Spend with no mind_id in meta — must be skipped, not crash.
        $this->writeSpend($userA, 77, ['kind' => 'query'], relatedId: null);

        // Non-mind feature — must be ignored.
        $this->writeSpend($userA, 999, ['mind_id' => $big->id, 'kind' => 'query'], relatedId: $big->id, feature: 'persona');

        $top = $this->usage->topMinds(limit: 10);

        $this->assertCount(3, $top);
        $ids = $top->pluck('mind.id')->all();
        $this->assertSame([$big->id, $medium->id, $small->id], $ids);

        $byId = $top->keyBy(fn ($row) => $row['mind']->id);
        $this->assertSame(['ingest' => 30, 'query' => 50, 'total' => 80], [
            'ingest' => $byId[$big->id]['ingest'],
            'query'  => $byId[$big->id]['query'],
            'total'  => $byId[$big->id]['total'],
        ]);
        $this->assertSame(['ingest' => 20, 'query' => 20, 'total' => 40], [
            'ingest' => $byId[$medium->id]['ingest'],
            'query'  => $byId[$medium->id]['query'],
            'total'  => $byId[$medium->id]['total'],
        ]);
        $this->assertSame(['ingest' => 5, 'query' => 10, 'total' => 15], [
            'ingest' => $byId[$small->id]['ingest'],
            'query'  => $byId[$small->id]['query'],
            'total'  => $byId[$small->id]['total'],
        ]);
    }

    public function test_top_minds_respects_limit(): void
    {
        $user = $this->makeUser();
        $a = $this->makeMind($user, 'A');
        $b = $this->makeMind($user, 'B');
        $c = $this->makeMind($user, 'C');

        $this->writeSpend($user, 100, ['mind_id' => $a->id, 'kind' => 'query'], relatedId: $a->id);
        $this->writeSpend($user, 50,  ['mind_id' => $b->id, 'kind' => 'query'], relatedId: $b->id);
        $this->writeSpend($user, 10,  ['mind_id' => $c->id, 'kind' => 'query'], relatedId: $c->id);

        $top = $this->usage->topMinds(limit: 2);
        $this->assertCount(2, $top);
        $this->assertSame([$a->id, $b->id], $top->pluck('mind.id')->all());
    }

    public function test_returns_empty_results_when_no_matching_spend(): void
    {
        $user = $this->makeUser();
        $mind = $this->makeMind($user);

        $out = $this->usage->usageForMind($mind->id);
        $this->assertSame(['ingest' => 0, 'query' => 0, 'total' => 0, 'days' => 30], $out);

        $this->assertSame([], $this->usage->ingestionBySource($mind->id));
        $this->assertTrue($this->usage->topMinds()->isEmpty());
    }
}
