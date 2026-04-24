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
