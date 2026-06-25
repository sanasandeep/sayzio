# Scaling link / visit tracking (≈1M users, ≈100M rows)

This document describes the tracking write/read pipeline after the scale work
and the operator runbook for partitioning, retention, and load verification.

> **Hard constraint:** the database is a shared, cross-region AWS RDS Postgres.
> Every migration is **additive and shared-DB-safe**. We never run
> `migrate:fresh` or any destructive in-place rewrite as part of a deploy.

## Architecture at a glance

```
redirect / block click
        │  (synchronous, cheap)
        ▼
 bot / blocked-family / throttle gate
 atomic block-cap reservation
        │
        ▼
 ClickWriteBuffer  ──(terminate)──►  PersistLinkClicksJob (queue)
        │                                    │
        │                                    ├─ geo enrich + insert link_clicks (insertOrIgnore on event_id)
        │                                    └─ append rows to counter_deltas
        ▼
 (request returns immediately)

 analytics:flush-counters  (every minute, single worker)
        └─ fold counter_deltas → links / biolink_blocks totals

 analytics:rollup-daily    (daily)
        └─ link_clicks → link_click_daily (+ _dimensions)

 dashboard reads (AnalyticsRollupReader)
        └─ finalized days from rollup + current day from raw
```

### What stays synchronous on the hot path
Bot/throttle/blocked-family filtering and the atomic block-cap reservation run
**before** anything is buffered, so abusive or over-cap traffic never reaches the
write path. Everything else — geo lookup, the `link_clicks` insert, counter
updates, and `LinkClicked`/`BlockClicked` events — is deferred to the queued
`PersistLinkClicksJob`. The redirect response does **no** synchronous
`link_clicks` insert or counter `UPDATE`.

### Idempotency
Each pending click carries a `event_id` (uuid). The job inserts with
`insertOrIgnore` against a partial unique index on `event_id`, so a re-delivered
job never double-counts.

### Eventual consistency tradeoff
Link/block counters are updated by the once-a-minute `analytics:flush-counters`
fold, so totals lag real time by up to ~1 minute. `links:recount` (RecountLinkStats)
remains the periodic backstop that re-derives exact totals.

## Counters: `counter_deltas`
Append-only table. The job appends aggregated deltas per batch; the flush command
folds them with a max-id high-water mark (single `UPDATE` per entity, then deletes
`id <= max`). Because only one scheduled worker folds, there is no hot-row
contention regardless of click rate.

## Daily rollups
`analytics:rollup-daily` finalizes whole past days into `link_click_daily` and
`link_click_daily_dimensions`, advancing the `analytics.rollup.last_date`
watermark and re-rolling a short trailing lookback (`--lookback`, default 2 days)
for late clicks. `AnalyticsRollupReader` serves finalized days from the rollup and
only the current day from raw `link_clicks`, keeping `Api/LinkController::analytics`
(`by_day` / `by_country` / `by_device` / `by_source`) fast at 100M rows. Mobile
API response key names are unchanged.

## Time partitioning (operator-gated)

The high-volume tables (`link_clicks`, `page_sessions`) are designed for native
monthly **RANGE** partitioning on their time column (`clicked_at` / `started_at`).
Partitioning lets retention drop a whole expired month as an O(1) metadata
operation instead of deleting tens of millions of rows.

### Why conversion is NOT an automatic migration
Postgres cannot turn an already-populated plain table into a partitioned one in
place. The safe conversion is *create a partitioned twin → copy data → swap
names*, which on a shared cross-region RDS with ~100M rows must happen in a
planned maintenance window — never inside `migrate --force` on deploy.

### Partitioning rules that the conversion must respect
Postgres imposes two hard rules on a partitioned table that a plain table does
not have, so a naive "copy + swap" is **not** enough:

- The **PRIMARY KEY and every UNIQUE key must include the partition key column**
  (`clicked_at` / `started_at`). So `link_clicks_pkey (id)` becomes
  `(id, clicked_at)` and `page_sessions_pkey (id)` becomes `(id, started_at)`.
- **Unique indexes cannot carry a partial (`WHERE`) predicate.** The
  `event_id` idempotency index (`UNIQUE (event_id) WHERE event_id IS NOT NULL`)
  becomes a non-partial `UNIQUE (event_id, clicked_at)`. This preserves the
  intent: `insertOrIgnore` (`ON CONFLICT DO NOTHING`) still dedupes a
  re-delivered job (same `event_id` + `clicked_at`), and `NULL` event_ids never
  collide in a unique index anyway.

