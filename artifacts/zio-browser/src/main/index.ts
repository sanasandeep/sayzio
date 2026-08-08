/**
 * Zio Browser — Electron main process entry point.
 */
import path from 'path';
import { app, BrowserWindow, Menu, session, dialog, webContents } from 'electron';
import type { BaseWindow } from 'electron';
import { initDb, getPreference, setPreference, getMuteAllTabs, isDomainMuted, setDomainMuted, pruneHistoryOlderThan, setSiteSettings, addBookmark, isBookmarked, getAllBookmarks, getRecentHistory } from './db';
import { chromePrefersDark, restoreWebsiteAppearance, initThemeBridge } from './theme';
import { resolveSiteSettingsForUrl, contentBlockerOverrideForOrigin, adBlockOverrideForOrigin, invalidateSiteSettingsCache } from './site-settings';
import { PREFERENCE_KEYS, type PreferenceKey } from '../shared/db-schema';
import { VK_PREF_KEYS } from '../shared/virtual-keyboard';
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
  profileIdForWindow,
  setLogoutHandler,
} from './ipc-handlers';
import { setupDownloadManager } from './download-manager';
import { getPrivateSession, registerPrivateWindow } from './private-session';
import { setupPermissionHandlers } from './permission-handler';
import { setupTrackerBlocking, resetBlockedCount, installTrackerHooks, setSiteOverrideResolver } from './tracker-blocker';
import { initAdBlocker, setAdBlockPolicyResolver, isAdBlockingEffectiveForWc, getCosmeticStylesForUrl } from './ad-blocker';
import { initAdBlockPolicy, startAdminPolicySync, isAdBlockActiveForWc, overrideForRequestHost, getStrength } from './adblock-policy';
import { setRequestHostOverrideResolver } from './tracker-blocker';
import { setupPrivacyControls, installPrivacyHooks } from './privacy';
import type { WindowMode } from '../shared/window-mode';
import { ZIO_PANEL_DIVIDER_WIDTH } from '../shared/window-mode';
import { setupAutoUpdater } from './auto-updater';
import { loadStoredExtensions, loadBuiltinExtension } from './extension-manager';
import type { RecentlyClosedEntry, SessionTabLayout } from './tab-manager';

const isDev = process.env['NODE_ENV'] === 'development';

