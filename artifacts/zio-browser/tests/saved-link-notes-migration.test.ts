/**
 * Unit tests for the one-time saved_links.notes → account-notes migration
 * (migrateSavedLinkNotes in src/main/notes-store.ts).
 *
 * Uses the REAL SQLite store (better-sqlite3, in-memory) with a mocked
 * ApiClient so we can assert exactly which notes are created server-side.
 * Covered scenarios:
 *   - happy path: every legacy note becomes an account note, rows cleared,
 *     completion flag set
 *   - already-migrated no-op: flag set → zero DB/network work
 *   - partial-crash resume: rows cleared in a previous run are never
 *     re-migrated (no duplicates)
 *   - mid-run API failure: earlier rows stay cleared, retry only migrates
 *     the remaining rows (never twice for the same row)
 *   - offline / signed-out: rows and flag untouched, migration retries later
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { PREFERENCE_KEYS } from '../src/shared/db-schema';
import type { ApiDialerNote, DialerNoteInput } from '../src/shared/api-client';

// db.ts imports `app` from electron only to resolve the default DB path;
// we always pass ':memory:' so a minimal stub is enough.
vi.mock('electron', () => ({
  app: { getPath: () => '/tmp' },
}));

// Signed-in by default; individual tests flip this to simulate signed-out.
const retrieveTokenMock = vi.fn<() => string | null>(() => 'test-token');
vi.mock('../src/main/auth-store', () => ({
  retrieveToken: () => retrieveTokenMock(),
}));

// Mock ApiClient: record createNote calls, allow programmable failures.
const createNoteMock = vi.fn<(input: DialerNoteInput) => Promise<ApiDialerNote>>();
vi.mock('../src/shared/api-client', () => ({
  ApiClient: class {
    createNote(input: DialerNoteInput): Promise<ApiDialerNote> {
      return createNoteMock(input);
    }
  },
}));

type DbModule = typeof import('../src/main/db');
type NotesModule = typeof import('../src/main/notes-store');

let db: DbModule;
let notes: NotesModule;
let rawDb: import('better-sqlite3').Database;

let nextNoteId = 1;
function fakeNote(input: DialerNoteInput): ApiDialerNote {
  return {
    id: nextNoteId++,
    title: input.title ?? null,
    body: input.body ?? null,
    number: null,
    remind_at: null,
    done: false,
    color: null,
    kind: 'note',
    checklist: [],
    attached_url: input.attached_url ?? null,
    attached_title: input.attached_title ?? null,
    attached_host: null,
    source_type: null,
    source_id: null,
    own: true,
    owner_name: null,
    share_phones: [],
    updated_at: new Date().toISOString(),
    created_at: new Date().toISOString(),
  };
}

function insertSavedLink(id: string, url: string, title: string, noteText: string | null, deleted = 0): void {
  rawDb.prepare(`
    INSERT INTO saved_links(id, collection_id, url, normalized_url, title, notes, saved_at, updated_at, deleted)
    VALUES(?, 'col-1', ?, ?, ?, ?, ?, ?, ?)
  `).run(id, url, url, title, noteText, new Date().toISOString(), new Date().toISOString(), deleted);
}

function remainingNotes(): Array<{ id: string; notes: string | null }> {
  return rawDb.prepare('SELECT id, notes FROM saved_links ORDER BY id').all() as Array<{ id: string; notes: string | null }>;
}

beforeEach(async () => {
  vi.resetModules();
  db = await import('../src/main/db');
  notes = await import('../src/main/notes-store');
  rawDb = db.initDb(':memory:');
  rawDb.prepare(`
    INSERT INTO collections(id, name, created_at, updated_at) VALUES('col-1', 'Test', ?, ?)
  `).run(new Date().toISOString(), new Date().toISOString());
  // db.ts needs the API base url preference for makeClient().
  db.setPreference(PREFERENCE_KEYS.SAYZIO_API_BASE_URL, 'https://api.test');
  retrieveTokenMock.mockReturnValue('test-token');
  createNoteMock.mockReset();
  createNoteMock.mockImplementation(async (input) => fakeNote(input));
  nextNoteId = 1;
});

describe('migrateSavedLinkNotes — happy path', () => {
  it('migrates every legacy note, clears rows, and sets the completion flag', async () => {
    insertSavedLink('a', 'https://a.test/page', 'Link A', 'note for A');
    insertSavedLink('b', 'https://b.test/page', 'Link B', 'note for B');
    insertSavedLink('c', 'https://c.test/page', 'Link C', null); // no note — skipped

    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'done', migrated: 2 });

    // Both notes created against the API, attached to their link URL.
    expect(createNoteMock).toHaveBeenCalledTimes(2);
    const inputs = createNoteMock.mock.calls.map(([i]) => i);
    expect(inputs).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ body: 'note for A', attached_url: 'https://a.test/page', title: 'Link A' }),
        expect.objectContaining({ body: 'note for B', attached_url: 'https://b.test/page', title: 'Link B' }),
      ]),
    );

    // Legacy text cleared on every migrated row.
    expect(remainingNotes()).toEqual([
      { id: 'a', notes: null },
      { id: 'b', notes: null },
      { id: 'c', notes: null },
    ]);
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBe('1');

    // Migrated notes land in the local cache too.
    const cached = rawDb.prepare('SELECT COUNT(*) AS n FROM account_notes_cache').get() as { n: number };
    expect(cached.n).toBe(2);
  });

  it('trims note bodies and skips whitespace-only and deleted rows', async () => {
    insertSavedLink('a', 'https://a.test', 'A', '  padded note  ');
    insertSavedLink('b', 'https://b.test', 'B', '   '); // whitespace only
    insertSavedLink('c', 'https://c.test', 'C', 'note on deleted link', 1); // deleted

    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'done', migrated: 1 });
    expect(createNoteMock).toHaveBeenCalledTimes(1);
    expect(createNoteMock.mock.calls[0]?.[0]).toEqual(
      expect.objectContaining({ body: 'padded note', attached_url: 'https://a.test' }),
    );
  });

  it('completes immediately with no network traffic when there is nothing to migrate', async () => {
    insertSavedLink('a', 'https://a.test', 'A', null);
    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'nothing-to-migrate', migrated: 0 });
    expect(createNoteMock).not.toHaveBeenCalled();
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBe('1');
  });

  it('empty source set completes even while signed out (no client needed)', async () => {
    retrieveTokenMock.mockReturnValue(null);
    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'nothing-to-migrate', migrated: 0 });
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBe('1');
  });
});

describe('migrateSavedLinkNotes — already migrated', () => {
  it('is a no-op when the completion flag is set, even if note text exists', async () => {
    insertSavedLink('a', 'https://a.test', 'A', 'stale text that must NOT re-migrate');
    db.setPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED, '1');

    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'already-done', migrated: 0 });
    expect(createNoteMock).not.toHaveBeenCalled();
    // Row is left untouched (already-done path never mutates saved_links).
    expect(remainingNotes()).toEqual([{ id: 'a', notes: 'stale text that must NOT re-migrate' }]);
  });

  it('running twice back-to-back migrates each row exactly once', async () => {
    insertSavedLink('a', 'https://a.test', 'A', 'only once');
    const first = await notes.migrateSavedLinkNotes();
    const second = await notes.migrateSavedLinkNotes();
    expect(first.status).toBe('done');
    expect(second).toEqual({ status: 'already-done', migrated: 0 });
    expect(createNoteMock).toHaveBeenCalledTimes(1);
  });
});

describe('migrateSavedLinkNotes — partial-crash resume', () => {
  it('never re-migrates rows already cleared by a previous (crashed) run', async () => {
    // Simulate a crash after row "a" was migrated+cleared but before the
    // completion flag was set: "a" has notes = NULL, "b" still pending.
    insertSavedLink('a', 'https://a.test', 'A', null);
    insertSavedLink('b', 'https://b.test', 'B', 'still pending');

    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'done', migrated: 1 });
    expect(createNoteMock).toHaveBeenCalledTimes(1);
    expect(createNoteMock.mock.calls[0]?.[0]).toEqual(
      expect.objectContaining({ body: 'still pending', attached_url: 'https://b.test' }),
    );
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBe('1');
  });

  it('mid-run API failure clears only migrated rows; retry finishes the rest without duplicates', async () => {
    insertSavedLink('a', 'https://a.test', 'A', 'note A');
    insertSavedLink('b', 'https://b.test', 'B', 'note B');
    insertSavedLink('c', 'https://c.test', 'C', 'note C');

    // First run: succeed on the first create, then die (network drop).
    // Track creates that actually SUCCEEDED server-side — a failed attempt
    // never lands a note, so retrying it is correct, not a duplicate.
    const succeeded: string[] = [];
    let calls = 0;
    createNoteMock.mockImplementation(async (input) => {
      calls += 1;
      if (calls >= 2) throw new Error('network down');
      succeeded.push(input.body ?? '');
      return fakeNote(input);
    });
    await expect(notes.migrateSavedLinkNotes()).rejects.toThrow('network down');

    // Exactly one row cleared; flag NOT set; two rows still pending.
    const afterCrash = remainingNotes();
    expect(afterCrash.filter(r => r.notes === null)).toHaveLength(1);
    expect(afterCrash.filter(r => r.notes !== null)).toHaveLength(2);
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBeNull();

    // Retry with the API healthy again: only the two pending rows migrate.
    createNoteMock.mockImplementation(async (input) => {
      succeeded.push(input.body ?? '');
      return fakeNote(input);
    });
    const retry = await notes.migrateSavedLinkNotes();
    expect(retry).toEqual({ status: 'done', migrated: 2 });

    // Across both runs each of the 3 notes SUCCESSFULLY created exactly once
    // server-side — no duplicates from the failed attempt being retried.
    expect(succeeded.sort()).toEqual(['note A', 'note B', 'note C']);
    expect(remainingNotes().every(r => r.notes === null)).toBe(true);
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBe('1');
  });
});

describe('migrateSavedLinkNotes — offline / signed out', () => {
  it('leaves rows and flag untouched when not signed in', async () => {
    retrieveTokenMock.mockReturnValue(null);
    insertSavedLink('a', 'https://a.test', 'A', 'note A');

    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'offline', migrated: 0 });
    expect(createNoteMock).not.toHaveBeenCalled();
    expect(remainingNotes()).toEqual([{ id: 'a', notes: 'note A' }]);
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBeNull();
    // Nothing sneaks into the offline op queue — the migration retries
    // wholesale on next sign-in instead of queueing blind creates.
    expect(notes.pendingNoteOpsCount()).toBe(0);
  });

  it('retries successfully after the user signs in', async () => {
    retrieveTokenMock.mockReturnValue(null);
    insertSavedLink('a', 'https://a.test', 'A', 'note A');
    expect((await notes.migrateSavedLinkNotes()).status).toBe('offline');

    retrieveTokenMock.mockReturnValue('fresh-token');
    const result = await notes.migrateSavedLinkNotes();
    expect(result).toEqual({ status: 'done', migrated: 1 });
    expect(createNoteMock).toHaveBeenCalledTimes(1);
    expect(remainingNotes()).toEqual([{ id: 'a', notes: null }]);
    expect(db.getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED)).toBe('1');
  });
});