`PartitionManager::convertToPartitioned()` handles both automatically — it
introspects the live table and recreates the PK, unique keys, secondary indexes,
foreign keys and the owning `id` sequence on the partitioned twin, injecting the
partition key and dropping partial predicates where required.

### Conversion runbook
1. Dry-run to print the exact SQL and current row count:
   ```
   php artisan tracking:setup-partitions link_clicks
   ```
   Empty or small tables can be converted directly with `--execute`, which runs
   the full faithful conversion (twin → DEFAULT partition + rolling month
   partitions → copy → recreate PK/unique/secondary indexes/FKs/sequence → swap)
   in one transaction and retains the original rows as `<table>_old_plain`. The
   command refuses to auto-convert a large populated table and prints the runbook
   instead.
2. For a large table, in a maintenance window run the printed SQL: create the
   partitioned twin + DEFAULT partition + the month partitions you need,
   `INSERT ... SELECT` (batch for large tables), rename swap, then recreate the
   keys/indexes/sequence **per the rules above** (PK/unique include the partition
   key; no partial unique). Verify row counts, then drop the retained
   `*_old_plain` table.
3. Once partitioned, the scheduled `tracking:maintain-partitions` keeps a rolling
   buffer of future month partitions (`--months-ahead`, default 3) so inserts
   always have a target. Historical rows whose month has no dedicated partition
   land in the catch-all DEFAULT partition; `stats:prune-history` drops whole
   expired month partitions (O(1)) and chunk-deletes any DEFAULT remainder.

`tracking:maintain-partitions` and the partition-drop path in retention are both
**no-ops** until a table is actually partitioned, so they are safe to schedule
before conversion.

## Retention safety cap & visibility

`stats:prune-history` runs daily and enforces retention with a hard physical cap:

| Plan retention (max across active plans) | `stats.hard_max_days` (AppSetting) | Result |
|---|---|---|
| finite N days | unset | prune older than N |
| finite N days | H days | prune older than `min(N, H)` |
| unlimited (-1) or unconfigured | unset | **no delete**, but report sizes + alert if growing unbounded |
| unlimited (-1) or unconfigured | H days | prune older than H; admins alerted that the hard cap (not plan policy) deleted data |

Key safety properties:
- Plan retention is the **global maximum** across active plans (the tables aren't
  partitioned by plan, so we must never delete data the most generous plan can
  still show).
- Unlimited / unconfigured plan retention never deletes by itself — but it is
  **never a silent no-op**: every run records `stats.prune.last_run` and, when a
  table's estimated size crosses `stats.alert_row_threshold`
  (default 50M) with nothing to prune it, raises a once-per-day admin system alert.
- When a table is partitioned, expired whole months are dropped first; a chunked
  row delete mops up any remainder (DEFAULT partition / non-partitioned tables).

Relevant AppSettings:
- `stats.hard_max_days` — operator hard physical cap (days). Bounds storage even
  under unlimited plan retention.
- `stats.alert_row_threshold` — estimated row count that triggers the growth alert.
- `stats.prune.last_run` — last run's outcome (for the admin dashboard / audit).

The retention rules above are resolved through `Common\Support\StatsRetentionPolicy`
(single source for the prune command + the admin read surfaces) and surfaced as a
cached read model by `Common\Support\StatsStorageHealth`.

### Admin UI

Admins don't need shell access to see or bound this growth:
- **Web**: `/admin/stats-storage` (System nav → "Analytics Storage", gated by
  `settings.manage`) shows the effective retention window + reason, plan retention,
  hard cap, per-table estimated row counts with an over-threshold badge, and the last
  sweep outcome — and lets an admin set or clear `stats.hard_max_days` and
  `stats.alert_row_threshold`. The admin dashboard also raises an amber banner when a
  table is growing unbounded.
- **Mobile**: same panel at `/admin/stats-storage` over `GET|PUT /api/v1/admin/stats-storage`
  (gated by `settings.manage`, exposed as the `manage_settings` capability).

## Load / latency verification

