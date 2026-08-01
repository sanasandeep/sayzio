/**
 * Session-restore check for split-tab layout survival across a full app
 * restart.
 *
 * Launches the REAL built Electron app twice against the SAME user-data dir:
 *
 *  Run 1: navigate the active tab to a fixture page, enter the
 *         Website+Website split, point the second pane at another fixture
 *         page, drag the persisted split ratio away from the default, then
 *         close the app cleanly (fires the 'close' session snapshot +
 *         before-quit clean-exit stamp).
 *
 *  Run 2: relaunch with the same user-data dir and assert the DOCUMENTED
 *         restore behavior: the session snapshot persists each tab's layout
 *         (TabManager.getSessionSnapshot stores urls + active index + a
 *         parallel `layouts` array of mode/splitRatio/secondUrl), so the
 *         split tab comes back fully rebuilt:
 *           - active tab URL is the primary pane's fixture page
 *           - tab mode is 'browser+browser' again
 *           - split ratio restored at the saved 0.3 (not the 0.5 default)
 *           - split divider present, second-pane window back on page B
 *           - the second pane restored INSIDE the split, not as its own tab
 *
 * This harness pins the split-layout persistence behavior.
 *
 * Run:  xvfb-run -a node artifacts/zio-browser/tests/e2e-split-view/run-restore.cjs
 */
'use strict';

const path = require('path');
const os = require('os');
const fs = require('fs');
const http = require('http');
const { _electron } = require('/home/runner/workspace/node_modules/.pnpm/playwright@1.59.1/node_modules/playwright');

const APP_DIR = path.resolve(__dirname, '../..');
const MAIN = path.join(APP_DIR, 'dist/main/main/index.js');
const LOG_FILE = process.env.ZIO_E2E_LOG || '/tmp/zio-split-restore-e2e.log';

fs.writeFileSync(LOG_FILE, `start ${new Date().toISOString()}\n`);
function log(line) {
  console.log(line);
  try { fs.appendFileSync(LOG_FILE, line + '\n'); } catch { /* ignore */ }
}

// Global watchdog — never let a hung Electron keep the run alive forever.
const watchdog = setTimeout(() => {
  log('WATCHDOG: run exceeded 300s — force exiting');
  process.exit(3);
}, 300000);
watchdog.unref();

let failures = 0;
function ok(cond, label) {
  if (cond) {
    log(`  ✓ ${label}`);
  } else {
    failures++;
    log(`  ✗ FAIL: ${label}`);
  }
}

async function waitFor(fn, label, timeout = 15000, interval = 250) {
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

// Local fixture server — pane URLs must survive across BOTH launches, so the
// server lives for the whole harness and its port is baked into the snapshot.
function startFixtureServer() {
  return new Promise((resolve) => {
    const server = http.createServer((req, res) => {
      const name = (req.url || '/').replace(/[^a-z]/g, '') || 'root';
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`<!doctype html><title>Zio restore ${name}</title><h1 id="marker-${name}">pane ${name}</h1>`);
    });
    server.listen(0, '127.0.0.1', () => {
      resolve({ server, base: `http://127.0.0.1:${server.address().port}` });
    });
  });
}

// Find the chrome (renderer) window — not the splash and not native pane pages.
async function appPage(app) {
  return waitFor(async () => {
    for (const p of app.windows()) {
      const url = p.url();
      if (url.startsWith('data:')) continue;
      if (!url.includes('index.html')) continue;
      const hasBar = await p.locator('button[title="More tools"]').count().catch(() => 0);
      if (hasBar > 0) return p;
      // First-run mode picker: choose Browser mode to reach the ChromeBar.
      const picker = await p.locator('text=Choose how you want to use this window').count().catch(() => 0);
      if (picker > 0) {
        log('mode picker shown — selecting Browser mode');
        await p.locator('button div:text-is("Browser")').first().click().catch(e => log('picker card click err: ' + e.message));
        await p.locator('button:has-text("Open in")').first().click().catch(e => log('picker open click err: ' + e.message));
      }
    }
    return null;
  }, 'chrome window with ChromeBar', 30000);
}

