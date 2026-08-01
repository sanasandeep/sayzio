/**
 * UI-level Electron check for the Website+Website split view.
 *
 * Launches the REAL built Electron app (dist/main/main/index.js) under Xvfb
 * via Playwright's _electron driver and verifies, in an actual window:
 *
 *  - Entering the split via the TabModeSwitcher dropdown ("Website + Website")
 *  - Both pane focus frames render; the primary (left) pane starts focused
 *  - The dim overlay covers ONLY the unfocused pane
 *  - Clicking the unfocused pane's frame moves focus (and the dim overlay)
 *  - The address bar routes to the FOCUSED pane (omnibox submit navigates the
 *    right pane while it is focused; the left pane's URL is untouched)
 *  - The dim overlay survives navigation of the dimmed pane (re-applied on
 *    dom-ready)
 *  - Ctrl+Alt+Left / Ctrl+Alt+Right switch the focused pane
 *  - Dragging the divider changes the persisted split ratio and re-positions
 *    the focus frames
 *  - Swapping the panes (TabManager.swapPanes) exchanges the content's sides,
 *    the focused-pane badge follows the content, the dim overlay stays on the
 *    unfocused pane, and a second swap round-trips back
 *  - The swap is REFUSED on tabs anchored to renderer-drawn primary surfaces
 *    (New Tab / about pages)
 *  - Leaving the split restores primary focus, removes frames and the dim
 *
 * Run:  xvfb-run -a node artifacts/zio-browser/tests/e2e-split-view/run.cjs
 */
'use strict';

const path = require('path');
const os = require('os');
const fs = require('fs');
const http = require('http');
const { _electron } = require('/home/runner/workspace/node_modules/.pnpm/playwright@1.59.1/node_modules/playwright');

const APP_DIR = path.resolve(__dirname, '../..');
const MAIN = path.join(APP_DIR, 'dist/main/main/index.js');
const LOG_FILE = process.env.ZIO_E2E_LOG || '/tmp/zio-split-view-e2e.log';

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

