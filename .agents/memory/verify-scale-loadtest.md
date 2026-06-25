---
name: tracking:verify-scale load-test gotchas
description: Why the verify-scale harness can't produce real baselines in isolated 1inme envs, plus its rollup/cleanup pitfalls.
---

# tracking:verify-scale load testing

The harness exists to measure insert/flush/rollup/read latency for the scaled
tracking pipeline. Running it "at production scale" in a Replit isolated 1inme
env does NOT yield production baselines. Two structural reasons:

- **The env DB is far + high-latency + concurrently mutated.** Per-query RTT
  measured ~0.8 s, and the `link_clicks`/`page_sessions` partition count changed
  between consecutive command invocations (5 → 7) within one session — i.e. other
  agents / scheduled partition maintenance touch the same DB mid-run. So absolute
  ms are RTT-bound, not pipeline-bound, and the table state isn't stable enough
  for a clean before/after-partition comparison.
  **How to apply:** for trustworthy baselines, run on a dedicated low-latency
  (co-located, non-cross-region) staging Postgres; here, treat results as a
  functional smoke + relative-stage view only.

- **120s tool cap vs throughput.** ~720 rows/sec single-connection means 100M
  rows is ~hours; large `--rows` won't fit one bash call. Background procs get
  reaped. Keep runs modest and prime state first.

## Harness pitfalls found & fixed
- **Tag/dimension column drift.** The harness tagged synthetic rows on
  `utm_source` and seeded `country`/`device`/`referer` — none of which exist on
  the real `link_clicks` (it has `source`, `country_code`, `device_type`,
  `referrer`). `filterColumns` silently dropped them, so rows were untaggable and
  `--cleanup` (which deletes by the tag column) couldn't find them. Fixed to tag
  on `source` and seed the real dimension columns; cleanup now hard-fails if the
  tag column is missing instead of erroring on a non-existent column.
  **Why:** a load harness whose cleanup can't identify its own rows is worse than
  useless on a shared/real DB.

## Operational note: rollup won't complete on a fresh box
`analytics:rollup-daily` ignores `--lookback` when there is **no watermark** and
backfills 30 days (`resolveStart` → `now()->subDays(30)`). The harness passes only
`--lookback`, so a first run does a 30-day backfill (~30 × ~18 queries) and times
out on a high-latency DB. Prime once with `analytics:rollup-daily --days=2` to set
the watermark, then the harness's rollup call is bounded to the lookback window.
