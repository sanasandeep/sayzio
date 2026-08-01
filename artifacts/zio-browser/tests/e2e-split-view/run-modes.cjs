/**
 * UI-level Electron check for the REMAINING TabModeSwitcher modes — everything
 * other than Website+Website (which run.cjs covers). Sibling harness sharing
 * the same infrastructure (ABI swap via run-validation.sh, xvfb, local fixture
 * server, isolated user-data dir).
 *
 * For each mode reachable from the TabModeSwitcher dropdown it verifies:
 *
 *  - Entering the mode via the REAL dropdown updates the tab state's `mode`
 *  - The expected renderer surface appears (Ask Zio panel input for zio modes,
 *    the "My Files" pane for files modes, the split divider for two-pane
 *    layouts, and NO Website+Website focus frames anywhere outside that mode)
 *  - The dim overlay never applies outside Website+Website (the pane-dim code
 *    path is browser+browser-only)
 *  - Toolbar routing: in every mode that includes a Website pane, the omnibox
 *    submit navigates the PRIMARY website pane (never the companion pane),
 *    and the shared toolbar state reflects that pane's URL
 *  - Exiting back to a single Website tab removes the companion surface and
 *    the toolbar controls the primary pane again with its URL intact
 *
 * Covered modes (in run order):
 *   browser+zio, zio, browser+files, files, dashboard+browser, dashboard,
 *   dashboard+zio, dashboard+files, files+zio
 *
 * Run:  xvfb-run -a node artifacts/zio-browser/tests/e2e-split-view/run-modes.cjs
 */
'use strict';

const path = require('path');
const os = require('os');
const fs = require('fs');
const http = require('http');
const { _electron } = require('/home/runner/workspace/node_modules/.pnpm/playwright@1.59.1/node_modules/playwright');

const APP_DIR = path.resolve(__dirname, '../..');
const MAIN = path.join(APP_DIR, 'dist/main/main/index.js');
const LOG_FILE = process.env.ZIO_E2E_LOG || '/tmp/zio-split-modes-e2e.log';

fs.writeFileSync(LOG_FILE, `start ${new Date().toISOString()}\n`);
function log(line) {
  console.log(line);
  try { fs.appendFileSync(LOG_FILE, line + '\n'); } catch { /* ignore */ }
}

// Global watchdog — never let a hung Electron keep the run alive forever.
const watchdog = setTimeout(() => {
  log('WATCHDOG: run exceeded 420s — force exiting');
  process.exit(3);
}, 420000);
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

