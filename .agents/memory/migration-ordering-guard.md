---
name: Migration ordering guard
description: How broken migration ordering (FK/modify to a later-created table) is caught, and why there are two complementary checks.
---

# Migration ordering guard

Two complementary checks catch a migration that modifies or foreign-keys a table
only created by a LATER-dated migration (the bug builds fine on the shared RDS
where migrations were applied incrementally, but breaks `migrate:fresh` from an
empty DB — the #2989 class):

1. **Real-DB (gold standard):** `.github/workflows/laravel-migrations.yml` runs
   `migrate:fresh` against a throwaway Postgres. Catches everything but needs a
   wipeable database, so it can ONLY live in GitHub Actions. **Its trigger paths
   MUST be app-wide (`artifacts/1inme/**`), not just `database/migrations/**`:**
   `migrate:fresh` executes data migrations that call seeders and app code (e.g.
   seven_plan_lineup → PlansAndAddonsSeeder::seedPlans()), so a from-empty
   regression can be introduced in `database/seeders/**` or `app/**` without
   touching a migration file. A narrow migrations-only filter silently skips
   exactly those PRs — that is how the is_internal seeder bug slipped the gate.
   NOTE: this only makes the job *run*; GitHub branch protection (required status
   check) is what actually *blocks* merge and lives in repo settings, not YAML.
2. **DB-free static replay:** `MigrationOrderInspector` (Common\Support) +
   `db:check-migration-ordering` command + `MigrationOrderingTest`. Registered as
   the Replit validation step `migration-ordering` and as a no-DB GitHub job.

**Why the static one exists:** the Replit pre-merge validation env's only
reachable database is the shared RDS, which must never be `migrate:fresh`-ed
(destructive-DB guard blocks it). So the static replay is the only ordering
signal that can gate a merge here. Post-merge runs `migrate` (not fresh) against
the already-populated RDS, so it never catches ordering on its own.

**How the static replay works (mirrors SchemaManifest):** replays every
migration's `up()` in filename order under `Connection::pretend()` with the
`Schema` facade swapped for an in-memory recorder; `build()` is never called so no
DDL/PDO. Tracks created tables; flags a forward `foreign_key` or
`modify_before_create` reference.

**Key non-obvious mechanism:** `->constrained()` and
`foreign(...)->references(...)->on(...)` both register a `foreign` command on the
Blueprint with the target table in `$command->on` (constrained derives it from
the column name; the value can be a `Stringable`, cast it). This is readable via
`Blueprint::getCommands()` WITHOUT calling `build()` because the blueprint
callback runs in the constructor.

**Third class — `write_column_before_create` (seeder writes a not-yet-created
column):** a migration that runs a seeder/backfill writing a column only added by
a LATER migration (the `is_internal`-in-PlansAndAddonsSeeder bug that broke
`migrate:fresh`). Schema-only checks miss it because the write lives in data
(PHP), not a Blueprint. Caught by capturing the pretend query-log delta per
migration in `inspect()` and parsing INSERT/UPDATE column lists against the
recorded schema (`inspectWriteQuery`). **Non-obvious:** the pretend query log IS
populated during `Connection::pretend()` (writes are inert but still `logQuery`'d,
incl. Eloquent `insertGetId`'s `... returning "id"`); Blueprint DDL never hits the
log (`build()` isn't called), so this only ever sees data writes + raw
`DB::statement` SQL. Only writes to a KNOWN table are flagged (under-detect, never
false-positive). The seeder-side fix: `Schema::hasColumn` guard that SKIPS the
internal-plan def until the column exists (don't just strip the key — creating the
row early makes the dedicated seed_unlimited_plan overlay never set is_internal).

**Blind spot (under-detects only, never false-positives):** tables created or FKs
declared via raw `DB::statement('CREATE/ALTER TABLE ...')` are invisible to the
Blueprint recorder. There are currently no raw CREATE TABLE statements in the
codebase, so this can't cause a missed forward reference today. (`inspectWriteQuery`
does parse raw `ALTER TABLE ... ADD COLUMN` from the query log so such a column
isn't then falsely flagged by a later same-migration write.)

**Verifying locally:** phpunit can't run in the isolated dev env (shared RDS +
destructive guard), so detection was verified via
`scripts/verify-migration-ordering.php` (standalone boot, exercises the recorder
directly, no DB, not in CI). Constructing a `Blueprint` directly needs the
connection's schema grammar initialised first (`$conn->getSchemaBuilder()`);
`inspect()` gets this for free by resolving `app('db.schema')`.
