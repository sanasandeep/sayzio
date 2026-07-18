/**
 * Local SQLite database for Zio Browser.
 * Uses better-sqlite3 for synchronous access (Electron main process only).
 *
 * All data-access functions that scope by profile accept a profileId parameter
 * (default 'default'). The active profile is tracked module-level and applied
 * automatically via getActiveProfileId().
 */
import path from 'path';
import { app } from 'electron';
import Database from 'better-sqlite3';
import {
  CREATE_TABLES_SQL,
  MIGRATION_SQL,
  SCHEMA_VERSION,
  PREFERENCE_KEYS,
  type PreferenceKey,
} from '../shared/db-schema';
import type { Collection, SavedLink } from '../shared/collection-store';
import { generateId, normalizeCollectionUrl } from '../shared/collection-store';
import { normalizeUrlForHistory } from '../shared/omnibox';
import type { SyncRecord, SyncQueueItem, SyncEntityKind } from '../shared/sync-engine';
import { nextAttemptAt } from '../shared/sync-engine';
import { DEFAULT_PROFILE_ID } from '../shared/profile-store';
import type { BrowserProfile } from '../shared/profile-store';

export interface HistoryEntry {
  id: string;
  profile_id: string;
  url: string;
  normalized_url: string;
  title: string | null;
  favicon_url: string | null;
  visit_count: number;
  last_visited: string;
  created_at: string;
  updated_at: string;
  deleted: boolean;
  synced_at: string | null;
}

export interface Bookmark {
  id: string;
  profile_id: string;
  url: string;
  normalized_url: string;
  title: string;
  description: string | null;
  favicon_url: string | null;
  folder: string | null;
  created_at: string;
  updated_at: string;
  deleted: boolean;
  synced_at: string | null;
}

export interface Download {
  id: string;
  url: string;
  filename: string;
  save_path: string | null;
  mime_type: string | null;
  total_bytes: number | null;
  received_bytes: number;
  state: 'pending' | 'progressing' | 'completed' | 'interrupted' | 'cancelled';
  created_at: string;
  completed_at: string | null;
}

export interface SavedPassword {
  id: string;
  origin: string;
  username: string;
  password_enc: string;
  created_at: string;
  updated_at: string;
}

let _db: Database.Database | null = null;

/** Currently active browser profile — all scoped queries filter by this. */
let _activeProfileId: string = DEFAULT_PROFILE_ID;

export function getActiveProfileId(): string {
  return _activeProfileId;
}

export function setActiveProfileId(profileId: string): void {
  _activeProfileId = profileId;
}

export function getDb(): Database.Database {
  if (!_db) {
    throw new Error('Database not initialized. Call initDb() first.');
  }
  return _db;
}

export function initDb(dbPath?: string): Database.Database {
  const resolvedPath = dbPath ?? path.join(app.getPath('userData'), 'zio-browser.db');
  _db = new Database(resolvedPath);
  _db.exec(CREATE_TABLES_SQL);
  migrateSchema(_db);
  return _db;
}

function migrateSchema(db: Database.Database): void {
  const row = db.prepare('SELECT version FROM schema_version LIMIT 1').get() as { version: number } | undefined;
  const currentVersion = row?.version ?? 0;

  if (currentVersion < SCHEMA_VERSION) {
    db.transaction(() => {
      // Run incremental migration SQL for each missing version
      for (let v = Math.max(1, currentVersion + 1); v <= SCHEMA_VERSION; v++) {
        const sql = MIGRATION_SQL[v];
        if (sql) {
          // Split on ';' and run each statement individually (SQLite pragma)
          for (const stmt of sql.split(';').map(s => s.trim()).filter(Boolean)) {
            try {
              db.exec(stmt + ';');
            } catch (err) {
              // Ignore "already exists" / "duplicate column" errors from idempotent migrations
              const msg = String(err);
              if (!msg.includes('already exists') && !msg.includes('duplicate column')) {
                throw err;
              }
            }
          }
        }
      }

      if (currentVersion === 0) {
        db.prepare('INSERT OR REPLACE INTO schema_version(version) VALUES(?)').run(SCHEMA_VERSION);
      } else {
        db.prepare('UPDATE schema_version SET version = ?').run(SCHEMA_VERSION);
      }
    })();
  }
}

