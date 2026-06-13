import { defineConfig } from "drizzle-kit";
import path from "path";
import { resolveConnection } from "./src/connection";

// Mirror the runtime pool: external hosts such as AWS RDS are wired through the
// shared DB_* components and require SSL (TLS without CA verification).
//
// We pass discrete credentials (not a URL) whenever they're available because
// drizzle-kit ignores an explicit `ssl` option when a connection URL is
// supplied, which makes it connect without TLS and get rejected by RDS's
// `force_ssl`.
const { connectionString, host, port, database, user, password, ssl } =
  resolveConnection();

export default defineConfig({
  schema: path.join(__dirname, "./src/schema/index.ts"),
  dialect: "postgresql",
  dbCredentials: connectionString
    ? { url: connectionString, ...(ssl ? { ssl } : {}) }
    : {
        host: host!,
        port: port!,
        database: database!,
        user: user!,
        password: password!,
        ...(ssl ? { ssl } : {}),
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
