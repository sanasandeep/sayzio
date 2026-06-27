---
name: CREATE-race reconcile (23505 on pg_ catalog)
description: Why hasTable guards can't stop concurrent CREATE races, and how db:reconcile-migrations must classify them.
---

# Concurrent CREATE races vs migration idempotency

**Rule:** A racing/orphaned `CREATE TABLE` (or type/index/constraint/sequence) on
the shared RDS does NOT surface as `42P07 duplicate_table`. It surfaces as
`23505 unique_violation` on a **pg_catalog** uniqueness index — most often
`pg_type_typname_nsp_index` (a table's implicit rowtype already exists), also
`pg_class_relname_nsp_index` (relation name), `pg_constraint_conname_nsp_index`.
`db:reconcile-migrations` must treat a 23505 whose constraint name is
**`pg_`-prefixed** as an idempotency (reconcilable) case, while still aborting on
genuine data unique-violations (user constraints are never `pg_`-prefixed).

**Why:** `IDEMPOTENT_SQLSTATES` deliberately excludes 23505 (it's usually a data
dup). But the system-catalog flavour of 23505 means "this schema object already
exists" — exactly the orphan/concurrent-create situation reconcile exists to heal.
Excluding it made reconcile abort on every raced CREATE, so the ~190-migration
backlog never converged and the homepage flapped 200↔500.

**Per-migration `hasTable` guards CANNOT fix CREATE races.** `if (!hasTable) create`
has a check-then-create gap: two parallel post-merges both read false, both
`CREATE`, one loses on the catalog unique index. Guards reduce, never eliminate.
The durable fix lives in the heal command, not in N migrations.

**How to apply:** When the shared dev/demo RDS schema is broken by parallel
task-agent post-merges, don't edit migrations one-by-one. The systemic fix is the
`pg_`-prefixed 23505 branch in `ReconcilePendingMigrations::isIdempotencyError`,
plus making genuine DATA seeders idempotent (`insertOrIgnore`/`updateOrInsert`,
e.g. site_pages footer seeder). post-merge runs `migrate --force ||
db:reconcile-migrations --force` UNCAPPED (~600s), so once reconcile is hardened
every merge auto-drains the backlog. Manual `timeout 115` passes are capped and
only clear a few slow DDL applies each; prefer letting the uncapped auto-heal
converge. `/up/schema` returns 503 until pending hits 0, then auto-clears.
