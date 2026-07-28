/**
 * End-to-end persistence tests for pinned toolbar tools.
 *
 * The renderer tests cover the 2-pin cap and cross-surface sync, but the
 * actual restart-survival path is: renderer → window.zio.prefs (preload IPC)
 * → main-process setPreference() → SQLite `preferences` table. These tests
 * exercise the REAL preferences store (better-sqlite3, in-memory DB) to make
 * sure the `pinned_toolbar_tools` value round-trips through the DB and that
 * a corrupt stored value degrades to an empty pin list instead of crashing.
 */
import { describe, it, expect, beforeAll, vi } from 'vitest';
import {
  PINNED_TOOLS_PREF_KEY,
  parsePinnedTools,
  serializePinnedTools,
  type PinnableTool,
} from '../src/shared/toolbar-pins';
import { PREFERENCE_KEYS } from '../src/shared/db-schema';

// db.ts imports `app` from electron only to resolve the default DB path;
// we always pass ':memory:' so a minimal stub is enough.
vi.mock('electron', () => ({
  app: { getPath: () => '/tmp' },
}));

type DbModule = typeof import('../src/main/db');
let db: DbModule;
let rawDb: import('better-sqlite3').Database;

beforeAll(async () => {
  db = await import('../src/main/db');
  rawDb = db.initDb(':memory:');
});

describe('pinned toolbar tools persistence (main-process preferences store)', () => {
  it('the shared pref key is a registered preference key', () => {
    expect(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS).toBe(PINNED_TOOLS_PREF_KEY);
  });

  it('round-trips a pinned list through the real preferences store', () => {
    const pins: PinnableTool[] = ['dialer', 'screenshot'];
    db.setPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS, serializePinnedTools(pins));

    // Simulate a restart: read straight back from the store, as the
    // prefs:get IPC handler does on next launch.
    const stored = db.getPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS);
    expect(stored).not.toBeNull();
    expect(parsePinnedTools(stored)).toEqual(pins);
  });

  it('persists the value in the SQLite preferences table', () => {
    db.setPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS, serializePinnedTools(['reading_list']));
    const row = rawDb
      .prepare('SELECT value FROM preferences WHERE key = ?')
      .get(PINNED_TOOLS_PREF_KEY) as { value: string } | undefined;
    expect(row).toBeDefined();
    expect(JSON.parse(row!.value)).toEqual(['reading_list']);
  });

  it('an empty pin list round-trips as empty (not null-ish surprises)', () => {
    db.setPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS, serializePinnedTools([]));
    expect(parsePinnedTools(db.getPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS))).toEqual([]);
  });

  it('a missing preference yields an empty pin list', () => {
    rawDb.prepare('DELETE FROM preferences WHERE key = ?').run(PINNED_TOOLS_PREF_KEY);
    const stored = db.getPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS);
    expect(stored).toBeNull();
    expect(parsePinnedTools(stored)).toEqual([]);
  });

  it('corrupt JSON in the DB yields an empty pin list, not a crash', () => {
    rawDb
      .prepare('INSERT OR REPLACE INTO preferences(key, value) VALUES(?, ?)')
      .run(PINNED_TOOLS_PREF_KEY, '{not valid json[');
    const stored = db.getPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS);
    expect(stored).toBe('{not valid json[');
    expect(() => parsePinnedTools(stored)).not.toThrow();
    expect(parsePinnedTools(stored)).toEqual([]);
  });

  it('well-formed JSON with unknown/duplicate ids is sanitized on read', () => {
    rawDb
      .prepare('INSERT OR REPLACE INTO preferences(key, value) VALUES(?, ?)')
      .run(PINNED_TOOLS_PREF_KEY, JSON.stringify(['dialer', 'bogus_tool', 'dialer', 'device_lab', 'screenshot']));
    // Unknown id dropped, duplicate dropped, capped at MAX_PINNED_TOOLS (2).
    expect(parsePinnedTools(db.getPreference(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS))).toEqual([
      'dialer',
      'device_lab',
    ]);
  });
});
