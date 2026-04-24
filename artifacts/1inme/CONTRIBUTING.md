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
