/**
 * Account notes (Dialer Notes) module for the Zio Browser.
 *
 * Notes live on the Sayzio account (the same /api/v1/dialer/notes API the web
 * and mobile apps use) so a note created anywhere appears everywhere. The
 * browser keeps a local cache table (account_notes_cache) for instant/offline
 * reads and an offline op queue (notes_ops_queue) so create/edit/delete work
 * offline and replay when connectivity returns (last-write-wins on the
 * server).
 *
 * Also owns the one-time idempotent migration of legacy local
 * saved_links.notes text into account notes attached to each link's URL.
 */

import { randomUUID } from 'crypto';
import { BrowserWindow } from 'electron';
import { getDb, getPreference, setPreference } from './db';
import { retrieveToken } from './auth-store';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import { ApiClient } from '../shared/api-client';
import type { ApiDialerNote, DialerNoteInput } from '../shared/api-client';

// ── API client plumbing ──────────────────────────────────────────────────────

function makeClient(): ApiClient | null {
  const token = retrieveToken();
  const baseUrl = getPreference(PREFERENCE_KEYS.SAYZIO_API_BASE_URL);
  if (!token || !baseUrl) return null;
  return new ApiClient({ baseUrl, token });
}

/** Strip a leading www. from a lowercased hostname (mirrors the server rule). */
export function hostFromUrl(url: string): string | null {
  try {
    return new URL(url).hostname.toLowerCase().replace(/^www\./, '') || null;
  } catch {
    return null;
  }
}

// ── Renderer notifications ───────────────────────────────────────────────────

/**
 * Tell every window that the notes cache changed so live UI (e.g. the toolbar
 * note-count badge) can re-read counts without waiting for a panel close or
 * navigation. Best-effort — never throws.
 */
function notifyNotesChanged(): void {
  try {
    for (const win of BrowserWindow.getAllWindows()) {
      if (!win.isDestroyed()) win.webContents.send('notes:changed');
    }
  } catch {
    // best-effort UI refresh only
  }
}

// ── Local cache ──────────────────────────────────────────────────────────────

interface CacheRow {
  id: number;
  own: number;
  payload: string;
}

function cacheNote(note: ApiDialerNote): void {
  const db = getDb();
  db.prepare(`
    INSERT INTO account_notes_cache(id, own, attached_host, attached_url, payload, updated_at, cached_at)
    VALUES(?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT(id) DO UPDATE SET
      own = excluded.own,
      attached_host = excluded.attached_host,
      attached_url = excluded.attached_url,
      payload = excluded.payload,
      updated_at = excluded.updated_at,
      cached_at = excluded.cached_at
  `).run(
    note.id,
    note.own ? 1 : 0,
    note.attached_host ?? null,
    note.attached_url ?? null,
    JSON.stringify(note),
    note.updated_at ?? null,
    new Date().toISOString(),
  );
}

function replaceCache(notes: ApiDialerNote[], shared: ApiDialerNote[]): void {
  const db = getDb();
  const tx = db.transaction(() => {
    db.prepare('DELETE FROM account_notes_cache').run();
    for (const n of notes) cacheNote(n);
    for (const n of shared) cacheNote(n);
  });
  tx();
}

function removeFromCache(id: number): void {
  getDb().prepare('DELETE FROM account_notes_cache WHERE id = ?').run(id);
}

