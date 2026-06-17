---
name: Drizzle schema barrel circular import
description: Why drizzle table files must import drizzleSchema from its own module, not the schema barrel
---

In `lib/db/src/schema`, the `drizzle` pgSchema object must live in its own module
(`drizzle-schema.ts`), not in the barrel `index.ts`. Table files import it from
there; the barrel re-exports both `drizzle-schema` and the table files.

**Why:** If a table file imports `drizzleSchema` from `./index` and the barrel
also `export *`s that table file, drizzle-kit's CJS loader evaluates the re-export
getter before the `const drizzleSchema` is initialized → runtime
`ReferenceError: Cannot access 'drizzleSchema' before initialization` during
`drizzle-kit push`. TypeScript typecheck does NOT catch this; only `push` fails.

**How to apply:** When adding any new Drizzle table, put it in its own file under
`schema/`, import `drizzleSchema` from `./drizzle-schema`, and add `export *` to
`schema/index.ts`. Never reintroduce the schema declaration into the barrel.
