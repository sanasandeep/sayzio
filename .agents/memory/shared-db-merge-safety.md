---
name: Shared-DB merge safety (no wipe on merge)
description: Operational rules that keep merges/deploys from wiping the shared live RDS that every env points at.
---

# Shared-DB merge safety

The Laravel `1inme` app, the api-server (drizzle), every isolated task env, AND the
deployed app ALL point `DB_*` at the same distant AWS RDS `postgres` database. So
any schema-resetting command run from anywhere wipes production for everyone.

**Rule: merges and deploys are ADDITIVE-ONLY. Never reintroduce a wipe path into
`scripts/post-merge.sh` or the deploy run.**

**Why:** post-merge once did `drizzle-kit push --force` against the shared DB and,
on a perceived wipe, ran `migrate:fresh --force --seed` (drops every table). That
is what made tables vanish after merges. Both legs are gone.

**Durable constraints to preserve when touching DB tooling / post-merge / deploy:**
- Drizzle owns only its own `drizzle` Postgres schema (`pgSchema` + drizzle.config
  `schemaFilter`); it must never be allowed to manage/drop `public` (Laravel's).
- Use NON-force drizzle `push` in automation (a data-loss diff must abort, not
  force). `push --force` is gated away from the RDS host by default; override only
  deliberately with `ALLOW_DESTRUCTIVE_DB_COMMANDS=1`.
- Reconcile-orphans logic may only treat **duplicate / "already exists"** errors
  as already-applied. Missing-object errors (undefined table/column/object) signal
  a real ordering/dependency failure and MUST abort loudly — recording them as
  applied bakes in permanent ledger/schema drift.
- Destructive Laravel schema commands (migrate:fresh/refresh/reset/rollback,
  db:wipe, schema:dump) are blocked against the RDS host by a CommandStarting
  guard, same `ALLOW_DESTRUCTIVE_DB_COMMANDS=1` escape hatch.

**How to apply:** to reset a schema in dev/test, point `DB_*` at the local
Postgres — not the RDS — or set the escape-hatch env var on purpose.
