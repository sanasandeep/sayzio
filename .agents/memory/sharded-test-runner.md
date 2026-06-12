---
name: Sharded PHPUnit runner (1inme)
description: How the 1inme Laravel suite is sharded to bound test memory, and the constraints that make it work.
---

# Sharded PHPUnit runner

`artifacts/1inme` runs its full PHPUnit suite as several short-lived phpunit
processes via `composer test:sharded` (`scripts/run-sharded-tests.php`) instead
of one long-lived `php artisan test` process. Goal: bound peak memory by the
largest shard so it stays under the 512M ceiling regardless of test count.

**Why this design (the non-obvious parts):**
- Migration is paid ONCE. The first shard runs with `skip=0` and its
  `RefreshDatabase` performs the single `migrate:fresh` on the shared
  `1inme_testing` DB. Later shards launch with `SHARDED_TEST_SKIP_MIGRATION=1`;
  `tests/bootstrap.php` reads that env and sets
  `RefreshDatabaseState::$migrated = true`, so they skip migrating and only wrap
  each test in a transaction, reusing the schema. 224 migrations against remote
  Postgres are the slow part — never let them run per-shard.
- This is safe because tests call `$this->seed(...)` inside their own `setUp()`
  (per-test, inside the rolled-back transaction); none rely on RefreshDatabase's
  migrate-time `--seed`. So skipping migrate never skips needed seed data.
- `paratest` is NOT viable here: per-worker DB creation/migration made it 7x+
  slower and stalled against the remote Postgres host. Sequential shards on ONE
  DB avoid per-worker migration cost entirely.

**Assumption to preserve:** the first shard must contain at least one
`RefreshDatabase` test (true today — ~78% of files use it and the largest-first
distribution spreads them). If a future change makes shard 1 all non-DB tests,
no schema gets built and skip-shards fail. If that ever becomes a risk, switch
to an explicit up-front `migrate:fresh` with `DB_DATABASE=1inme_testing
DB_CONNECTION=pgsql` env vars (real env vars win over `.env` via phpdotenv).

**When skip=0 (normal `artisan test`), `tests/bootstrap.php` is a no-op** —
identical to the old `vendor/autoload.php` bootstrap. So any failures seen in
shard 1 are pre-existing suite/environment failures, not caused by sharding.
