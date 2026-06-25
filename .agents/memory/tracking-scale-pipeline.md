---
name: Scaled tracking write/read pipeline
description: How 1inme link/visit tracking is decoupled for 1M-user scale — async writes, delta counters, rollups, partitioning, retention safety.
---

# Scaled tracking pipeline (1inme Laravel)

The redirect/click hot path is decoupled from durable writes. Full runbook lives
in `artifacts/1inme/docs/scaling-tracking.md`.

## Layers (each independently a no-op-safe addition)
- **Async write**: `ClickWriteBuffer` (request singleton, terminating flush) +
  `PendingClick` DTO + `PersistLinkClicksJob`. `LinkTrackingService` keeps
  bot/throttle/blocked-family gate + atomic block-cap reservation SYNC and defers
  geo/insert/counters/events. `track()` returning `null` STILL means block-cap
  refusal — preserve that contract.
- **Counters**: never UPDATE hot `links.total_clicks`/`biolink_blocks.click_count`
  directly. Append to `counter_deltas`; `analytics:flush-counters` (single
  scheduled worker) folds via a captured max-id high-water mark then deletes
  `id <= max`. Counters are **eventually-consistent (~1 min)**; `links:recount`
  (RecountLinkStats) is the exact-total backstop.
- **Rollups**: `analytics:rollup-daily` finalizes whole past days into
  `link_click_daily`(+`_dimensions`), watermark = AppSetting
  `analytics.rollup.last_date`, re-rolls a short lookback for late clicks.
  `AnalyticsRollupReader` = rollup for finalized days + raw for current day;
  wired into `Api/LinkController::analytics`. Mobile response key names unchanged.

## Time partitioning is OPERATOR-GATED, not a migration
Postgres can't convert a populated table to partitioned in place. On the shared
cross-region RDS this is a maintenance-window task. `tracking:setup-partitions`
prints a runbook for large tables and refuses to auto-convert; only empty/small
tables convert with `--execute`. `tracking:maintain-partitions` (create future
months) and the retention partition-drop path are **no-ops until partitioned**,
so they're always safe to schedule. The whole pipeline works fine unpartitioned.

## Retention safety (`stats:prune-history`)
Plan retention = GLOBAL max `stats_retention_days` across active plans; `-1` or
unconfigured ⇒ never delete by itself. But it is **never a silent no-op**: it
records AppSetting `stats.prune.last_run` and raises a once-per-day admin
`systemAlert` when an estimated table size (planner `reltuples`, not count(*))
crosses `stats.alert_row_threshold` with nothing to prune. Operator hard cap
AppSetting `stats.hard_max_days` bounds storage even under unlimited retention
(prunes `min(plan, hard)`), alerting that the cap did the deleting.

## Durability rules for the async write path (don't regress these)
- `ClickWriteBuffer::flush()` must clear the buffer ONLY after a confirmed
  handoff. On queue dispatch failure it persists the batch synchronously in
  `terminate()` (post-response, so no visitor latency) — never clear-then-dispatch
  (that silently drops clicks on a queue outage).
- `PersistLinkClicksJob` must NOT blanket-swallow per-row exceptions. Skip only
  permanently-bad payloads (QueryException SQLSTATE class 22/23 — e.g. FK to a
  deleted link); RETHROW transient DB errors (connection/deadlock/resource) so
  the queue retries. Retry needs job-level `$tries` (>1) because the scheduled
  worker runs `--tries=1`; insertOrIgnore on `event_id` makes replay safe.
  **Why:** code review rejected the first cut for losing clicks on both queue and
  DB failures. Counter drift from a partially-retried batch self-heals via
  RecountLinkStats, but the click ROWS must never be lost.

## Rollup read-path invariants (don't regress these)
- Switching an analytics surface to read from `link_click_daily(_dimensions)`
  (via `AnalyticsRollupReader`) means EVERY reset/clear path for that link must
  ALSO delete its rollup rows, not just raw `link_clicks`. Both the API
  (`Api\LinkController::reset`) and web (`User\LinkController::resetStats`) full
  resets clear the rollups; mobile/web parity requires it because the mobile API
  analytics endpoint reads the rollups. Alias-scoped resets stay partial (rollups
  are link/day aggregates, not alias-decomposable).
  **Why:** code review caught reset leaving stale rollup rows → analytics kept
  returning pre-reset by_day/by_dimension values.
- `analytics:flush-counters` must apply the counter UPDATEs and the matching
  `counter_deltas` DELETE in ONE `DB::transaction` (using a captured max-id
  high-water mark). Separate update-then-delete can double-apply on a mid-run
  crash → silent overcount.

## Gotcha: do not name command methods `alert()` or `callSilent()`
`Illuminate\Console\Command` already declares public `alert()` and
`callSilent()`. A private override is a fatal "access level must be public" error
that crashes EVERY artisan invocation (even unrelated migrations), because all
commands load at boot. Use distinct names (e.g. `raiseAlert`, `runMetered`).