// ── Profiles ─────────────────────────────────────────────────────────────────

export function listProfiles(): BrowserProfile[] {
  const db = getDb();
  const rows = db.prepare('SELECT * FROM profiles ORDER BY created_at ASC').all() as Array<{
    id: string; workspace_id: string | null; name: string; created_at: string;
  }>;
  const profiles: BrowserProfile[] = [
    { id: DEFAULT_PROFILE_ID, workspaceId: null, name: 'Personal', isPersonal: true },
  ];
  for (const row of rows) {
    if (row.id !== DEFAULT_PROFILE_ID) {
      profiles.push({
        id: row.id,
        workspaceId: row.workspace_id,
        name: row.name,
        isPersonal: false,
      });
    }
  }
  return profiles;
}

export function upsertProfile(profile: BrowserProfile): void {
  if (profile.id === DEFAULT_PROFILE_ID) return; // Default profile is virtual
  const db = getDb();
  db.prepare(`
    INSERT INTO profiles(id, workspace_id, name, created_at)
    VALUES(?, ?, ?, ?)
    ON CONFLICT(id) DO UPDATE SET name = excluded.name, workspace_id = excluded.workspace_id
  `).run(profile.id, profile.workspaceId ?? null, profile.name, new Date().toISOString());
}

export function deleteProfile(profileId: string): void {
  if (profileId === DEFAULT_PROFILE_ID) return;
  const db = getDb();
  db.prepare('DELETE FROM profiles WHERE id = ?').run(profileId);
}

// ── Preferences ─────────────────────────────────────────────────────────────

export function getPreference(key: PreferenceKey): string | null {
  const db = getDb();
  const row = db.prepare('SELECT value FROM preferences WHERE key = ?').get(key) as { value: string } | undefined;
  return row?.value ?? null;
}

export function setPreference(key: PreferenceKey, value: string): void {
  const db = getDb();
  db.prepare('INSERT OR REPLACE INTO preferences(key, value) VALUES(?, ?)').run(key, value);
}

export function getAllPreferences(): Record<string, string> {
  const db = getDb();
  const rows = db.prepare('SELECT key, value FROM preferences').all() as Array<{ key: string; value: string }>;
  return Object.fromEntries(rows.map(r => [r.key, r.value]));
}

// ── History ──────────────────────────────────────────────────────────────────

export function recordVisit(url: string, title: string | null, faviconUrl?: string, profileId?: string): HistoryEntry {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const normalized = normalizeUrlForHistory(url);
  const now = new Date().toISOString();

  const existing = db.prepare('SELECT * FROM history WHERE profile_id = ? AND normalized_url = ? AND deleted = 0').get(pid, normalized) as HistoryEntry | undefined;

  if (existing) {
    db.prepare(`
      UPDATE history
      SET title = COALESCE(?, title), favicon_url = COALESCE(?, favicon_url),
          visit_count = visit_count + 1, last_visited = ?, updated_at = ?
      WHERE id = ?
    `).run(title, faviconUrl ?? null, now, now, existing.id);
    return db.prepare('SELECT * FROM history WHERE id = ?').get(existing.id) as HistoryEntry;
  }

  const id = generateId();
  db.prepare(`
    INSERT INTO history(id, profile_id, url, normalized_url, title, favicon_url, visit_count, last_visited, created_at, updated_at, deleted)
    VALUES(?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 0)
  `).run(id, pid, url, normalized, title, faviconUrl ?? null, now, now, now);
  return db.prepare('SELECT * FROM history WHERE id = ?').get(id) as HistoryEntry;
}

