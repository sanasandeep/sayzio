// Export your models here. Add one export per file
// export * from "./posts";
//
// Each model/table should ideally be split into different files.
// Each model/table should define a Drizzle table, insert schema, and types:
//
//   import { pgTable, text, serial } from "drizzle-orm/pg-core";
//   import { createInsertSchema } from "drizzle-zod";
//   import { z } from "zod/v4";
//
//   export const postsTable = pgTable("posts", {
//     id: serial("id").primaryKey(),
//     title: text("title").notNull(),
//   });
//
//   export const insertPostSchema = createInsertSchema(postsTable).omit({ id: true });
//   export type InsertPost = z.infer<typeof insertPostSchema>;
//   export type Post = typeof postsTable.$inferSelect;

import { pgSchema } from "drizzle-orm/pg-core";

// The Laravel `1inme` app owns the `public` schema (managed via `php artisan
// migrate`). Drizzle owns a dedicated `drizzle` schema so its diffs never touch
// Laravel's tables (see `schemaFilter` in drizzle.config.ts). Declaring the
// schema here means drizzle-kit `push` creates and preserves it — without a
// declaration, push treats the schema as undefined and drops it. Every Drizzle
// table added later must be defined on `drizzleSchema` (e.g.
// `drizzleSchema.table("posts", { ... })`) to land in this namespace.
export const drizzleSchema = pgSchema("drizzle");