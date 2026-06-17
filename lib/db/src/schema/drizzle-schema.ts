import { pgSchema } from "drizzle-orm/pg-core";

// The Laravel `1inme` app owns the `public` schema (managed via `php artisan
// migrate`). Drizzle owns a dedicated `drizzle` schema so its diffs never touch
// Laravel's tables (see `schemaFilter` in drizzle.config.ts). Declaring the
// schema here means drizzle-kit `push` creates and preserves it — without a
// declaration, push treats the schema as undefined and drops it. Every Drizzle
// table added later must be defined on `drizzleSchema` (e.g.
// `drizzleSchema.table("posts", { ... })`) to land in this namespace.
//
// This lives in its own module (not the barrel `index.ts`) so table files can
// import it without a circular dependency — the barrel re-exports both this and
// the table files, and a cycle through the barrel fails under drizzle-kit's CJS
// loader ("Cannot access 'drizzleSchema' before initialization").
export const drizzleSchema = pgSchema("drizzle");
