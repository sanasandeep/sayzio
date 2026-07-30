/**
 * Zio Browser — Electron main process entry point.
 */
import path from 'path';
import { app, BrowserWindow, Menu, session, nativeTheme, dialog, webContents } from 'electron';
import type { BaseWindow } from 'electron';
import { initDb, getPreference, setPreference, getMuteAllTabs, isDomainMuted, setDomainMuted, pruneHistoryOlderThan, setSiteSettings } from './db';
import { resolveSiteSettingsForUrl, contentBlockerOverrideForOrigin, invalidateSiteSettingsCache } from './site-settings';
import { PREFERENCE_KEYS, type PreferenceKey } from '../shared/db-schema';
import { hostForMutePolicy } from '../shared/mute-policy';
import { sessionPartitionForProfile, DEFAULT_PROFILE_ID } from '../shared/profile-store';
import { seedSayzioWebSession } from './sayzio-session';
import { TabManager } from './tab-manager';
import { WindowModeManager, CHROME_HEIGHT } from './window-mode-manager';
import {
  registerIpcHandlers,
  registerTabManager,
  registerModeManager,
  registerWindowProfile,
  getTabManagerForWindow,
  getModeManagerForWindow,
  setLogoutHandler,
} from './ipc-handlers';
import { setupDownloadManager } from './download-manager';
import { getPrivateSession, registerPrivateWindow } from './private-session';
import { setupPermissionHandlers } from './permission-handler';
import { setupTrackerBlocking, resetBlockedCount, installTrackerHooks, setSiteOverrideResolver } from './tracker-blocker';
import { setupPrivacyControls, installPrivacyHooks } from './privacy';
import type { WindowMode } from '../shared/window-mode';
import { ZIO_PANEL_DIVIDER_WIDTH } from '../shared/window-mode';
import { setupAutoUpdater } from './auto-updater';
import { loadStoredExtensions, loadBuiltinExtension } from './extension-manager';
import type { RecentlyClosedEntry } from './tab-manager';

const isDev = process.env['NODE_ENV'] === 'development';

let mainWindow: BrowserWindow | null = null;
let splashWindow: BrowserWindow | null = null;

// ── Branded splash screen ─────────────────────────────────────────────────────
// A small frameless window shown instantly on launch (Opera-style) while the
// main window loads. Closed by closeSplash() when the main window is ready.

const SPLASH_HTML = `<!doctype html><html><head><meta charset="utf-8"><style>
  html,body{margin:0;height:100%;overflow:hidden;user-select:none;-webkit-user-select:none}
  body{display:flex;flex-direction:column;align-items:center;justify-content:center;
    background:radial-gradient(120% 120% at 50% 0%,#232347 0%,#14142a 55%,#0d0d1a 100%);
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#e8e6ff;
    -webkit-app-region:drag}
  .logo{position:relative;width:88px;height:88px;margin-bottom:22px}
  .core{position:absolute;inset:0;border-radius:26px;
    background:linear-gradient(135deg,#4f7cff 0%,#7a5cff 100%);
    display:flex;align-items:center;justify-content:center;
    font-size:44px;font-weight:800;color:#fff;letter-spacing:-2px;
    box-shadow:0 8px 40px rgba(90,110,255,.45);
    animation:pop .6s cubic-bezier(.2,1.4,.4,1) both}
  .ring{position:absolute;inset:-10px;border-radius:34px;border:2px solid rgba(120,130,255,.5);
    animation:pulse 1.6s ease-out infinite}
  .name{font-size:22px;font-weight:700;letter-spacing:-.5px;animation:fade .8s .15s ease both}
  .by{font-size:12px;color:rgba(200,200,255,.55);margin-top:6px;animation:fade .8s .3s ease both}
  .bar{width:150px;height:3px;border-radius:2px;background:rgba(255,255,255,.10);
    margin-top:26px;overflow:hidden;animation:fade .8s .4s ease both}
  .bar i{display:block;width:40%;height:100%;border-radius:2px;
    background:linear-gradient(90deg,#4f7cff,#7a5cff);
    animation:slide 1.1s ease-in-out infinite}
  @keyframes pulse{0%{transform:scale(.92);opacity:.9}100%{transform:scale(1.25);opacity:0}}
  @keyframes pop{from{transform:scale(.6);opacity:0}to{transform:scale(1);opacity:1}}
  @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  @keyframes slide{0%{margin-left:-40%}100%{margin-left:100%}}
</style></head><body>
  <div class="logo"><div class="ring"></div><div class="core">Z</div></div>
  <div class="name">Zio Browser</div>
  <div class="by">by Sayzio &middot; sayzio.app</div>
  <div class="bar"><i></i></div>
</body></html>`;