`tracking:verify-scale` seeds synthetic clicks (tagged `source='verify-scale'`)
and measures insert throughput, counter-flush latency, rollup latency, and
dashboard read latency through `AnalyticsRollupReader`. Run on a staging / load
box, never as a scheduled job.

```
# plan only
php artisan tracking:verify-scale --dry-run

# seed 100k clicks against link 123 spread over 30 days, then measure
php artisan tracking:verify-scale --link=123 --rows=100000 --days=30

# remove exactly what was seeded
php artisan tracking:verify-scale --cleanup
```

The harness reports rows/sec for inserts and milliseconds for the flush, rollup,
and dashboard read so regressions in any stage are visible before they reach
production scale.

> **Synthetic-row tag column.** Seeded rows are tagged on the `source` column
> (`source='verify-scale'`), which is what `--cleanup` deletes by. The harness
> still filters its payload to columns that actually exist (`filterColumns`), so
> it tolerates schema drift, but the **tag column must be present** or `--cleanup`
> cannot identify what to remove (it now fails loudly instead of erroring on a
> missing column). Earlier revisions tagged on a non-existent `utm_source`
> column, which silently produced untaggable, un-cleanable synthetic rows.

### Recorded run — first verification pass

First end-to-end pass of the harness against a real link. **Important caveat:**
this was run against a far, high-latency Postgres (~0.8 s per query round-trip)
that was also being mutated concurrently (partition count changed between
invocations), i.e. **not** a dedicated, production-scale, low-latency load box.
The absolute milliseconds below are therefore dominated by per-query network RTT,
not by the pipeline's algorithmic behaviour, and must **not** be read as
production baselines. They are useful only as (a) a functional smoke that every
stage runs end-to-end and `--cleanup` removes exactly what was seeded, and (b) a
view of the *relative* cost of each stage.

| Stage | Measurement (network-bound) | Notes |
|---|---|---|
| Insert | ~4000 rows in ~5.6 s (~720 rows/sec) | Single synchronous connection, batched inserts; throughput is RTT-bound, not CPU/IO-bound. |
| Counter flush | ~1.5 s | `counter_deltas` empty in this harness (see limitation below), so this is mostly command/bootstrap + fold overhead, not real fold cost. |
| Daily rollup | ~33 s for a 2-day window | Heaviest stage; cost scales with `days × dimensions` query count (≈18–21 queries/day). At ~0.8 s/query this dominates. |
| Dashboard read (`byDay`) | ~4.6 s | A handful of reader queries (finalised rollup + current-day raw); RTT-bound. |

Operational findings from this pass:

- **Rollup first-run backfill vs `--lookback`.** `analytics:rollup-daily` ignores
  `--lookback` when there is no watermark and backfills 30 days. The harness only
  passes `--lookback`, so on a fresh box the rollup stage measures a 30-day
  backfill (≈30 × ~18 queries) — not a `--days`-sized window like the other
  stages. Prime the watermark first (`analytics:rollup-daily --days=2`) to get a
  bounded, comparable rollup measurement, or the rollup call will not complete on
  a high-latency box.
- **Flush stage under-measured.** `verify-scale` inserts straight into
  `link_clicks` and never appends to `counter_deltas`, so `analytics:flush-counters`
  folds an empty table and the reported flush time is essentially fixed overhead.
  To exercise the real fold path, the harness would need to seed `counter_deltas`
  (or drive the buffered `PersistLinkClicksJob` path) — see follow-ups.
- **Insert path is single-connection and synchronous**, so reported rows/sec is a
  floor; production write throughput comes from many concurrent request workers
  feeding the async job, which this single-process harness does not model.

To get trustworthy production-scale baselines, re-run this harness on a dedicated,
low-latency staging Postgres (co-located, not cross-region) at large `--rows`
(e.g. 1M+), both before and after partition conversion, and record the numbers
above as the real baseline.

## Scheduled jobs (routes/console.php)

| Command | Cadence | Purpose |
|---|---|---|
| `queue:work --stop-when-empty` | every minute | drain `PersistLinkClicksJob` |
| `analytics:flush-counters` | every minute | fold counter deltas |
| `analytics:rollup-daily` | daily 03:45 | finalize daily rollups |
| `tracking:maintain-partitions` | monthly (1st, 02:30) | provision future partitions |
| `stats:prune-history` | daily 04:05 | retention + growth visibility |

All scheduled with `withoutOverlapping()->onOneServer()`.
