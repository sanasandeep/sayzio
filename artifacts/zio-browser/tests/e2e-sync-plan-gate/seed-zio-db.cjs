/**
 * Seed a fresh Zio Browser SQLite DB for the sync plan-gate e2e.
 *
 * Must run with the NODE-ABI better-sqlite3 binary in place (the orchestrator
 * swaps in the Electron-ABI binary only afterwards, for the app itself).
 *
 * Env: ZIO_USER_DATA (dir), LARAVEL_BASE, SANCTUM_TOKEN, ZIO_DEVICE_ID
 */
'use strict';
const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');

const APP_DIR = path.resolve(__dirname, '../..');
const { CREATE_TABLES_SQL, SCHEMA_VERSION, PREFERENCE_KEYS } =
  require(path.join(APP_DIR, 'dist/main/shared/db-schema.js'));

const userData = process.env.ZIO_USER_DATA;
const baseUrl = process.env.LARAVEL_BASE;
const token = process.env.SANCTUM_TOKEN;
const deviceId = process.env.ZIO_DEVICE_ID || 'zio-e2e-device-1';
if (!userData || !baseUrl || !token) {
  console.error('seed-zio-db: missing ZIO_USER_DATA / LARAVEL_BASE / SANCTUM_TOKEN');
  process.exit(1);
}

fs.mkdirSync(userData, { recursive: true });
const db = new Database(path.join(userData, 'zio-browser.db'));
db.exec(CREATE_TABLES_SQL);
db.prepare('INSERT OR REPLACE INTO schema_version(version) VALUES(?)').run(SCHEMA_VERSION);

const setPref = db.prepare('INSERT OR REPLACE INTO preferences(key, value) VALUES(?, ?)');
setPref.run(PREFERENCE_KEYS.SAYZIO_API_BASE_URL, baseUrl);
setPref.run(PREFERENCE_KEYS.DEVICE_ID, deviceId);
// Signed-in state: plain-token fallback (safeStorage unavailable under Xvfb CI).
setPref.run('auth_token_encrypted', `plain:${token}`);
setPref.run('auth_user_json', JSON.stringify({ id: Number(process.env.SEED_USER_ID || 0), name: 'Zio Gate E2E', email: process.env.SEED_EMAIL || null }));
// Skip the first-run window-mode picker if the pref exists in this build.
try { setPref.run('window_mode', 'browser'); } catch { /* best-effort */ }

// One queued bookmarks push with 3 NEW items — drives all three phases:
//  A) plan gated → 402 plan_upgrade_required
//  B) plan capped at 2 → accepted 2, rejected 1 (over-cap notice)
//  C) plan open → remaining item flushes automatically
const now = new Date().toISOString();
const items = ['bk1', 'bk2', 'bk3'].map((id, i) => ({
  local_id: id,
  updated_at: now,
  deleted: false,
  data: { url: `https://example.com/${id}`, title: `E2E bookmark ${i + 1}` },
}));
db.prepare(`INSERT INTO sync_queue (id, entity, payload, attempts, next_attempt_at, last_error, created_at, profile_id)
            VALUES (?, 'bookmarks', ?, 0, ?, NULL, ?, NULL)`)
  .run('q-e2e-gate-1', JSON.stringify(items), now, now);

db.close();
console.log(`seed-zio-db: seeded ${path.join(userData, 'zio-browser.db')}`);
