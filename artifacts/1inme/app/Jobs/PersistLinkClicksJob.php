<?php

namespace App\Jobs;

use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Database\QueryException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Persist a batch of buffered click payloads off the request hot path.
 *
 * For each payload it:
 *   1. resolves geo from the stored IP (the slow lookup, deferred from the redirect),
 *   2. insertOrIgnore()s the link_clicks row keyed on event_id (idempotent — a
 *      retried/re-delivered batch never double-inserts or double-counts),
 *   3. for genuinely-inserted human rows, accumulates counter deltas which are
 *      APPENDed to counter_deltas (lock-free) rather than UPDATE-ing the hot
 *      links/biolink_blocks rows directly,
 *   4. dispatches the LinkClicked / BlockClicked events.
 *
 * Bot/throttle classification and the atomic per-block cap reservation already
 * happened synchronously in LinkTrackingService before the redirect; this job
 * only records what was already decided.
 */
class PersistLinkClicksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Retry the whole batch on transient failure. The scheduled worker runs with
     * --tries=1, but a job-level $tries overrides that, so a rethrown transient
     * DB error (connection drop, deadlock) actually gets retried instead of being
     * lost. Idempotent insertOrIgnore (event_id) makes re-running the batch safe.
     */
    public int $tries = 3;

    /** Seconds to wait between retries (transient blips usually clear quickly). */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /** Columns that actually exist on link_clicks (others are dropped before insert). */
    private const CLICK_COLUMNS = [
        'event_id', 'link_id', 'alias', 'viewer_user_id', 'block_id', 'block_type',
        'destination_url', 'ip_address', 'country_code', 'city', 'latitude', 'longitude',
        'browser', 'os', 'device_type', 'referrer', 'source', 'user_agent', 'channel',
        'is_bot', 'is_throttled', 'language', 'utm_params', 'matched_rule_id', 'clicked_at',
    ];

    /**
     * @param array<int, array<string, mixed>> $batch
     */
    public function __construct(public array $batch)
    {
    }

    public function handle(): void
    {
        if (empty($this->batch) || !Schema::hasTable('link_clicks')) {
            return;
        }

        $hasEventId   = Schema::hasColumn('link_clicks', 'event_id');
        $hasMatched   = Schema::hasColumn('link_clicks', 'matched_rule_id');
        $hasThrottled = Schema::hasColumn('link_clicks', 'is_throttled');
        $geo          = app(GeoIpService::class);

        // Aggregate counter deltas across the whole batch so we APPEND at most one
        // delta row per entity instead of one per click.
        $linkDeltas  = [];   // link_id  => ['total' => n, 'unique' => n]
        $blockDeltas = [];   // block_id => clicks
        $events      = [];   // queued (link_id, row) tuples to fire after the loop
        $linkCache   = [];

        foreach ($this->batch as $payload) {
            try {
                $row = $this->buildRow($payload, $geo, $hasEventId, $hasMatched, $hasThrottled);
                if ($row === null) {
                    continue;
                }

                // Idempotent insert: when an event_id unique index exists, a
                // duplicate (retry/re-delivery) is silently ignored and reports
                // 0 affected rows, so we never double-count.
                $inserted = true;
                if ($hasEventId && !empty($row['event_id'])) {
                    $inserted = DB::table('link_clicks')->insertOrIgnore([$row]) > 0;
                } else {
                    DB::table('link_clicks')->insert($row);
                }

                if (!$inserted) {
                    continue;
                }

                $isBot = (bool) ($payload['is_bot'] ?? false);
                if ($isBot) {
                    // Bot/throttled rows are recorded but excluded from human counters.
                    continue;
                }

                $linkId = (int) $row['link_id'];
                $linkDeltas[$linkId] ??= ['total' => 0, 'unique' => 0];
                $linkDeltas[$linkId]['total']++;

                if ($this->isUniqueClick($linkId, (string) ($row['ip_address'] ?? ''), $row['clicked_at'], (string) ($row['event_id'] ?? ''), $hasEventId)) {
                    $linkDeltas[$linkId]['unique']++;
                }

                // Uncapped blocks still need their click_count bumped; capped
                // blocks were already incremented by the atomic reservation.
                if (!empty($payload['block_counter_pending']) && !empty($row['block_id'])) {
                    $bid = (int) $row['block_id'];
                    $blockDeltas[$bid] = ($blockDeltas[$bid] ?? 0) + 1;
                }

                $events[] = $row;
            } catch (\Throwable $e) {
                if ($this->isTransient($e)) {
                    // Transient DB failure (connection drop, deadlock, resource
                    // exhaustion). Let the job FAIL so the queue retries the whole
                    // batch — never swallow it, or the clicks are silently lost.
                    // Idempotent insertOrIgnore (event_id) means already-inserted
                    // rows won't double-insert on retry; any counter drift from a
                    // partially-processed batch self-heals via RecountLinkStats.
                    throw $e;
                }
                // Permanently-bad single payload (e.g. its link was deleted
                // between the request and this job, tripping the FK, or a data
                // exception). Retrying can't help, so skip just this row.
                Log::warning('PersistLinkClicksJob dropped a bad payload: ' . $e->getMessage());
            }
        }

        $this->appendCounterDeltas($linkDeltas, $blockDeltas);
        $this->dispatchEvents($events, $linkCache);
        $this->dispatchMilestoneChecks($linkDeltas);
    }

    /**
     * Dispatch milestone-check jobs for each link that received human clicks.
     * Each job checks idempotency itself via `link_click_milestone_fires`.
     *
     * @param array<int, array{total:int,unique:int}> $linkDeltas
     */
    private function dispatchMilestoneChecks(array $linkDeltas): void
    {
        $affectedLinkIds = array_keys(array_filter($linkDeltas, fn ($d) => $d['total'] > 0));
        if (empty($affectedLinkIds)) {
            return;
        }
        try {
            \App\Jobs\CheckClickMilestonesJob::dispatch($affectedLinkIds);
        } catch (\Throwable $e) {
            Log::warning('PersistLinkClicksJob: failed to dispatch milestone check: ' . $e->getMessage());
        }
    }

    /**
     * Should this exception cause the whole batch to be retried? Integrity (class
     * 23) and data (class 22) violations are permanently bad payloads — skip them.
     * Everything else from the DB (connection 08, deadlock/serialization 40,
     * resource 53, operator-intervention 57, system 58) is transient — retry.
     * Non-DB errors are treated as a bad single payload (non-retryable).
     */
    private function isTransient(\Throwable $e): bool
    {
        if (!$e instanceof QueryException) {
            return false;
        }
        $sqlState = (string) $e->getCode();
        return !(str_starts_with($sqlState, '22') || str_starts_with($sqlState, '23'));
    }

    /**
     * Build the full insert row: drop unknown keys, resolve geo, normalise types.
     * Returns null when the payload is missing the minimum required fields.
     */
    private function buildRow(array $payload, GeoIpService $geo, bool $hasEventId, bool $hasMatched, bool $hasThrottled): ?array
    {
        if (empty($payload['link_id']) || empty($payload['clicked_at'])) {
            return null;
        }

        // Deferred geo lookup (the slow part we kept off the redirect).
        if (!array_key_exists('country_code', $payload)) {
            $g = $geo->detectGeo($payload['ip_address'] ?? null);
            $payload['country_code'] = $g['country_code'] ?? null;
            $payload['city']         = $g['city'] ?? null;
            $payload['latitude']     = $g['latitude'] ?? null;
            $payload['longitude']    = $g['longitude'] ?? null;
        }

        $row = [];
        foreach (self::CLICK_COLUMNS as $col) {
            if (array_key_exists($col, $payload)) {
                $row[$col] = $payload[$col];
            }
        }

        if (!$hasEventId) {
            unset($row['event_id']);
        }
        if (!$hasMatched) {
            unset($row['matched_rule_id']);
        }
        if (!$hasThrottled) {
            unset($row['is_throttled']);
        }

        // utm_params is a jsonb column — encode the array form.
        if (array_key_exists('utm_params', $row)) {
            $row['utm_params'] = $row['utm_params'] === null ? null : json_encode($row['utm_params']);
        }

        $row['is_bot'] = (bool) ($row['is_bot'] ?? false);

        return $row;
    }

    /**
     * Mirror the legacy synchronous uniqueness rule: a click is "unique" when no
     * other click from the same IP for this link landed in the trailing 24h.
     * Excludes this row by its event_id so the just-inserted row doesn't count
     * against itself.
     */
    private function isUniqueClick(int $linkId, string $ip, mixed $clickedAt, string $eventId, bool $hasEventId): bool
    {
        if ($ip === '') {
            return true;
        }

        $when = $clickedAt instanceof \DateTimeInterface ? Carbon::instance($clickedAt) : Carbon::parse((string) $clickedAt);

        $q = DB::table('link_clicks')
            ->where('link_id', $linkId)
            ->where('ip_address', $ip)
            ->where('clicked_at', '>=', $when->copy()->subDay())
            ->where('clicked_at', '<=', $when);

        if ($hasEventId && $eventId !== '') {
            $q->where(function ($w) use ($eventId) {
                $w->where('event_id', '!=', $eventId)->orWhereNull('event_id');
            });
        }

        return !$q->exists();
    }

    /**
     * @param array<int, array{total:int,unique:int}> $linkDeltas
     * @param array<int, int>                          $blockDeltas
     */
    private function appendCounterDeltas(array $linkDeltas, array $blockDeltas): void
    {
        if (!Schema::hasTable('counter_deltas')) {
            // No buffer table (un-migrated): fall back to a direct, batched
            // increment so counters still move. Single worker => no contention.
            $this->applyDirectly($linkDeltas, $blockDeltas);
            return;
        }

        $rows = [];
        $now  = now();
        foreach ($linkDeltas as $linkId => $d) {
            if ($d['total'] === 0 && $d['unique'] === 0) {
                continue;
            }
            $rows[] = [
                'entity_type'  => 'link',
                'entity_id'    => $linkId,
                'total_delta'  => $d['total'],
                'unique_delta' => $d['unique'],
                'created_at'   => $now,
            ];
        }
        foreach ($blockDeltas as $blockId => $clicks) {
            if ($clicks === 0) {
                continue;
            }
            $rows[] = [
                'entity_type'  => 'block',
                'entity_id'    => $blockId,
                'total_delta'  => $clicks,
                'unique_delta' => 0,
                'created_at'   => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('counter_deltas')->insert($rows);
        }
    }

    private function applyDirectly(array $linkDeltas, array $blockDeltas): void
    {
        foreach ($linkDeltas as $linkId => $d) {
            $update = [];
            if ($d['total'] > 0) {
                $update['total_clicks'] = DB::raw('total_clicks + ' . (int) $d['total']);
            }
            if ($d['unique'] > 0) {
                $update['unique_clicks'] = DB::raw('unique_clicks + ' . (int) $d['unique']);
            }
            if ($update) {
                DB::table('links')->where('id', $linkId)->update($update);
            }
        }
        foreach ($blockDeltas as $blockId => $clicks) {
            if ($clicks > 0) {
                DB::table('biolink_blocks')->where('id', $blockId)->increment('click_count', $clicks);
            }
        }
    }

    /**
     * Fire the downstream LinkClicked / BlockClicked events for the inserted
     * human rows. These currently have no listeners, but preserving the dispatch
     * keeps webhook/notification hooks working if listeners are added.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, Link|null>            $linkCache
     */
    private function dispatchEvents(array $rows, array &$linkCache): void
    {
        foreach ($rows as $row) {
            try {
                $linkId = (int) $row['link_id'];
                $link = $linkCache[$linkId] ??= Link::find($linkId);
                if (!$link) {
                    continue;
                }

                $click = (new LinkClick())->forceFill($row);

                if (!empty($row['block_id'])) {
                    $block = BiolinkBlock::find((int) $row['block_id']);
                    if ($block) {
                        \App\Events\BlockClicked::dispatch($link, $block, $click, (string) ($row['destination_url'] ?? ''));
                    }
                } else {
                    \App\Events\LinkClicked::dispatch($link, $click);
                }
            } catch (\Throwable $e) {
                Log::warning('PersistLinkClicksJob event dispatch failed: ' . $e->getMessage());
            }
        }
    }
}