function createSplashWindow(): void {
  try {
    splashWindow = new BrowserWindow({
      width: 420,
      height: 300,
      frame: false,
      resizable: false,
      movable: true,
      minimizable: false,
      maximizable: false,
      fullscreenable: false,
      alwaysOnTop: true,
      show: true,
      backgroundColor: '#0d0d1a',
      title: 'Zio Browser',
      webPreferences: { contextIsolation: true, nodeIntegration: false, sandbox: true },
    });
    splashWindow.on('closed', () => { splashWindow = null; });
    void splashWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(SPLASH_HTML)}`);
  } catch (err) {
    // The splash is purely cosmetic — never let it interfere with startup.
    console.error('Splash window failed:', err);
    splashWindow = null;
  }
}

function closeSplash(): void {
  try {
    if (splashWindow && !splashWindow.isDestroyed()) splashWindow.destroy();
  } catch {
    // ignore — cosmetic only
  }
  splashWindow = null;
}

// ── Fail-soft DB access ───────────────────────────────────────────────────────
// If the native SQLite module fails to load (e.g. an ABI mismatch in a packaged
// build), the browser must still open — preferences simply won't persist.
// Every startup-path DB call goes through these wrappers so a DB failure can
// never prevent the main window from appearing.

function safeGetPreference(key: PreferenceKey): string | null {
  try {
    return getPreference(key);
  } catch {
    return null;
  }
}

function safeSetPreference(key: PreferenceKey, value: string): void {
  try {
    setPreference(key, value);
  } catch {
    // DB unavailable — skip persistence rather than crash.
  }
}

// Surface unexpected main-process errors instead of dying silently with a
// menu bar and no window.
let startupErrorShown = false;
function reportStartupError(context: string, err: unknown): void {
  const detail = err instanceof Error ? (err.stack ?? err.message) : String(err);
  console.error(`${context}:`, detail);
  if (!startupErrorShown && app.isReady()) {
    startupErrorShown = true;
    dialog.showErrorBox(`Zio Browser — ${context}`, detail);
  }
}

process.on('uncaughtException', (err) => reportStartupError('Unexpected error', err));
process.on('unhandledRejection', (reason) => reportStartupError('Unexpected error', reason));

function getRendererUrl(): string {
  if (isDev) {
    return `http://localhost:${process.env['VITE_PORT'] ?? 5173}`;
  }
  return `file://${path.join(__dirname, '../renderer/index.html')}`;
}

// ── Normal window ─────────────────────────────────────────────────────────────