export function searchHistory(query: string, limit = 20, profileId?: string): HistoryEntry[] {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const like = `%${query.replace(/[%_]/g, c => `\\${c}`)}%`;
  return db.prepare(`
    SELECT * FROM history
    WHERE profile_id = ? AND deleted = 0 AND (url LIKE ? ESCAPE '\\' OR title LIKE ? ESCAPE '\\')
    ORDER BY last_visited DESC
    LIMIT ?
  `).all(pid, like, like, limit) as HistoryEntry[];
}

export function getRecentHistory(limit = 50, profileId?: string): HistoryEntry[] {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  return db.prepare('SELECT * FROM history WHERE profile_id = ? AND deleted = 0 ORDER BY last_visited DESC LIMIT ?').all(pid, limit) as HistoryEntry[];
}

export function clearHistory(profileId?: string): void {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  db.prepare('UPDATE history SET deleted = 1, updated_at = ? WHERE profile_id = ? AND deleted = 0').run(new Date().toISOString(), pid);
}

/**
 * Soft-delete history entries created at or after `sinceIso` (or all entries
 * when `sinceIso` is null). Returns the deleted rows so callers can propagate
 * sync tombstones to the server.
 */
export function clearHistoryByRange(sinceIso: string | null): HistoryEntry[] {
  const db = getDb();
  const now = new Date().toISOString();
  let rows: HistoryEntry[];
  if (sinceIso) {
    rows = db.prepare(
      'SELECT * FROM history WHERE deleted = 0 AND last_visited >= ?',
    ).all(sinceIso) as HistoryEntry[];
    if (rows.length > 0) {
      db.prepare(
        'UPDATE history SET deleted = 1, updated_at = ? WHERE deleted = 0 AND last_visited >= ?',
      ).run(now, sinceIso);
    }
  } else {
    rows = db.prepare('SELECT * FROM history WHERE deleted = 0').all() as HistoryEntry[];
    if (rows.length > 0) {
      db.prepare('UPDATE history SET deleted = 1, updated_at = ?').run(now);
    }
  }
  return rows;
}

export function deleteHistoryEntry(id: string): boolean {
  const db = getDb();
  const now = new Date().toISOString();
  const result = db.prepare('UPDATE history SET deleted = 1, updated_at = ? WHERE id = ? AND deleted = 0').run(now, id);
  return (result.changes ?? 0) > 0;
}

// ── Bookmarks ────────────────────────────────────────────────────────────────

export function addBookmark(url: string, title: string, options: Partial<Bookmark> = {}, profileId?: string): Bookmark {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const normalized = normalizeCollectionUrl(url);
  const now = new Date().toISOString();

  const existing = db.prepare('SELECT * FROM bookmarks WHERE profile_id = ? AND normalized_url = ? AND deleted = 0').get(pid, normalized) as Bookmark | undefined;
  if (existing) return existing;

  const id = generateId();
  db.prepare(`
    INSERT INTO bookmarks(id, profile_id, url, normalized_url, title, description, favicon_url, folder, created_at, updated_at, deleted)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
  `).run(id, pid, url, normalized, title, options.description ?? null, options.favicon_url ?? null, options.folder ?? null, now, now);
  return db.prepare('SELECT * FROM bookmarks WHERE id = ?').get(id) as Bookmark;
}

export function removeBookmark(url: string, profileId?: string): boolean {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const normalized = normalizeCollectionUrl(url);
  const now = new Date().toISOString();
  const result = db.prepare('UPDATE bookmarks SET deleted = 1, updated_at = ? WHERE profile_id = ? AND normalized_url = ? AND deleted = 0').run(now, pid, normalized);
  return (result.changes ?? 0) > 0;
}

