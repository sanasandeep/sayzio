# Contributing to 1INME

## Pre-flight: run migrations from scratch on PostgreSQL

Before pushing changes that touch anything under
[`database/migrations/`](database/migrations), please run the same check CI
runs and make sure it passes locally:

```bash
cd artifacts/1inme
php artisan migrate:fresh --force
```

> **Run this against PostgreSQL, not SQLite.** The bug class this guard is
> meant to catch (e.g. PDO `?` placeholder vs. Postgres `jsonb ?` operator,
> Postgres-specific DDL) only shows up on Postgres. Make sure your `.env` has
> `DB_CONNECTION=pgsql` and that `DB_HOST`/`DB_PORT`/`DB_DATABASE`/
> `DB_USERNAME`/`DB_PASSWORD` point at a reachable PostgreSQL instance before
> you run the command — otherwise a green local result means nothing.

This rebuilds the schema from a clean database, so any migration that crashes
on a fresh PostgreSQL install (e.g. a PDO `?` placeholder colliding with the
Postgres `jsonb ?` operator, a missing column referenced in a backfill, a
nondeterministic ordering bug, etc.) shows up immediately instead of after the
change has shipped.

A GitHub Actions job — `.github/workflows/laravel-migrations.yml` — runs the
exact same command against a real PostgreSQL 16 service on every push or pull
request that changes:

- `artifacts/1inme/database/migrations/**`
- `artifacts/1inme/composer.json` / `composer.lock`
- the workflow file itself

If that job is red, your migrations will not boot a fresh install. Fix them
locally with the command above before merging.

## Backfill / seed migration `down()` policy

A *backfill* or *seed* migration is any migration under
[`database/migrations/`](database/migrations) whose `up()` mutates rows or JSON
values rather than just changing schema. Examples: inserting starter rows into
`site_pages`, appending a default to every plan's `features` JSON, marking
historical rows with a "status = unknown" sentinel.

Their `down()` must follow these rules so a rollback never silently throws
away admin-edited or recovered data:

1. **Default to a no-op.** If the migration only adds rows or only appends
   keys to existing rows, `down()` must be a no-op with a one-line comment
   explaining why. Reverting an insert means deleting a row that an admin or
   curator may have edited since the upgrade ran — that is irreversible data
   loss for them.

2. **Never delete a row unconditionally.** If a paired schema change forces
   the seeded rows out (e.g. you are dropping the column they were created to
   populate), gate every delete on a "still equals the seeded value" check.
   Read the row first and skip the delete if any column the seed wrote
   differs from what `up()` wrote — that means the row has drifted and is no
   longer ours to remove.

3. **Never strip a JSON key unconditionally.** The same rule applies to keys
   inside JSON columns (`features`, `extra`, `settings`, `meta`, etc.): only
   `unset` a key if its current value is structurally equal (PHP `==` on the
   decoded array) to the default the matching `up()` wrote. If it has
   changed, an admin or a later migration touched it — leave it.

4. **Pure schema changes are exempt.** Dropping a column on rollback is
   expected to lose the data in that column. The rules above only apply to
   the row / JSON-value mutations a backfill performs, not to the column
   add/drop the same migration may also ship.

The only acceptable shapes of a backfill `down()` are therefore:

- a no-op with a comment explaining why, or
- a guarded undo that compares each affected row / JSON value against the
  exact default `up()` wrote and skips it on any drift.

When in doubt, prefer no-op. A failed rollback is recoverable; an erased
admin edit is not.

## Pre-flight: run the PHPUnit suite against PostgreSQL

The PHPUnit suite under [`tests/`](tests) used to default to a sqlite
in-memory database, which made it possible for Postgres-only bugs (e.g. the
`jsonb ?` operator collision) to pass locally and then fail in CI / production.
[`phpunit.xml`](phpunit.xml) now defaults to PostgreSQL so local runs exercise
the same engine production uses.

To run the suite locally:

```bash
cd artifacts/1inme
php artisan test
```

### Sharded runner (bounded memory for the full suite)

