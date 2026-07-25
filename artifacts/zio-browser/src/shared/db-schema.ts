/**
 * SQLite schema definitions for the Zio Browser local-first store.
 * These are the CREATE TABLE statements used by src/main/db.ts.
 *
 * Keeping schema in a shared module lets tests verify the schema without
 * importing better-sqlite3 (which requires native bindings).
 */

export const SCHEMA_VERSION = 9;

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

CREATE TABLE IF NOT EXISTS profiles (
  id            TEXT PRIMARY KEY NOT NULL,
  workspace_id  TEXT,
  name          TEXT NOT NULL,
  created_at    TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS history (
  id             TEXT PRIMARY KEY NOT NULL,
  profile_id     TEXT NOT NULL DEFAULT 'default',
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

CREATE INDEX IF NOT EXISTS history_profile_url ON history(profile_id, normalized_url);
CREATE INDEX IF NOT EXISTS history_profile_visited ON history(profile_id, last_visited DESC);

CREATE TABLE IF NOT EXISTS bookmarks (
  id             TEXT PRIMARY KEY NOT NULL,
  profile_id     TEXT NOT NULL DEFAULT 'default',
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

CREATE INDEX IF NOT EXISTS bookmarks_profile_url ON bookmarks(profile_id, normalized_url);
CREATE INDEX IF NOT EXISTS bookmarks_profile_folder ON bookmarks(profile_id, folder);

CREATE TABLE IF NOT EXISTS collections (
  id          TEXT PRIMARY KEY NOT NULL,
  profile_id  TEXT NOT NULL DEFAULT 'default',
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
  created_at      TEXT NOT NULL,
  profile_id      TEXT
);

CREATE INDEX IF NOT EXISTS sync_queue_due ON sync_queue(next_attempt_at);

CREATE TABLE IF NOT EXISTS sayzio_links (
  id         INTEGER PRIMARY KEY NOT NULL,
  type       TEXT NOT NULL,
  alias      TEXT NOT NULL,
  title      TEXT,
  long_url   TEXT,
  short_url  TEXT NOT NULL,
  cached_at  TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS saved_passwords (
  id           TEXT PRIMARY KEY NOT NULL,
  origin       TEXT NOT NULL,
  username     TEXT NOT NULL,
  password_enc TEXT NOT NULL,
  created_at   TEXT NOT NULL,
  updated_at   TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS saved_passwords_origin ON saved_passwords(origin);

CREATE TABLE IF NOT EXISTS site_permissions (
  origin     TEXT NOT NULL,
  permission TEXT NOT NULL,
  decision   TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  PRIMARY KEY (origin, permission)
);

CREATE INDEX IF NOT EXISTS site_permissions_origin ON site_permissions(origin);

CREATE TABLE IF NOT EXISTS reading_list (
  id          TEXT PRIMARY KEY NOT NULL,
  url         TEXT NOT NULL,
  normalized_url TEXT NOT NULL,
  title       TEXT NOT NULL,
  favicon_url TEXT,
  is_read     INTEGER NOT NULL DEFAULT 0,
  saved_at    TEXT NOT NULL,
  created_at  TEXT NOT NULL,
  updated_at  TEXT NOT NULL,
  deleted     INTEGER NOT NULL DEFAULT 0,
  synced_at   TEXT
);

CREATE INDEX IF NOT EXISTS reading_list_url ON reading_list(normalized_url);
CREATE INDEX IF NOT EXISTS reading_list_is_read ON reading_list(is_read);

CREATE TABLE IF NOT EXISTS sessions (
  id         TEXT PRIMARY KEY NOT NULL,
  name       TEXT NOT NULL,
  snapshot   TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
`;

/**
 * SQL statements run during schema version migrations.
 * Each key is the target version; its SQL brings the DB from version-1 to version.
 */
export const MIGRATION_SQL: Record<number, string> = {
  6: `
    ALTER TABLE history    ADD COLUMN profile_id TEXT NOT NULL DEFAULT 'default';
    ALTER TABLE bookmarks  ADD COLUMN profile_id TEXT NOT NULL DEFAULT 'default';
    ALTER TABLE collections ADD COLUMN profile_id TEXT NOT NULL DEFAULT 'default';
    CREATE TABLE IF NOT EXISTS profiles (
      id            TEXT PRIMARY KEY NOT NULL,
      workspace_id  TEXT,
      name          TEXT NOT NULL,
      created_at    TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS history_profile_url     ON history(profile_id, normalized_url);
    CREATE INDEX IF NOT EXISTS history_profile_visited  ON history(profile_id, last_visited DESC);
    CREATE INDEX IF NOT EXISTS bookmarks_profile_url   ON bookmarks(profile_id, normalized_url);
    CREATE INDEX IF NOT EXISTS bookmarks_profile_folder ON bookmarks(profile_id, folder);
  `,
  7: `
    CREATE TABLE IF NOT EXISTS sayzio_links (
      id         INTEGER PRIMARY KEY NOT NULL,
      type       TEXT NOT NULL,
      alias      TEXT NOT NULL,
      title      TEXT,
      long_url   TEXT,
      short_url  TEXT NOT NULL,
      cached_at  TEXT NOT NULL
    );
  `,
  8: `
    ALTER TABLE sync_queue ADD COLUMN profile_id TEXT;
  `,
  9: `
    CREATE TABLE IF NOT EXISTS sessions (
      id         TEXT PRIMARY KEY NOT NULL,
      name       TEXT NOT NULL,
      snapshot   TEXT NOT NULL,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );
  `,
};

/**
 * Locally cached Sayzio link (row shape of the `sayzio_links` table).
 * Pull-only cache: rows are replaced wholesale on each refresh.
 */
export interface CachedSayzioLink {
  id: number;
  type: string;
  alias: string;
  title: string | null;
  long_url: string | null;
  short_url: string;
  cached_at: string;
}

export const PREFERENCE_KEYS = {
  SEARCH_ENGINE: 'search_engine',
  DEFAULT_ZOOM: 'default_zoom',
  CLOUD_SYNC_ENABLED: 'cloud_sync_enabled',
  CLOUD_SYNC_BOOKMARKS: 'cloud_sync_bookmarks',
  CLOUD_SYNC_COLLECTIONS: 'cloud_sync_collections',
  CLOUD_SYNC_HISTORY: 'cloud_sync_history',
  CLOUD_SYNC_READING_LIST: 'cloud_sync_reading_list',
  SAYZIO_API_BASE_URL: 'sayzio_api_base_url',
  THEME: 'theme',
  DEVICE_ID: 'device_id',
  HISTORY_DAYS_RETENTION: 'history_days_retention',
  NEW_TAB_PAGE: 'new_tab_page',
  DOWNLOAD_PATH: 'download_path',
  DOWNLOAD_ASK: 'download_ask',
  SAVE_PASSWORDS: 'save_passwords',
  WINDOW_MODE: 'window_mode',
  SPLIT_RATIO: 'split_ratio',
  ZIO_PANEL_WIDTH: 'zio_panel_width',
  ZIO_PANEL_DOCKED: 'zio_panel_docked',
  ACTIVE_PROFILE: 'active_profile',
  PINNED_TABS: 'pinned_tabs',
  SESSION_TABS: 'session_tabs',
  TRACKER_BLOCKING_ENABLED: 'tracker_blocking_enabled',
  MUTED_DOMAINS: 'muted_domains',
  MUTE_ALL_TABS: 'mute_all_tabs',
  SPELLCHECK_ENABLED: 'spellcheck_enabled',
  TRANSLATE_TARGET_LANG: 'translate_target_lang',
  EXTENSION_PATHS: 'extension_paths',
  CLEAN_EXIT: 'clean_exit',
  STARTUP_MODE: 'startup_mode',
  DO_NOT_TRACK: 'do_not_track',
  BLOCK_THIRD_PARTY_COOKIES: 'block_third_party_cookies',
  TRACKER_STATS: 'tracker_stats',
  IMPORT_ENABLED: 'import_enabled',
} as const;

export type PreferenceKey = typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS];
