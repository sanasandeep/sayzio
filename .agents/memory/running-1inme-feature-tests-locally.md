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

**Better local recipe (works, used to get PaidPageTest green in ~7s):** point DB_* at the **local `helium`** Postgres `1inme_testing` DB (not the dev DB, not RDS) and skip migration:
```
HELIUM_PW=$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)
DB_HOST=helium DB_PORT=5432 DB_DATABASE=1inme_testing DB_USERNAME=postgres \
DB_PASSWORD="$HELIUM_PW" DB_SSLMODE=disable DB_URL= DATABASE_URL= \
SHARDED_TEST_SKIP_MIGRATION=1 php artisan test --filter=YourTest
```
- The isolated-env **process env** sets `DB_HOST=...rds.amazonaws.com DB_DATABASE=postgres DB_SSLMODE=require` (un-migrated RDS), which overrides phpunit's non-forced `<env>` — that's why a naive `php artisan test` hits RDS `postgres` and 500s on missing tables. Override DB_* explicitly on the command line.
- `helium` `1inme_testing` is a real, mostly-migrated dedicated test DB (sslmode=disable). SKIP_MIGRATION reuses its schema and rolls back each test in a transaction, so it never wipes anything. It's stale vs the full migration set, so a test needing a brand-new column may still need CI.

**Public-page Feature test pitfall (workspace scope):** the catch-all `/{alias}` (and `/@handle`) routes have NO SetActiveWorkspace middleware, so a real visitor request carries no bound `current_workspace` and Link's `workspace` global scope is skipped. Test setup helpers that bind `current_workspace` (needed so created models get a workspace_id) leak that binding; the **last** user built wins, so a guest/visitor GET wrongly scopes `resolveByAlias` to that workspace and 404s the owner's link. Fix: `app()->forgetInstance('current_workspace')` (+ `workspace_owner`) right before the public GET. Also: creator-page renders need `$creator->handle` set or `branded-reactions.blade.php`'s `route('creator-profile.react', ['handle'=>...])` throws a UrlGenerationException.

**Stale-helium cascade (and the real fix — just migrate helium up):** any test that touches a recently-added column/table helium lacks fails with a SWALLOWED first query (app try/catch) surfacing only as a cascade `SQLSTATE[25P02] current transaction is aborted` on a later innocent query (`plans`, `app_settings`, even `users.fresh()`). It hits user-creation paths (the `User::created` provisioner hook) AND request-pipeline paths (auth/maintenance middleware). The cascade gives no clue which migration is missing.
**Don't trust-CI-and-give-up — helium `1inme_testing` is LOCAL (`DB_SSLMODE=disable`), so plain `migrate` is ~ms/migration (a big backlog drains in minutes, data backfills included).** Check `migrate:status | grep -ci pending`; if behind, run `... php artisan migrate --force` against helium (NOT fresh, NOT RDS), then `SHARDED_TEST_SKIP_MIGRATION=1 php artisan test --filter=...` runs the full suite GREEN, including AI/wizard flow tests. The earlier "trust CI for flow tests" advice was only because helium was stale — migrating it up is the cheaper, decisive path. (Distant-RDS `migrate:fresh` is still the thing to avoid; helium plain `migrate` is fine.)

**Linked-identifier test gotcha:** `User::created` auto-attaches ONE primary, verified `email` LinkedIdentifier from `users.email`. A test helper that *creates its own* primary email (the old `verifyEmail` pattern) then violates the `linked_identifiers_one_primary_per_user` partial-unique — fails in CI too, masked on stale helium by the cascade. Fetch the auto-created row instead of making a second primary.

**NEVER run a naive `php artisan test ...` (no DB_* override) in an isolated env:** the process env points DB at the un-migrated cross-region RDS `postgres`, and `RefreshDatabase` runs `migrate:fresh` on first boot = it DROPS that DB and partially replays (leaving orphaned committed DDL). It also wastes ~90s/boot on cross-region latency. Always either use the helium recipe above, or accept CI-only for flow tests.

**Bottom line:** prefer the helium `1inme_testing` + SKIP_MIGRATION recipe for fast local green on schema-stable tests; for AI/wizard flow tests trust CI (`.github/workflows/laravel-tests.yml`). Don't burn an hour on cross-region `migrate:fresh`.

**Best recipe when helium is stale / unavailable — ephemeral throwaway local Postgres (full `migrate:fresh` GREEN in ~21s):** the `postgresql` nix binaries (`initdb`,`pg_ctl`,`psql`) are on PATH. Spin a private cluster, run the *real* `RefreshDatabase` migrate:fresh (all ~318 migrations) against it, then drop it. Totally isolated — never touches RDS or helium dev data, so no wipe risk and no SKIP_MIGRATION needed.
- **Gotcha 1 — reaping:** a backgrounded `pg_ctl start` is KILLED between tool calls. Start the server AND run the tests in ONE bash invocation (start → poll-ready → `php artisan test` → `pg_ctl stop`).
- **Gotcha 2 — socket dir:** `initdb`'s default unix-socket lock dir is `/run/postgresql` (missing → `FATAL: could not create lock file`). Pass `-o "-p <port> -k /tmp"` on start.
- **Gotcha 3 — env override:** `unset DB_DATABASE` (or `env -u DB_DATABASE`) so phpunit.xml's non-forced `1inme_testing` applies, and pass `DB_HOST=127.0.0.1 DB_PORT=<port> DB_USERNAME=postgres DB_PASSWORD= DB_SSLMODE=disable PGSSLMODE=disable`.
- Full working sequence: `initdb -D /tmp/pg -U postgres --auth=trust`; `pg_ctl -D /tmp/pg -o "-p 55432 -k /tmp" -l /tmp/pg.log start`; poll `psql -h 127.0.0.1 -p 55432 -U postgres -d postgres -c 'select 1'`; `createdb 1inme_testing`; then `env -u DB_DATABASE DB_HOST=127.0.0.1 DB_PORT=55432 DB_USERNAME=postgres DB_PASSWORD= DB_SSLMODE=disable PGSSLMODE=disable php artisan test --filter=YourTest`. (First test pays the ~14s migrate:fresh; the rest are ~0.2s each.)