// Local fixture server — the website pane navigates to pages we control, so
// the run never depends on external network reachability. (Dashboard panes
// point at the live Sayzio dashboard; we never wait on that load.)
function startFixtureServer() {
  return new Promise((resolve) => {
    const server = http.createServer((req, res) => {
      const name = (req.url || '/').replace(/[^a-z]/g, '') || 'root';
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`<!doctype html><title>Zio modes ${name}</title><h1 id="marker-${name}">pane ${name}</h1>`);
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

// Find the Playwright page for a native pane by its fixture URL.
async function panePage(app, urlPart) {
  return waitFor(async () => {
    for (const p of app.windows()) {
      if (p.url().includes(urlPart)) return p;
    }
    return null;
  }, `pane page for ${urlPart}`, 20000);
}

// The dim overlay is inserted CSS drawing html::before as a fixed rgba(0,0,0,0.25) layer.
async function paneDimStyle(pane) {
  return pane.evaluate(() => {
    const s = getComputedStyle(document.documentElement, '::before');
    return { position: s.position, bg: s.backgroundColor };
  }).catch((e) => ({ evalError: String(e && e.message || e).slice(0, 120) }));
}

function isDimStyle(s) {
  return !!s && s.position === 'fixed' && s.bg === 'rgba(0, 0, 0, 0.25)';
}

// Assert the fixture pane at urlPart is NOT dimmed (re-resolving the window
// handle each poll — WebContentsView Page handles go stale).
async function assertUndimmed(app, urlPart, label) {
  const start = Date.now();
  let last = null;
  while (Date.now() - start < 10000) {
    for (const p of app.windows()) {
      if (!p.url().includes(urlPart)) continue;
      last = await paneDimStyle(p);
      if (!isDimStyle(last)) { ok(true, label); return; }
    }
    await new Promise(r => setTimeout(r, 300));
  }
  ok(false, `${label} (last=${JSON.stringify(last)})`);
}

async function tabState(page, tabId) {
  return page.evaluate((id) => window.zio.tabs.getState(id), tabId);
}

// Assert the pane at urlPart still holds its fixture document (not blank),
// re-resolving the window handle on every poll — held Page handles for
// WebContentsView panes go stale across detach/reattach cycles.
async function assertPaneMarker(app, urlPart, markerId, label) {
  const start = Date.now();
  let last = null;
  while (Date.now() - start < 10000) {
    for (const p of app.windows()) {
      if (!p.url().includes(urlPart)) continue;
      last = await p.evaluate((id) => !!document.getElementById(id), markerId)
        .catch((e) => ({ evalError: String(e && e.message || e).slice(0, 120) }));
      if (last === true) { ok(true, label); return; }
    }
    await new Promise(r => setTimeout(r, 300));
  }
  ok(false, `${label} (last=${JSON.stringify(last)})`);
}

// Enter a tab mode through the REAL TabModeSwitcher dropdown by its label.
async function enterModeViaDropdown(page, label, expectedMode, tabId) {
  await page.locator('button[title^="Tab view:"]').click();
  // Mode entries render their label as an exact-text div inside the item button.
  const item = page.locator(`button:has(> div > div:text-is("${label}"))`).first();
  await waitFor(() => item.count(), `dropdown item "${label}"`, 5000);
  await item.click();
  const st = await waitFor(async () => {
    const s = await tabState(page, tabId);
    return s?.mode === expectedMode ? s : null;
  }, `tab mode ${expectedMode}`, 10000);
  return st;
}

async function exitToBrowser(page, tabId) {
  await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser'), tabId);
  await waitFor(async () => (await tabState(page, tabId))?.mode === 'browser', 'tab back to browser mode', 10000);
}

const ZIO_INPUT = 'textarea[placeholder^="Ask about this page"]';
const FILES_TEXT = 'text=My Files';
const DIVIDER = 'div[title="Drag to resize split"]';
const FOCUS_FRAME = 'div[title="Address bar controls this pane"]';

(async () => {
  const { server, base } = await startFixtureServer();
  log(`fixture server at ${base}`);

  // Isolated user data dir so this run never touches real prefs/DB.
  const userData = fs.mkdtempSync(path.join(os.tmpdir(), 'zio-e2e-modes-'));

  const app = await _electron.launch({
    args: [MAIN, `--user-data-dir=${userData}`, '--no-sandbox', '--disable-gpu'],
    cwd: APP_DIR,
    env: { ...process.env, NODE_ENV: 'production', ELECTRON_DISABLE_SANDBOX: '1' },
    timeout: 60000,
  });

  try {
    log('launched; polling windows…');
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

    // ── Setup: navigate the active tab's primary pane to fixture page A ──
    const tabId = await waitFor(() => page.evaluate(() => window.zio.tabs.getActive()), 'active tab id', 15000);
    await page.evaluate(({ id, url }) => window.zio.tabs.navigate(id, url), { id: tabId, url: `${base}/a` });
    await waitFor(async () => (await tabState(page, tabId))?.url?.includes('/a'), 'primary pane on page A', 15000);
    const omnibox = page.locator('input[placeholder="Search or enter URL"]');

    console.log('\n── Website + Ask Zio ──');

    let st = await enterModeViaDropdown(page, 'Website + Ask Zio', 'browser+zio', tabId);
    ok(st.mode === 'browser+zio', 'TabModeSwitcher enters Website + Ask Zio');
    await waitFor(() => page.locator(ZIO_INPUT).count(), 'Ask Zio panel input', 10000);
    ok(true, 'Ask Zio companion panel renders in the split');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'no Website+Website focus frames in browser+zio');
    ok(await page.locator(DIVIDER).count() === 0, 'no native split divider (Ask Zio pane is renderer-drawn)');
    await assertUndimmed(app, '/a', 'website pane is never dimmed outside Website+Website');
    // Toolbar routing: the omnibox drives the (only) website pane.
    await omnibox.click();
    await omnibox.fill(`${base}/b`);
    await omnibox.press('Enter');
    await waitFor(async () => (await tabState(page, tabId))?.url?.includes('/b'), 'website pane navigated to /b', 15000);
    ok(true, 'omnibox submit navigates the website pane while the Zio split is open');
    await panePage(app, '/b');
    ok(await page.locator(ZIO_INPUT).count() > 0, 'Ask Zio panel survives the website-pane navigation');

    // Tab-switch survival: open a second (plain browser) tab, switch away and
    // back, and assert the split layout is fully restored on return.
    const sideTabZio = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/side`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === sideTabZio,
      'second tab active (browser+zio survival)', 10000);
    await waitFor(async () => (await page.locator(ZIO_INPUT).count()) === 0,
      'Zio panel hidden while the other tab is active', 10000);
    ok(true, 'Ask Zio panel hidden while a plain browser tab is active');
    st = await tabState(page, tabId);
    ok(st?.mode === 'browser+zio', 'inactive tab keeps its browser+zio mode while detached');
    await page.evaluate((id) => window.zio.tabs.activate(id), tabId);
    st = await waitFor(async () => {
      const s = await tabState(page, tabId);
      return s?.mode === 'browser+zio' && (await page.evaluate(() => window.zio.tabs.getActive())) === tabId ? s : null;
    }, 'first tab reactivated in browser+zio', 10000);
    ok(st.mode === 'browser+zio', 'tab restores browser+zio mode after switching back');
    await waitFor(() => page.locator(ZIO_INPUT).count(), 'Ask Zio panel restored after tab switch', 10000);
    ok(true, 'Ask Zio companion panel restored after switching away and back');
    ok(await page.locator(DIVIDER).count() === 0, 'still no native divider after the tab round-trip');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'still no focus frames after the tab round-trip');
    // The reattached website pane must actually paint its document (not blank).
    await assertPaneMarker(app, '/b', 'marker-b', 'website pane content intact after reattach (browser+zio)');
    ok(String(st?.url ?? '').includes('/b'), 'toolbar still shows the website pane URL after the round-trip');
    await assertUndimmed(app, '/b', 'website pane undimmed after the tab round-trip (browser+zio)');
    await page.evaluate((id) => window.zio.tabs.close(id), sideTabZio);

    await exitToBrowser(page, tabId);
    ok(await page.locator(ZIO_INPUT).count() === 0, 'Ask Zio panel removed after leaving the split');
    st = await tabState(page, tabId);
    ok(String(st?.url ?? '').includes('/b'), 'toolbar keeps the website pane URL after exiting browser+zio');

    console.log('\n── Ask Zio (full view) ──');

    st = await enterModeViaDropdown(page, 'Ask Zio', 'zio', tabId);
    ok(st.mode === 'zio', 'TabModeSwitcher enters full-view Ask Zio');
    await waitFor(() => page.locator(ZIO_INPUT).count(), 'full-view Ask Zio input', 10000);
    ok(true, 'Ask Zio fills the tab in zio mode');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'no focus frames in full-view Ask Zio');
    ok(String(st?.url ?? '').includes('/b'), 'toolbar state keeps the website URL while Ask Zio fills the tab');
    await exitToBrowser(page, tabId);
    st = await tabState(page, tabId);
    ok(String(st?.url ?? '').includes('/b'), 'website pane URL intact after leaving full-view Ask Zio');

    console.log('\n── Website + My Files ──');

    st = await enterModeViaDropdown(page, 'Website + My Files', 'browser+files', tabId);
    ok(st.mode === 'browser+files', 'TabModeSwitcher enters Website + My Files');
    await waitFor(() => page.locator(FILES_TEXT).count(), 'My Files pane', 10000);
    ok(true, 'My Files pane renders on the right of the split');
    ok(await page.locator(DIVIDER).count() === 1, 'split divider rendered for browser+files');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'no focus frames in browser+files');
    // Toolbar routing: omnibox drives the website (left/primary) pane.
    await omnibox.click();
    await omnibox.fill(`${base}/c`);
    await omnibox.press('Enter');
    await waitFor(async () => (await tabState(page, tabId))?.url?.includes('/c'), 'website pane navigated to /c', 15000);
    ok(true, 'omnibox submit navigates the website pane while My Files is open');
    await panePage(app, '/c');
    ok(await page.locator(FILES_TEXT).count() > 0, 'My Files pane survives the website-pane navigation');
    await assertUndimmed(app, '/c', 'website pane undimmed in browser+files');

    // Tab-switch survival for a divider-bearing split: switch away and back,
    // then assert mode, Files pane, divider and pane content all restore.
    const sideTabFiles = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/side`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === sideTabFiles,
      'second tab active (browser+files survival)', 10000);
    await waitFor(async () => (await page.locator(FILES_TEXT).count()) === 0,
      'Files pane hidden while the other tab is active', 10000);
    ok(true, 'My Files pane hidden while a plain browser tab is active');
    st = await tabState(page, tabId);
    ok(st?.mode === 'browser+files', 'inactive tab keeps its browser+files mode while detached');
    await page.evaluate((id) => window.zio.tabs.activate(id), tabId);
    st = await waitFor(async () => {
      const s = await tabState(page, tabId);
      return s?.mode === 'browser+files' && (await page.evaluate(() => window.zio.tabs.getActive())) === tabId ? s : null;
    }, 'first tab reactivated in browser+files', 10000);
    ok(st.mode === 'browser+files', 'tab restores browser+files mode after switching back');
    await waitFor(() => page.locator(FILES_TEXT).count(), 'My Files pane restored after tab switch', 10000);
    ok(true, 'My Files pane restored after switching away and back');
    ok(await page.locator(DIVIDER).count() === 1, 'split divider restored after the tab round-trip');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'still no focus frames after the tab round-trip (browser+files)');
    await assertPaneMarker(app, '/c', 'marker-c', 'website pane content intact after reattach (browser+files)');
    ok(String(st?.url ?? '').includes('/c'), 'toolbar still shows the website pane URL after the round-trip (browser+files)');
    await assertUndimmed(app, '/c', 'website pane undimmed after the tab round-trip (browser+files)');
    await page.evaluate((id) => window.zio.tabs.close(id), sideTabFiles);

    await exitToBrowser(page, tabId);
    ok(await page.locator(FILES_TEXT).count() === 0, 'My Files pane removed after leaving the split');

    console.log('\n── My Files (full view) ──');

    st = await enterModeViaDropdown(page, 'My Files', 'files', tabId);
    ok(st.mode === 'files', 'TabModeSwitcher enters full-view My Files');
    await waitFor(() => page.locator(FILES_TEXT).count(), 'full-view My Files pane', 10000);
    ok(true, 'My Files fills the tab in files mode');
    ok(await page.locator(DIVIDER).count() === 0, 'no divider in full-view My Files');
    await exitToBrowser(page, tabId);
    st = await tabState(page, tabId);
    ok(String(st?.url ?? '').includes('/c'), 'website pane URL intact after leaving full-view My Files');

    console.log('\n── Dashboard + Website ──');

    st = await enterModeViaDropdown(page, 'Dashboard + Website', 'dashboard+browser', tabId);
    ok(st.mode === 'dashboard+browser', 'TabModeSwitcher enters Dashboard + Website');
    await waitFor(() => page.locator(DIVIDER).count(), 'divider for dashboard+browser', 10000);
    ok(await page.locator(DIVIDER).count() === 1, 'split divider rendered for dashboard+browser');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'no focus frames in dashboard+browser');
    // Toolbar routing: the omnibox drives the WEBSITE pane, not the dashboard.
    await omnibox.click();
    await omnibox.fill(`${base}/d`);
    await omnibox.press('Enter');
    await waitFor(async () => (await tabState(page, tabId))?.url?.includes('/d'), 'website pane navigated to /d', 15000);
    ok(true, 'omnibox submit navigates the website pane next to the dashboard');
    await panePage(app, '/d');
    await exitToBrowser(page, tabId);
    st = await tabState(page, tabId);
    ok(String(st?.url ?? '').includes('/d'), 'toolbar controls the website pane again after leaving dashboard+browser');

    console.log('\n── Dashboard (full view) ──');

    st = await enterModeViaDropdown(page, 'Dashboard', 'dashboard', tabId);
    ok(st.mode === 'dashboard', 'TabModeSwitcher enters full-view Dashboard');
    ok(await page.locator(FOCUS_FRAME).count() === 0, 'no focus frames in full-view Dashboard');
    await exitToBrowser(page, tabId);
    st = await tabState(page, tabId);
    ok(String(st?.url ?? '').includes('/d'), 'website pane URL intact after leaving full-view Dashboard');

    console.log('\n── Dashboard + Ask Zio ──');

    st = await enterModeViaDropdown(page, 'Dashboard + Ask Zio', 'dashboard+zio', tabId);
    ok(st.mode === 'dashboard+zio', 'TabModeSwitcher enters Dashboard + Ask Zio');
    await waitFor(() => page.locator(ZIO_INPUT).count(), 'Ask Zio panel in dashboard+zio', 10000);
    ok(true, 'Ask Zio companion panel renders next to the dashboard');
    ok(await page.locator(DIVIDER).count() === 0, 'no native split divider in dashboard+zio');
    await exitToBrowser(page, tabId);
    ok(await page.locator(ZIO_INPUT).count() === 0, 'Ask Zio panel removed after leaving dashboard+zio');

    console.log('\n── Dashboard + My Files ──');

    st = await enterModeViaDropdown(page, 'Dashboard + My Files', 'dashboard+files', tabId);
    ok(st.mode === 'dashboard+files', 'TabModeSwitcher enters Dashboard + My Files');
    await waitFor(() => page.locator(FILES_TEXT).count(), 'My Files pane in dashboard+files', 10000);
    ok(true, 'My Files pane renders next to the dashboard');
    await waitFor(() => page.locator(DIVIDER).count(), 'divider for dashboard+files', 10000);
    ok(await page.locator(DIVIDER).count() === 1, 'split divider rendered for dashboard+files');
    await exitToBrowser(page, tabId);
    ok(await page.locator(FILES_TEXT).count() === 0, 'My Files pane removed after leaving dashboard+files');

    console.log('\n── My Files + Ask Zio ──');

    st = await enterModeViaDropdown(page, 'My Files + Ask Zio', 'files+zio', tabId);
    ok(st.mode === 'files+zio', 'TabModeSwitcher enters My Files + Ask Zio');
    await waitFor(async () =>
      (await page.locator(FILES_TEXT).count()) > 0 && (await page.locator(ZIO_INPUT).count()) > 0,
      'Files pane + Zio panel together', 10000);
    ok(true, 'My Files and Ask Zio render together in files+zio');
    ok(await page.locator(DIVIDER).count() === 0, 'no native split divider in files+zio (both panes renderer-drawn)');
    await exitToBrowser(page, tabId);
    ok(await page.locator(FILES_TEXT).count() === 0 && await page.locator(ZIO_INPUT).count() === 0,
      'both companion surfaces removed after leaving files+zio');
    st = await tabState(page, tabId);
    ok(String(st?.url ?? '').includes('/d'), 'website pane URL intact at the end of the mode tour');

    // ── Close-while-detached teardown (blank-pane / orphaned-window leak) ──
    // Closing a split tab while ANOTHER tab is active exercises the teardown
    // path for DETACHED companion views (secondView / dashboardView). If
    // closeTab doesn't destroy them, they leak WebContentsViews that linger
    // as orphaned windows in app.windows().
    console.log('\n── Close-while-detached teardown ──');

    const windowCount = () => app.windows().length;
    const hasWindowWith = (part) => app.windows().some((p) => p.url().includes(part));
    const windowUrls = () => app.windows().map((p) => p.url().slice(0, 80)).join(' | ');
    // Baseline: chrome window + the single active browser tab's pane, PLUS
    // any windows the mode tour left behind. The Dashboard sections created
    // tabId's lazy dashboardView (kept detached for cheap re-entry — that's
    // by design, not a leak); its remote Sayzio page can commit LATE, making
    // its Playwright page target register minutes after creation. Capturing
    // the baseline before that target appears makes every windowCount()
    // === baseline check below fail by one. Wait for the window set to
    // settle (unchanged for a few seconds) before snapshotting it.
    {
      const settleStart = Date.now();
      let stableSince = Date.now();
      let lastUrls = windowUrls();
      while (Date.now() - settleStart < 60000) {
        await new Promise((r) => setTimeout(r, 1000));
        const urls = windowUrls();
        if (urls !== lastUrls) {
          lastUrls = urls;
          stableSince = Date.now();
        } else if (Date.now() - stableSince >= 5000) {
          break;
        }
      }
      console.log(`  (baseline window set settled: [${lastUrls}])`);
    }
    const baseline = windowCount();

    // Case 1: Website+Website split closed while detached.
    const splitTab = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/x`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === splitTab,
      'split tab active', 10000);
    await panePage(app, '/x');
    await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser+browser'), splitTab);
    await waitFor(async () => (await tabState(page, splitTab))?.mode === 'browser+browser',
      'split tab in browser+browser', 10000);
    // Primary (/x) + second pane both register as extra windows.
    await waitFor(() => windowCount() >= baseline + 2,
      'both Website+Website pane windows registered', 20000);
    ok(true, 'Website+Website split tab created with both native pane windows');

    // Detach it: activate the original tab, then close the DETACHED split tab.
    await page.evaluate((id) => window.zio.tabs.activate(id), tabId);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === tabId,
      'original tab active again (browser+browser case)', 10000);
    ok((await tabState(page, splitTab))?.mode === 'browser+browser',
      'split tab keeps browser+browser mode while detached');
    await page.evaluate((id) => window.zio.tabs.close(id), splitTab);
    try {
      await waitFor(() => windowCount() === baseline && !hasWindowWith('/x'),
        'split pane windows destroyed after close-while-detached', 20000);
      ok(true, 'closing a detached Website+Website tab destroys BOTH pane windows');
    } catch (e) {
      ok(false, `closing a detached Website+Website tab leaks pane windows (windows=${windowCount()} baseline=${baseline} hasX=${hasWindowWith('/x')} urls=[${windowUrls()}])`);
    }
    await assertPaneMarker(app, '/d', 'marker-d',
      'remaining tab still renders after closing the detached Website+Website tab');

    // Case 2: Dashboard+Website split closed while detached (dashboardView leak).
    const dashTab = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/y`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === dashTab,
      'dashboard-split tab active', 10000);
    await panePage(app, '/y');
    await page.evaluate((id) => window.zio.tabs.setMode(id, 'dashboard+browser'), dashTab);
    await waitFor(async () => (await tabState(page, dashTab))?.mode === 'dashboard+browser',
      'tab in dashboard+browser', 10000);
    // Website (/y) pane + dashboard pane both register as extra windows.
    await waitFor(() => windowCount() >= baseline + 2,
      'dashboard + website pane windows registered', 20000);
    ok(true, 'Dashboard+Website tab created with both native pane windows');

    await page.evaluate((id) => window.zio.tabs.activate(id), tabId);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === tabId,
      'original tab active again (dashboard+browser case)', 10000);
    ok((await tabState(page, dashTab))?.mode === 'dashboard+browser',
      'dashboard-split tab keeps its mode while detached');
    await page.evaluate((id) => window.zio.tabs.close(id), dashTab);
    try {
      await waitFor(() => windowCount() === baseline && !hasWindowWith('/y'),
        'dashboard pane windows destroyed after close-while-detached', 20000);
      ok(true, 'closing a detached Dashboard+Website tab destroys website AND dashboard pane windows');
    } catch (e) {
      ok(false, `closing a detached Dashboard+Website tab leaks pane windows (windows=${windowCount()} baseline=${baseline} hasY=${hasWindowWith('/y')} urls=[${windowUrls()}])`);
    }
    await assertPaneMarker(app, '/d', 'marker-d',
      'remaining tab still renders after closing the detached Dashboard+Website tab');
    st = await tabState(page, tabId);
    ok(st?.mode === 'browser' && String(st?.url ?? '').includes('/d'),
      'remaining tab state intact after both close-while-detached teardowns');

    // ── Close-while-ACTIVE teardown (attached panes + immediate neighbor activation) ──
    // The mirror case: closing the ACTIVE split tab itself (Ctrl+W while in a
    // split). Here the pane views are ATTACHED to the window when closeTab
    // runs, and closeTab immediately activates the neighbor tab — a different
    // path from the detached teardown above. Assert both pane windows are
    // destroyed (back to baseline) and the auto-activated neighbor paints.
    console.log('\n── Close-while-active teardown ──');

    // Case 1: Website+Website closed while it is the active tab.
    const activeSplitTab = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/p`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === activeSplitTab,
      'active-split tab active', 10000);
    await panePage(app, '/p');
    await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser+browser'), activeSplitTab);
    await waitFor(async () => (await tabState(page, activeSplitTab))?.mode === 'browser+browser',
      'active tab in browser+browser', 10000);
    await waitFor(() => windowCount() >= baseline + 2,
      'both Website+Website pane windows registered (active case)', 20000);
    ok(true, 'active Website+Website split created with both native pane windows');

    // Close it WHILE ACTIVE — panes are attached, neighbor activates immediately.
    await page.evaluate((id) => window.zio.tabs.close(id), activeSplitTab);
    try {
      await waitFor(() => windowCount() === baseline && !hasWindowWith('/p'),
        'split pane windows destroyed after close-while-active', 20000);
      ok(true, 'closing the ACTIVE Website+Website tab destroys BOTH pane windows');
    } catch (e) {
      ok(false, `closing the ACTIVE Website+Website tab leaks pane windows (windows=${windowCount()} baseline=${baseline} hasP=${hasWindowWith('/p')} urls=[${windowUrls()}])`);
    }
    // The neighbor tab must auto-activate and actually paint (not blank).
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === tabId,
      'neighbor tab auto-activated after close-while-active (browser+browser case)', 10000);
    ok(true, 'closing the active Website+Website tab auto-activates the neighbor tab');
    await assertPaneMarker(app, '/d', 'marker-d',
      'auto-activated neighbor tab renders after closing the active Website+Website tab');

    // Case 2: Dashboard+Website closed while active (attached dashboardView).
    const activeDashTab = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/q`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === activeDashTab,
      'active dashboard-split tab active', 10000);
    await panePage(app, '/q');
    await page.evaluate((id) => window.zio.tabs.setMode(id, 'dashboard+browser'), activeDashTab);
    await waitFor(async () => (await tabState(page, activeDashTab))?.mode === 'dashboard+browser',
      'active tab in dashboard+browser', 10000);
    await waitFor(() => windowCount() >= baseline + 2,
      'dashboard + website pane windows registered (active case)', 20000);
    ok(true, 'active Dashboard+Website split created with both native pane windows');

    await page.evaluate((id) => window.zio.tabs.close(id), activeDashTab);
    try {
      await waitFor(() => windowCount() === baseline && !hasWindowWith('/q'),
        'dashboard pane windows destroyed after close-while-active', 20000);
      ok(true, 'closing the ACTIVE Dashboard+Website tab destroys website AND dashboard pane windows');
    } catch (e) {
      ok(false, `closing the ACTIVE Dashboard+Website tab leaks pane windows (windows=${windowCount()} baseline=${baseline} hasQ=${hasWindowWith('/q')} urls=[${windowUrls()}])`);
    }
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === tabId,
      'neighbor tab auto-activated after close-while-active (dashboard+browser case)', 10000);
    ok(true, 'closing the active Dashboard+Website tab auto-activates the neighbor tab');
    await assertPaneMarker(app, '/d', 'marker-d',
      'auto-activated neighbor tab renders after closing the active Dashboard+Website tab');
    st = await tabState(page, tabId);
    ok(st?.mode === 'browser' && String(st?.url ?? '').includes('/d'),
      'neighbor tab state intact after both close-while-active teardowns');
  } finally {
    await app.close().catch(() => {});
    server.close();
  }

  if (failures > 0) {
    console.error(`\n${failures} assertion(s) FAILED`);
    process.exit(1);
  }
  console.log('\nAll remaining tab-mode checks PASSED');
  process.exit(0);
})().catch((err) => {
  console.error('\nE2E run errored:', err);
  process.exit(1);
});
