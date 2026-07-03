---
name: Stale Replit built-in DB (heliumdb) vs the real RDS
description: Why the publish "rename role→is_demo" popup is noise and how to inspect the DB the app actually uses.
---

# The app uses AWS RDS, not the Replit built-in database (heliumdb)

This repl has BOTH a Replit built-in Postgres (`DATABASE_URL`/`PGHOST`, database
name **`heliumdb`**) AND the real AWS RDS (`DB_*` secrets). The connection
resolver (`lib/db/src/connection.ts`) and Laravel both **prefer `DB_*` (RDS)**
over `DATABASE_URL`, and `DB_*` are always set in dev and prod. So `heliumdb` is
a **stale, unused leftover** (~240 tables, an old partial copy). Nothing in the
running app reads it.

**Consequences that look like bugs but aren't:**

- The sandbox `executeSql` / `checkDatabase` callbacks connect to **heliumdb**,
  NOT the app DB. To inspect real data you must query RDS with the `DB_*` creds
  (e.g. a `pg.Client` built from `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/
  DB_PASSWORD`, `ssl:{rejectUnauthorized:false}`) or via Laravel
  (`php artisan tinker` from bash inherits the `DB_*` env → hits RDS).
- The publish flow's **"Development database changes detected → rename
  `users.role` to `is_demo`?"** popup is a heliumdb-only diff. It is a naive
  false-positive rename and must NOT be submitted — `role` and `is_demo` are
  unrelated. It never touches RDS.

**`users.role` is intentionally absent on RDS** — the
`create_user_roles_pivot_seed_user_admin` migration moves roles to a `user_roles`
pivot + roles/permissions tables and drops the `users.role` column (hasColumn
guarded). Missing `role` is CORRECT, not drift.

**Authoritative RDS drift check:** `php artisan db:check-expected-columns`
(replays expected schema, one bulk information_schema diff). It reported "All 299
expected tables have their columns — no drift." Trust this over per-column
guessing or the heliumdb popup.

**Clean fix for publishing:** detach/remove the unused built-in `heliumdb`
database from the deployment (Database pane → Manage) so the deploy stops trying
to migrate a DB the app doesn't use. Safe because the app runs on RDS via `DB_*`.
It is destructive to the stale heliumdb copy, so confirm with the user first.
