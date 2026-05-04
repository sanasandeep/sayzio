import { defineConfig } from "drizzle-kit";
import path from "path";

if (!process.env.DATABASE_URL) {
  throw new Error("DATABASE_URL, ensure the database is provisioned");
}

export default defineConfig({
  schema: path.join(__dirname, "./src/schema/index.ts"),
  dialect: "postgresql",
  dbCredentials: {
    url: process.env.DATABASE_URL,
  },
  // The Laravel `1inme` artifact shares this Postgres database and owns
  // every real table in the `public` schema (managed via `php artisan
  // migrate`). Without this filter, drizzle-kit introspects every
  // Laravel table + sequence, sees that the drizzle schema is empty,
  // and generates DROP statements for all of them — which is what
  // makes Replit's deployment validator flag the generated migrations
  // as conflicting with production data.
  //
  // Restricting the filter to a dedicated `drizzle` Postgres schema
  // means drizzle-kit only ever sees objects it actually owns, so an
  // empty schema produces an empty diff. Real drizzle tables, when
  // added later, must declare `pgSchema('drizzle')` for this to keep
  // working.
  schemaFilter: ["drizzle"],
});