`php artisan test` runs **every** test in one long-lived PHP process. Each test
boots a fresh Laravel app (~1.4k lines of routes) and builds cyclic object
graphs, so resident memory grows roughly linearly across the run. As the app
keeps adding routes and tests, that single process eventually approaches the
`memory_limit` ceiling in [`phpunit.xml`](phpunit.xml).

The durable fix is **process isolation**: split the suite across several
short-lived phpunit processes ("shards") so peak memory is bounded by the
*largest shard*, not the whole suite — no matter how many tests are added.

```bash
cd artifacts/1inme
composer test:sharded                      # default: 4 shards
php scripts/run-sharded-tests.php --shards=6
php scripts/run-sharded-tests.php --dry-run # print the shard plan, run nothing
```

How it stays fast despite multiple processes:

- The `migrate:fresh` (224 migrations against remote Postgres — the slow part)
  is run **once**, by the first shard, on the shared `1inme_testing` database.
- Every later shard is launched with `SHARDED_TEST_SKIP_MIGRATION=1`, which
  [`tests/bootstrap.php`](tests/bootstrap.php) uses to tell Laravel's
  `RefreshDatabase` trait the schema already exists, so those shards skip
  migrating and only wrap each test in a transaction (rolled back per test, so
  the shared database stays clean across shards).
- Because shards run sequentially against the one database, there is **no**
  per-worker database creation/migration cost — the reason `paratest` was not
  viable in this environment.

Test files are spread across shards with a largest-first greedy heuristic
(file size as a duration proxy) to keep shards balanced. The runner streams each
shard's output live and exits non-zero if **any** shard failed, printing the
failing shard numbers.

Use `php artisan test` for everyday single-class iteration
(`php artisan test --filter=SomeTest`); reach for `composer test:sharded` when
running the whole suite, in CI, or whenever the single-process run gets close to
the memory ceiling.

### One-time local setup

The suite uses `RefreshDatabase`, which **drops and re-creates every table** in
the configured database on each run. To keep that destruction safely isolated
from your dev data, [`phpunit.xml`](phpunit.xml) pins the test database name
to `1inme_testing` (separate from the `1inme` dev database in
[`.env.example`](.env.example)).

> **In Replit task environments this is automated.** The post-merge setup
> script (`scripts/post-merge.sh`) creates the `1inme_testing` database after
> every merge — idempotently, on the same connection your `.env` points at — so
> RefreshDatabase feature tests (e.g. `EmailOnlyLoginPolicyTest`) run without
> any manual step. The steps below are the manual fallback for fresh local
> checkouts outside that flow.

1. Make sure your `.env` points at a reachable local PostgreSQL instance —
   `DB_CONNECTION=pgsql`, plus working `DB_HOST` / `DB_PORT` / `DB_USERNAME` /
   `DB_PASSWORD` values. (`phpunit.xml` only forces `DB_CONNECTION` and
   `DB_DATABASE`; the host/port/credentials fall through from your `.env`.)
2. Create the dedicated test database once:

   ```bash
   createdb -h "$DB_HOST" -U "$DB_USERNAME" 1inme_testing
   # or, equivalently:
   psql -h "$DB_HOST" -U "$DB_USERNAME" -c 'CREATE DATABASE "1inme_testing";'
   ```

3. Run `php artisan test` and you should see the suite spin up against
   Postgres. If it falls back to sqlite or fails to connect, double-check that
   the `1inme_testing` database exists and that your `.env` credentials can
   reach it.

> **Why not sqlite anymore?** Sibling workflow
> `.github/workflows/laravel-tests.yml` runs the suite on PostgreSQL in CI
> precisely because Postgres-only regressions used to slip through a
> sqlite-backed local run. Defaulting `phpunit.xml` to `pgsql` means
> developers catch those regressions before pushing instead of after.

> **CI behavior is unchanged.** PHPUnit's `<env>` entries don't override
> values that already exist in the process environment, so the explicit
> `DB_*` env vars set by `.github/workflows/laravel-tests.yml` continue to
> win there.