export function createWindow(): BrowserWindow {
  session.defaultSession.webRequest.onHeadersReceived((details, callback) => {
    callback({ responseHeaders: { ...details.responseHeaders } });
  });

  const win = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 800,
    minHeight: 600,
    title: 'Zio Browser',
    backgroundColor: nativeTheme.shouldUseDarkColors ? '#1a1a2e' : '#ffffff',
    webPreferences: {
      preload: path.join(__dirname, '../preload/index.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
      webSecurity: true,
    },
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
    trafficLightPosition: { x: 12, y: 20 },
    show: false,
  });

  // Restore the last-used profile from persisted preferences and bind it to
  // THIS window only (profiles are tracked per-window, never process-global).
  const savedProfileId = safeGetPreference(PREFERENCE_KEYS.ACTIVE_PROFILE) ?? DEFAULT_PROFILE_ID;
  registerWindowProfile(win, savedProfileId);

  // Pre-warm the profile session so it's available before the first tab opens
  if (savedProfileId !== DEFAULT_PROFILE_ID) {
    void session.fromPartition(sessionPartitionForProfile(savedProfileId));
  }

  // Initialize the tab manager with the restored profile session
  const tabManager = new TabManager(win);
  tabManager.setActiveProfilePartition(savedProfileId);
  registerTabManager(win, tabManager);

  // If the browser holds a Sanctum token, quietly establish a matching web
  // session in this profile's cookie jar so sayzio.app tabs open signed in.
  void seedSayzioWebSession(sessionPartitionForProfile(savedProfileId));


  // Guard against sends after the window is destroyed — tab teardown during
  // quit (destroyAll) still fires these callbacks, and an unguarded
  // webContents.send throws "Object has been destroyed".
  const sendToWin = (channel: string, ...args: unknown[]): void => {
    if (!win.isDestroyed() && !win.webContents.isDestroyed()) {
      win.webContents.send(channel, ...args);
    }
  };
  tabManager.setCallbacks({
    onTabStateChange: (tabId, state) => sendToWin('tab:state-changed', tabId, state),
    onTabCreated:      (tabId)        => sendToWin('tab:created', tabId),
    onTabClosed:       (tabId)        => sendToWin('tab:closed', tabId),
    onActiveTabChange: (tabId)        => sendToWin('tab:activated', tabId),
    onNavigate:        (tabId, url, title) => {
      sendToWin('tab:navigated', tabId, url, title);
      resetBlockedCount(tabId);
    },
    onAddToBiolink:    (url, title)   => sendToWin('biolink:add-page', url, title),
    onShortenPage:     (url, title)   => sendToWin('link:shorten-page', url, title),
    onCreateQr:        (url, title)   => sendToWin('link:create-qr', url, title),
    onAutofillPage:    (tabId)        => sendToWin('autofill:page', tabId),
    onDeviceLabPreview: (url)         => sendToWin('device-lab:preview-url', url),
    onFindResult:      (result) => sendToWin('tab:find-result', result),
    onTabOrderChange: (order) => sendToWin('tab:order-changed', order),
    onPinnedUrlsChange: (urls) => { safeSetPreference(PREFERENCE_KEYS.PINNED_TABS, JSON.stringify(urls)); },
    onRecentlyClosedChange: (entries: RecentlyClosedEntry[]) => sendToWin('tab:recently-closed-changed', entries),
    // Auto-mute: global "mute all tabs" policy or per-domain mute memory.
    resolveAutoMute: (url) => {
      try {
        if (getMuteAllTabs()) return true;
        const host = hostForMutePolicy(url);
        return host !== null && isDomainMuted(host);
      } catch {
        return false;
      }
    },
    // Persist the user's explicit per-tab mute choice as domain memory.
    onUserMuteChange: (url, muted) => {
      try {
        const host = hostForMutePolicy(url);
        if (host) setDomainMuted(host, muted);
      } catch {
        // DB unavailable — skip persistence rather than crash.
      }
    },
    // Reserve room on the right for the renderer-drawn Ask Zio panel when the
    // active tab is in zio-split mode. modeManager is assigned just below;
    // the callback only fires on layout passes long after startup.
    // When the docked panel is both preferred AND currently visible,
    // WindowModeManager's applyBrowserBounds() already reserves the
    // right-side width — return 0 here so the reserve isn't subtracted twice.
    resolveZioPanelReserve: () =>
      modeManager && !(modeManager.getZioPanelDocked() && modeManager.getZioPanelVisible())
        ? modeManager.getZioPanelWidth() + ZIO_PANEL_DIVIDER_WIDTH
        : 0,
    resolveSpellcheckEnabled: () =>
      (safeGetPreference(PREFERENCE_KEYS.SPELLCHECK_ENABLED) ?? '1') === '1',
    resolveTranslateLang: () =>
      safeGetPreference(PREFERENCE_KEYS.TRANSLATE_TARGET_LANG) ?? 'en',
    // Per-site "Settings for this website" (zoom / auto-play / pop-ups).
    resolveSiteSettings: (url) => resolveSiteSettingsForUrl(url),
    // Persist user-driven zoom changes per site (100% clears the override).
    onZoomPersist: (url, factor) => {
      try {
        const origin = new URL(url).origin;
        if (!origin.startsWith('http')) return;
        setSiteSettings(origin, { zoom: Math.abs(factor - 1) < 0.001 ? null : factor });
        invalidateSiteSettingsCache(origin);
      } catch {
        // DB unavailable — skip persistence rather than crash.
      }
    },
    onPopupBlocked: (pageUrl) => {
      let host = pageUrl;
      try { host = new URL(pageUrl).hostname; } catch { /* keep raw */ }
      sendToWin('toast:show', `Pop-up blocked on ${host}`);
    },
  });

  const savedMode  = (safeGetPreference(PREFERENCE_KEYS.WINDOW_MODE) as WindowMode | null) ?? 'browser';
  const savedRatio = parseFloat(safeGetPreference(PREFERENCE_KEYS.SPLIT_RATIO) ?? '0.35') || 0.35;

  const modeManager = new WindowModeManager(win, tabManager, savedMode, savedRatio);
  registerModeManager(win, modeManager);
  modeManager.setModeChangeCallback((mode) => sendToWin('window:mode-changed', mode));

  setupDownloadManager(session.defaultSession, win, false);

  win.on('resize', () => modeManager.applyBounds());

  // Failsafe: never leave the user with an invisible window. If the renderer
  // fails to load (so 'ready-to-show' never fires), show the window anyway
  // after a short grace period so at least the frame is visible.
  win.webContents.on('did-fail-load', (_e, code, desc, url) => {
    console.error(`Renderer failed to load (${code} ${desc}) at ${url}`);
  });
  const showFailsafe = setTimeout(() => {
    closeSplash();
    if (!win.isDestroyed() && !win.isVisible()) win.show();
  }, 6000);

  void win.loadURL(getRendererUrl());

  // Setup permission request / check handlers
  setupPermissionHandlers(
    session.defaultSession,
    win,
    (wc) => tabManager?.getTabIdByWebContentsId(wc.id) !== null,
  );

  // Setup tracker / ad blocking
  const trackerInitialEnabled = (safeGetPreference(PREFERENCE_KEYS.TRACKER_BLOCKING_ENABLED) ?? '0') === '1';
  setupTrackerBlocking(
    session.defaultSession,
    win,
    trackerInitialEnabled,
    (wcId) => tabManager?.getTabIdByWebContentsId(wcId) ?? null,
  );

  // Per-site content-blocker override ("Settings for this website"). Private
  // windows run in non-persistent sessions and never read per-site settings.
  setSiteOverrideResolver((wcId) => {
    try {
      const wc = webContents.fromId(wcId);
      if (!wc || wc.isDestroyed()) return null;
      if (!wc.session.isPersistent()) return null;
      const url = wc.getURL();
      if (!url) return null;
      const origin = new URL(url).origin;
      if (!origin.startsWith('http')) return null;
      return contentBlockerOverrideForOrigin(origin);
    } catch {
      return null;
    }
  });

  // Setup privacy controls (Do Not Track header, third-party cookie blocking)
  setupPrivacyControls(
    session.defaultSession,
    (safeGetPreference(PREFERENCE_KEYS.DO_NOT_TRACK) ?? '0') === '1',
    (safeGetPreference(PREFERENCE_KEYS.BLOCK_THIRD_PARTY_COOKIES) ?? '0') === '1',
  );

  // Tabs run in per-profile partition sessions (not the default session), so
  // install the tracker + privacy hooks on the active profile session too.
  {
    const profileSession = session.fromPartition(sessionPartitionForProfile(savedProfileId));
    installTrackerHooks(profileSession);
    installPrivacyHooks(profileSession);
    // Tabs live in this profile partition, so permission gating (including the
    // display-media / screen-sharing handler) must be installed here too.
    setupPermissionHandlers(
      profileSession,
      win,
      (wc) => tabManager?.getTabIdByWebContentsId(wc.id) !== null,
    );
  }

  win.once('ready-to-show', () => {
    clearTimeout(showFailsafe);
    closeSplash();
    win.show();
    modeManager.setMode(savedMode);
    {
      // Restore pinned + session tabs regardless of the launch mode. This used
      // to run only for 'browser', which made all previous tabs vanish when
      // the app launched in dashboard/split mode.
      // Restore pinned tabs from persistence (background, so they load silently)
      const savedPinnedJson = safeGetPreference(PREFERENCE_KEYS.PINNED_TABS) ?? '[]';
      let savedPinnedUrls: string[] = [];
      try {
        savedPinnedUrls = JSON.parse(savedPinnedJson) as string[];
      } catch {
        savedPinnedUrls = [];
      }
      let pinnedIds: string[] = [];
      if (savedPinnedUrls.length > 0) {
        pinnedIds = tabManager?.initPinnedUrls(savedPinnedUrls) ?? [];
      }

      // "On startup" preference: 'continue' (default) restores the previous
      // session's tabs; 'newtab' always starts fresh (pinned tabs still load).
      const startupMode = safeGetPreference(PREFERENCE_KEYS.STARTUP_MODE) ?? 'continue';

      // Restore the previous session's open tabs (in order, with active tab)
      const savedSessionJson = startupMode === 'newtab'
        ? ''
        : (safeGetPreference(PREFERENCE_KEYS.SESSION_TABS) ?? '');
      let sessionUrls: string[] = [];
      let sessionActiveIndex = -1;
      let sessionActivePinnedIndex = -1;
      try {
        const snap = JSON.parse(savedSessionJson) as { urls?: unknown; activeIndex?: unknown; activePinnedIndex?: unknown };
        if (Array.isArray(snap?.urls)) {
          sessionUrls = snap.urls.filter((u): u is string => typeof u === 'string' && u.length > 0);
        }
        if (typeof snap?.activeIndex === 'number') {
          sessionActiveIndex = snap.activeIndex;
        }
        if (typeof snap?.activePinnedIndex === 'number') {
          sessionActivePinnedIndex = snap.activePinnedIndex;
        }
      } catch {
        // No / invalid saved session — fall through to a fresh new tab
      }

      if (sessionUrls.length > 0 || (sessionActivePinnedIndex >= 0 && pinnedIds.length > 0)) {
        tabManager.restoreSessionTabs(sessionUrls, sessionActiveIndex);
        // If the previously active tab was a pinned tab, re-activate it now
        // (restoreSessionTabs only handles the non-pinned active case).
        if (sessionActiveIndex === -1 && sessionActivePinnedIndex >= 0) {
          const pinnedActive = pinnedIds[sessionActivePinnedIndex] ?? pinnedIds[pinnedIds.length - 1];
          if (pinnedActive) tabManager.activateTab(pinnedActive);
        }
        if (sessionUrls.length === 0) {
          // Only pinned tabs were open last session — nothing else to restore,
          // but make sure a pinned tab (not a fresh new tab) is showing.
          const fallback = pinnedIds[0];
          if (!tabManager.getActiveTabId() && fallback) tabManager.activateTab(fallback);
        }
      } else {
        // Open the default new tab (active, placed after pinned tabs)
        const newTabUrl = safeGetPreference(PREFERENCE_KEYS.NEW_TAB_PAGE) ?? undefined;
        tabManager.createTab(newTabUrl);
      }

      if (savedMode !== 'browser') {
        // Re-assert the launch mode: restoring/activating a tab attached and
        // focused its view, but dashboard/split own the startup layout.
        // setMode re-applies bounds (dashboard hides all tab views).
        modeManager.setMode(savedMode);
        if (savedMode === 'dashboard') {
          modeManager.getDashboardView()?.webContents.focus();
        }
      }
    }
    if (isDev) win.webContents.openDevTools({ mode: 'detach' });
  });

  // Persist the open (non-pinned) tabs so the next launch can restore them
  const persistSessionSnapshot = (): void => {
    try {
      const snapshot = tabManager.getSessionSnapshot();
      safeSetPreference(PREFERENCE_KEYS.SESSION_TABS, JSON.stringify(snapshot));
    } catch {
      // Never block window close on persistence errors
    }
  };
  win.on('close', persistSessionSnapshot);

  // Crash-recovery auto-snapshot: the 'close' event never fires on a crash,
  // so persist the open tabs every 30s while the window is alive. On the next
  // launch an unclean-exit flag triggers the "Restore previous session?"
  // prompt against this snapshot.
  const autoSnapshotTimer = setInterval(() => {
    if (!win.isDestroyed()) persistSessionSnapshot();
  }, 30_000);

  win.on('closed', () => {
    clearInterval(autoSnapshotTimer);
    modeManager.destroy();
    tabManager.destroyAll();
    if (win === mainWindow) mainWindow = null;
  });

  return win;
}

