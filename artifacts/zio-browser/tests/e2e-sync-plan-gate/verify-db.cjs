/**
 * Post-run raw-DB assertions for the sync plan-gate e2e.
 * Runs AFTER the Electron app has closed and the NODE-ABI better-sqlite3
 * binary has been restored. Verifies the plan-gate/rejected-notice state was
 * persisted as preferences (SYNC_PLAN_GATE / SYNC_REJECTED_NOTICE keys) and
 * that the sync queue fully drained.
 */
'use strict';
const path = require('path');
const Database = require('better-sqlite3');

const APP_DIR = path.resolve(__dirname, '../..');
const { PREFERENCE_KEYS } = require(path.join(APP_DIR, 'dist/main/shared/db-schema.js'));

const userData = process.env.ZIO_USER_DATA;
const db = new Database(path.join(userData, 'zio-browser.db'), { readonly: true });
const pref = (k) => {
  const row = db.prepare('SELECT value FROM preferences WHERE key = ?').get(k);
  return row ? row.value : null;
};

let failures = 0;
const ok = (cond, label) => {
  console.log(`${cond ? '  ✓' : '  ✗ FAIL:'} ${label}`);
  if (!cond) failures++;
};

const gateRaw = pref(PREFERENCE_KEYS.SYNC_PLAN_GATE);
ok(gateRaw !== null, 'SYNC_PLAN_GATE preference row was persisted by the client');
const gate = gateRaw ? JSON.parse(gateRaw) : null;
ok(gate && gate.blocked === false, 'gate ends unblocked after the upgrade flush (blocked=false persisted)');

const rejectedRaw = pref(PREFERENCE_KEYS.SYNC_REJECTED_NOTICE);
ok(rejectedRaw !== null, 'SYNC_REJECTED_NOTICE preference row was persisted by the client');
ok(!rejectedRaw || rejectedRaw === '', 'rejected notice ends cleared after the final flush');

const queued = db.prepare('SELECT COUNT(*) AS c FROM sync_queue').get().c;
ok(queued === 0, `sync queue fully drained (found ${queued})`);

db.close();
process.exit(failures > 0 ? 1 : 0);
