/**
 * SQLite schema definitions for the Zio Browser local-first store.
 * These are the CREATE TABLE statements used by src/main/db.ts.
 *
 * Keeping schema in a shared module lets tests verify the schema without
 * importing better-sqlite3 (which requires native bindings).
 */

export const SCHEMA_VERSION = 6;

export const CREATE_TABLES_SQL = `
PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS schema_version (
  version INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS preferences (
  key   TEXT PRIMARY KEY NOT NULL,
  value TEXT
);

CREATE TABLE IF NOT EXISTS history (
  id             TEXT PRIMARY KEY NOT NULL,
  url            TEXT NOT NULL,
  normalized_url TEXT NOT NULL,
  title          TEXT,
  favicon_url    TEXT,
  visit_count    INTEGER NOT NULL DEFAULT 1,
  last_visited   TEXT NOT NULL,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL,
  deleted        INTEGER NOT NULL DEFAULT 0,
  synced_at      TEXT
);

CREATE INDEX IF NOT EXISTS history_url ON history(normalized_url);
CREATE INDEX IF NOT EXISTS history_last_visited ON history(last_visited DESC);

CREATE TABLE IF NOT EXISTS bookmarks (
  id             TEXT PRIMARY KEY NOT NULL,
  url            TEXT NOT NULL,
  normalized_url TEXT NOT NULL,
  title          TEXT NOT NULL,
  description    TEXT,
  favicon_url    TEXT,
  folder         TEXT,
  created_at     TEXT NOT NULL,
  updated_at     TEXT NOT NULL,
  deleted        INTEGER NOT NULL DEFAULT 0,
  synced_at      TEXT
);

CREATE INDEX IF NOT EXISTS bookmarks_url ON bookmarks(normalized_url);
CREATE INDEX IF NOT EXISTS bookmarks_folder ON bookmarks(folder);

CREATE TABLE IF NOT EXISTS collections (
  id          TEXT PRIMARY KEY NOT NULL,
  name        TEXT NOT NULL,
  description TEXT,
  color       TEXT,
  icon        TEXT,
  created_at  TEXT NOT NULL,
  updated_at  TEXT NOT NULL,
  deleted     INTEGER NOT NULL DEFAULT 0,
  synced_at   TEXT
);

CREATE TABLE IF NOT EXISTS saved_links (
  id             TEXT PRIMARY KEY NOT NULL,
  collection_id  TEXT NOT NULL REFERENCES collections(id),
  url            TEXT NOT NULL,
  normalized_url TEXT NOT NULL,
  title          TEXT NOT NULL,
  description    TEXT,
  ai_summary     TEXT,
  ai_tags        TEXT NOT NULL DEFAULT '[]',
  ai_context     TEXT,
  notes          TEXT,
  favicon_url    TEXT,
  screenshot_url TEXT,
  saved_at       TEXT NOT NULL,
  updated_at     TEXT NOT NULL,
  deleted        INTEGER NOT NULL DEFAULT 0,
  synced_at      TEXT,
  ai_enriched    INTEGER NOT NULL DEFAULT 0,
  ai_coins_used  INTEGER
);

CREATE INDEX IF NOT EXISTS saved_links_collection ON saved_links(collection_id);
CREATE INDEX IF NOT EXISTS saved_links_url ON saved_links(normalized_url);

CREATE TABLE IF NOT EXISTS downloads (
  id           TEXT PRIMARY KEY NOT NULL,
  url          TEXT NOT NULL,
  filename     TEXT NOT NULL,
  save_path    TEXT,
  mime_type    TEXT,
  total_bytes  INTEGER,
  received_bytes INTEGER NOT NULL DEFAULT 0,
  state        TEXT NOT NULL DEFAULT 'pending',
  created_at   TEXT NOT NULL,
  completed_at TEXT
);

CREATE TABLE IF NOT EXISTS browser_devices (
  device_id   TEXT PRIMARY KEY NOT NULL,
  label       TEXT NOT NULL,
  platform    TEXT NOT NULL,
  registered_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS sync_state (
  entity   TEXT PRIMARY KEY NOT NULL,
  last_sync_at TEXT,
  last_error   TEXT
);

CREATE TABLE IF NOT EXISTS sync_queue (
  id              TEXT PRIMARY KEY NOT NULL,
  entity          TEXT NOT NULL,
  payload         TEXT NOT NULL,
  attempts        INTEGER NOT NULL DEFAULT 0,
  next_attempt_at TEXT NOT NULL,
  last_error      TEXT,
  created_at      TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS sync_queue_due ON sync_queue(next_attempt_at);

CREATE TABLE IF NOT EXISTS saved_passwords (
  id           TEXT PRIMARY KEY NOT NULL,
  origin       TEXT NOT NULL,
  username     TEXT NOT NULL,
  password_enc TEXT NOT NULL,
  created_at   TEXT NOT NULL,
  updated_at   TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS saved_passwords_origin ON saved_passwords(origin);
`;

export const PREFERENCE_KEYS = {
  SEARCH_ENGINE: 'search_engine',
  DEFAULT_ZOOM: 'default_zoom',
  CLOUD_SYNC_ENABLED: 'cloud_sync_enabled',
  CLOUD_SYNC_BOOKMARKS: 'cloud_sync_bookmarks',
  CLOUD_SYNC_COLLECTIONS: 'cloud_sync_collections',
  CLOUD_SYNC_HISTORY: 'cloud_sync_history',
  SAYZIO_API_BASE_URL: 'sayzio_api_base_url',
  THEME: 'theme',
  DEVICE_ID: 'device_id',
  HISTORY_DAYS_RETENTION: 'history_days_retention',
  NEW_TAB_PAGE: 'new_tab_page',
  DOWNLOAD_PATH: 'download_path',
  SAVE_PASSWORDS: 'save_passwords',
  WINDOW_MODE: 'window_mode',
  SPLIT_RATIO: 'split_ratio',
  ZIO_PANEL_WIDTH: 'zio_panel_width',
  ZIO_PANEL_DOCKED: 'zio_panel_docked',
} as const;

export type PreferenceKey = typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS];