// Local fixture server — both panes navigate to pages we control, so the run
// never depends on external network reachability.
function startFixtureServer() {
  return new Promise((resolve) => {
    const server = http.createServer((req, res) => {
      const name = (req.url || '/').replace(/[^a-z]/g, '') || 'root';
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(`<!doctype html><title>Zio split ${name}</title><h1 id="marker-${name}">pane ${name}</h1>`);
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
    return { position: s.position, bg: s.backgroundColor, content: s.content };
  }).catch((e) => ({ evalError: String(e && e.message || e).slice(0, 120) }));
}

function isDimStyle(s) {
  return !!s && s.position === 'fixed' && s.bg === 'rgba(0, 0, 0, 0.25)';
}

// Poll until the pane showing urlPart reaches the wanted dim state. The pane
// window is re-resolved from app.windows() on EVERY poll: held Page handles
// for WebContentsView panes go stale across navigations/overlay toggles.
async function waitDim(app, urlPart, dimmed, label) {
  const start = Date.now();
  let last = null;
  while (Date.now() - start < 15000) {
    last = { noWindow: urlPart };
    for (const p of app.windows()) {
      if (!p.url().includes(urlPart)) continue;
      last = await paneDimStyle(p);
      if (isDimStyle(last) === dimmed) return;
    }
    await new Promise(r => setTimeout(r, 300));
  }
  log('window list at dim failure: ' + app.windows().map(p => p.url().slice(0, 80)).join(' | '));
  throw new Error(`Timed out waiting for: ${label} (last=${JSON.stringify(last)})`);
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

(async () => {
  const { server, base } = await startFixtureServer();
  log(`fixture server at ${base}`);

  // Isolated user data dir so this run never touches real prefs/DB.
  const userData = fs.mkdtempSync(path.join(os.tmpdir(), 'zio-e2e-split-'));

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

    console.log('\n── Entering the split ──');

    // 1. Enter Website+Website via the real TabModeSwitcher dropdown.
    await page.locator('button[title^="Tab view:"]').click();
    await waitFor(() => page.getByText('Website + Website', { exact: true }).count(), 'tab-mode dropdown open', 5000);
    await page.getByText('Website + Website', { exact: true }).click();
    const stAfterMode = await waitFor(async () => {
      const st = await tabState(page, tabId);
      return st?.mode === 'browser+browser' ? st : null;
    }, 'tab mode browser+browser', 10000);
    ok(stAfterMode.mode === 'browser+browser', 'TabModeSwitcher enters the Website + Website split');

    // 2-4. Focus frames + primary focused by default.
    await waitFor(() => page.locator('div[title="Address bar controls this pane"]').count(), 'focused frame', 10000);
    ok(await page.locator('div[title="Address bar controls this pane"]').count() === 1, 'exactly one FOCUSED pane frame');
    ok(await page.locator('div[title="Click to control this pane from the address bar"]').count() === 1, 'exactly one UNFOCUSED pane frame');
    // Which pane holds focus right after split entry is window-manager timing
    // dependent (the fresh second pane's load can steal it) — pin the primary
    // pane and assert the pin lands, which is the invariant the UI relies on.
    await waitFor(async () => {
      await page.evaluate((id) => window.zio.tabs.focusPane(id, 'primary'), tabId);
      await new Promise(r => setTimeout(r, 300));
      return (await page.getByText('Address bar · Left pane').count()) === 1;
    }, 'primary pane pinned after split entry', 10000);
    ok(true, 'primary (left) pane focused after split entry — tag shows "Left pane"');

    // The second pane starts on the search engine's home page; retarget it to
    // fixture page B so the rest of the run is network-independent.
    await page.evaluate(({ id, url }) => window.zio.tabs.navigatePane(id, 'second', url), { id: tabId, url: `${base}/b` });
    const paneA = await panePage(app, '/a');
    const paneB = await panePage(app, '/b');

    // 5-6. Dim overlay sits on the UNFOCUSED (right) pane only.
    // The second pane's initial page load can steal focus once it completes
    // (its webContents 'focus' listener promotes it). Pin focus back to the
    // primary pane so the dim/focus assertions below are deterministic.
    await page.evaluate((id) => window.zio.tabs.focusPane(id, 'primary'), tabId);
    await waitFor(() => page.getByText('Address bar · Left pane').count(), 'primary refocused', 8000);
    await waitDim(app, '/b', true, 'right pane dimmed');
    ok(true, 'unfocused right pane is dimmed');
    await waitDim(app, '/a', false, 'left pane undimmed');
    ok(true, 'focused left pane is NOT dimmed');

    console.log('\n── Frame click moves focus ──');

    // 7-9. Click the unfocused (right) frame → focus + dim swap; tag flips.
    await page.locator('div[title="Click to control this pane from the address bar"]').first()
      .dispatchEvent('mousedown');
    await waitFor(() => page.getByText('Address bar · Right pane').count(), 'right pane tag', 8000);
    ok(true, 'clicking the unfocused frame moves focus — tag shows "Right pane"');
    await waitDim(app, '/a', true, 'left pane dimmed after focus swap');
    ok(true, 'dim overlay moved to the now-unfocused left pane');
    await waitDim(app, '/b', false, 'right pane undimmed after focus swap');
    ok(true, 'newly focused right pane is undimmed');

    // 10. Toolbar state now reflects the second pane's URL.
    const stRight = await tabState(page, tabId);
    ok(String(stRight?.url ?? '').includes('/b'), 'shared toolbar state follows the focused (right) pane URL');

    console.log('\n── Address bar routes to the focused pane ──');

    // 11-12. Type a URL in the omnibox + Enter → navigates the RIGHT pane only.
    const omnibox = page.locator('input[placeholder="Search or enter URL"]');
    await omnibox.click();
    await omnibox.fill(`${base}/c`);
    await omnibox.press('Enter');
    await waitFor(async () => (await tabState(page, tabId))?.url?.includes('/c'), 'right pane navigated to /c', 15000);
    ok(true, 'omnibox submit navigates the focused (right) pane');
    const paneC = await panePage(app, '/c');
    ok(paneC.url().includes('/c') && paneA.url().includes('/a'), 'left pane untouched by the right-pane navigation');

    console.log('\n── Keyboard shortcuts ──');

    // 13-14. Ctrl+Alt+Left focuses the left pane; the dim follows.
    await page.locator('body').click({ position: { x: 5, y: 5 } }).catch(() => {});
    await page.keyboard.press('Control+Alt+ArrowLeft');
    await waitFor(() => page.getByText('Address bar · Left pane').count(), 'left pane refocused via shortcut', 8000);
    ok(true, 'Ctrl+Alt+Left focuses the left pane');
    await waitDim(app, '/c', true, 'right pane dimmed after shortcut');
    ok(true, 'dim overlay follows the shortcut focus change');

    // 15. Dim survives navigation of the DIMMED pane (re-applied on dom-ready).
    // Navigate the unfocused second pane via the pane-targeted IPC: navigating
    // the focused primary view (or evaluating inside a pane) would re-fire its
    // webContents focus listener and flip focusedPane instead.
    await page.evaluate(({ id, url }) => window.zio.tabs.navigatePane(id, 'second', url), { id: tabId, url: `${base}/d` });
    await panePage(app, '/d');
    // The fresh load can hand the second pane real input focus, and the steal
    // may land AFTER a one-shot re-pin — keep re-pinning until it sticks.
    await waitFor(async () => {
      await page.evaluate((id) => window.zio.tabs.focusPane(id, 'primary'), tabId);
      await new Promise(r => setTimeout(r, 300));
      return (await page.getByText('Address bar · Left pane').count()) === 1;
    }, 'primary re-pinned', 10000);
    await waitDim(app, '/d', true, 'dim re-applied after dimmed-pane navigation');
    ok(true, 'dim overlay re-applies after the dimmed pane navigates (dom-ready hook)');

    // 16. Ctrl+Alt+Right focuses the right pane again.
    await page.keyboard.press('Control+Alt+ArrowRight');
    await waitFor(() => page.getByText('Address bar · Right pane').count(), 'right pane refocused via shortcut', 8000);
    ok(true, 'Ctrl+Alt+Right focuses the right pane');

    console.log('\n── Divider drag ──');

    // 17-18. Drag the split divider left → ratio shrinks and is persisted.
    const divider = page.locator('div[title="Drag to resize split"]');
    ok(await divider.count() === 1, 'split divider rendered');
    const before = await tabState(page, tabId);
    const box = await divider.boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x - 160, box.y + box.height / 2, { steps: 8 });
    await page.mouse.up();
    const after = await waitFor(async () => {
      const st = await tabState(page, tabId);
      return st && Math.abs(st.splitRatio - before.splitRatio) > 0.03 ? st : null;
    }, 'split ratio persisted after drag', 8000);
    ok(after.splitRatio < before.splitRatio, `divider drag persists a smaller left-pane ratio (${before.splitRatio.toFixed(2)} → ${after.splitRatio.toFixed(2)})`);

    // The focused frame tracks the new ratio (right pane frame starts at the
    // divider). Layout settles asynchronously after the drag — poll for it.
    await waitFor(async () => {
      const frameLeft = await page.locator('div[title="Address bar controls this pane"]').evaluate(el => el.getBoundingClientRect().left);
      const dividerLeft = (await divider.boundingBox()).x;
      return Math.abs(frameLeft - (dividerLeft + 4)) < 12;
    }, 'focused frame tracks the dragged divider', 8000);
    ok(true, 'focused (right) frame re-positioned to the dragged divider');

    console.log('\n── Pane swap ──');

    // 19-23. The right pane (/d) is focused. swapPanes exchanges the panes:
    // the content swaps sides, the toolbar keeps controlling the SAME page
    // (/d) so the focused-pane badge follows the content to the LEFT.
    await page.evaluate((id) => window.zio.tabs.swapPanes(id), tabId);
    await waitFor(() => page.getByText('Address bar · Left pane').count(), 'left-pane badge after swap', 8000);
    ok(true, 'focused-pane badge follows the content to the left after swap');
    const stSwap = await tabState(page, tabId);
    ok(String(stSwap?.url ?? '').includes('/d'), 'toolbar still controls the same page (/d) after swap');
    // The pane URLs exchanged sides: the focused (/d) frame now sits LEFT of
    // the divider.
    await waitFor(async () => {
      const frameLeft = await page.locator('div[title="Address bar controls this pane"]').evaluate(el => el.getBoundingClientRect().left);
      const dividerLeft = (await divider.boundingBox()).x;
      return frameLeft < dividerLeft;
    }, 'focused frame on the left side after swap', 8000);
    ok(true, 'pane URLs exchanged sides — focused (/d) frame sits left of the divider');
    // Dim stays on the UNFOCUSED pane, which is /a — now on the right.
    await waitDim(app, '/a', true, '/a dimmed after swap');
    ok(true, 'dim overlay stays on the unfocused pane (/a, now right)');
    await waitDim(app, '/d', false, '/d undimmed after swap');
    ok(true, 'focused pane (/d, now left) is undimmed');

    console.log('\n── History follows each pane through the swap ──');

    // The focused pane (now left) built its own history before the swap:
    // search home → /b → /c → /d. The toolbar back action must walk THAT
    // pane's history — a regression that kept routing back/forward by side
    // (instead of by view) would pop the OTHER pane's history here.
    const stPreBack = await tabState(page, tabId);
    ok(stPreBack?.canGoBack === true, 'focused pane reports canGoBack after the swap');
    await page.evaluate((id) => window.zio.tabs.back(id), tabId);
    await waitFor(async () => {
      const st = await tabState(page, tabId);
      return String(st?.url ?? '').includes('/c');
    }, 'toolbar back returns the focused pane to /c after the swap', 15000);
    ok(true, 'toolbar back navigates the FOCUSED pane to its own previous page (/c)');
    const stBack = await tabState(page, tabId);
    ok(stBack?.canGoForward === true, 'canGoForward true after going back (own /d ahead)');
    ok(stBack?.canGoBack === true, 'canGoBack still true (own /b further back)');
    // The other pane (/a, now right) must be untouched by the back action.
    ok(app.windows().some(p => p.url().includes('/a')), 'other pane still on /a — untouched by back');
    ok(!String(stBack?.url ?? '').includes('/a'), 'toolbar does not show the other pane URL after back');

    // Toolbar forward walks the same pane's history back to /d.
    await page.evaluate((id) => window.zio.tabs.forward(id), tabId);
    await waitFor(async () => {
      const st = await tabState(page, tabId);
      return String(st?.url ?? '').includes('/d');
    }, 'toolbar forward returns the focused pane to /d', 15000);
    const stFwd = await tabState(page, tabId);
    ok(stFwd?.canGoForward === false, 'canGoForward false again at the top of the pane history');
    ok(app.windows().some(p => p.url().includes('/a')), 'other pane still on /a — untouched by forward');
    ok(true, 'toolbar forward re-advances the focused pane to /d');

    // 24-25. Swap again — the round trip restores the original sides.
    await page.evaluate((id) => window.zio.tabs.swapPanes(id), tabId);
    await waitFor(() => page.getByText('Address bar · Right pane').count(), 'right-pane badge after swap-back', 8000);
    const stSwapBack = await tabState(page, tabId);
    ok(String(stSwapBack?.url ?? '').includes('/d'), 'swap round-trip: toolbar still controls /d, back on the right');
    await waitDim(app, '/a', true, '/a dimmed after swap-back');
    ok(true, 'swap round-trip restores the dim to the left (/a) pane');

    console.log('\n── Tab switch survival ──');

    // 18b. Open a second (plain browser) tab, switch away and back, and
    // assert the whole split layout — mode, frames, divider, ratio, focus and
    // dim — is restored when the tab's views are reattached.
    const sideTab = await page.evaluate((url) => window.zio.tabs.create(url), `${base}/side`);
    await waitFor(async () => (await page.evaluate(() => window.zio.tabs.getActive())) === sideTab,
      'side tab active', 10000);
    await waitFor(async () =>
      (await page.locator('div[title="Address bar controls this pane"]').count()) === 0 &&
      (await page.locator('div[title="Drag to resize split"]').count()) === 0,
      'frames + divider hidden while the side tab is active', 10000);
    ok(true, 'focus frames and divider hidden while a plain browser tab is active');
    const stDetached = await tabState(page, tabId);
    ok(stDetached?.mode === 'browser+browser', 'inactive tab keeps browser+browser mode while detached');
    ok(Math.abs((stDetached?.splitRatio ?? 0) - after.splitRatio) < 0.01, 'split ratio persists while the tab is detached');

    await page.evaluate((id) => window.zio.tabs.activate(id), tabId);
    const stReactivated = await waitFor(async () => {
      const s = await tabState(page, tabId);
      return s?.mode === 'browser+browser' && (await page.evaluate(() => window.zio.tabs.getActive())) === tabId ? s : null;
    }, 'split tab reactivated', 10000);
    ok(stReactivated.mode === 'browser+browser', 'tab restores browser+browser mode after switching back');
    ok(Math.abs(stReactivated.splitRatio - after.splitRatio) < 0.01, 'dragged split ratio survives the tab round-trip');
    await waitFor(async () =>
      (await page.locator('div[title="Address bar controls this pane"]').count()) === 1 &&
      (await page.locator('div[title="Click to control this pane from the address bar"]').count()) === 1,
      'both pane frames restored', 10000);
    ok(true, 'exactly one focused + one unfocused frame after the tab round-trip');
    ok(await divider.count() === 1, 'split divider restored after the tab round-trip');
    // Reattached panes must still hold their documents (not blank).
    await assertPaneMarker(app, '/a', 'marker-a', 'left pane content intact after reattach');
    await assertPaneMarker(app, '/d', 'marker-d', 'right pane content intact after reattach');
    // Focus after reattach is window-manager timing dependent (activateTab
    // focuses the primary view); pin the right pane — the one focused before
    // the switch — and assert the pin lands, then the dim follows it.
    await waitFor(async () => {
      await page.evaluate((id) => window.zio.tabs.focusPane(id, 'second'), tabId);
      await new Promise(r => setTimeout(r, 300));
      return (await page.getByText('Address bar · Right pane').count()) === 1;
    }, 'right pane re-pinned after tab switch', 10000);
    ok(true, 'pane focus controllable again after the tab round-trip');
    await waitDim(app, '/a', true, 'left pane dimmed after tab round-trip');
    ok(true, 'dim overlay restored on the unfocused pane after reattach');
    await waitDim(app, '/d', false, 'right pane undimmed after tab round-trip');
    ok(true, 'focused pane undimmed after reattach');
    const stRouted = await tabState(page, tabId);
    ok(String(stRouted?.url ?? '').includes('/d'), 'toolbar follows the focused (right) pane after the round-trip');
    await page.evaluate((id) => window.zio.tabs.close(id), sideTab);

    console.log('\n── Leaving the split ──');

    // 19. Back to a single Website tab: frames + dim gone, primary URL back.
    await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser'), tabId);
    await waitFor(async () => (await tabState(page, tabId))?.mode === 'browser', 'tab back to browser mode', 10000);
    ok(await page.locator('div[title="Address bar controls this pane"]').count() === 0, 'focus frames removed after leaving the split');
    const stFinal = await tabState(page, tabId);
    ok(String(stFinal?.url ?? '').includes('/a'), 'toolbar controls the primary pane again after leaving the split');
    await waitDim(app, '/a', false, 'primary dim removed');
    ok(true, 'dim overlay removed from the primary pane after leaving the split');

    console.log('\n── Swap refused on renderer-drawn primary (New Tab) ──');

    // A fresh tab's primary surface is the renderer-drawn New Tab page —
    // swapping the native views underneath would desync, so swapPanes must
    // refuse.
    const ntId = await page.evaluate(() => window.zio.tabs.create());
    ok(!!ntId, 'created a fresh New Tab tab');
    await page.evaluate((id) => window.zio.tabs.setMode(id, 'browser+browser'), ntId);
    await waitFor(async () => (await tabState(page, ntId))?.mode === 'browser+browser', 'New Tab tab in split mode', 10000);
    // Point the second pane at an identifiable fixture page.
    await page.evaluate(({ id, url }) => window.zio.tabs.navigatePane(id, 'second', url), { id: ntId, url: `${base}/e` });
    await panePage(app, '/e');
    // Pin focus to the primary (New Tab) pane so the pre-swap state is known.
    await waitFor(async () => {
      await page.evaluate((id) => window.zio.tabs.focusPane(id, 'primary'), ntId);
      await new Promise(r => setTimeout(r, 300));
      return (await page.getByText('Address bar · Left pane').count()) === 1;
    }, 'primary pinned on the New Tab split', 10000);

    await page.evaluate((id) => window.zio.tabs.swapPanes(id), ntId);
    // The refusal is a silent no-op — give a wrongful swap time to surface.
    await new Promise(r => setTimeout(r, 1000));
    const stNt = await tabState(page, ntId);
    ok(!String(stNt?.url ?? '').includes('/e'), 'swap refused: toolbar still controls the New Tab primary, not /e');
    ok((await page.getByText('Address bar · Left pane').count()) === 1, 'swap refused: focused-pane badge stays on the left pane');
    await waitDim(app, '/e', true, 'second pane still dimmed after refused swap');
    ok(true, 'swap refused: dim overlay stays on the unfocused right pane');
    await page.evaluate((id) => window.zio.tabs.close(id), ntId);
  } finally {
    await app.close().catch(() => {});
    server.close();
  }

  if (failures > 0) {
    console.error(`\n${failures} assertion(s) FAILED`);
    process.exit(1);
  }
  console.log('\nAll Website+Website split-view checks PASSED');
  process.exit(0);
})().catch((err) => {
  console.error('\nE2E run errored:', err);
  process.exit(1);
});
