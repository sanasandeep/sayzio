---
name: drizzle-kit + SSL-forced RDS quirks
description: Three gotchas connecting drizzle-kit (and node-postgres bulk loads) to an external SSL-forced Postgres like AWS RDS.
---

# drizzle-kit + external SSL-forced Postgres (e.g. AWS RDS)

## 1. drizzle-kit ignores the `ssl` object when given a connection URL
If `dbCredentials` provides a `url`/`connectionString`, drizzle-kit drops the
sibling `ssl` option and connects without TLS — RDS with `rds.force_ssl=1`
then rejects with `28000`. **Fix:** pass *discrete* fields (host/port/database/
user/password) plus `ssl` instead of a URL. Also, `sslmode=require` baked into a
URL makes node-postgres behave like `verify-full` (self-signed cert error), so
strip `sslmode` from any URL and use an explicit `ssl: { rejectUnauthorized:
false }`. `lib/db/src/connection.ts` returns discrete fields for the DB_* path
for exactly this reason.

## 2. `push` DROPS any schema not declared in code
With `schemaFilter: ["drizzle"]`, drizzle-kit `push` will emit `DROP SCHEMA
drizzle` if nothing in `schema/index.ts` declares it — so creating the schema
out-of-band (`CREATE SCHEMA drizzle`) does NOT survive the next push. **Fix:**
declare it in code: `export const drizzleSchema = pgSchema("drizzle");`. Then
push creates and preserves the (even empty) schema. Future Drizzle tables must
be defined on `drizzleSchema` to land in that namespace and stay isolated from
Laravel's `public` tables.
**Verify** with `pg_namespace`, not `information_schema.schemata` (the latter
hides schemas by privilege and gave a false "missing").

## 3. Bulk-loading a dump over high-latency RDS: use ONE simple-query round trip
Detached/long psql or multi-statement loads to a far region (e.g. ap-south-2)
stall for minutes. Generate plain SQL (`pg_dump --inserts --no-owner
--no-privileges`), strip psql `\`-meta lines, and execute the whole file as a
single `client.query(bigString)` via node-postgres — one round trip, completes
in seconds and is immune to per-statement latency.

## Cross-region latency is inherent, not a bug
From a Replit dev container to ap-south-2 RDS: ~251ms per query round trip and
~3s to establish a fresh SSL connection. A query-heavy Laravel page (no PHP
connection pooling — new PDO connection per request) can take 30–60s. The app
is functionally correct (HTTP 200); the latency disappears when deployed near
the RDS region. Don't chase it as a defect.