// ── Private / incognito window ────────────────────────────────────────────────

export function createPrivateWindow(startUrl?: string): BrowserWindow {
  const privateSession = getPrivateSession();

  const win = new BrowserWindow({
    width: 1280,
    height: 800,
    minWidth: 800,
    minHeight: 600,
    // Always dark — unmistakable visual signal that this is an incognito window
    title: '🔒 Private – Zio Browser',
    backgroundColor: '#0d0d1a',
    webPreferences: {
      preload: path.join(__dirname, '../preload/index.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
      webSecurity: true,
      // The renderer (app chrome) uses its own default session.
      // Only the tab WebContentsViews use the isolated private session.
    },
    // macOS: inset traffic lights over the app chrome.
    // Windows/Linux: hide the default frame and draw a dark overlay title bar
    // (with matching window controls) so the incognito window is unmistakably
    // distinct — the renderer supplies the drag-region header row.
    titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'hidden',
    ...(process.platform !== 'darwin'
      ? { titleBarOverlay: { color: '#0d0d1a', symbolColor: '#93c5fd', height: 36 } }
      : {}),
    trafficLightPosition: { x: 12, y: 20 },
    show: false,
  });

  // Register before any 'closed' listener so teardown fires correctly.
  registerPrivateWindow(win);

  // Private windows still read profile-scoped data (bookmarks/collections)
  // for the user's last-used profile, but never write history.
  const savedProfileId = safeGetPreference(PREFERENCE_KEYS.ACTIVE_PROFILE) ?? DEFAULT_PROFILE_ID;
  registerWindowProfile(win, savedProfileId);

  // Private TabManager uses the isolated in-memory session for all tabs.
  const tabManager = new TabManager(win, { privateSession });
  registerTabManager(win, tabManager);

  // Guard against sends after the window is destroyed (quit-time teardown).
  const sendToWin = (channel: string, ...args: unknown[]): void => {
    if (!win.isDestroyed() && !win.webContents.isDestroyed()) {
      win.webContents.send(channel, ...args);
    }
  };
  tabManager.setCallbacks({
    onTabStateChange: (tabId, state) => sendToWin('tab:state-changed', tabId, state),
    onTabCreated:      (tabId)        => sendToWin('tab:created', tabId),
    onTabClosed:       (tabId)        => sendToWin('tab:closed', tabId),
    onActiveTabChange: (tabId)        => sendToWin('tab:activated', tabId),
    onNavigate:        (tabId, url, title) => sendToWin('tab:navigated', tabId, url, title),
    // Link tools (shorten/QR) still work in private mode — they require the
    // account credentials but the visited page itself is never recorded.
    onAddToBiolink: (url, title) => sendToWin('biolink:add-page', url, title),
    onShortenPage: (url, title) => sendToWin('link:shorten-page', url, title),
    onCreateQr: (url, title) => sendToWin('link:create-qr', url, title),
    onAutofillPage: (tabId) => sendToWin('autofill:page', tabId),
    onDeviceLabPreview: (url) => sendToWin('device-lab:preview-url', url),
    onFindResult: (result) => sendToWin('tab:find-result', result),
    // Private windows still honor stored mute policy (read-only)…
    resolveAutoMute: (url) => {
      try {
        if (getMuteAllTabs()) return true;
        const host = hostForMutePolicy(url);
        return host !== null && isDomainMuted(host);
      } catch {
        return false;
      }
    },
    // …but never persist new mute preferences (no onUserMuteChange).
    resolveSpellcheckEnabled: () =>
      (safeGetPreference(PREFERENCE_KEYS.SPELLCHECK_ENABLED) ?? '1') === '1',
    resolveTranslateLang: () =>
      safeGetPreference(PREFERENCE_KEYS.TRANSLATE_TARGET_LANG) ?? 'en',
  });

  // Private windows are browser-only — no dashboard or split pane.
  const modeManager = new WindowModeManager(win, tabManager, 'browser', 0.35);
  registerModeManager(win, modeManager);
  modeManager.setModeChangeCallback((mode) => sendToWin('window:mode-changed', mode));

  // Downloads complete normally but are NOT written to the persistent DB.
  setupDownloadManager(privateSession, win, true);

  win.on('resize', () => modeManager.applyBounds());

  win.webContents.on('did-fail-load', (_e, code, desc, url) => {
    console.error(`Renderer failed to load (${code} ${desc}) at ${url}`);
  });
  const showFailsafe = setTimeout(() => {
    if (!win.isDestroyed() && !win.isVisible()) win.show();
  }, 6000);

  void win.loadURL(getRendererUrl());

  win.once('ready-to-show', () => {
    clearTimeout(showFailsafe);
    win.show();
    modeManager.setMode('browser');
    tabManager.createTab(startUrl);
  });

  win.on('closed', () => {
    modeManager.destroy();
    tabManager.destroyAll();
  });

  return win;
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Electron menu click callbacks type `win` as `BaseWindow | undefined`.
 * Cast to `BrowserWindow` so our registry lookups compile.
 * `BrowserWindow` IS a `BaseWindow`, so the cast is always safe when the
 * menu is triggered from a `BrowserWindow` — which is always the case for
 * this application.
 */
function asBrowserWin(win: BaseWindow | undefined): BrowserWindow | undefined {
  return win as BrowserWindow | undefined;
}

// ── Application menu ──────────────────────────────────────────────────────────

function buildMenu(): void {
  const isMac = process.platform === 'darwin';

  const template: Electron.MenuItemConstructorOptions[] = [
    ...(isMac ? [{ label: app.getName(), submenu: [
      { role: 'about' as const },
      { type: 'separator' as const },
      { role: 'services' as const },
      { type: 'separator' as const },
      { role: 'hide' as const },
      { role: 'hideOthers' as const },
      { role: 'unhide' as const },
      { type: 'separator' as const },
      { role: 'quit' as const },
    ] }] : []),
    {
      label: 'File',
      submenu: [
        {
          label: 'New Tab',
          accelerator: 'CmdOrCtrl+T',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const mm = getModeManagerForWindow(browserWin);
            if (!tm) return;
            if (mm?.getMode() === 'dashboard') {
              mm.setMode('browser');
            }
            tm.createTab();
          },
        },
        {
          label: 'New Window',
          accelerator: 'CmdOrCtrl+N',
          click: () => { createWindow(); },
        },
        {
          label: 'New Private Window',
          accelerator: 'CmdOrCtrl+Shift+N',
          click: () => { createPrivateWindow(); },
        },
        {
          label: 'Close Tab',
          accelerator: 'CmdOrCtrl+W',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const id = tm?.getActiveTabId();
            if (id) tm?.closeTab(id);
          },
        },
        {
          label: 'Reopen Closed Tab',
          accelerator: 'CmdOrCtrl+Shift+T',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getTabManagerForWindow(browserWin)?.reopenClosedTab();
          },
        },
        { type: 'separator' },
        {
          label: 'Print…',
          accelerator: 'CmdOrCtrl+P',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const id = tm?.getActiveTabId();
            const wc = id ? tm?.getWebContents(id) : null;
            if (wc && !wc.isDestroyed()) wc.print();
          },
        },
        { type: 'separator' },
        isMac ? { role: 'close' as const } : { role: 'quit' as const },
      ],
    },
    {
      label: 'Edit',
      submenu: [
        { role: 'undo' as const },
        { role: 'redo' as const },
        { type: 'separator' as const },
        { role: 'cut' as const },
        { role: 'copy' as const },
        { role: 'paste' as const },
        { role: 'selectAll' as const },
        { type: 'separator' as const },
        {
          label: 'Command Palette',
          accelerator: 'CmdOrCtrl+K',
          click: (_item, bw) => {
            asBrowserWin(bw)?.webContents.send('palette:open');
          },
        },
        { label: 'Find on Page', accelerator: 'CmdOrCtrl+F', click: (_item, bw) => {
          asBrowserWin(bw)?.webContents.send('find:open');
        }},
        {
          label: 'Search Tabs',
          accelerator: 'CmdOrCtrl+Shift+A',
          click: () => {
            mainWindow?.webContents.send('tab:search-open');
          },
        },
      ],
    },
    {
      label: 'View',
      submenu: [
        {
          label: 'Dashboard Mode',
          accelerator: 'CmdOrCtrl+Shift+1',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getModeManagerForWindow(browserWin)?.setMode('dashboard');
          },
        },
        {
          label: 'Split Mode',
          accelerator: 'CmdOrCtrl+Shift+2',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getModeManagerForWindow(browserWin)?.setMode('split');
          },
        },
        {
          label: 'Browser Mode',
          accelerator: 'CmdOrCtrl+Shift+3',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getModeManagerForWindow(browserWin)?.setMode('browser');
          },
        },
        { type: 'separator' as const },
        { label: 'Zoom In', accelerator: 'CmdOrCtrl+=', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) { const s = tm?.getTabState(id); tm?.setZoom(id, (s?.zoomFactor ?? 1) + 0.1); }
        }},
        { label: 'Zoom Out', accelerator: 'CmdOrCtrl+-', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) { const s = tm?.getTabState(id); tm?.setZoom(id, (s?.zoomFactor ?? 1) - 0.1); }
        }},
        { label: 'Reset Zoom', accelerator: 'CmdOrCtrl+0', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.setZoom(id, 1.0);
        }},
        { type: 'separator' as const },
        { label: 'Reload', accelerator: 'CmdOrCtrl+R', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.reload(id);
        }},
        { label: 'Force Reload', accelerator: 'CmdOrCtrl+Shift+R', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.reload(id, true);
        }},
        { type: 'separator' as const },
        { label: 'Reader Mode', accelerator: 'CmdOrCtrl+Alt+R', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id && tm) {
            void tm.enterReaderMode(id).then((ok) => {
              if (!ok && !browserWin.isDestroyed()) {
                browserWin.webContents.send('toast:show', 'Reader mode isn’t available for this page.');
              }
            });
          }
        }},
        { type: 'separator' as const },
        { label: 'Developer Tools', accelerator: 'F12', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.getWebContents(id)?.openDevTools();
        }},
      ],
    },
    {
      label: 'History',
      submenu: [
        { label: 'Back', accelerator: 'Alt+Left', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.goBack(id);
        }},
        { label: 'Forward', accelerator: 'Alt+Right', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.goForward(id);
        }},
      ],
    },
    {
      label: 'Window',
      submenu: [
        { role: 'minimize' as const },
        { role: 'zoom' as const },
        ...(isMac ? [{ type: 'separator' as const }, { role: 'front' as const }] : []),
      ],
    },
  ];

  Menu.setApplicationMenu(Menu.buildFromTemplate(template));
}

