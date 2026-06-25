---
name: Edited-after-applied migration drift
description: Why columns/tables can be MISSING on the shared RDS even though migrate:status shows the migration as Ran.
---

# Edited-after-applied migration drift

On the long-lived shared RDS `postgres` DB, a migration that was already recorded
as "Ran" and is **later edited** to add more columns/tables will NEVER apply those
new additions — `migrate` does not re-run a recorded migration. `migrate:status`
shows 0 pending, yet the DB is missing the newly-added schema, and code that
references it 500s (e.g. `/creators` referenced `users.adult_flag_suspended_at`
that the edited `create_creator_payment_connections` migration added after it had
already been applied).

**Why:** Laravel keys the `migrations` ledger by filename; editing the file's body
does not change its identity, so it is skipped on subsequent `migrate` runs. The
shared DB accreted over many merges, so different envs applied different historical
versions of the same file.

**How to apply:** Never fix schema drift by editing an already-applied migration.
Add a NEW additive migration guarded with `Schema::hasColumn` / `Schema::hasTable`
so it fills gaps on drifted DBs and is a no-op on fresh DBs (where the original
migration already created everything). This stays within the additive-only
shared-DB policy and the destructive-command guard.

**Table-granularity variant:** the drift isn't always columns. A single create
migration that creates several tables, each wrapped in its own
`if (!Schema::hasTable(...))`, can be expanded later to add MORE tables. On DBs
that already ran the earlier version, only the original tables exist and the
newly-added ones are silently absent — `ExpectedSchemaHealth` reports them as
whole-missing tables (not column drift), and the dashboard "Fix now" button
CANNOT repair whole-missing tables (it only re-creates columns; tables land under
`unrepairable`). Heal them the same way: a new migration that re-creates ONLY the
missing tables, guarded by `hasTable`, with column/index/FK definitions
byte-for-byte identical to the owning create migration. EXPECTED (a column-repair
map) needs no change for whole-table drift. Real instance: `inbox_threads`
existed but `inbox_messages` / `inbox_snippets` / `inbox_thread_conversions`
(all added later to `create_inbox_unified_tables`) were missing.

**Detection (no longer just smoke-testing):** `ExpectedSchemaHealth` (mirrors
`SchemaHealth`/`WorkspaceColumnHealth`/`TemplateDesignHealth`) probes a curated
manifest `EXPECTED = [table => [columns]]` of critical hot-path columns directly
against the live schema, independent of the `migrations` ledger. When you add a
column to an EXISTING table that public/controller code reads, add it to that
manifest. Surfaced the same three ways as the migration check: admin dashboard
banner, `/up/schema` probe (now returns `schema_drift` alongside
`pending_migrations`; either non-zero ⇒ 503), and the hourly
`db:check-expected-columns` ops alert (in-app + email, cooldown + newly-missing
bypass). Fix is still `migrate --force` (the guarded additive migrations), not a
repair in the command. There are now FOUR parallel health systems following this
exact pattern — keep them in lockstep when touching one.