export function isBookmarked(url: string, profileId?: string): boolean {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const normalized = normalizeCollectionUrl(url);
  const row = db.prepare('SELECT id FROM bookmarks WHERE profile_id = ? AND normalized_url = ? AND deleted = 0 LIMIT 1').get(pid, normalized);
  return row !== undefined;
}

export function getAllBookmarks(folder?: string, profileId?: string): Bookmark[] {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  if (folder) {
    return db.prepare('SELECT * FROM bookmarks WHERE profile_id = ? AND deleted = 0 AND folder = ? ORDER BY created_at DESC').all(pid, folder) as Bookmark[];
  }
  return db.prepare('SELECT * FROM bookmarks WHERE profile_id = ? AND deleted = 0 ORDER BY created_at DESC').all(pid) as Bookmark[];
}

export function searchBookmarks(query: string, limit = 20, profileId?: string): Bookmark[] {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const like = `%${query.replace(/[%_]/g, c => `\\${c}`)}%`;
  return db.prepare(`
    SELECT * FROM bookmarks
    WHERE profile_id = ? AND deleted = 0 AND (url LIKE ? ESCAPE '\\' OR title LIKE ? ESCAPE '\\')
    ORDER BY created_at DESC LIMIT ?
  `).all(pid, like, like, limit) as Bookmark[];
}

export function getBookmarksAsSyncRecords(profileId?: string): SyncRecord[] {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const rows = db.prepare('SELECT * FROM bookmarks WHERE profile_id = ?').all(pid) as Bookmark[];
  return rows.map(r => ({
    local_id: r.id,
    updated_at: r.updated_at,
    deleted: Boolean(r.deleted),
    synced_at: r.synced_at,
    data: { url: r.url, title: r.title, description: r.description, folder: r.folder, favicon_url: r.favicon_url },
  }));
}

export function upsertBookmarkFromSync(record: SyncRecord, profileId?: string): void {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  const data = record.data as { url: string; title: string; description?: string; folder?: string; favicon_url?: string };
  const now = new Date().toISOString();
  db.prepare(`
    INSERT INTO bookmarks(id, profile_id, url, normalized_url, title, description, favicon_url, folder, created_at, updated_at, deleted, synced_at)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON CONFLICT(id) DO UPDATE SET
      url = excluded.url, title = excluded.title, description = excluded.description,
      favicon_url = excluded.favicon_url, folder = excluded.folder,
      updated_at = excluded.updated_at, deleted = excluded.deleted, synced_at = excluded.synced_at
  `).run(
    record.local_id, pid, data.url, normalizeCollectionUrl(data.url), data.title,
    data.description ?? null, data.favicon_url ?? null, data.folder ?? null,
    record.updated_at, record.updated_at, record.deleted ? 1 : 0, now,
  );
}

// ── Collections ──────────────────────────────────────────────────────────────

export function getAllCollections(profileId?: string): Collection[] {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  return (db.prepare(`
    SELECT c.*, (SELECT COUNT(*) FROM saved_links sl WHERE sl.collection_id = c.id AND sl.deleted = 0) as item_count
    FROM collections c WHERE c.profile_id = ? AND c.deleted = 0 ORDER BY c.updated_at DESC
  `).all(pid) as Array<Collection & { item_count: number }>).map(row => ({ ...row, deleted: Boolean(row.deleted) }));
}

export function createCollectionInDb(collection: Collection, profileId?: string): void {
  const db = getDb();
  const pid = profileId ?? _activeProfileId;
  db.prepare(`
    INSERT INTO collections(id, profile_id, name, description, color, icon, created_at, updated_at, deleted, synced_at)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)
  `).run(collection.id, pid, collection.name, collection.description, collection.color, collection.icon, collection.created_at, collection.updated_at);
}