// ── App lifecycle ─────────────────────────────────────────────────────────────

app.whenReady().then(() => {
  // Show the branded splash immediately — before any heavy startup work.
  createSplashWindow();
  try {
    initDb();
  } catch (err) {
    console.error('Failed to initialize database:', err);
    // Fall back to an in-memory database so the browser still opens —
    // history/bookmarks won't persist this session, but nothing crashes.
    try {
      initDb(':memory:');
      console.error('Using in-memory database fallback (no persistence this session).');
    } catch (err2) {
      console.error('In-memory database fallback also failed:', err2);
      // Surface the ORIGINAL failure — it names the real cause (e.g. a native
      // module built for the wrong CPU architecture). The app continues in
      // degraded mode: browsing works, history/bookmarks/sync are off.
      reportStartupError('Local database unavailable', err);
    }
  }
  // ── History auto-delete (retention sweep) ────────────────────────────────
  // When the user picks an auto-delete window (e.g. 30 days), prune older
  // history at startup and every 6 hours while the app runs. '0'/unset = keep
  // forever. Never let a sweep failure interfere with startup.
  const runHistoryRetentionSweep = () => {
    try {
      const days = parseInt(safeGetPreference(PREFERENCE_KEYS.HISTORY_DAYS_RETENTION) ?? '0', 10);
      if (days > 0) pruneHistoryOlderThan(days);
    } catch { /* sweep is best-effort */ }
  };
  runHistoryRetentionSweep();
  setInterval(runHistoryRetentionSweep, 6 * 60 * 60 * 1000);

  // ── Crash recovery ──────────────────────────────────────────────────────
  // '0' means the previous run never reached before-quit — i.e. it crashed or
  // was force-killed. Offer to restore (the periodic auto-snapshot keeps
  // SESSION_TABS fresh even without a clean close). Declining starts fresh.
  const previousExitUnclean = safeGetPreference(PREFERENCE_KEYS.CLEAN_EXIT) === '0';
  safeSetPreference(PREFERENCE_KEYS.CLEAN_EXIT, '0');
  if (previousExitUnclean) {
    let hasSnapshot = false;
    try {
      const snap = JSON.parse(safeGetPreference(PREFERENCE_KEYS.SESSION_TABS) ?? '') as { urls?: unknown };
      hasSnapshot = Array.isArray(snap?.urls) && snap.urls.length > 0;
    } catch {
      hasSnapshot = false;
    }
    if (hasSnapshot) {
      const choice = dialog.showMessageBoxSync({
        type: 'question',
        title: 'Zio Browser',
        message: "Zio Browser didn't close properly last time.",
        detail: 'Do you want to restore the tabs you had open?',
        buttons: ['Restore tabs', 'Start fresh'],
        defaultId: 0,
        cancelId: 1,
      });
      if (choice === 1) {
        // Start fresh — drop the stale snapshot so the window opens a new tab.
        safeSetPreference(PREFERENCE_KEYS.SESSION_TABS, '');
      }
    }
  }

  // Load persisted unpacked extensions into the default session before the
  // first window opens (fail-soft — a broken extension never blocks startup).
  // Built-in first (so it claims its id), then user extensions.
  void loadBuiltinExtension()
    .catch((err) => {
      console.error('Failed to load built-in extension:', err);
    })
    .then(() => loadStoredExtensions())
    .catch((err) => {
      console.error('Failed to load stored extensions:', err);
  });
  try {
    mainWindow = createWindow();
    setupAutoUpdater();

    // Register IPC handlers once — global, serves all windows.
    registerIpcHandlers(mainWindow);

    // Logout: close every open window (normal + private) and open one fresh
    // logged-out window so no signed-in state remains visible anywhere.
    setLogoutHandler(() => {
      // Open the fresh logged-out window FIRST so there is never a
      // zero-window moment — otherwise 'window-all-closed' would quit
      // the app on Windows/Linux before the new window appears.
      const oldWindows = BrowserWindow.getAllWindows();
      mainWindow = createWindow();
      for (const win of oldWindows) {
        if (!win.isDestroyed()) win.destroy();
      }
    });

    buildMenu();
  } catch (err) {
    closeSplash();
    reportStartupError('Failed to start', err);
  }

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      mainWindow = createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

// Mark the exit as clean so the next launch skips the crash-recovery prompt.
app.on('before-quit', () => {
  safeSetPreference(PREFERENCE_KEYS.CLEAN_EXIT, '1');
});

// Security: Prevent new window creation from web content
app.on('web-contents-created', (_, contents) => {
  contents.on('will-attach-webview', (event) => {
    event.preventDefault();
  });
});

// Silence the unused CHROME_HEIGHT import warning
void CHROME_HEIGHT;
