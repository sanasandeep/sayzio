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
   wipeable database, so it can ONLY live in GitHub Actions.
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

**Blind spot (under-detects only, never false-positives):** tables created or FKs
declared via raw `DB::statement('CREATE/ALTER TABLE ...')` are invisible to the
Blueprint recorder. There are currently no raw CREATE TABLE statements in the
codebase, so this can't cause a missed forward reference today.

**Verifying locally:** phpunit can't run in the isolated dev env (shared RDS +
destructive guard), so detection was verified via
`scripts/verify-migration-ordering.php` (standalone boot, exercises the recorder
directly, no DB, not in CI). Constructing a `Blueprint` directly needs the
connection's schema grammar initialised first (`$conn->getSchemaBuilder()`);
`inspect()` gets this for free by resolving `app('db.schema')`.
