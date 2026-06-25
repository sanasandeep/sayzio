<?php

namespace Tests\Feature\Analytics;

use App\Jobs\PersistLinkClicksJob;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\AnalyticsRollupReader;
use App\Modules\Common\Services\ClickWriteBuffer;
use App\Modules\Common\Support\PartitionManager;
use App\Modules\User\Models\LinkClick;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Covers the scaled tracking pipeline: contention-safe counter fold, daily
 * rollup + reader, and retention safety (no-op vs hard physical cap).
 */
class TrackingScaleTest extends AnalyticsTestCase
{
    public function test_flush_counters_folds_deltas_and_clears_them(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, ['total_clicks' => 0, 'unique_clicks' => 0]);

        DB::table('counter_deltas')->insert([
            ['entity_type' => 'link', 'entity_id' => $link->id, 'total_delta' => 3, 'unique_delta' => 2, 'created_at' => now()],
            ['entity_type' => 'link', 'entity_id' => $link->id, 'total_delta' => 1, 'unique_delta' => 0, 'created_at' => now()],
        ]);

        $this->artisan('analytics:flush-counters')->assertExitCode(0);

        $link->refresh();
        $this->assertSame(4, (int) $link->total_clicks);
        $this->assertSame(2, (int) $link->unique_clicks);
        $this->assertSame(0, (int) DB::table('counter_deltas')->count());
    }

    public function test_daily_rollup_finalizes_past_days_and_reader_returns_them(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Two human clicks yesterday — should be finalized into the rollup.
        foreach (range(1, 2) as $_) {
            LinkClick::create([
                'link_id'    => $link->id,
                'clicked_at' => now()->subDay()->setTime(10, 0),
                'is_bot'     => false,
                'event_id'   => (string) Str::uuid(),
            ]);
        }

        $this->artisan('analytics:rollup-daily')->assertExitCode(0);

        $this->assertDatabaseHas('link_click_daily', [
            'link_id'      => $link->id,
            'click_date'   => now()->subDay()->toDateString(),
            'total_clicks' => 2,
        ]);

        $reader = app(AnalyticsRollupReader::class);
        $byDay  = $reader->byDay($link->id, now()->subDays(3), now());
        $this->assertNotEmpty($byDay);
    }

    public function test_prune_is_noop_when_a_plan_keeps_history_forever(): void
    {
        Plan::query()->update(['status' => 'inactive']);
        Plan::create([
            'name'     => 'Forever',
            'slug'     => 'forever-' . bin2hex(random_bytes(3)),
            'status'   => 'active',
            'features' => ['stats_retention_days' => -1],
        ]);

        $user = $this->makeUser();
        $link = $this->makeLink($user);
        $old  = LinkClick::create([
            'link_id'    => $link->id,
            'clicked_at' => now()->subYears(2),
            'is_bot'     => false,
            'event_id'   => (string) Str::uuid(),
        ]);

        $this->artisan('stats:prune-history')->assertExitCode(0);

        $this->assertDatabaseHas('link_clicks', ['id' => $old->id]);
    }

    public function test_hard_cap_prunes_even_under_unlimited_retention(): void
    {
        Plan::query()->update(['status' => 'inactive']);
        Plan::create([
            'name'     => 'Forever',
            'slug'     => 'forever-' . bin2hex(random_bytes(3)),
            'status'   => 'active',
            'features' => ['stats_retention_days' => -1],
        ]);

        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $old = LinkClick::create([
            'link_id'    => $link->id,
            'clicked_at' => now()->subDays(400),
            'is_bot'     => false,
            'event_id'   => (string) Str::uuid(),
        ]);
        $recent = LinkClick::create([
            'link_id'    => $link->id,
            'clicked_at' => now()->subDays(5),
            'is_bot'     => false,
            'event_id'   => (string) Str::uuid(),
        ]);

        $this->artisan('stats:prune-history', ['--hard-max-days' => 365])->assertExitCode(0);

        $this->assertDatabaseMissing('link_clicks', ['id' => $old->id]);
        $this->assertDatabaseHas('link_clicks', ['id' => $recent->id]);
    }

    public function test_partition_manager_is_noop_on_unpartitioned_tables(): void
    {
        $manager = app(PartitionManager::class);

        $this->assertFalse($manager->isPartitioned('link_clicks'));
        $this->assertSame([], $manager->ensureFuturePartitions('link_clicks', 2));
        $this->assertSame([], $manager->dropPartitionsBefore('link_clicks', now()->subYear()));
    }

    public function test_maintain_partitions_command_runs_clean_without_partitions(): void
    {
        $this->artisan('tracking:maintain-partitions')->assertExitCode(0);
    }

    public function test_flush_does_not_double_apply_folded_deltas_on_rerun(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, ['total_clicks' => 0, 'unique_clicks' => 0]);

        DB::table('counter_deltas')->insert([
            ['entity_type' => 'link', 'entity_id' => $link->id, 'total_delta' => 5, 'unique_delta' => 3, 'created_at' => now()],
        ]);

        // First run folds + clears the deltas in one transaction.
        $this->artisan('analytics:flush-counters')->assertExitCode(0);
        // Second run must be a clean no-op — the deltas were deleted atomically
        // with the counter update, so they can never be replayed (no overcount).
        $this->artisan('analytics:flush-counters')->assertExitCode(0);

        $link->refresh();
        $this->assertSame(5, (int) $link->total_clicks);
        $this->assertSame(3, (int) $link->unique_clicks);
        $this->assertSame(0, (int) DB::table('counter_deltas')->count());
    }

    public function test_api_reset_clears_daily_rollups_so_analytics_is_not_stale(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, ['total_clicks' => 9, 'unique_clicks' => 4]);
        $day  = now()->subDay()->toDateString();

        LinkClick::create([
            'link_id'    => $link->id,
            'clicked_at' => now()->subDay()->setTime(9, 0),
            'is_bot'     => false,
            'event_id'   => (string) Str::uuid(),
        ]);
        DB::table('link_click_daily')->insert([
            'link_id' => $link->id, 'click_date' => $day,
            'total_clicks' => 9, 'unique_visitors' => 4, 'bot_clicks' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('link_click_daily_dimensions')->insert([
            'link_id' => $link->id, 'click_date' => $day,
            'dimension' => 'country', 'dim_value' => 'US', 'clicks' => 9,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->postJson('/api/v1/links/' . $link->id . '/reset')
            ->assertOk();

        $this->assertSame(0, (int) DB::table('link_clicks')->where('link_id', $link->id)->count());
        $this->assertSame(0, (int) DB::table('link_click_daily')->where('link_id', $link->id)->count());
        $this->assertSame(0, (int) DB::table('link_click_daily_dimensions')->where('link_id', $link->id)->count());
    }

    public function test_buffer_persists_synchronously_when_queue_dispatch_fails(): void
    {
        // Simulate a queue outage: dispatching the job throws. The buffer must
        // NOT lose the click — it falls back to persisting inline.
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('queue down'));

        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $buffer = app(ClickWriteBuffer::class);
        $buffer->push([
            'link_id'    => $link->id,
            'clicked_at' => now(),
            'is_bot'     => false,
            'event_id'   => (string) Str::uuid(),
        ]);

        $buffer->flush();

        // The click survived via the synchronous fallback instead of vanishing.
        $this->assertDatabaseHas('link_clicks', ['link_id' => $link->id]);
    }

    public function test_idempotent_insert_does_not_double_count_on_replay(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user, ['total_clicks' => 0]);

        $payload = [
            'link_id'    => $link->id,
            'clicked_at' => now(),
            'is_bot'     => false,
            'event_id'   => (string) Str::uuid(),
            'ip_address' => '203.0.113.7',
        ];

        // Run the same batch twice (as a re-delivered/retried job would).
        (new PersistLinkClicksJob([$payload]))->handle();
        (new PersistLinkClicksJob([$payload]))->handle();

        // Exactly one row inserted despite two runs (event_id idempotency).
        $this->assertSame(1, (int) DB::table('link_clicks')->where('link_id', $link->id)->count());
    }
}
