// Refuse to run a destructive drizzle-kit operation (e.g. `push --force`)
// against the shared live AWS RDS Postgres. `push --force` can DROP columns,
// tables, and whole schemas to make the DB match the code, so running it against
// the database every environment shares would wipe production data.
//
// Mirrors the host resolution in src/connection.ts: DB_* components take
// precedence over DATABASE_URL. The guard is bypassable for a deliberate,
// one-off operation via ALLOW_DESTRUCTIVE_DB_COMMANDS=1.

function resolveHost() {
  const { DATABASE_URL, DB_HOST, DB_USERNAME, DB_PASSWORD } = process.env;

  if (DB_HOST && DB_USERNAME && DB_PASSWORD) {
    return DB_HOST;
  }

  if (DATABASE_URL) {
    try {
      return new URL(DATABASE_URL).hostname;
    } catch {
      return DATABASE_URL;
    }
  }

  return "";
}

const host = resolveHost();
const isSharedLiveDb = /\.rds\.amazonaws\.com/.test(host);
const overridden = process.env.ALLOW_DESTRUCTIVE_DB_COMMANDS === "1";

if (isSharedLiveDb && !overridden) {
  console.error(
    "\n::1inme:: BLOCKED destructive drizzle command — refusing to run " +
      "`drizzle-kit push --force` against the shared live database " +
      `(${host}).\n` +
      "  `push --force` DROPS columns/tables/schemas to match the code and " +
      "would wipe production data that every environment shares.\n" +
      "  Use the non-force `pnpm --filter db run push` (it aborts on " +
      "data-loss diffs), point DB_* at a local Postgres, or set " +
      "ALLOW_DESTRUCTIVE_DB_COMMANDS=1 to override deliberately.\n",
  );
  process.exit(1);
}