function readCache(filter?: { url?: string; domain?: string }): { notes: ApiDialerNote[]; shared: ApiDialerNote[] } {
  const db = getDb();
  let rows: CacheRow[];
  if (filter?.url) {
    rows = db.prepare('SELECT id, own, payload FROM account_notes_cache WHERE attached_url = ?').all(filter.url) as CacheRow[];
  } else if (filter?.domain) {
    const host = filter.domain.toLowerCase().replace(/^www\./, '');
    rows = db.prepare('SELECT id, own, payload FROM account_notes_cache WHERE attached_host = ?').all(host) as CacheRow[];
  } else {
    rows = db.prepare('SELECT id, own, payload FROM account_notes_cache').all() as CacheRow[];
  }
  const notes: ApiDialerNote[] = [];
  const shared: ApiDialerNote[] = [];
  for (const r of rows) {
    try {
      const note = JSON.parse(r.payload) as ApiDialerNote;
      (r.own ? notes : shared).push(note);
    } catch {
      // Corrupt cache row — skip; it will be replaced on next refresh.
    }
  }
  const byRecency = (a: ApiDialerNote, b: ApiDialerNote): number =>
    (b.updated_at ?? '').localeCompare(a.updated_at ?? '');
  notes.sort(byRecency);
  shared.sort(byRecency);
  return { notes, shared };
}

/**
 * Cheap cache-only count of notes (own + shared) attached to a host. Used by
 * the toolbar badge on every tab switch/navigation, so it must never hit the
 * network — the cache is refreshed by the regular list/save/delete flows.
 */
export function countNotesForHost(host: string): number {
  const normalized = host.toLowerCase().replace(/^www\./, '');
  if (!normalized) return 0;
  const row = getDb()
    .prepare('SELECT COUNT(*) AS n FROM account_notes_cache WHERE attached_host = ?')
    .get(normalized) as { n: number };
  return row.n;
}

// ── Offline op queue ─────────────────────────────────────────────────────────

interface NoteOpRow {
  id: string;
  op: 'create' | 'update' | 'delete';
  note_id: number | null;
  local_id: number | null;
  payload: string | null;
  attempts: number;
}

/** Negative synthetic ids for notes created offline (never collide with server ids). */
function nextLocalId(): number {
  const db = getDb();
  const row = db.prepare('SELECT MIN(id) AS m FROM account_notes_cache').get() as { m: number | null };
  return Math.min(0, row.m ?? 0) - 1;
}

function enqueueOp(op: 'create' | 'update' | 'delete', noteId: number | null, localId: number | null, payload: DialerNoteInput | null): void {
  getDb().prepare(`
    INSERT INTO notes_ops_queue(id, op, note_id, local_id, payload, attempts, next_attempt_at, created_at)
    VALUES(?, ?, ?, ?, ?, 0, ?, ?)
  `).run(
    randomUUID(),
    op,
    noteId,
    localId,
    payload ? JSON.stringify(payload) : null,
    new Date().toISOString(),
    new Date().toISOString(),
  );
}

export function pendingNoteOpsCount(): number {
  const row = getDb().prepare('SELECT COUNT(*) AS n FROM notes_ops_queue').get() as { n: number };
  return row.n;
}

/**
 * Replay queued offline ops against the server, oldest first. Stops at the
 * first failure (keeps ordering: a create must land before its updates).
 * Returns true when the queue is fully drained.
 */
export async function flushNoteOps(): Promise<boolean> {
  const client = makeClient();
  if (!client) return false;
  const db = getDb();
  let applied = 0;

  for (;;) {
    const row = db.prepare('SELECT * FROM notes_ops_queue ORDER BY created_at ASC LIMIT 1').get() as NoteOpRow | undefined;
    if (!row) {
      if (applied > 0) notifyNotesChanged();
      return true;
    }
    try {
      if (row.op === 'create') {
        const input = JSON.parse(row.payload ?? '{}') as DialerNoteInput;
        const saved = await client.createNote(input);
        const tx = db.transaction(() => {
          if (row.local_id != null) {
            removeFromCache(row.local_id);
            // Re-point any queued follow-up ops at the real server id.
            db.prepare('UPDATE notes_ops_queue SET note_id = ? WHERE note_id = ?').run(saved.id, row.local_id);
          }
          db.prepare('DELETE FROM notes_ops_queue WHERE id = ?').run(row.id);
        });
        tx();
        cacheNote(saved);
      } else if (row.op === 'update') {
        if (row.note_id != null && row.note_id > 0) {
          const input = JSON.parse(row.payload ?? '{}') as DialerNoteInput;
          const saved = await client.updateNote(row.note_id, input);
          cacheNote(saved);
        }
        db.prepare('DELETE FROM notes_ops_queue WHERE id = ?').run(row.id);
      } else {
        if (row.note_id != null && row.note_id > 0) {
          try {
            await client.deleteNote(row.note_id);
          } catch (e) {
            // Already gone on the server — treat as success.
            if (!(e instanceof Error && 'status' in e && (e as { status?: number }).status === 404)) throw e;
          }
        }
        db.prepare('DELETE FROM notes_ops_queue WHERE id = ?').run(row.id);
      }
      applied += 1;
    } catch (e) {
      db.prepare('UPDATE notes_ops_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?')
        .run(e instanceof Error ? e.message : String(e), row.id);
      if (applied > 0) notifyNotesChanged();
      return false;
    }
  }
}

