---
name: Schema manifest by migration replay
description: How 1inme derives the expected DB schema automatically instead of a hand-maintained list, and the safety constraints that make it non-destructive.
---

# Auto-derived expected schema (migration replay)

`ExpectedSchemaHealth` no longer keeps a hand-curated table=>columns list. The
expected schema is derived by `SchemaManifest` replaying every migration's
`up()` and folding create/table/drop/rename/dropColumn/renameColumn into a net
table=>columns map, then diffed against the live DB to catch edited-after-applied
drift on ANY column.

**Why it is safe to run against prod / shared RDS:**
- The `Schema` facade is swapped for an in-memory recorder; `build()` is never
  called, so no DDL is generated.
- The whole replay runs inside `Connection::pretend()`, so any backfill writes a
  migration does are inert and selects return `[]`.
- `down()` is never run — rollback `dropColumn`s must not pollute the expected map.

**Gotchas learned:**
- Backfill migrations that `insertGetId` under pretend make PostgresProcessor emit
  cosmetic "undefined array key 0" warnings (it does `select(...)[0]` on `[]`).
  Scope a `set_error_handler` around the pretend call to swallow E_WARNING/NOTICE,
  restore in finally. These warnings are proof pretend is working, not a bug.
- Diff against live DB with ONE bulk `information_schema.columns` query
  (`table_schema = any(current_schemas(false))`), NOT per-table `getColumnListing`
  — cross-region RDS makes ~250 per-table probes time out; bulk is ~1.5s.
- Cache the manifest keyed by a fingerprint of migration filenames+mtimes so it
  rebuilds automatically on any migration add/edit; no manual cache busting.
- Known blind spot: columns added/dropped via raw `DB::statement` ALTER are
  invisible to the Blueprint recorder. Raw adds = under-detection only (safe);
  raw column drops would be a false positive, but none exist in this codebase.
- `IGNORED_TABLES` (cache/jobs/sessions/migrations/etc.) suppress framework-table
  false positives; `IGNORED_COLUMNS` is the per-table escape hatch.
