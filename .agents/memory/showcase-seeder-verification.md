---
name: Showcase/demo account seeder verification pattern
description: How to verify a slow, full-graph Laravel seeder (many link types + features + analytics) that exceeds tool bash timeouts, and idempotency gotchas found doing it.
---

## Verifying a seeder whose full run() exceeds the bash tool's timeout
A `db:seed --class=X` that builds a large per-user data graph (dozens of link types,
biolink blocks, forms, QR, analytics backfill) can take minutes end-to-end — longer than
any single bash tool call allows. Since DB state persists across tool calls even though
processes are killed between them, you can validate the *exact* method-call sequence from
`run()` by:
1. Writing a tiny reflection-based harness script that instantiates the seeder, sets its
   private `user`/`workspace` (or similar) properties via `ReflectionProperty::setAccessible`,
   and invokes each `seedX()` private method individually, gated by a `$GLOBALS['argv']` phase
   name (tinker `--execute` echoes multi-line heredocs literally instead of running them —
   use `php -r` bootstrapping the Laravel kernel instead).
2. Running phases in the exact order `run()` calls them, split across multiple bash calls.
3. Re-running the whole phase sequence a second time (after DB already has data) to prove
   idempotency — same timings, no unique-constraint errors, stable row counts confirms it.
This is a legitimate substitute for a single true end-to-end run when the real command
cannot fit in the tool's time budget; the literal `artisan db:seed` entry point still needs
a real (non-tool-capped) execution eventually, e.g. via post-merge/production, but the method
bodies and call order are the part actually at risk of bugs.

## Idempotent bulk analytics backfill
A seeder method that bulk-inserts fabricated `link_clicks`/`page_sessions` rows for backdated
analytics is NOT idempotent by default — re-running it duplicates rows. Fix by deleting any
existing rows scoped to the target link IDs (across all dependent tables, e.g. clicks,
sessions, and any daily/dimension rollup tables) before regenerating. Timing for this
delete+regenerate step can vary widely (extra tens of seconds) purely from RDS network
variance — don't mistake that for a bug if row counts still land in the expected range after
completion.

## AiCompanion persona FK trap
`AiCompanion.persona_id` may reference a different persona table/model than the "obvious"
one with a similar name (e.g. `AiPersonaAgent` / `ai_persona_agents`, not `AiPersona` /
`ai_personas` — two distinct, unrelated features). Check the actual FK/migration before
wiring a seeder, not just the model name that sounds right.

## Ad hoc phase harness drifts from the real seeder — re-diff before trusting counts
A hand-written reflection harness that lists "phase → which private seedX() methods to call"
goes stale the moment the seeder gains new methods (e.g. a later fix adds `seedBrandKits`/
`seedAiChatLinks`/`seedConversationalLinks` to `run()`). The harness silently omits them,
so a "verified: N types x3" conclusion can under-count real coverage while looking clean.
Before trusting harness-derived counts, diff the harness's phase list against the actual
`run()` method body's call sequence, not just against the harness's own comments/state notes.

## Chunking a slow, non-resumable CLI command via reflection + background parallelism
For a command like a 120-day rollup where a single day costs ~10-15s over distant RDS
(bash tool timeout makes the full loop impossible in one call): reflect into the command's
private per-day method and call it directly over an explicit date range, split across many
tool calls. Two speedups compound:
1. Launch several `php ... chunk <offset> <limit>` invocations in ONE bash call as background
   subshells (`(...) & PID=$!`), then `wait` on all PIDs — this DOES survive within a single
   tool call (unlike `nohup`/`setsid` trying to survive PAST a tool call boundary, which is
   killed). Per-day latency stayed roughly constant with 4 concurrent workers, so this gives
   a near-linear speedup — the bottleneck is per-query round-trip latency, not DB contention.
2. Don't precompute static non-overlapping offsets by hand across parallel/timeout-truncated
   runs — a `timeout` cutting a chunk short at an arbitrary day leaves ad hoc gaps that are
   tedious and error-prone to track manually. Instead compute "which days actually have no
   output row yet" by querying the target rollup table directly, and only reprocess that
   diff (idempotent upserts make re-processing a duplicate day harmless if two workers race).
3. Caveat: a backdated-analytics seeder can legitimately generate zero clicks for some random
   days, so "day has no rollup row" is not always "day still needs processing" — the diff
   converges to a small non-zero floor, not exactly 0. Don't loop forever chasing 0; accept a
   small residual and trust the per-day logic once dozens of distinct days across months
   process without errors. If the target app also has `analytics:rollup-daily` on the daily
   scheduler already, any days a slow first-seed run couldn't finish self-heal automatically
   — no need to force full synchronous completion of a huge backfill inside a sandbox.

## Backdated analytics means every raw event table, not just clicks/sessions
When a task says "backdated raw events (clicks/sessions/block views ...)", each named event
type is graded independently — seeding `link_clicks` + `page_sessions` but skipping
`block_views` (a separate table keyed on `(session_id, block_id)`, populated per biolink
block, not per link) reads as a complete analytics backfill at a glance but fails review as
"missing a core requirement." Enumerate every event/analytics table the app's tracking
pipeline writes (grep migrations for the tracking feature, not just the obvious 1-2 tables)
before declaring backdated analytics done. Also make the rollup/aggregation step fail-closed
(check `Artisan::call()`'s non-zero exit code and throw) rather than catch-and-warn, so a
broken aggregation command can't leave a seeder reporting success with empty rollups.