// ── Public operations (IPC surface) ──────────────────────────────────────────

export interface NotesListResult {
  notes: ApiDialerNote[];
  shared: ApiDialerNote[];
  /** True when served from the local cache (offline / not signed in). */
  offline: boolean;
  /** Number of local changes still waiting to sync. */
  pending: number;
  /** True when the user is signed in (notes require an account). */
  authed: boolean;
}

/**
 * List notes — network-first with cache fallback. Any pending offline ops are
 * flushed first so the server list reflects local edits when possible.
 */
export async function listAccountNotes(filter?: { url?: string; domain?: string }): Promise<NotesListResult> {
  const client = makeClient();
  if (client) {
    try {
      await flushNoteOps();
      const res = await client.listNotes(filter);
      if (!filter?.url && !filter?.domain) {
        replaceCache(res.notes, res.shared);
      } else {
        for (const n of [...res.notes, ...res.shared]) cacheNote(n);
      }
      return { ...res, offline: false, pending: pendingNoteOpsCount(), authed: true };
    } catch {
      // fall through to cache
    }
  }
  const cached = readCache(filter);
  return { ...cached, offline: true, pending: pendingNoteOpsCount(), authed: client != null };
}

/**
 * Create or update a note. Online: writes straight to the API. Offline: the
 * change is cached optimistically and queued for replay.
 */
export async function saveAccountNote(id: number | null, input: DialerNoteInput): Promise<ApiDialerNote> {
  const client = makeClient();
  if (client) {
    try {
      await flushNoteOps();
      const saved = id != null && id > 0
        ? await client.updateNote(id, input)
        : await client.createNote(input);
      cacheNote(saved);
      notifyNotesChanged();
      return saved;
    } catch (e) {
      // Validation errors (422) should surface, not queue.
      if (e instanceof Error && 'status' in e && (e as { status?: number }).status === 422) throw e;
      // Network/other failure — fall through to offline path.
    }
  }
  // Offline path: optimistic cache + queue.
  const now = new Date().toISOString();
  if (id != null) {
    const existing = readCache().notes.find(n => n.id === id) ?? null;
    const merged: ApiDialerNote = {
      ...(existing ?? emptyNote(id)),
      ...normalizeInput(input),
      id,
      updated_at: now,
    };
    cacheNote(merged);
    enqueueOp('update', id, null, input);
    notifyNotesChanged();
    return merged;
  }
  const localId = nextLocalId();
  const created: ApiDialerNote = { ...emptyNote(localId), ...normalizeInput(input), id: localId, created_at: now, updated_at: now };
  cacheNote(created);
  enqueueOp('create', null, localId, input);
  notifyNotesChanged();
  return created;
}