async function launchApp(userData) {
  const app = await _electron.launch({
    args: [MAIN, `--user-data-dir=${userData}`, '--no-sandbox', '--disable-gpu'],
    cwd: APP_DIR,
    env: { ...process.env, NODE_ENV: 'production', ELECTRON_DISABLE_SANDBOX: '1' },
    timeout: 60000,
  });
  app.process().stderr?.on('data', (d) => log('[main-err] ' + String(d).slice(0, 300)));
  let page = await appPage(app);
  // The mode pick can recreate the window right after detection — settle and
  // re-acquire if our handle went stale.
  await new Promise(r => setTimeout(r, 1500));
  if (page.isClosed() || (await page.locator('button[title="More tools"]').count().catch(() => 0)) === 0) {
    log('page handle stale after mode pick — re-acquiring');
    page = await appPage(app);
  }
  page.on('console', (m) => { if (m.type() === 'error') log('[renderer] ' + m.text().slice(0, 200)); });
  return { app, page };
}

async function tabState(page, tabId) {
  return page.evaluate((id) => window.zio.tabs.getState(id), tabId);
}

(async () => {
  const { server, base } = await startFixtureServer();
  log(`fixture server at ${base}`);

  // Shared user data dir — the whole point is persistence across launches.
  const userData = fs.mkdtempSync(path.join(os.tmpdir(), 'zio-e2e-restore-'));

  // ════════════════════ Run 1: build the split, close cleanly ═══════════════
  console.log('\n══ Run 1: set up a split tab and close the app ══');
  {
    const { app, page } = await launchApp(userData);
    try {
      const tabId = await waitFor(() => page.evaluate(() => window.zio.tabs.getActive()), 'active tab id', 15000);
      await page.evaluate(({ id, url }) => window.zio.tabs.navigate(id, url), { id: tabId, url: `${base}/a` });
      await waitFor(async () => (await tabState(page, tabId))?.url?.includes('/a'), 'primary pane on page A', 15000);

      await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser+browser'), tabId);
      await waitFor(async () => (await tabState(page, tabId))?.mode === 'browser+browser', 'tab in browser+browser mode', 10000);
      ok(true, 'tab entered the Website + Website split');

      // Point the second pane at an identifiable fixture page and wait for the
      // pane window to exist so its URL would be snapshot-able if the product
      // ever persisted it.
      await page.evaluate(({ id, url }) => window.zio.tabs.navigatePane(id, 'second', url), { id: tabId, url: `${base}/b` });
      await waitFor(() => app.windows().some(p => p.url().includes('/b')), 'second pane on page B', 20000);
      ok(true, 'second pane loaded fixture page B');

      // Move the ratio away from the 0.5 default so run 2 can tell "restored"
      // apart from "reset to default".
      await page.evaluate(({ id, ratio }) => window.zio.tabs.setSplitRatio(id, ratio), { id: tabId, ratio: 0.3 });
      const stSplit = await waitFor(async () => {
        const s = await tabState(page, tabId);
        return s && Math.abs(s.splitRatio - 0.3) < 0.01 ? s : null;
      }, 'split ratio persisted at 0.3', 8000);
      ok(Math.abs(stSplit.splitRatio - 0.3) < 0.01, `split ratio set to ${stSplit.splitRatio.toFixed(2)} before close`);
      ok(await page.locator('div[title="Drag to resize split"]').count() === 1, 'split divider rendered before close');

      // Second tab in a NON-browser split mode (Website + My Files): its
      // mode + ratio must be persisted too (no second-pane URL — My Files
      // is an app surface recreated from the mode alone).
      const filesTabId = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/c`);
      await waitFor(async () => (await tabState(page, filesTabId))?.url?.includes('/c'), 'files-split tab on page C', 15000);
      await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser+files'), filesTabId);
      await waitFor(async () => (await tabState(page, filesTabId))?.mode === 'browser+files', "tab in 'browser+files' mode", 10000);
      await page.evaluate(({ id, ratio }) => window.zio.tabs.setSplitRatio(id, ratio), { id: filesTabId, ratio: 0.7 });
      await waitFor(async () => {
        const s = await tabState(page, filesTabId);
        return s && Math.abs(s.splitRatio - 0.7) < 0.01;
      }, 'files-split ratio persisted at 0.7', 8000);
      ok(true, "second tab entered the Website + My Files split at ratio 0.7");

      // Re-activate the ORIGINAL split tab so the snapshot's active index
      // points at it — run 2 asserts the ACTIVE restored tab shows /a.
      await page.evaluate((id) => window.zio.tabs.activate(id), tabId);
      await waitFor(() => page.evaluate(() => window.zio.tabs.getActive()).then(a => a === tabId), 'original split tab re-activated', 8000);

      // Settle so the mode/ratio state has flushed, then close CLEANLY —
      // BrowserWindow 'close' persists the session snapshot and before-quit
      // stamps CLEAN_EXIT, so run 2 restores silently (no crash dialog).
      await new Promise(r => setTimeout(r, 500));
    } finally {
      await app.close().catch(() => {});
    }
    log('run 1 closed');
  }

  // ════════════════════ Run 2: relaunch and assert restore ══════════════════
  console.log('\n══ Run 2: relaunch with the same user-data dir ══');
  {
    const { app, page } = await launchApp(userData);
    try {
      // Session restore recreates the tab from its saved PRIMARY URL and
      // activates it.
      const tabId = await waitFor(async () => {
        const id = await page.evaluate(() => window.zio.tabs.getActive());
        if (!id) return null;
        const st = await tabState(page, id);
        return st?.url?.includes('/a') ? id : null;
      }, 'restored active tab on page A', 30000);
      ok(true, 'session restore recreated the tab and it shows the primary pane URL (/a)');

      // Documented behavior: mode/ratio/second-pane URL ARE persisted — the
      // split tab comes back fully rebuilt.
      const st = await waitFor(async () => {
        const s = await tabState(page, tabId);
        return s?.mode === 'browser+browser' ? s : null;
      }, "restored tab back in 'browser+browser' mode", 15000);
      ok(st?.mode === 'browser+browser', `restored tab re-entered the Website + Website split (mode='${st?.mode}')`);
      ok(Math.abs((st?.splitRatio ?? 0) - 0.3) < 0.01, `split ratio restored at the saved 0.3 (got ${st?.splitRatio})`);

      // Split chrome is back: the divider renders again.
      await waitFor(async () => (await page.locator('div[title="Drag to resize split"]').count()) === 1, 'split divider rendered after restore', 10000);
      ok(true, 'split divider rendered after restore');

      // The second pane is back on page B, inside the split — not as its
      // own separate tab.
      await waitFor(() => app.windows().some(p => p.url().includes('/b')), 'second pane window restored on page B', 20000);
      ok(true, 'second-pane window restored showing /b');
      const allTabs = await page.evaluate(async () => {
        const order = await window.zio.tabs.getOrder();
        const urls = [];
        for (const id of order) {
          const s = await window.zio.tabs.getState(id);
          urls.push(String(s?.url ?? ''));
        }
        return urls;
      });
      ok(Array.isArray(allTabs) && !allTabs.some(u => u.includes('/b')), `second-pane URL restored inside the split, not as its own tab (tabs: ${JSON.stringify(allTabs)})`);
      ok(allTabs.filter(u => u.includes('/a')).length === 1, 'exactly one restored tab for the primary URL');

      // The restored split is fully functional: it can leave and re-enter.
      await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser'), tabId);
      await waitFor(async () => (await tabState(page, tabId))?.mode === 'browser', 'restored tab leaves split', 10000);
      await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser+browser'), tabId);
      await waitFor(async () => (await tabState(page, tabId))?.mode === 'browser+browser', 'restored tab re-enters split', 10000);
      ok(true, 'restored tab can leave and re-enter the Website + Website split');

      // The NON-browser split (Website + My Files) is restored too: the tab
      // on page C comes back in 'browser+files' mode at the saved 0.7 ratio.
      const filesTab = await waitFor(async () => {
        const order = await page.evaluate(() => window.zio.tabs.getOrder());
        for (const id of order) {
          const s = await tabState(page, id);
          if (s?.url?.includes('/c')) return { id, state: s };
        }
        return null;
      }, 'restored Website + My Files tab on page C', 20000);
      const fState = await waitFor(async () => {
        const s = await tabState(page, filesTab.id);
        return s?.mode === 'browser+files' ? s : null;
      }, "restored tab back in 'browser+files' mode", 15000);
      ok(fState?.mode === 'browser+files', `restored tab re-entered the Website + My Files split (mode='${fState?.mode}')`);
      ok(Math.abs((fState?.splitRatio ?? 0) - 0.7) < 0.01, `files-split ratio restored at the saved 0.7 (got ${fState?.splitRatio})`);
    } finally {
      await app.close().catch(() => {});
    }
    log('run 2 closed');
  }

  server.close();
  try { fs.rmSync(userData, { recursive: true, force: true }); } catch { /* ignore */ }

  if (failures > 0) {
    console.error(`\n${failures} assertion(s) FAILED`);
    process.exit(1);
  }
  console.log('\nAll split-tab session-restore checks PASSED');
  process.exit(0);
})().catch((err) => {
  console.error('\nE2E run errored:', err);
  process.exit(1);
});
