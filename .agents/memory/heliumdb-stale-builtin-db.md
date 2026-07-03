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

**Prod also uses RDS, not the built-in.** `DB_*` (incl. `DB_HOST/DB_DATABASE/
DB_USERNAME/DB_PASSWORD/DB_CONNECTION`) are stored as **Secrets, which are
GLOBAL** (not env-scoped) → present in the deployment too. Laravel's pgsql config
(`config/database.php`) reads discrete `DB_*` and `DB_URL` (which is NOT set) —
it does **not** read `DATABASE_URL`. So even though `DATABASE_URL`/`PGHOST`
(built-in) also exist as secrets, Laravel ignores them and connects to RDS in
both dev and prod. `.env` is gitignored but irrelevant (secrets win + point to
RDS anyway).

**Consequences that look like bugs but aren't:**

- The sandbox `executeSql` / `checkDatabase` callbacks connect to **heliumdb**,
  NOT the app DB. To inspect real data you must query RDS with the `DB_*` creds
  (e.g. a `pg.Client` built from `DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/
  DB_PASSWORD`, `ssl:{rejectUnauthorized:false}`) or via Laravel
  (`php artisan tinker` from bash inherits the `DB_*` env → hits RDS).
- The publish flow's **"Development database changes detected → rename
  `users.role` to `is_demo`?"** popup diffs the **built-in dev DB vs the built-in
  prod DB** (both stale copies) and applies the result to the built-in prod DB.
  It never touches RDS. **Safe resolution: choose "No, create new column" →
  Submit** (adds empty `is_demo`, drops `role` — same direction as RDS, which
  already has is_demo and no role; converges the two built-in copies so the popup
  stops recurring). Do NOT pick "Yes, rename" (would shove role's string into the
  is_demo flag). Truly permanent fix = remove the built-in DB (Database tool →
  Settings → Remove database; also detach it from the deployment's Database →
  Manage). Removal/submit are UI-only actions the agent can't perform.

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