let mainWindow: BrowserWindow | null = null;
// True once a restore-or-fresh decision was already made this launch (either
// via the crash-recovery prompt or the 'Ask me every time' startup prompt),
// so the user is never asked twice in one launch.
let startupRestorePromptShown = false;
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
  .logo{position:relative;width:96px;height:96px;margin-bottom:20px}
  .core{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    animation:pop .6s cubic-bezier(.2,1.4,.4,1) both}
  .core img{width:96px;height:96px;object-fit:contain;
    filter:drop-shadow(0 10px 32px rgba(122,92,255,.55));
    animation:bob 2.4s .6s ease-in-out infinite}
  .glow{position:absolute;inset:-18px;border-radius:50%;
    background:radial-gradient(50% 50% at 50% 50%,rgba(122,92,255,.35) 0%,rgba(122,92,255,0) 70%);
    animation:breathe 2.4s .6s ease-in-out infinite}
  .ring{position:absolute;inset:-12px;border-radius:50%;border:2px solid rgba(120,130,255,.5);
    animation:pulse 1.6s ease-out infinite}
  .name{font-size:22px;font-weight:700;letter-spacing:-.5px;animation:fade .8s .15s ease both}
  .by{font-size:12px;color:rgba(200,200,255,.55);margin-top:6px;animation:fade .8s .3s ease both}
  .bar{width:150px;height:3px;border-radius:2px;background:rgba(255,255,255,.10);
    margin-top:26px;overflow:hidden;animation:fade .8s .4s ease both}
  .bar i{display:block;width:40%;height:100%;border-radius:2px;
    background:linear-gradient(90deg,#4f7cff,#7a5cff);
    animation:slide 1.1s ease-in-out infinite}
  @keyframes pulse{0%{transform:scale(.92);opacity:.9}100%{transform:scale(1.25);opacity:0}}
  @keyframes bob{0%,100%{transform:translateY(0) rotate(-2deg)}50%{transform:translateY(-7px) rotate(2deg)}}
  @keyframes breathe{0%,100%{opacity:.7;transform:scale(.95)}50%{opacity:1;transform:scale(1.08)}}
  @keyframes pop{from{transform:scale(.6);opacity:0}to{transform:scale(1);opacity:1}}
  @keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
  @keyframes slide{0%{margin-left:-40%}100%{margin-left:100%}}
</style></head><body>
  <div class="logo"><div class="glow"></div><div class="ring"></div><div class="core"><img alt="" src="data:image/png;base64,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"></div></div>
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
    backgroundColor: chromePrefersDark() ? '#1a1a2e' : '#ffffff',
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
  // DEFERRED until after the window is visible: retrieving the token hits
  // safeStorage synchronously, and on macOS (ad-hoc-signed builds especially)
  // that pops a blocking keychain password prompt. Running it before the
  // chrome renderer loaded used to leave the whole window white until the
  // user answered the prompt.
  let webSessionSeeded = false;
  const seedWebSessionOnceVisible = (): void => {
    if (webSessionSeeded) return;
    webSessionSeeded = true;
    // Small delay so the first chrome paint lands before any keychain prompt.
    setTimeout(() => { void seedSayzioWebSession(sessionPartitionForProfile(savedProfileId)); }, 400);
  };


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
    // Reserve room on the right for the renderer-drawn docked Ask Zio panel.
    // The renderer reports visibility via window:set-zio-panel-visible exactly
    // when it draws the docked panel (toggle-open OR zio-split tab mode), so
    // this single callback is the ONLY place the reserve is computed — the
    // old split between applyBrowserBounds() and this callback let the two
    // disagree and the native view covered the panel's left edge + divider.
    // Mode-aware: the docked panel only exists in browser window mode, so a
    // stale visible=true (renderer hasn't reported yet after a main-process
    // mode switch to split/dashboard) must not narrow those layouts.
    resolveZioPanelReserve: () =>
      modeManager && modeManager.getMode() === 'browser' && modeManager.getZioPanelVisible()
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
    // Virtual keyboard: reporter injection gate + field-focus relay.
    resolveVkEnabled: () => safeGetPreference(VK_PREF_KEYS.ENABLED) === '1',
    onVkFocus: (payload) => sendToWin('vk:focus', payload),
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
    seedWebSessionOnceVisible();
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

  // Full ad blocker (EasyList/EasyPrivacy engine) — separate toggle, off by
  // default. Shares the tracker-blocker webRequest dispatcher. The layered
  // policy resolver (admin policy → pauses → per-site "Ads" override → user
  // lists → global toggle/strength) owns the effective on/off decision, and
  // the request-host override enforces admin/user domain lists in every
  // session (private windows included), even when the global toggle is off.
  const adBlockInitialEnabled = (safeGetPreference(PREFERENCE_KEYS.AD_BLOCKING_ENABLED) ?? '0') === '1';
  initAdBlocker(adBlockInitialEnabled);
  initAdBlockPolicy(adBlockInitialEnabled);
  setAdBlockPolicyResolver((wcId) => isAdBlockActiveForWc(wcId));
  setRequestHostOverrideResolver((host) => overrideForRequestHost(host));
  startAdminPolicySync();

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
    // The startup mode pick can recreate the window and destroy this one
    // while this callback is still queued — restoring the session into a
    // destroyed window throws mid-restore and strands the whole launch.
    // The replacement window runs its own ready-to-show restore.
    if (win.isDestroyed()) return;
    closeSplash();
    win.show();
    seedWebSessionOnceVisible();
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

      // "On startup" preference: 'ask' (default) prompts before restoring the
      // previous session's tabs; 'continue' restores silently; 'newtab'
      // always starts fresh (pinned tabs still load).
      const startupModeRaw = safeGetPreference(PREFERENCE_KEYS.STARTUP_MODE) ?? 'ask';
      const startupMode = startupModeRaw === 'continue' || startupModeRaw === 'newtab'
        ? startupModeRaw
        : 'ask';

      // Restore the previous session's open tabs (in order, with active tab)
      const savedSessionJson = startupMode === 'newtab'
        ? ''
        : (safeGetPreference(PREFERENCE_KEYS.SESSION_TABS) ?? '');
      let sessionUrls: string[] = [];
      let sessionActiveIndex = -1;
      let sessionActivePinnedIndex = -1;
      let sessionLayouts: (SessionTabLayout | null)[] | undefined;
      try {
        const snap = JSON.parse(savedSessionJson) as { urls?: unknown; activeIndex?: unknown; activePinnedIndex?: unknown; layouts?: unknown };
        if (Array.isArray(snap?.urls)) {
          // Keep layouts index-aligned with the filtered URL list.
          const rawLayouts = Array.isArray(snap?.layouts) ? (snap.layouts as unknown[]) : null;
          const filteredLayouts: (SessionTabLayout | null)[] = [];
          snap.urls.forEach((u, i) => {
            if (typeof u === 'string' && u.length > 0) {
              sessionUrls.push(u);
              const l = rawLayouts?.[i];
              filteredLayouts.push(l && typeof l === 'object' && typeof (l as SessionTabLayout).mode === 'string' ? (l as SessionTabLayout) : null);
            }
          });
          if (rawLayouts) sessionLayouts = filteredLayouts;
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

      // 'Ask me every time': confirm before restoring. Skipped when the
      // crash-recovery prompt already asked this launch, and when there is
      // nothing to restore. Declining keeps the snapshot on disk untouched —
      // it is simply not restored into this window.
      if (startupMode === 'ask' && !startupRestorePromptShown && sessionUrls.length > 0) {
        startupRestorePromptShown = true;
        const choice = dialog.showMessageBoxSync(win, {
          type: 'question',
          title: 'Zio Browser',
          message: 'Restore your previous tabs?',
          detail: `You had ${sessionUrls.length === 1 ? '1 tab' : `${sessionUrls.length} tabs`} open last time.`,
          buttons: ['Restore tabs', 'Start fresh'],
          defaultId: 0,
          cancelId: 1,
        });
        if (choice === 1) {
          sessionUrls = [];
          sessionLayouts = undefined;
          sessionActiveIndex = -1;
          sessionActivePinnedIndex = -1;
        }
      }

      if (sessionUrls.length > 0 || (sessionActivePinnedIndex >= 0 && pinnedIds.length > 0)) {
        tabManager.restoreSessionTabs(sessionUrls, sessionActiveIndex, sessionLayouts);
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

  // Private-window tabs run in an isolated in-memory session, so the tracker
  // + ad-blocking dispatcher must be installed on it too (idempotent). The
  // per-site override resolvers skip non-persistent sessions, so private
  // windows always follow the global toggles only.
  installTrackerHooks(privateSession);

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
    // Virtual keyboard works in private windows too — typing is just never
    // learned (vk:record-words rejects private senders).
    resolveVkEnabled: () => safeGetPreference(VK_PREF_KEYS.ENABLED) === '1',
    onVkFocus: (payload) => sendToWin('vk:focus', payload),
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

/** Navigate the focused window's active tab (or open a new tab) to a URL. */
function openUrlInWindow(bw: BaseWindow | undefined, url: string): void {
  const browserWin = asBrowserWin(bw) ?? BrowserWindow.getFocusedWindow() ?? mainWindow ?? undefined;
  if (!browserWin || browserWin.isDestroyed()) return;
  const tm = getTabManagerForWindow(browserWin);
  if (!tm) return;
  const mm = getModeManagerForWindow(browserWin);
  if (mm?.getMode() === 'dashboard') mm.setMode('browser');
  const activeId = tm.getActiveTabId();
  if (activeId) tm.navigate(activeId, url);
  else tm.createTab(url);
}

/** Profile scope for menu-built bookmark/history lists (focused window). */
function menuProfileId(): string {
  const win = BrowserWindow.getFocusedWindow() ?? mainWindow;
  return win && !win.isDestroyed() ? profileIdForWindow(win) : DEFAULT_PROFILE_ID;
}

/** Trim long page titles so menu rows stay readable. */
function menuLabel(title: string | null | undefined, url: string): string {
  const raw = (title && title.trim()) || url;
  return raw.length > 60 ? `${raw.slice(0, 57)}…` : raw;
}

function buildMenu(): void {
  const isMac = process.platform === 'darwin';

  // Dynamic rows are computed at build time; the menu is rebuilt on every
  // window focus (and after Bookmark This Page) so they stay fresh.
  const profileId = menuProfileId();
  let bookmarkItems: Electron.MenuItemConstructorOptions[] = [];
  let recentHistoryItems: Electron.MenuItemConstructorOptions[] = [];
  try {
    bookmarkItems = getAllBookmarks(undefined, profileId).slice(0, 15).map((b) => ({
      label: menuLabel(b.title, b.url),
      click: (_item: Electron.MenuItem, bw: BaseWindow | undefined) => openUrlInWindow(bw, b.url),
    }));
  } catch { /* db unavailable — menu stays static */ }
  try {
    recentHistoryItems = getRecentHistory(10, profileId).map((h) => ({
      label: menuLabel(h.title, h.url),
      click: (_item: Electron.MenuItem, bw: BaseWindow | undefined) => openUrlInWindow(bw, h.url),
    }));
  } catch { /* db unavailable — menu stays static */ }

  const settingsItem: Electron.MenuItemConstructorOptions = {
    label: 'Settings…',
    accelerator: 'CmdOrCtrl+,',
    click: (_item, bw) => {
      const browserWin = asBrowserWin(bw) ?? BrowserWindow.getFocusedWindow() ?? mainWindow ?? undefined;
      if (browserWin && !browserWin.isDestroyed()) browserWin.webContents.send('settings:open');
    },
  };

  const template: Electron.MenuItemConstructorOptions[] = [
    ...(isMac ? [{ label: app.getName(), submenu: [
      { role: 'about' as const },
      { type: 'separator' as const },
      settingsItem,
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
        {
          label: 'Save Page As…',
          accelerator: 'CmdOrCtrl+S',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const id = tm?.getActiveTabId();
            if (id) void tm?.savePageAs(id);
          },
        },
        { type: 'separator' },
        ...(!isMac ? [settingsItem, { type: 'separator' as const }] : []),
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
        {
          label: 'Swap Split Panes',
          accelerator: 'CmdOrCtrl+Shift+S',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const id = tm?.getActiveTabId();
            if (id) tm?.swapPanes(id);
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
        { label: 'Stop', accelerator: 'CmdOrCtrl+.', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.stop(id);
        }},
        { label: 'View Page Source', accelerator: 'CmdOrCtrl+U', click: (_item, bw) => {
          const browserWin = asBrowserWin(bw);
          if (!browserWin) return;
          const tm = getTabManagerForWindow(browserWin);
          const id = tm?.getActiveTabId();
          if (id) tm?.viewPageSource(id);
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
      label: 'Bookmarks',
      submenu: [
        {
          label: 'Bookmark This Page',
          accelerator: 'CmdOrCtrl+D',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            const tm = getTabManagerForWindow(browserWin);
            const id = tm?.getActiveTabId();
            const state = id ? tm?.getTabState(id) : null;
            const url = state?.url;
            if (!url || url === 'about:newtab' || url.startsWith('about:')) return;
            const pid = profileIdForWindow(browserWin);
            try {
              if (!isBookmarked(url, pid)) {
                addBookmark(url, state?.title || url, {}, pid);
              }
              browserWin.webContents.send('bookmarks:changed');
              browserWin.webContents.send('toast:show', 'Bookmarked this page.');
              buildMenu();
            } catch { /* db unavailable */ }
          },
        },
        ...(bookmarkItems.length > 0
          ? [{ type: 'separator' as const }, ...bookmarkItems]
          : []),
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
        {
          label: 'Reopen Closed Tab',
          click: (_item, bw) => {
            const browserWin = asBrowserWin(bw);
            if (!browserWin) return;
            getTabManagerForWindow(browserWin)?.reopenClosedTab();
          },
        },
        ...(recentHistoryItems.length > 0
          ? [
              { type: 'separator' as const },
              { label: 'Recently Visited', enabled: false },
              ...recentHistoryItems,
            ]
          : []),
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

// Branded app name — drives the macOS application menu title and OS-level
// surfaces that read app.getName(). Must be set before the menu is built.
app.setName('Zio Browser');

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
  // ── Website appearance ───────────────────────────────────────────────────
  // Restore the persisted website color-scheme override (default: system) —
  // this owns nativeTheme.themeSource; the chrome Appearance setting never
  // touches it. The bridge relays real OS scheme changes to all windows.
  try {
    restoreWebsiteAppearance();
    initThemeBridge();
  } catch { /* fall back to Electron's default 'system' */ }

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
      startupRestorePromptShown = true;
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

  // Refresh the dynamic Bookmarks/History menu rows whenever a window gains
  // focus (also re-scopes them to that window's active profile).
  app.on('browser-window-focus', () => {
    try { buildMenu(); } catch { /* keep the previous menu */ }
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

  // Cosmetic (element-hiding) ad filtering: inject the EasyList element-hiding
  // CSS on every http(s) document once the DOM is ready. Covers tabs in all
  // profile sessions and private windows; a fresh document per navigation
  // means no cleanup is needed.
  contents.on('dom-ready', () => {
    try {
      if (contents.isDestroyed()) return;
      if (!isAdBlockingEffectiveForWc(contents.id)) return;
      // Cosmetic element-hiding is the Strict extra; Balanced (default) does
      // network blocking only to minimize page breakage.
      if (getStrength() !== 'strict') return;
      const styles = getCosmeticStylesForUrl(contents.getURL());
      if (styles) void contents.insertCSS(styles, { cssOrigin: 'user' }).catch(() => { /* page may be gone */ });
    } catch {
      // Cosmetics are best-effort — never break page load.
    }
  });
});

// Silence the unused CHROME_HEIGHT import warning
void CHROME_HEIGHT;
