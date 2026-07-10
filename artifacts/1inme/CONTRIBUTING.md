# Contributing to Sayzio

## Pre-flight: run the Blade/Alpine view guards before you push

Three static guards catch a class of bug that compiles and typechecks fine but
silently breaks whole Alpine components at runtime (dashboard, template picker,
etc.):

- **alpine-line-comments** — `//` line comments inside a double-quoted Alpine
  attribute expression (`x-data` / `x-init` / any `x-*` / `@*` / `:*`). The
  browser flattens the attribute to one line, so `//` swallows the rest —
  including the closing `)` / `}` — throwing an *Alpine Expression Error* that
  kills the whole component's bindings.
- **blade-json-in-attr** — `@json(` inside a double-quoted attribute (emits
  literal quotes that truncate `x-data` / `@click` and silently kill Alpine;
  use `@js` instead).
- **blade-comment-echo** — live `{{ }}` / `{!! !!}` echoes inside plain
  HTML/CSS comments.

These are enforced on **every merge** in
[`scripts/post-merge.sh`](../../scripts/post-merge.sh), but that only catches
offenders *after* the code is merged — the merge fails and someone has to fix
it and re-run over the distant RDS. Run them locally first so they never reach
a merge:

```bash
# From the repo root — runs all three guards together, one clear pass/fail:
pnpm --filter @workspace/scripts run check:view-guards
```

### Automatic pre-push enforcement (recommended)

A committed git hook runs `check:view-guards` before every `git push`. Install
it once per clone:

```bash
# From the repo root:
sh .githooks/install.sh
# (equivalently: git config core.hooksPath .githooks)
```

After that, a push carrying a broken Alpine/Blade attribute is rejected locally
in ~1s instead of failing a merge minutes later. To bypass in a pinch (not
recommended): `git push --no-verify`. The hook is a no-op if `pnpm` isn't
available; the post-merge pipeline still enforces the same guards as a backstop.

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
exact same command against a real PostgreSQL 16 (and MySQL 8) service. The
workflow triggers on **every** push and pull request, but a cheap `changes`
job first checks whether anything under `artifacts/1inme/**` (or the workflow
file itself) actually changed:

- if it did, the real `migrate cycle (…)` jobs run the full migrate:fresh /
  reset / migrate cycle;
- if it did not, a passthrough companion job reports the same check names as
  green so the job never blocks an unrelated (marketing-only, mobile-only,
  `lib/**`) PR.

This always-report design is what lets those jobs be marked as **required**
status checks in branch protection without deadlocking PRs that don't touch the
filtered paths — a required check that simply never runs would leave the PR
waiting forever. The same pattern is applied to every job in the sibling
`.github/workflows/laravel-tests.yml`.

If the real job is red, your migrations will not boot a fresh install. Fix them
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

- The `migrate:fresh` (248 migrations against remote Postgres — the slow part)
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
from your dev data, [`phpunit.xml`](phpunit.xml) defaults the test database name
to `1inme_testing` (separate from the `1inme` dev database in
[`.env.example`](.env.example)).

