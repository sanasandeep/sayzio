---
name: Running 1inme Feature tests locally
description: Why the 1inme PHPUnit Feature suite is impractical to run in isolated/dev envs, and the partial workaround.
---

# Running 1inme Feature tests locally

The 1inme Feature suite is effectively un-runnable in isolated task envs / dev because of the cross-region RDS (`ap-south-2`) latency, on top of the already-documented distant-DB problems.

**Two compounding costs:**
1. `RefreshDatabase` does one `migrate:fresh` per phpunit process. Over cross-region RDS that is ~30–45 min for the ~233 migrations, and backgrounded runs get reaped mid-migration (leaving `1inme_testing` partially migrated).
2. Even with migration skipped, each test BOOT issues many cross-region queries (settings, middleware, plan/feature lookups, etc.), so per-test wall time is minutes. 3 tests took 15+ min.

**Partial workaround (read-only verification only, NOT a clean run):**
- The CLI default connection points at the fully-migrated **dev** DB (`DB_DATABASE=postgres`), while phpunit forces `1inme_testing` via a *non-forced* `<env>` — so a real process env var overrides it.
- `SHARDED_TEST_SKIP_MIGRATION=1 DB_DATABASE=postgres php vendor/bin/phpunit --filter=...` skips `migrate:fresh` (bootstrap.php sets `RefreshDatabaseState::$migrated=true`) and runs against the dev schema, with each test wrapped in a transaction that rolls back.
- **Danger:** if `SHARDED_TEST_SKIP_MIGRATION` is NOT set, this runs `migrate:fresh` and DROPS the dev DB. Always confirm the flag is in the process env before trusting it.
- **Limitation:** tests that scan global tables (e.g. `RenewDueSubscriptions` counting due subscriptions) are NOT isolated from real dev data, so count-based assertions are non-deterministic there. They only behave on the empty schema that `migrate:fresh` gives in CI.

**Bottom line:** trust CI (`.github/workflows/laravel-tests.yml`, local Postgres) for green; locally rely on `php -l` + static cross-check of referenced symbols/signatures. Don't burn an hour waiting on cross-region `migrate:fresh`.
