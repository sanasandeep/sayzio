/**
 * UI-level Electron check for the slimmed toolbar (Task: overflow "⋯" menu).
 *
 * Launches the REAL built Electron app (dist/main/main/index.js) under Xvfb via
 * Playwright's _electron driver and verifies, in an actual browser window:
 *
 *  Normal window:
 *   - "⋯" (More tools) button opens the overflow menu
 *   - Reading List row present; opens the Reading List panel (about:newtab)
 *   - Dialer row present; clicking it opens the sign-in modal (signed out)
 *   - Device Lab row present; clicking it opens the sign-in modal (signed out)
 *   - Screenshot rows hidden on about:newtab, appear once a page is loaded
 *   - "New Private Window" row opens a second (private) window
 *
 *  Private window:
 *   - Overflow menu opens; Dialer + Screenshot rows are hidden by design
 *   - Reading List row still present
 *   - "You're already in a private window" note shown
 *
 *  Create popover:
 *   - "Shorten this page" row appears when a page is loaded and clicking it
 *     opens the ShortenPopover (signed-out variant shows the sign-in prompt)
 *
 * Run:  xvfb-run -a node artifacts/zio-browser/tests/e2e-toolbar/run.cjs
 */
'use strict';

const path = require('path');
const os = require('os');
const fs = require('fs');
const { _electron } = require('/home/runner/workspace/node_modules/.pnpm/playwright@1.59.1/node_modules/playwright');

const APP_DIR = path.resolve(__dirname, '../..');
const MAIN = path.join(APP_DIR, 'dist/main/main/index.js');
const LOG_FILE = process.env.ZIO_E2E_LOG || '/tmp/zio-toolbar-e2e.log';

fs.writeFileSync(LOG_FILE, `start ${new Date().toISOString()}\n`);
function log(line) {
  console.log(line);
  try { fs.appendFileSync(LOG_FILE, line + '\n'); } catch { /* ignore */ }
}

// Global watchdog — never let a hung Electron keep the run alive forever.
const watchdog = setTimeout(() => {
  log('WATCHDOG: run exceeded 240s — force exiting');
  process.exit(3);
}, 240000);
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

// Find the app window (not the splash, whose URL is a data: URL).
async function appPage(app, { exclude = [], label = 'app window' } = {}) {
  return waitFor(async () => {
    for (const p of app.windows()) {
      if (exclude.includes(p)) continue;
      const url = p.url();
      if (!url.startsWith('data:') && (url.includes('index.html') || url.startsWith('http'))) {
        // Ensure the ChromeBar has rendered
        const hasBar = await p.locator('button[title="More tools"]').count().catch(() => 0);
        if (hasBar > 0) return p;
        // First-run mode picker: choose Browser mode to reach the ChromeBar.
        const picker = await p.locator('text=Choose how you want to use this window').count().catch(() => 0);
        if (picker > 0) {
          log('mode picker shown — selecting Browser mode');
          // Exact-match the "Browser" label — the Split card's description also
          // contains the word "browser", so :has-text substring matching is wrong.
          await p.locator('button div:text-is("Browser")').first().click().catch(e => log('picker card click err: ' + e.message));
          await p.locator('button:has-text("Open in")').first().click().catch(e => log('picker open click err: ' + e.message));
        } else {
          const body = await p.evaluate(() => document.body?.innerText?.slice(0, 150) ?? '<no body>').catch(e => 'evalerr:' + e.message);
          log(`poll: bar=0 body="${body}"`);
        }
      }
    }
    return null;
  }, label, 30000);
}

async function openOverflow(page) {
  await page.locator('button[title="More tools"]').click();
  await waitFor(() => page.getByText('New Private Window').count(), 'overflow menu open', 5000);
}

async function closeAuthModal(page) {
  // The ✕ inside the auth modal — a bare `button:has-text("✕")` matches
  // tab-close buttons first, so scope to the dialog containing the heading.
  await page
    .locator('div:has(> div > div > h2:text-is("Sign in to Sayzio")) button:has-text("✕")')
    .first()
    .click()
    .catch(async () => {
      // Fallback: click the ✕ nearest to the heading via DOM walk.
      await page.evaluate(() => {
        const h2 = Array.from(document.querySelectorAll('h2')).find(h => h.textContent === 'Sign in to Sayzio');
        const modal = h2?.closest('div[style*="fixed"]') ?? h2?.parentElement?.parentElement?.parentElement;
        const btn = modal ? Array.from(modal.querySelectorAll('button')).find(b => b.textContent === '✕') : null;
        btn?.click();
      });
    });
}