> **⚠️ Hazard: an exported `DB_DATABASE` can override `phpunit.xml`.** A PHPUnit
> `<env>` entry does **not** override a value already present in the process
> environment unless it is marked `force="true"` — and `DB_DATABASE` in
> `phpunit.xml` is intentionally **not** forced (see below). So if your shell
> has `DB_DATABASE` exported (e.g. `DB_DATABASE=postgres` pointing at the shared
> dev/live RDS), `php artisan test` connects **there** and the first
> `RefreshDatabase` test **drops every table on that database**. This actually
> happened once and wiped the shared dev DB.
>
> **Why not just add `force="true"`?** Because CI
> (`.github/workflows/laravel-tests.yml`) deliberately exports its own
> `DB_DATABASE` (`onein_me_ci` / paratest's `onein_me_ci_test_N`) and relies on
> it winning over `phpunit.xml`; forcing the value would clobber CI's database.
>
> **The real protection** is a fail-closed guard in
> [`tests/TestCase.php`](tests/TestCase.php): `setUp()` resolves the database the
> framework will actually use and **aborts the entire run before any migration**
> if it is not a recognized test database (a `*_testing` name locally, or any
> database when running in CI). If you see `ABORTING TEST RUN: refusing to run
> against a non-test database`, unset the stray `DB_DATABASE` in your shell (or
> point it at `1inme_testing`) and re-run.

> **In Replit task environments this is automated.** The post-merge setup
> script (`scripts/post-merge.sh`) creates the `1inme_testing` database after
> every merge — idempotently, on the same connection your `.env` points at — so
> RefreshDatabase feature tests (e.g. `EmailOnlyLoginPolicyTest`) run without
> any manual step. The steps below are the manual fallback for fresh local
> checkouts outside that flow.

1. Make sure your `.env` points at a reachable local PostgreSQL instance —
   `DB_CONNECTION=pgsql`, plus working `DB_HOST` / `DB_PORT` / `DB_USERNAME` /
   `DB_PASSWORD` values. (`phpunit.xml` only defaults `DB_CONNECTION` and
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

### Zero-setup local runner: `composer test:local` (ephemeral throwaway Postgres)

If you don't already have a reachable `1inme_testing` database — or you're on a
Replit task environment whose process env points `DB_*` at the shared
cross-region RDS — the manual setup above is fragile: a stray exported
`DB_DATABASE` can aim `RefreshDatabase` at the wrong database, and the RDS is
slow enough that a full `migrate:fresh` takes tens of minutes.

[`scripts/test-local.sh`](scripts/test-local.sh) (aliased as
`composer test:local`) removes all of that. It:

1. `initdb`s a **private, throwaway** PostgreSQL 16 cluster under `/tmp` (the
   same `postgresql` nix binaries CI uses),
2. starts it on a private port, creates `1inme_testing`,
3. exports the `DB_*` overrides itself (host/port/user + blanked `DB_URL` /
   `DATABASE_URL` + `DB_SSLMODE=disable`) so nothing points at RDS,
4. runs the suite (sharded by default), and
5. **tears the whole cluster down on exit** (via a `trap`), even on failure.

```bash
cd artifacts/1inme
composer test:local                                  # full suite, sharded (default 4)
bash scripts/test-local.sh --filter=PaidPageTest     # forward args to the runner
TEST_LOCAL_MODE=artisan bash scripts/test-local.sh --filter=SomeTest   # single class
TEST_LOCAL_SHARDS=6 bash scripts/test-local.sh       # override shard count
TEST_LOCAL_KEEP=1 bash scripts/test-local.sh --filter=X   # leave the cluster up to poke at
```

**Why this is drift-free.** The database is built by replaying *every* migration
from an empty schema (`RefreshDatabase`'s `migrate:fresh`), so it matches the
migration files exactly — there is no chance of the
`link_clicks.clicked_at` / `created_at`-style column drift you get from a
long-lived, partially-migrated dedicated test DB. Nothing it does can touch the
shared dev/live RDS, so there is no wipe risk either.

**Cost.** The first shard pays the one-time `migrate:fresh` (~14s locally); each
test after that is ~0.2s. A single class finishes in ~20–35s including that
migration; the full suite is several minutes.

**Gotchas** (already handled by the script, noted here so you don't reintroduce
them if you hack on it):

- A backgrounded `pg_ctl start` is reaped between separate shell invocations, so
  the cluster is started **and** used **and** stopped inside one process — never
  split start and run across turns.
- `initdb`'s default unix-socket dir (`/run/postgresql`) doesn't exist in the
  container; the script starts Postgres with `-k /tmp`.
- Batching many test classes into a single ephemeral run can OOM-kill Postgres
  mid-run (surfacing as a flood of identical `Connection refused` cascades); the
  sharded runner bounds per-shard memory, and for one-off spot checks use
  `TEST_LOCAL_MODE=artisan` with a single `--filter` class.

### Running AI-dependent tests without an OpenAI key

**No OpenAI (or ElevenLabs/Whisper) API key is required to run the suite.** The
AI feature tests (e.g. `MarketingStrategistTest`, `ResumeImportTest`,
`MindCitationLinkTest`) do **not** call a live model. They:

- self-enable the AI engine for the test via
  `AiEngineSettings::setEnabled(true)` (the engine defaults **off** so a stray
  `php artisan test` never bills a real provider), and
- Mockery-mock `App\Services\AI\OpenAiService` (`->shouldReceive('chat')…`) so
  the "model" response is a fixture, and the metering/refund/citation logic
  under test runs against that fixture deterministically.

So you should **not** set `OPENAI_API_KEY` for tests, and you should not "skip"
these tests — they pass offline as-is. If a future AI test is added that hits a
real endpoint instead of mocking the service, that is the bug to fix (mock the
service); do not paper over it with a live key or an environment skip.

### PHP version: CI runs 8.3, deprecations are checked on 8.4

The main test job in [`.github/workflows/laravel-tests.yml`](../../.github/workflows/laravel-tests.yml)
runs on **PHP 8.3** against `postgres:16`. A *separate* job pins **PHP 8.4** and
runs `composer lint:deprecations` (a compile-time deprecation scan — see
[`scripts/check-php-deprecations.php`](scripts/check-php-deprecations.php)),
because 8.4 introduced a wave of new deprecation notices the 8.3 job wouldn't
see.

If your local PHP is **8.4** (as on Replit) and the 8.3 suite is what you want to
mirror, be aware of these deltas — none of which change the suite's pass/fail
outcome versus 8.3, but they're worth knowing when triaging:

- **Runtime behavior is the same for the suite.** Runtime-error semantics that
  the tests exercise are unchanged between 8.3 and 8.4. For example, reading a
  property on `null` (`$x->id` where `$x === null`) is an `E_WARNING` on **both**
  8.3 and 8.4 — Laravel's error handler converts that warning to an
  `ErrorException` in the `testing` environment identically on both versions. So
  a test that trips such a warning fails the same way on 8.3 and 8.4; that is a
  genuine code/test bug, **not** a version delta.
- **Compile-time deprecations are the real 8.4 surface.** New 8.4 deprecations
  (e.g. implicitly-nullable parameters like `function f(Foo $x = null)`) are what
  the dedicated 8.4 CI job catches via `composer lint:deprecations`. Run that
  locally on 8.4 before pushing PHP changes:

  ```bash
  cd artifacts/1inme
  composer lint:deprecations
  ```

- If you need to exactly reproduce the 8.3 job, install PHP 8.3 and point the
  suite at it; otherwise 8.4 is fine for day-to-day runs, with the deprecation
  linter as the version-specific guard.
