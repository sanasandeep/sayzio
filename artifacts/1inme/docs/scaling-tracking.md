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

### Conversion runbook
1. Dry-run to print the exact SQL and current row count:
   ```
   php artisan tracking:setup-partitions link_clicks
   ```
   (Empty or small tables can be converted directly with `--execute`; the command
   refuses to auto-convert a large populated table and prints the runbook instead.)
2. In a maintenance window, run the printed SQL: create the partitioned twin +
   DEFAULT partition + the month partitions you need, `INSERT ... SELECT` (batch
   for large tables), rename swap, recreate indexes/sequences, verify, then drop
   the retained `*_old_plain` table.
3. Once partitioned, the scheduled `tracking:maintain-partitions` keeps a rolling
   buffer of future month partitions (`--months-ahead`, default 3) so inserts
   always have a target.

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

## Load / latency verification

`tracking:verify-scale` seeds synthetic clicks (tagged `utm_source='verify-scale'`)
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

## Scheduled jobs (routes/console.php)

| Command | Cadence | Purpose |
|---|---|---|
| `queue:work --stop-when-empty` | every minute | drain `PersistLinkClicksJob` |
| `analytics:flush-counters` | every minute | fold counter deltas |
| `analytics:rollup-daily` | daily 03:45 | finalize daily rollups |
| `tracking:maintain-partitions` | monthly (1st, 02:30) | provision future partitions |
| `stats:prune-history` | daily 04:05 | retention + growth visibility |

All scheduled with `withoutOverlapping()->onOneServer()`.
