---
name: Telling a fresh schema from wiped data on the shared RDS
description: How to decide whether the shared RDS showing 0 rows is real data loss or just a freshly (re)created empty schema, and the standing risk that pre-guard task agents reset it.
---

When the shared RDS `postgres` DB suddenly shows `users/plans/links = 0 rows` and a
shrunken `migrations` ledger, DO NOT assume catastrophic data loss. Distinguish
the two cases with the **identity sequence**, read-only:

```sql
select last_value, is_called from users_id_seq;
```
- `is_called=false` (last_value=1) ⇒ **no row was ever inserted** ⇒ the table was
  freshly created (DROP+CREATE resets the sequence). This is a fresh/empty schema,
  NOT wiped data.
- `is_called=true` / last_value > 1 ⇒ rows existed and were removed ⇒ real
  truncate/delete — escalate.

Always print the effective connection (`config("database.connections.$c.host")`
+ `database`) in the SAME query as any count — real `DB_*` env vars override
`.env` (which carries dead `helium`/`heliumdb` values), so a stray query can
silently hit the wrong DB.

**Standing risk (why this happens):** every isolated task-agent env and the deploy
all point `DB_*` at the same RDS `postgres`. `AppServiceProvider::guardDestructiveSchemaCommands()`
blocks `migrate:fresh/refresh/reset/rollback`, `db:wipe`, `schema:dump` against
`*.rds.amazonaws.com` — but a task agent **forked before that guard existed** (or
one with `ALLOW_DESTRUCTIVE_DB_COMMANDS=1`) can still run a fresh-class command and
reset the shared schema mid-session. You cannot retroactively fix already-forked
agents; the guard on main protects future ones. Symptoms: ledger count swings
wildly (e.g. 229→14→38), table count drops to a few dozen, sequences read fresh.
The schema self-rebuilds as concurrent post-merge runs re-apply migrations.
