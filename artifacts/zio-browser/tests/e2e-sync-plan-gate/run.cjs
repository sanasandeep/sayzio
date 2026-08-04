/**
 * REAL-API e2e for the Zio Browser sync plan gate (Task: confirm the sync
 * upgrade prompt appears end-to-end against the real 402 gate).
 *
 * Launches the REAL built Electron app under Xvfb via Playwright's _electron
 * driver against a REAL Laravel server (booted by run.sh on a throwaway local
 * Postgres). The Zio SQLite DB is pre-seeded (seed-zio-db.cjs) with a signed-in
 * token, base URL, device id, and ONE queued bookmarks push of 3 new items.
 *
 * Phases (server plan switched via psql between them):
 *  A) plan browser_sync=false → real 402 plan_upgrade_required from
 *     /api/v1/browser/devices/{id}/bookmarks → client persists the gate and
 *     Settings → Sync shows "Sync is paused" + the upgrade hint & button.
 *  B) plan browser_sync=true, max_browser_sync_items=2 → "Retry sync now"
 *     pushes for real; server accepts 2 / rejects 1 → the
 *     "1 item not synced — over your plan's limit (limit: 2)" notice shows.
 *  C) plan browser_sync=true, no cap → the remaining queued item flushes
 *     AUTOMATICALLY (background retry loop, no UI action) → queue empty,
 *     "All changes are synced.", and the server holds all 3 bookmark rows.
 *
 * Requires env: ZIO_USER_DATA, LARAVEL_BASE, SEED_USER_ID,
 * PLAN_CAPPED_ID, PLAN_OPEN_ID, and PG* vars for psql plan switches.
 */
'use strict';

// Under the parallel validation battery Playwright's Electron launch can fail
// twice: the awaited launch promise rejects AND a duplicate rejection escapes
// from an internal helper, which crashes Node before the retry loop's catch
// runs. Log-and-continue so the retry loop below owns the failure.
process.on('unhandledRejection', (err) => {
  try { log(`unhandledRejection (ignored, retry loop owns launch failures): ${String((err && err.message) || err).split('\n')[0]}`); }
  catch (_) { console.error('unhandledRejection:', err); }
});

const path = require('path');
const fs = require('fs');
const { execFileSync } = require('child_process');
const { _electron } = require('/home/runner/workspace/node_modules/.pnpm/playwright@1.59.1/node_modules/playwright');

const APP_DIR = path.resolve(__dirname, '../..');
const MAIN = path.join(APP_DIR, 'dist/main/main/index.js');
const LOG_FILE = process.env.ZIO_E2E_LOG || '/tmp/zio-sync-plan-gate-e2e.log';

fs.writeFileSync(LOG_FILE, `start ${new Date().toISOString()}\n`);
function log(line) {
  console.log(line);
  try { fs.appendFileSync(LOG_FILE, line + '\n'); } catch { /* ignore */ }
}

const watchdog = setTimeout(() => {
  log('WATCHDOG: run exceeded 600s — force exiting');
  process.exit(3);
}, 600000);
watchdog.unref();

let failures = 0;
function ok(cond, label) {
  if (cond) log(`  ✓ ${label}`);
  else { failures++; log(`  ✗ FAIL: ${label}`); }
}

async function waitFor(fn, label, timeout = 30000, interval = 400) {
  const start = Date.now();
  let last;
  while (Date.now() - start < timeout) {
    try {
      last = await fn();
      if (last) return last;
    } catch { /* retry */ }
    await new Promise(r => setTimeout(r, interval));
  }
  throw new Error(`Timed out waiting for: ${label} (last=${JSON.stringify(last)})`);
}

// Spawning psql can hit transient fork pressure (EAGAIN) under the parallel
// validation battery — retry a few times before giving up.
function psql(args) {
  for (let attempt = 1; ; attempt++) {
    try {
      return execFileSync('psql', args, { encoding: 'utf8' });
    } catch (e) {
      if (attempt >= 5) throw e;
      log(`psql attempt ${attempt} failed (${String(e.message || e).split('\n')[0]}); retrying in ${attempt * 5}s…`);
      // Synchronous sleep with no extra process (we're already under fork pressure).
      Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, attempt * 5000);
    }
  }
}