export function updateCollection(id: string, updates: Partial<Pick<Collection, 'name' | 'description' | 'color' | 'icon'>>): void {
  const db = getDb();
  const now = new Date().toISOString();
  const fields: string[] = [];
  const values: unknown[] = [];
  if (updates.name !== undefined) { fields.push('name = ?'); values.push(updates.name); }
  if (updates.description !== undefined) { fields.push('description = ?'); values.push(updates.description); }
  if (updates.color !== undefined) { fields.push('color = ?'); values.push(updates.color); }
  if (updates.icon !== undefined) { fields.push('icon = ?'); values.push(updates.icon); }
  if (fields.length === 0) return;
  fields.push('updated_at = ?'); values.push(now);
  values.push(id);
  db.prepare(`UPDATE collections SET ${fields.join(', ')} WHERE id = ?`).run(...values);
}

export function deleteCollection(id: string): void {
  const db = getDb();
  const now = new Date().toISOString();
  db.transaction(() => {
    db.prepare('UPDATE saved_links SET deleted = 1, updated_at = ? WHERE collection_id = ?').run(now, id);
    db.prepare('UPDATE collections SET deleted = 1, updated_at = ? WHERE id = ?').run(now, id);
  })();
}

type SqliteSavedLink = Omit<SavedLink, 'ai_tags' | 'deleted' | 'ai_enriched'> & {
  ai_tags: string;
  deleted: number;
  ai_enriched: number;
};

export function getSavedLinksForCollection(collectionId: string): SavedLink[] {
  const db = getDb();
  const rows = db.prepare('SELECT * FROM saved_links WHERE collection_id = ? AND deleted = 0 ORDER BY saved_at DESC').all(collectionId) as SqliteSavedLink[];
  return rows.map(r => ({ ...r, ai_tags: JSON.parse(r.ai_tags ?? '[]') as string[], deleted: Boolean(r.deleted), ai_enriched: Boolean(r.ai_enriched) }));
}

export function saveLinkToCollection(link: SavedLink): void {
  const db = getDb();
  db.prepare(`
    INSERT INTO saved_links(id, collection_id, url, normalized_url, title, description, ai_summary, ai_tags,
      ai_context, notes, favicon_url, screenshot_url, saved_at, updated_at, deleted, synced_at, ai_enriched, ai_coins_used)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, 0, NULL)
  `).run(
    link.id, link.collection_id, link.url, link.normalized_url, link.title, link.description,
    link.ai_summary, JSON.stringify(link.ai_tags), link.ai_context, link.notes,
    link.favicon_url, link.screenshot_url, link.saved_at, link.updated_at,
  );
}

export function updateSavedLinkAiEnrichment(id: string, summary: string, tags: string[], context: string, coinsUsed: number): void {
  const db = getDb();
  db.prepare(`
    UPDATE saved_links
    SET ai_summary = ?, ai_tags = ?, ai_context = ?, ai_enriched = 1, ai_coins_used = ?, updated_at = ?
    WHERE id = ?
  `).run(summary, JSON.stringify(tags), context, coinsUsed, new Date().toISOString(), id);
}

// ── Sync helpers ─────────────────────────────────────────────────────────────

export function getSyncState(entity: string): { lastSyncAt: string | null; lastError: string | null } {
  const db = getDb();
  const row = db.prepare('SELECT last_sync_at, last_error FROM sync_state WHERE entity = ?').get(entity) as { last_sync_at: string | null; last_error: string | null } | undefined;
  return { lastSyncAt: row?.last_sync_at ?? null, lastError: row?.last_error ?? null };
}

export function setSyncState(entity: string, lastSyncAt: string | null, lastError: string | null = null): void {
  const db = getDb();
  db.prepare('INSERT OR REPLACE INTO sync_state(entity, last_sync_at, last_error) VALUES(?, ?, ?)').run(entity, lastSyncAt, lastError);
}

// ── Sync retry queue ─────────────────────────────────────────────────────────

