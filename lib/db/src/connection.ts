export type ResolvedConnection = {
  /**
   * Discrete connection components. Preferred over `connectionString` because
   * some tools (notably drizzle-kit) ignore an explicit `ssl` option when a
   * connection URL is supplied, which breaks TLS-required hosts like AWS RDS.
   */
  host?: string;
  port?: number;
  database?: string;
  user?: string;
  password?: string;
  /**
   * Set only for the `DATABASE_URL` fallback (local managed DB), where we don't
   * have the credentials broken out into components.
   */
  connectionString?: string;
  ssl: { rejectUnauthorized: boolean } | undefined;
};

/**
 * Resolve the Postgres connection string and SSL options from the environment.
 *
 * External hosts such as AWS RDS are wired through the standard `DB_*`
 * components (shared with the Laravel `1inme` app via the Replit secrets
 * manager). `DATABASE_URL` is reserved and auto-populated by Replit for the
 * built-in database, so it cannot be repointed at an external host — when the
 * `DB_*` components are present they take precedence, otherwise we fall back to
 * `DATABASE_URL`.
 *
 * `sslmode=require` means "encrypt, but don't verify the server certificate
 * chain", which maps to `ssl: { rejectUnauthorized: false }` for node-postgres.
 *
 * Newer `pg` / `pg-connection-string` reinterpret a `sslmode=require` query
 * param as `verify-full` (full chain verification), which rejects AWS RDS's
 * self-signed CA chain even when we also pass an explicit `ssl` object. To
 * avoid that conflict we strip `sslmode` from the connection string whenever
 * we return an explicit `ssl` object — `{ rejectUnauthorized: false }` then
 * remains the single source of truth for TLS behaviour.
 */
export function resolveConnection(): ResolvedConnection {
  const {
    DATABASE_URL,
    DB_HOST,
    DB_PORT,
    DB_DATABASE,
    DB_USERNAME,
    DB_PASSWORD,
    DB_SSLMODE,
  } = process.env;

  let sslMode: string | undefined;

  // Preferred path: discrete DB_* components (shared with the Laravel app).
  if (DB_HOST && DB_USERNAME && DB_PASSWORD) {
    sslMode = DB_SSLMODE || "prefer";
    const requiresSsl =
      /^(require|verify-ca|verify-full)$/.test(sslMode) ||
      /\.rds\.amazonaws\.com/.test(DB_HOST);
    return {
      host: DB_HOST,
      port: DB_PORT ? Number(DB_PORT) : 5432,
      database: DB_DATABASE || "postgres",
      user: DB_USERNAME,
      password: DB_PASSWORD,
      ssl: requiresSsl ? { rejectUnauthorized: false } : undefined,
    };
  }

  // Fallback: a full connection URL (Replit's reserved built-in DATABASE_URL).
  if (DATABASE_URL) {
    const sslModeMatch = /[?&]sslmode=([^&]+)/.exec(DATABASE_URL);
    sslMode = sslModeMatch?.[1];
    const requiresSsl =
      (sslMode !== undefined &&
        /^(require|verify-ca|verify-full)$/.test(sslMode)) ||
      /\.rds\.amazonaws\.com/.test(DATABASE_URL);

    let connectionString = DATABASE_URL;
    if (requiresSsl) {
      // Strip sslmode so newer pg can't reinterpret it as verify-full; the
      // explicit ssl object governs TLS instead.
      connectionString = connectionString
        .replace(/([?&])sslmode=[^&]*&?/, "$1")
        .replace(/[?&]$/, "");
    }

    return {
      connectionString,
      ssl: requiresSsl ? { rejectUnauthorized: false } : undefined,
    };
  }

  throw new Error(
    "No database connection configured. Set DB_HOST/DB_USERNAME/DB_PASSWORD " +
      "for an external Postgres (e.g. AWS RDS), or provide DATABASE_URL.",
  );
}
