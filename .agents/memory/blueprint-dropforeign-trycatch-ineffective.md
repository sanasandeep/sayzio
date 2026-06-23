---
name: Blueprint dropForeign try/catch is ineffective
description: Why wrapping $table->dropForeign() in try/catch inside Schema::table() does NOT swallow the error, and how to make a Postgres FK/column migration idempotent.
---

A migration that wraps a Blueprint mutation in try/catch to "tolerate" drift does
NOT work:

```php
Schema::table('t', function (Blueprint $table) {
    try { $table->dropForeign(['x_id']); } catch (\Throwable $e) { /* ignore */ }
    $table->string('y')->nullable();
});
```

**Why:** `Blueprint` only *records* commands during the closure; Laravel compiles
and EXECUTES the SQL after the closure returns. So the `DROP CONSTRAINT` runs
outside the try block and the exception escapes — `migrate` aborts on
`42704 constraint ... does not exist` (or `42701` duplicate column), poisoning the
shared-DB deploy/merge.

**How to apply:** make such migrations idempotent with native guards, not
Blueprint try/catch:
- Drop a FK/constraint: `DB::statement('ALTER TABLE t DROP CONSTRAINT IF EXISTS t_x_id_foreign')` (native Postgres, no-op if absent).
- Add a column: `if (! Schema::hasColumn('t','y')) { Schema::table(... add ...); }`.

This matters on the 1inme shared RDS because interrupted runs leave drifted tables
(FK already gone / column already added), and `db:reconcile-migrations` only
auto-heals *duplicate* ("already exists") states — missing-object errors abort
loudly by design, so the migration itself must be written to tolerate them.