// Switch the fixture user's plan on the REAL Laravel DB (throwaway local PG).
function setUserPlan(planId) {
  const userId = Number(process.env.SEED_USER_ID);
  psql([
    '-h', process.env.PGHOST, '-p', process.env.PGPORT,
    '-U', process.env.PGUSER, '-d', process.env.PGDATABASE,
    '-v', 'ON_ERROR_STOP=1',
    '-c', `UPDATE users SET plan_id = ${Number(planId)} WHERE id = ${userId};`,
  ]);
  log(`server: switched user ${userId} to plan ${planId}`);
}

function countServerBookmarks() {
  const userId = Number(process.env.SEED_USER_ID);
  const out = psql([
    '-h', process.env.PGHOST, '-p', process.env.PGPORT,
    '-U', process.env.PGUSER, '-d', process.env.PGDATABASE,
    '-t', '-A', '-c',
    `SELECT COUNT(*) FROM browser_bookmarks WHERE user_id = ${userId} AND deleted = false;`,
  ]);
  return Number(out.trim());
}

// Find the app window (not the splash, whose URL is a data: URL) and get past
// the first-run mode picker if it shows (same approach as e2e-toolbar).
async function appPage(app) {
  return waitFor(async () => {
    for (const p of app.windows()) {
      const url = p.url();
      if (url.startsWith('data:')) continue;
      if (!(url.includes('index.html') || url.startsWith('http'))) continue;
      const hasBar = await p.locator('button[title="Settings"]').count().catch(() => 0);
      if (hasBar > 0) return p;
      const picker = await p.locator('text=Choose how you want to use this window').count().catch(() => 0);
      if (picker > 0) {
        log('mode picker shown — selecting Browser mode');
        // Exact-match the "Browser" card label, then confirm (same as e2e-toolbar).
        await p.locator('button div:text-is("Browser")').first().click().catch(() => {});
        await p.locator('button:has-text("Open in")').first().click().catch(() => {});
      }
    }
    // Diagnostics: window URLs help debug boots starved by concurrent runs.
    log('  …windows: ' + app.windows().map(p => p.url().slice(0, 60)).join(' | '));
    return null;
  }, 'app window with ChromeBar', 180000, 1000);
}

async function planStatus(page) {
  return page.evaluate(async () => {
    const gate = await window.zio.sync.planStatus();
    const pending = await window.zio.sync.pendingCount();
    return { gate, pending };
  });
}

