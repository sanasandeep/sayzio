/**
 * Schema-level tests that don't require better-sqlite3 native bindings.
 * Verifies the sayzio_links cache table exists in both the fresh-install
 * schema and the incremental migration path.
 */
import { describe, it, expect } from 'vitest';
import { SCHEMA_VERSION, CREATE_TABLES_SQL, MIGRATION_SQL } from '../src/shared/db-schema';

describe('db-schema: sayzio_links cache', () => {
  it('schema version is current', () => {
    expect(SCHEMA_VERSION).toBe(8);
  });

  it('fresh installs create the sayzio_links table', () => {
    expect(CREATE_TABLES_SQL).toContain('CREATE TABLE IF NOT EXISTS sayzio_links');
  });

  it('existing installs migrate to add the sayzio_links table', () => {
    expect(MIGRATION_SQL[7]).toBeDefined();
    expect(MIGRATION_SQL[7]).toContain('CREATE TABLE IF NOT EXISTS sayzio_links');
  });

  it('every version up to SCHEMA_VERSION beyond 1 has a migration or is a no-op gap', () => {
    // Migrations are keyed by target version; the highest key must not exceed SCHEMA_VERSION.
    const keys = Object.keys(MIGRATION_SQL).map(Number);
    expect(Math.max(...keys)).toBeLessThanOrEqual(SCHEMA_VERSION);
  });
});
