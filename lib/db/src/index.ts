import { drizzle } from "drizzle-orm/node-postgres";
import pg from "pg";
import * as schema from "./schema";
import { resolveConnection } from "./connection";

const { Pool } = pg;

const { connectionString, host, port, database, user, password, ssl } =
  resolveConnection();

export const pool = new Pool({
  ...(connectionString
    ? { connectionString }
    : { host, port, database, user, password }),
  ...(ssl ? { ssl } : {}),
});
export const db = drizzle(pool, { schema });

export * from "./schema";