(async () => {
  const userData = process.env.ZIO_USER_DATA;
  log(`launching Electron (userData=${userData}, api=${process.env.LARAVEL_BASE})`);
  // Under the parallel validation battery the cgroup pid/thread cap can starve
  // Chromium's boot (pthread_create EAGAIN → "Process failed to launch!").
  // Retry a few times with backoff — the pressure is transient.
  let app;
  for (let attempt = 1; ; attempt++) {
    try {
      app = await _electron.launch({
        args: [MAIN, `--user-data-dir=${userData}`, '--no-sandbox', '--disable-gpu'],
        cwd: APP_DIR,
        env: { ...process.env, NODE_ENV: 'production' },
      });
      break;
    } catch (e) {
      if (attempt >= 4) throw e;
      log(`electron launch attempt ${attempt} failed (${String(e.message || e).split('\n')[0]}); retrying in ${attempt * 15}s…`);
      await new Promise(r => setTimeout(r, attempt * 15000));
    }
  }

  try {
    const page = await appPage(app);
    log('app window ready');

    // ── Phase A: real 402 plan gate ─────────────────────────────────────────
    log('Phase A: waiting for the background runner to hit the real 402 gate…');
    const gated = await waitFor(async () => {
      const s = await planStatus(page);
      return s.gate && s.gate.gate && s.gate.gate.blocked === true ? s : null;
    }, 'plan gate blocked=true', 90000);
    ok(gated.gate.gate.feature === 'browser_sync', `persisted gate feature is browser_sync (got ${gated.gate.gate.feature})`);
    ok(gated.pending >= 1, `item stays queued while gated (pending=${gated.pending})`);
    ok(countServerBookmarks() === 0, 'server stored no bookmarks while gated');

    // Settings → Sync UI shows the upgrade hint.
    await page.locator('button[title="Settings"]').click();
    await page.locator('input[placeholder="Search settings"]').waitFor({ timeout: 10000 });
    // Exact-match the settings nav item: the toolbar's "Sync paused — upgrade
    // to resume" pill also substring-matches "Sync" and precedes it in the DOM.
    await page.locator('button:has(span:text-is("Sync"))').first().click();
    await page.locator('text=Sync is paused').waitFor({ timeout: 15000 });
    ok(true, 'Settings → Sync shows "Sync is paused"');
    ok(await page.locator("text=Your current plan doesn't include browser sync").count() > 0,
      'upgrade hint copy is visible');
    ok(await page.locator('button:has-text("See upgrade options")').count() > 0,
      '"See upgrade options" button is visible');

    // ── Phase B: over-cap rejection notice ─────────────────────────────────
    setUserPlan(process.env.PLAN_CAPPED_ID);
    log('Phase B: retrying against the capped plan (cap=2, pushing 3)…');
    await page.locator('button:has-text("Retry sync now")').click();
    const capped = await waitFor(async () => {
      const s = await planStatus(page);
      return s.gate && s.gate.rejected && s.gate.rejected.count >= 1 ? s : null;
    }, 'rejected notice recorded', 45000);
    ok(capped.gate.rejected.count === 1, `rejected count is 1 (got ${capped.gate.rejected.count})`);
    ok(capped.gate.rejected.limit === 2, `rejected limit is 2 (got ${capped.gate.rejected.limit})`);
    ok(capped.gate.gate.blocked === false, 'plan gate cleared by the successful (partial) push');
    await page.locator("text=1 item not synced — over your plan's limit (limit: 2)").waitFor({ timeout: 15000 });
    ok(true, 'over-cap notice text is visible in Settings → Sync');
    ok(countServerBookmarks() === 2, `server stored exactly the 2 accepted bookmarks (got ${countServerBookmarks()})`);

    // ── Phase C: upgrade → queued item flushes automatically ───────────────
    setUserPlan(process.env.PLAN_OPEN_ID);
    log('Phase C: waiting for the AUTOMATIC background flush (no UI action)…');
    const drained = await waitFor(async () => {
      const s = await planStatus(page);
      return s.pending === 0 ? s : null;
    }, 'queue drained automatically', 200000, 1000);
    // The rejected-notice pref is cleared a moment AFTER the queue drains —
    // poll instead of asserting the drain-time snapshot (race under load).
    await waitFor(async () => {
      const s = await planStatus(page);
      return s.gate.rejected === null || s.gate.rejected === undefined ? s : null;
    }, 'rejected notice cleared', 30000, 500);
    ok(true, 'rejected notice cleared after the clean flush');
    ok(drained.gate.gate.blocked === false, 'gate remains unblocked');
    ok(countServerBookmarks() === 3, `all 3 bookmarks landed on the server (got ${countServerBookmarks()})`);
    await page.locator('text=All changes are synced.').waitFor({ timeout: 15000 });
    ok(true, 'Settings → Sync shows "All changes are synced."');
  } catch (err) {
    failures++;
    log(`  ✗ FAIL: ${err && err.message ? err.message : err}`);
  } finally {
    await app.close().catch(() => {});
  }

  if (failures > 0) {
    log(`RESULT: FAILED (${failures} failures)`);
    process.exit(1);
  }
  log('RESULT: PASS — sync plan gate verified end-to-end against the real 402 gate');
  process.exit(0);
})().catch(err => {
  log(`fatal: ${err && err.stack ? err.stack : err}`);
  process.exit(1);
});