/** Delete a note (queues the delete when offline). */
export async function deleteAccountNote(id: number): Promise<void> {
  const client = makeClient();
  if (client && id > 0) {
    try {
      await flushNoteOps();
      await client.deleteNote(id);
      removeFromCache(id);
      notifyNotesChanged();
      return;
    } catch (e) {
      if (e instanceof Error && 'status' in e && (e as { status?: number }).status === 404) {
        removeFromCache(id);
        notifyNotesChanged();
        return;
      }
      // fall through to offline queue
    }
  }
  removeFromCache(id);
  if (id > 0) {
    enqueueOp('delete', id, null, null);
  } else {
    // Note only ever existed locally — drop its pending create/updates.
    getDb().prepare('DELETE FROM notes_ops_queue WHERE local_id = ? OR note_id = ?').run(id, id);
  }
  notifyNotesChanged();
}

function emptyNote(id: number): ApiDialerNote {
  return {
    id,
    title: null,
    body: null,
    number: null,
    remind_at: null,
    done: false,
    color: null,
    kind: 'note',
    checklist: [],
    attached_url: null,
    attached_title: null,
    attached_host: null,
    source_type: null,
    source_id: null,
    own: true,
    owner_name: null,
    share_phones: [],
    updated_at: null,
    created_at: null,
  };
}

/** Project a DialerNoteInput onto the cached-note shape (derives attached_host). */
function normalizeInput(input: DialerNoteInput): Partial<ApiDialerNote> {
  const out: Partial<ApiDialerNote> = {};
  if (input.kind !== undefined) out.kind = input.kind;
  if (input.title !== undefined) out.title = input.title;
  if (input.body !== undefined) out.body = input.body;
  if (input.checklist !== undefined) out.checklist = input.checklist ?? [];
  if (input.number !== undefined) out.number = input.number;
  if (input.remind_at !== undefined) out.remind_at = input.remind_at;
  if (input.done !== undefined) out.done = input.done;
  if (input.color !== undefined) out.color = input.color;
  if (input.share_phones !== undefined) out.share_phones = input.share_phones;
  if (input.attached_url !== undefined) {
    out.attached_url = input.attached_url;
    out.attached_host = input.attached_url ? hostFromUrl(input.attached_url) : null;
    out.attached_title = input.attached_url ? (input.attached_title ?? null) : null;
  } else if (input.attached_title !== undefined) {
    out.attached_title = input.attached_title;
  }
  return out;
}

// ── One-time saved_links.notes migration ────────────────────────────────────

export interface SavedLinkNotesMigrationResult {
  status: 'done' | 'already-done' | 'nothing-to-migrate' | 'offline';
  migrated: number;
}

/**
 * One-time import of legacy local saved-link note text into account notes
 * attached to each link's URL. Idempotent — a preference flag records
 * completion, and an empty source set completes immediately without any
 * network traffic. Requires the user to be signed in (skipped until then).
 */
export async function migrateSavedLinkNotes(): Promise<SavedLinkNotesMigrationResult> {
  if (getPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED) === '1') {
    return { status: 'already-done', migrated: 0 };
  }
  const db = getDb();
  const rows = db.prepare(
    "SELECT id, url, title, notes FROM saved_links WHERE deleted = 0 AND notes IS NOT NULL AND TRIM(notes) != ''",
  ).all() as Array<{ id: string; url: string; title: string; notes: string }>;

  if (rows.length === 0) {
    setPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED, '1');
    return { status: 'nothing-to-migrate', migrated: 0 };
  }

  const client = makeClient();
  if (!client) return { status: 'offline', migrated: 0 };

  let migrated = 0;
  for (const row of rows) {
    const saved = await client.createNote({
      kind: 'note',
      title: row.title || null,
      body: row.notes.trim(),
      attached_url: row.url,
      attached_title: row.title || null,
    });
    cacheNote(saved);
    // Clear the migrated text so a partial run (crash mid-way) never
    // duplicates notes on retry — each row is migrated at most once.
    db.prepare('UPDATE saved_links SET notes = NULL, updated_at = ? WHERE id = ?')
      .run(new Date().toISOString(), row.id);
    migrated += 1;
  }
  setPreference(PREFERENCE_KEYS.SAVED_LINKS_NOTES_MIGRATED, '1');
  return { status: 'done', migrated };
}