export function enqueueSyncPush(entity: SyncEntityKind, payload: string, error: string | null = null): SyncQueueItem {
  const db = getDb();
  const now = new Date().toISOString();
  const id = generateId();
  db.prepare(`
    INSERT INTO sync_queue(id, entity, payload, attempts, next_attempt_at, last_error, created_at)
    VALUES(?, ?, ?, 1, ?, ?, ?)
  `).run(id, entity, payload, nextAttemptAt(1), error, now);
  return db.prepare('SELECT * FROM sync_queue WHERE id = ?').get(id) as SyncQueueItem;
}

export function getSyncQueueItems(): SyncQueueItem[] {
  const db = getDb();
  return db.prepare('SELECT * FROM sync_queue ORDER BY created_at ASC').all() as SyncQueueItem[];
}

export function countSyncQueue(): number {
  const db = getDb();
  const row = db.prepare('SELECT COUNT(*) as n FROM sync_queue').get() as { n: number };
  return row.n;
}

export function markSyncQueueFailure(id: string, error: string): void {
  const db = getDb();
  const row = db.prepare('SELECT attempts FROM sync_queue WHERE id = ?').get(id) as { attempts: number } | undefined;
  if (!row) return;
  const attempts = row.attempts + 1;
  db.prepare('UPDATE sync_queue SET attempts = ?, next_attempt_at = ?, last_error = ? WHERE id = ?')
    .run(attempts, nextAttemptAt(attempts), error, id);
}

export function removeSyncQueueItem(id: string): void {
  const db = getDb();
  db.prepare('DELETE FROM sync_queue WHERE id = ?').run(id);
}

const SYNC_ENTITY_TABLES: Record<SyncEntityKind, string> = {
  bookmarks: 'bookmarks',
  collections: 'collections',
  history: 'history',
};

/** Stamp synced_at on local rows after a queued push finally succeeds. */
export function markRecordsSynced(entity: SyncEntityKind, ids: string[]): void {
  if (ids.length === 0) return;
  const db = getDb();
  const table = SYNC_ENTITY_TABLES[entity];
  const now = new Date().toISOString();
  const placeholders = ids.map(() => '?').join(', ');
  db.prepare(`UPDATE ${table} SET synced_at = ? WHERE id IN (${placeholders})`).run(now, ...ids);
}

// ── Site permissions ─────────────────────────────────────────────────────────

export interface SitePermissionRow {
  origin: string;
  permission: string;
  decision: 'allow' | 'block';
  updated_at: string;
}

export function getSitePermission(origin: string, permission: string): 'allow' | 'block' | null {
  const db = getDb();
  const row = db.prepare('SELECT decision FROM site_permissions WHERE origin = ? AND permission = ?').get(origin, permission) as { decision: string } | undefined;
  if (!row) return null;
  return row.decision as 'allow' | 'block';
}

export function setSitePermission(origin: string, permission: string, decision: 'allow' | 'block'): void {
  const db = getDb();
  db.prepare(`
    INSERT INTO site_permissions(origin, permission, decision, updated_at)
    VALUES(?, ?, ?, ?)
    ON CONFLICT(origin, permission) DO UPDATE SET decision = excluded.decision, updated_at = excluded.updated_at
  `).run(origin, permission, decision, new Date().toISOString());
}

export function getAllSitePermissions(): SitePermissionRow[] {
  const db = getDb();
  return db.prepare('SELECT * FROM site_permissions ORDER BY origin ASC, permission ASC').all() as SitePermissionRow[];
}

export function revokeSitePermission(origin: string, permission: string): void {
  const db = getDb();
  db.prepare('DELETE FROM site_permissions WHERE origin = ? AND permission = ?').run(origin, permission);
}

export function clearAllSitePermissions(): void {
  const db = getDb();
  db.prepare('DELETE FROM site_permissions').run();
}

// ── Downloads ────────────────────────────────────────────────────────────────