async function closeOverflowIfOpen(page) {
  // Click somewhere neutral to close via outside-mousedown handler
  await page.mouse.click(400, 200);
  await new Promise(r => setTimeout(r, 300));
}

(async () => {
  // Isolated user data dir so this run never touches real prefs/DB.
  const userData = fs.mkdtempSync(path.join(os.tmpdir(), 'zio-e2e-toolbar-'));

  const app = await _electron.launch({
    args: [MAIN, `--user-data-dir=${userData}`, '--no-sandbox', '--disable-gpu'],
    cwd: APP_DIR,
    env: {
      ...process.env,
      NODE_ENV: 'production',
      ELECTRON_DISABLE_SANDBOX: '1',
    },
    timeout: 60000,
  });

  try {
    log('launched; polling windows…');
    const winDump = setInterval(() => {
      try {
        log('windows: ' + JSON.stringify(app.windows().map(w => w.url().slice(0, 80))));
      } catch (e) { log('windump err: ' + e.message); }
    }, 3000);
    app.process().stderr?.on('data', (d) => log('[main-err] ' + String(d).slice(0, 300)));
    let page = await appPage(app, { label: 'main window ChromeBar' });
    clearInterval(winDump);
    page.on('console', (m) => { if (m.type() === 'error') log('[renderer] ' + m.text().slice(0, 200)); });
    console.log('\n── Normal window ──');

    // The mode-pick can recreate/replace the window right after detection —
    // settle, and re-acquire the live app window if our handle went stale.
    await new Promise(r => setTimeout(r, 1500));
    if (page.isClosed() || (await page.locator('button[title="More tools"]').count().catch(() => 0)) === 0) {
      log('page handle stale after mode pick — re-acquiring app window');
      page = await appPage(app, { label: 'main window ChromeBar (re-acquire)' });
    }

    // 1. Overflow opens on about:newtab
    await openOverflow(page);
    log('menu text: ' + JSON.stringify(await page.evaluate(() => {
      const btns = Array.from(document.querySelectorAll('button')).map(b => b.textContent?.trim()).filter(Boolean);
      return btns.slice(-25);
    })));
    ok(await page.getByText('Save to reading list').count() === 1, 'Reading List row present');
    ok(await page.getByText('Dialer — search & call on your phone').count() === 1, 'Dialer row present');
    ok(await page.getByText('Device Lab — phone / tablet / desktop preview').count() === 1, 'Device Lab row present');
    ok(await page.getByText('Screenshot — visible area').count() === 0, 'Screenshot rows hidden on about:newtab');
    ok(await page.getByText('New Private Window').count() === 1, 'New Private Window row present');

    // 2. Reading List row → panel opens (about:newtab path = toggle panel)
    await page.getByText('Save to reading list').click();
    await waitFor(() => page.getByText('Reading List', { exact: true }).count(), 'Reading List panel', 5000);
    ok(true, 'Reading List row opens the Reading List panel');
    ok(await page.getByText('New Private Window').count() === 0, 'overflow menu closed after item click');
    // close the panel by toggling again from the menu
    await openOverflow(page);
    await page.getByText('Save to reading list').click();
    await new Promise(r => setTimeout(r, 300));

    // 3. Dialer row → sign-in modal (signed out)
    await openOverflow(page);
    await page.getByText('Dialer — search & call on your phone').click();
    await waitFor(() => page.getByText('Sign in to Sayzio').count(), 'auth modal from Dialer', 5000);
    ok(true, 'Dialer row works (opens sign-in modal when signed out)');
    await closeAuthModal(page);
    await waitFor(async () => (await page.getByText('Sign in to Sayzio').count()) === 0, 'auth modal closed', 5000);

    // 4. Device Lab row → sign-in modal (signed out)
    await openOverflow(page);
    await page.getByText('Device Lab — phone / tablet / desktop preview').click();
    await waitFor(() => page.getByText('Sign in to Sayzio').count(), 'auth modal from Device Lab', 5000);
    ok(true, 'Device Lab row works (opens sign-in modal when signed out)');
    await closeAuthModal(page);
    await waitFor(async () => (await page.getByText('Sign in to Sayzio').count()) === 0, 'auth modal closed', 5000);

    // 5. Navigate the active tab to a real page → Screenshot rows appear
    await page.evaluate(async () => {
      // Preload exposes getActive/getOrder (no list()) — use the active tab id.
      const active = await window.zio.tabs.getActive();
      const order = await window.zio.tabs.getOrder();
      const tabId = (typeof active === 'string' ? active : active?.id)
        ?? (Array.isArray(order) ? order[0] : order?.[0]);
      await window.zio.tabs.navigate(tabId, 'data:text/html,<title>Zio E2E page</title><h1>hello</h1>');
    });
    await waitFor(async () => {
      await closeOverflowIfOpen(page);
      await openOverflow(page);
      const n = await page.getByText('Screenshot — visible area').count();
      if (n === 0) await closeOverflowIfOpen(page);
      return n;
    }, 'Screenshot rows after navigation', 20000, 500);
    ok(true, 'Screenshot — visible area row appears once a page is loaded');
    ok(await page.getByText('Screenshot — full page').count() === 1, 'Screenshot — full page row present');

    // 6. Screenshot (visible) → screenshot sheet appears
    await page.getByText('Screenshot — visible area').click();
    const gotSheet = await waitFor(
      () => page.locator('img[src^="data:image"]').count(),
      'screenshot sheet image', 15000,
    ).catch(() => 0);
    ok(!!gotSheet, 'Screenshot — visible area captures and shows the screenshot sheet');
    await page.keyboard.press('Escape');
    // Close the sheet without touching tab-close ✕ buttons: click a ✕/Close
    // button inside the container that holds the screenshot image.
    await page.evaluate(() => {
      const img = document.querySelector('img[src^="data:image"]');
      let node = img?.parentElement ?? null;
      while (node) {
        const btn = Array.from(node.querySelectorAll('button')).find(
          b => b.textContent?.trim() === '✕' || b.title === 'Close' || b.textContent?.trim() === 'Close',
        );
        if (btn) { btn.click(); return; }
        node = node.parentElement;
      }
    }).catch(() => {});
    await waitFor(async () => (await page.locator('img[src^="data:image"]').count()) === 0, 'screenshot sheet closed', 5000)
      .catch(() => log('warn: screenshot sheet image still present'));
    // The sheet's dimmed backdrop closes on a direct click (e.target === overlay).
    // Dispatch clicks on any remaining full-screen fixed overlays until gone.
    await waitFor(async () => {
      const remaining = await page.evaluate(() => {
        const overlays = Array.from(document.querySelectorAll('div')).filter(d => {
          const s = d.getAttribute('style') ?? '';
          return s.includes('position: fixed') && s.includes('inset: 0') && s.includes('rgba(0, 0, 0');
        });
        for (const o of overlays) o.click();
        return overlays.length;
      });
      return remaining === 0;
    }, 'screenshot sheet backdrop dismissed', 5000).catch(() => log('warn: backdrop still present'));
    await new Promise(r => setTimeout(r, 400));

    // 7. Create popover → "Shorten this page" row → ShortenPopover
    console.log('\n── Create popover ──');
    log('covering element: ' + await page.evaluate(() => {
      const btn = Array.from(document.querySelectorAll('button')).find(b => b.title?.startsWith('Create — shorten'));
      if (!btn) return '<no create btn>';
      const r = btn.getBoundingClientRect();
      const el = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      return `${el?.tagName}.${el?.className} title=${el?.getAttribute?.('title')} style=${(el?.getAttribute?.('style') ?? '').slice(0, 120)}`;
    }).catch(e => 'evalerr ' + e.message));
    // The + Create button is auth-gated (signed-out click opens the sign-in
    // modal). Seed a fake signed-in state via the preload auth IPC + reload
    // so the popover path can be exercised.
    await page.evaluate(async () => {
      await window.zio.auth.storeToken('e2e-fake-token');
      await window.zio.auth.storeUser({ id: 1, name: 'E2E User', email: 'e2e@example.com' });
    });
    // A renderer reload breaks the main-process tab registry, so instead open
    // a fresh normal window whose auth store hydrates the seeded token.
    const beforeCreateWin = app.windows().length;
    await page.evaluate(() => window.zio.window.openNew());
    await waitFor(() => app.windows().length > beforeCreateWin, 'signed-in window created', 15000);
    const cpage = await appPage(app, { exclude: [page], label: 'signed-in window ChromeBar' });
    // Navigate its active tab to a real page so canShorten is true.
    await waitFor(async () => {
      const info = await cpage.evaluate(async () => {
        const tabId = await window.zio.tabs.getActive();
        if (!tabId) return { tabId: null, url: '' };
        const state = await window.zio.tabs.getState(tabId);
        const url = state?.url ?? '';
        // The omnibox parser treats a data: input (no "//") as a search query,
        // so the tab may land on an http(s) search page — any real url is fine
        // for canShorten; it only excludes '' and about:newtab.
        if (!url || url === 'about:newtab') {
          await window.zio.tabs.navigate(tabId, 'https://example.com/');
        }
        return { tabId, url };
      });
      log('signed-in window active tab: ' + JSON.stringify(info));
      return !!info.url && info.url !== 'about:newtab';
    }, 'signed-in window tab on real page', 15000, 700);
    await new Promise(r => setTimeout(r, 800));
    await cpage.locator('button[title^="Create — shorten this page"]').click({ timeout: 10000 });
    await waitFor(() => cpage.getByText('Shorten this page', { exact: true }).count(), 'Create popover Shorten row', 8000)
      .catch(async (e) => {
        log('create popover body: ' + JSON.stringify(await cpage.evaluate(() =>
          document.body.innerText.slice(-600)).catch(() => '<eval fail>')));
        throw e;
      });
    ok(true, 'Create popover shows the "Shorten this page" row');
    await cpage.getByText('Shorten this page', { exact: true }).click();
    await waitFor(() => cpage.getByText('QR Code').count(), 'ShortenPopover open (Shorten/QR tabs)', 8000);
    ok(true, 'Shorten row opens the ShortenPopover');
    await cpage.keyboard.press('Escape');
    // Back to signed-out for the private-window checks. Leave the extra
    // window open — window.close() tears down more than its own page here.
    await cpage.evaluate(() => window.zio.auth.clear());

    // 8. New Private Window row — the original `page` handle can go stale
    // after new windows open, so re-acquire a live normal-window page.
    console.log('\n── Private window ──');
    let base = null;
    await waitFor(async () => {
      log('windows now: ' + JSON.stringify(app.windows().map(w => ({ url: w.url().slice(-30), closed: w.isClosed() }))));
      base = await appPage(app, { label: 'normal window for private test' });
      await closeOverflowIfOpen(base);
      try {
        await base.locator('button[title="More tools"]').click({ timeout: 3000 });
        return true;
      } catch (e) {
        log('base overflow click failed: ' + e.message.split('\n')[0]);
        return false;
      }
    }, 'live normal window with working overflow button', 30000, 1000);
    await waitFor(() => base.getByText('New Private Window').count(), 'overflow menu open', 5000);
    const preExisting = app.windows().slice();
    await base.getByText('New Private Window').click();
    await waitFor(() => app.windows().length > preExisting.length, 'new window created', 15000);
    const priv = await appPage(app, { exclude: preExisting, label: 'private window ChromeBar' });
    await waitFor(() => priv.getByText('🔒 Private').count(), 'private badge', 10000);
    ok(true, 'New Private Window opens a private window (🔒 badge visible)');

    // 9. Overflow menu inside the private window
    await openOverflow(priv);
    ok(await priv.getByText('Save to reading list').count() === 1, 'private: Reading List row present');
    ok(await priv.getByText('Dialer — search & call on your phone').count() === 0, 'private: Dialer row hidden');
    ok(await priv.getByText('Screenshot — visible area').count() === 0, 'private: Screenshot rows hidden');
    ok(await priv.getByText("You're already in a private window").count() === 1, 'private: already-private note shown');

    // 10. Reading List works in the private window too
    await priv.getByText('Save to reading list').click();
    await waitFor(() => priv.getByText('Reading List', { exact: true }).count(), 'private Reading List panel', 5000);
    ok(true, 'private: Reading List row opens the panel');
  } finally {
    await app.close().catch(() => {});
  }

  if (failures > 0) {
    console.error(`\n${failures} assertion(s) FAILED`);
    process.exit(1);
  }
  console.log('\nAll toolbar overflow-menu UI checks PASSED');
  process.exit(0);
})().catch((err) => {
  console.error('\nE2E run errored:', err);
  process.exit(1);
});