export function recordDownload(download: Omit<Download, 'created_at' | 'completed_at'>): void {
  const db = getDb();
  db.prepare(`
    INSERT OR REPLACE INTO downloads(id, url, filename, save_path, mime_type, total_bytes, received_bytes, state, created_at)
    VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)
  `).run(download.id, download.url, download.filename, download.save_path, download.mime_type, download.total_bytes, download.received_bytes, download.state, new Date().toISOString());
}

export function updateDownload(id: string, updates: Partial<Pick<Download, 'save_path' | 'received_bytes' | 'total_bytes' | 'state' | 'completed_at'>>): void {
  const db = getDb();
  const fields: string[] = [];
  const values: unknown[] = [];
  const keys = ['save_path', 'received_bytes', 'total_bytes', 'state', 'completed_at'] as const;
  for (const k of keys) {
    if (k in updates) { fields.push(`${k} = ?`); values.push(updates[k] ?? null); }
  }
  if (!fields.length) return;
  values.push(id);
  db.prepare(`UPDATE downloads SET ${fields.join(', ')} WHERE id = ?`).run(...values);
}

export function getRecentDownloads(limit = 50): Download[] {
  const db = getDb();
  return db.prepare('SELECT * FROM downloads ORDER BY created_at DESC LIMIT ?').all(limit) as Download[];
}

export function searchDownloads(query: string, limit = 50): Download[] {
  const db = getDb();
  const like = `%${query.replace(/[%_]/g, c => `\\${c}`)}%`;
  return db.prepare(`
    SELECT * FROM downloads
    WHERE (filename LIKE ? ESCAPE '\\' OR url LIKE ? ESCAPE '\\')
    ORDER BY created_at DESC LIMIT ?
  `).all(like, like, limit) as Download[];
}

export function deleteDownload(id: string): void {
  const db = getDb();
  db.prepare('DELETE FROM downloads WHERE id = ?').run(id);
}

export function clearAllDownloads(): void {
  const db = getDb();
  db.prepare("DELETE FROM downloads WHERE state IN ('completed', 'interrupted', 'cancelled')").run();
}

// ── Saved passwords ──────────────────────────────────────────────────────────

export function savePassword(origin: string, username: string, passwordEnc: string): SavedPassword {
  const db = getDb();
  const now = new Date().toISOString();
  const existing = db.prepare(
    'SELECT * FROM saved_passwords WHERE origin = ? AND username = ?',
  ).get(origin, username) as SavedPassword | undefined;

  if (existing) {
    db.prepare(
      'UPDATE saved_passwords SET password_enc = ?, updated_at = ? WHERE id = ?',
    ).run(passwordEnc, now, existing.id);
    return db.prepare('SELECT * FROM saved_passwords WHERE id = ?').get(existing.id) as SavedPassword;
  }

  const id = generateId();
  db.prepare(`
    INSERT INTO saved_passwords(id, origin, username, password_enc, created_at, updated_at)
    VALUES(?, ?, ?, ?, ?, ?)
  `).run(id, origin, username, passwordEnc, now, now);
  return db.prepare('SELECT * FROM saved_passwords WHERE id = ?').get(id) as SavedPassword;
}

export function getPasswordsForOrigin(origin: string): SavedPassword[] {
  const db = getDb();
  return db.prepare(
    'SELECT * FROM saved_passwords WHERE origin = ? ORDER BY updated_at DESC',
  ).all(origin) as SavedPassword[];
}

export function getAllSavedPasswords(): SavedPassword[] {
  const db = getDb();
  return db.prepare(
    'SELECT * FROM saved_passwords ORDER BY origin ASC, updated_at DESC',
  ).all() as SavedPassword[];
}

export function deletePassword(id: string): boolean {
  const db = getDb();
  const result = db.prepare('DELETE FROM saved_passwords WHERE id = ?').run(id);
  return (result.changes ?? 0) > 0;
}

export function deleteAllPasswords(): void {
  const db = getDb();
  db.prepare('DELETE FROM saved_passwords').run();
}
