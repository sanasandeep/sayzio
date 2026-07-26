/**
 * IPC handlers — bridge between the renderer process and the main process.
 * All sensitive operations (DB, auth, downloads) run here.
 *
 * ipcMain.handle() registers GLOBAL handlers that serve every window, so
 * private-mode suppression is determined dynamically from event.sender
 * rather than at registration time.
 */
import { app, ipcMain, shell, dialog, clipboard, nativeTheme, BrowserWindow, session, nativeImage } from 'electron';
import * as fs from 'fs';
import * as path from 'path';
import { randomUUID } from 'crypto';
import type { TabManager } from './tab-manager';
import type { TabMode } from '../shared/window-mode';
import type { WindowModeManager } from './window-mode-manager';
import { SyncRetryRunner } from './sync-retry';
import { detectBrowsers, readBrowserData, parseBookmarksHtml } from './browser-import';
import type { SyncEntityKind } from '../shared/sync-engine';
import { isSyncDue, SYNC_INTERVALS } from '../shared/sync-engine';
import {
  initDb,
  isDbInitialized,
  getPreference,
  setPreference,
  getAllPreferences,
  recordVisit,
  searchHistory,
  getRecentHistory,
  clearHistory,
  clearHistoryByRange,
  pruneHistoryOlderThan,
  countHistorySince,
  deleteHistoryByHost,
  deleteHistoryEntry,
  addBookmark,
  importHistoryEntries,
  removeBookmark,
  isBookmarked,
  getAllBookmarks,
  searchBookmarks,
  getAllCollections,
  createCollectionInDb,
  updateCollection,
  deleteCollection,
  getSavedLinksForCollection,
  saveLinkToCollection,
  updateSavedLinkAiEnrichment,
  getRecentDownloads,
  searchDownloads,
  deleteDownload,
  clearAllDownloads,
  countDownloadsSince,
  clearDownloadsByRange,
  getSyncState,
  setSyncState,
  enqueueSyncPush,
  getCachedSayzioLinks,
  replaceSayzioLinksCache,
  clearSayzioLinksCache,
  countSyncQueue,
  countSyncQueueByProfile,
  savePassword,
  getPasswordsForOrigin,
  getAllSavedPasswords,
  deletePassword,
  deleteAllPasswords,
  listProfiles,
  upsertProfile,
  getActiveProfileId,
  getAllSitePermissions,
  setSitePermission,
  revokeSitePermission,
  clearAllSitePermissions,
  listNamedSessions,
  getNamedSession,
  saveNamedSession,
  deleteNamedSession,
  addToReadingList,
  isInReadingList,
  getReadingList,
  getUnreadCount,
  markReadingListItemRead,
  removeFromReadingList,
  getMutedDomains,
  setDomainMuted,
  getMuteAllTabs,
  setMuteAllTabs,
} from './db';
import { getActiveItem } from './download-manager';
import { resolvePermissionRequest } from './permission-handler';
import {
  setTrackerBlockingEnabled,
  isTrackerBlockingEnabled,
  getBlockedCount,
  resetBlockedCount,
  getTrackerStats,
  installTrackerHooks,
} from './tracker-blocker';
import { setDoNotTrack, setBlockThirdPartyCookies, installPrivacyHooks } from './privacy';
import { SEARCH_ENGINES } from '../shared/omnibox';
import { buildAutofillScript } from '../shared/form-autofill';
import type { AutofillCard } from '../shared/form-autofill';
import { storeToken, retrieveToken, clearToken, storeUser, retrieveUser, clearUser } from './auth-store';
import { encryptPassword, decryptPassword } from './password-store';
import { createCollection, createSavedLink } from '../shared/collection-store';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import type { WindowMode } from '../shared/window-mode';
import {
  DEFAULT_ZIO_PANEL_WIDTH,
  MIN_ZIO_PANEL_WIDTH,
  MAX_ZIO_PANEL_WIDTH,
} from '../shared/window-mode';
import { isPrivateWindow } from './private-session';
import { listExtensions, addExtensionFromDialog, removeExtension } from './extension-manager';
import { profileFromWorkspace, sessionPartitionForProfile, DEFAULT_PROFILE_ID } from '../shared/profile-store';
import { seedSayzioWebSession, resetSayzioSessionSeeds } from './sayzio-session';
import type { BrowserProfile } from '../shared/profile-store';

type PrefKey = typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS];

// ── Per-window registries ────────────────────────────────────────────────────

/**
 * Maps BrowserWindow id → TabManager so tab IPC handlers can resolve the
 * correct manager for the calling window (normal vs. private).
 */
const tabManagerRegistry = new Map<number, TabManager>();
const modeManagerRegistry = new Map<number, WindowModeManager>();

/**
 * Maps BrowserWindow id → active profile ID. Each window carries its OWN
 * active profile, so switching the profile in one window never changes the
 * DB scope (or session partition) of any other window — including background
 * sync running in another window.
 */
const windowProfileRegistry = new Map<number, string>();

export function registerWindowProfile(win: BrowserWindow, profileId: string): void {
  windowProfileRegistry.set(win.id, profileId);
  win.once('closed', () => windowProfileRegistry.delete(win.id));
}

/** Resolve the active profile for the window that sent an IPC event. */
function resolveProfileId(event: Electron.IpcMainInvokeEvent): string {
  const win = BrowserWindow.fromWebContents(event.sender);
  if (!win) return DEFAULT_PROFILE_ID;
  return windowProfileRegistry.get(win.id) ?? DEFAULT_PROFILE_ID;
}

export function registerTabManager(win: BrowserWindow, tabManager: TabManager): void {
  tabManagerRegistry.set(win.id, tabManager);
  win.once('closed', () => tabManagerRegistry.delete(win.id));
}

export function registerModeManager(win: BrowserWindow, modeManager: WindowModeManager): void {
  modeManagerRegistry.set(win.id, modeManager);
  win.once('closed', () => modeManagerRegistry.delete(win.id));
}

/**
 * Public accessors used by the menu (menu click callbacks receive the
 * focused BrowserWindow directly, so they bypass IPC entirely).
 */
export function getTabManagerForWindow(win: BrowserWindow): TabManager | null {
  return tabManagerRegistry.get(win.id) ?? null;
}

export function getModeManagerForWindow(win: BrowserWindow): WindowModeManager | null {
  return modeManagerRegistry.get(win.id) ?? null;
}

// ── IPC helpers ──────────────────────────────────────────────────────────────

/**
 * Resolve the TabManager for the window that sent an IPC event.
 */
function resolveTabManager(event: Electron.IpcMainInvokeEvent): TabManager | null {
  const win = BrowserWindow.fromWebContents(event.sender);
  if (!win) return null;
  return tabManagerRegistry.get(win.id) ?? null;
}

function resolveModeManager(event: Electron.IpcMainInvokeEvent): WindowModeManager | null {
  const win = BrowserWindow.fromWebContents(event.sender);
  if (!win) return null;
  return modeManagerRegistry.get(win.id) ?? null;
}

/** Lower-bound ISO timestamp for a browsing-data range ('all' → null). */
function rangeToSinceIso(range: string): string | null {
  if (range === 'all') return null;
  const msMap: Record<string, number> = {
    '15min': 15 * 60 * 1000,
    hour: 60 * 60 * 1000,
    day: 24 * 60 * 60 * 1000,
    week: 7 * 24 * 60 * 60 * 1000,
    '4weeks': 28 * 24 * 60 * 60 * 1000,
  };
  const ms = msMap[range];
  return ms ? new Date(Date.now() - ms).toISOString() : null;
}

/**
 * Return true when the IPC event was sent from a private/incognito window.
 */
function senderIsPrivate(event: Electron.IpcMainInvokeEvent): boolean {
  const win = BrowserWindow.fromWebContents(event.sender);
  return win !== null && isPrivateWindow(win);
}

/**
 * Every persistent session that may hold browsing data: the Electron default
 * session plus each known profile's partition session. Tabs run in per-profile
 * partitions, so clear/count/forget operations must cover all of them.
 */
function allDataSessions(): Electron.Session[] {
  const sessions: Electron.Session[] = [session.defaultSession];
  try {
    for (const profile of listProfiles()) {
      const sess = session.fromPartition(sessionPartitionForProfile(profile.id));
      if (!sessions.includes(sess)) sessions.push(sess);
    }
  } catch { /* best-effort — default session is always covered */ }
  return sessions;
}

/**
 * Validate a "forget this site" target host. Rejects anything that is not a
 * plausible registrable hostname (empty, single-label like "com", bare public
 * suffixes like "co.uk") so suffix matching cannot wipe data across unrelated
 * domains.
 */
function isValidForgetHost(target: string): boolean {
  if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/.test(target)) return false;
  const publicSuffixes = new Set([
    'co.uk', 'org.uk', 'gov.uk', 'ac.uk', 'me.uk', 'net.uk',
    'com.au', 'net.au', 'org.au', 'co.nz', 'co.jp', 'co.in', 'co.kr', 'co.za',
    'com.br', 'com.mx', 'com.ar', 'com.cn', 'com.tw', 'com.sg', 'com.hk',
  ]);
  return !publicSuffixes.has(target);
}

// ── Handler registration ─────────────────────────────────────────────────────

let _handlersRegistered = false;

/**
 * Callback invoked after auth:clear (logout). Set by main/index.ts to close
 * every open window and open one fresh logged-out window. Registered via a
 * setter to avoid a circular import between index.ts and this module.
 */
let _logoutHandler: (() => void) | null = null;

export function setLogoutHandler(fn: () => void): void {
  _logoutHandler = fn;
}

/**
 * Register all IPC handlers.  Must be called exactly once after the first
 * (normal) BrowserWindow has been created.  Private windows reuse the same
 * global handlers; per-window routing is done via event.sender.
 */
export function registerIpcHandlers(mainWindow: BrowserWindow): void {
  if (_handlersRegistered) return;
  _handlersRegistered = true;

  // Background retry loop for failed sync pushes.
  const syncRetryRunner = new SyncRetryRunner({
    onQueueChanged: (pendingCount) => {
      mainWindow.webContents.send('sync:queue-changed', pendingCount, countSyncQueueByProfile());
    },
  });
  // Only run the background sync loop when the local database is available —
  // without it every tick would throw (degraded mode: browsing still works).
  if (isDbInitialized()) {
    syncRetryRunner.start();
  }

  // ── DB init ──────────────────────────────────────────────────────────────
  ipcMain.handle('db:init', () => { initDb(); return { ok: true }; });

  // ── Preferences ─────────────────────────────────────────────────────────
  ipcMain.handle('prefs:get', (_, key: PrefKey) => getPreference(key));
  ipcMain.handle('prefs:set', (_, key: PrefKey, value: string) => {
    setPreference(key, value);
    // Apply side-effects for prefs that drive live main-process behaviour.
    if (key === PREFERENCE_KEYS.SEARCH_ENGINE) {
      const engine = SEARCH_ENGINES[value];
      if (engine) {
        for (const tm of tabManagerRegistry.values()) tm.setSearchEngine(engine);
      }
    } else if (key === PREFERENCE_KEYS.DO_NOT_TRACK) {
      setDoNotTrack(value === '1');
    } else if (key === PREFERENCE_KEYS.BLOCK_THIRD_PARTY_COOKIES) {
      setBlockThirdPartyCookies(value === '1');
    } else if (key === PREFERENCE_KEYS.HISTORY_DAYS_RETENTION) {
      // Apply the new retention window immediately (the periodic sweep in
      // main/index.ts keeps it enforced afterwards).
      const days = parseInt(value, 10);
      if (days > 0) {
        try { pruneHistoryOlderThan(days); } catch { /* best-effort */ }
      }
    }
    return true;
  });
  ipcMain.handle('prefs:all', () => getAllPreferences());

  // ── Theme ────────────────────────────────────────────────────────────────
  ipcMain.handle('theme:get', () => nativeTheme.shouldUseDarkColors ? 'dark' : 'light');
  ipcMain.handle('theme:set', (_, mode: 'system' | 'light' | 'dark') => {
    nativeTheme.themeSource = mode;
    return nativeTheme.shouldUseDarkColors ? 'dark' : 'light';
  });

  // ── Auth ─────────────────────────────────────────────────────────────────
  ipcMain.handle('auth:store-token', (event, token: string) => {
    storeToken(token);
    // Fresh token → forget old seed state, then establish a web session in
    // the calling window's active profile partition so Sayzio tabs open
    // signed in as the newly authenticated user.
    resetSayzioSessionSeeds();
    // Never seed from a private window: its tab manager still reports a
    // persistent profile partition string, so seeding here would write a
    // durable web session from a private-mode action.
    if (!senderIsPrivate(event)) {
      const partition = resolveTabManager(event)?.getActivePartition();
      if (partition) void seedSayzioWebSession(partition);
    }
    return true;
  });
  ipcMain.handle('auth:get-token', () => retrieveToken());
  ipcMain.handle('auth:clear', () => {
    clearToken(); clearUser(); clearSayzioLinksCache();
    resetSayzioSessionSeeds();
    // Logout closes every window and opens a single fresh (logged-out) one.
    // Deferred so the renderer's invoke() resolves before its window closes.
    if (_logoutHandler) setTimeout(() => _logoutHandler?.(), 50);
    return true;
  });
  ipcMain.handle('auth:store-user', (_, user: Record<string, unknown>) => { storeUser(user); return true; });
  ipcMain.handle('auth:get-user', () => retrieveUser());

  // ── Tabs ─────────────────────────────────────────────────────────────────
  ipcMain.handle('tabs:create', (event, url?: string, background?: boolean) =>
    resolveTabManager(event)?.createTab(url, background) ?? null,
  );
  ipcMain.handle('tabs:close', (event, id: string) => { resolveTabManager(event)?.closeTab(id); return true; });
  ipcMain.handle('tabs:activate', (event, id: string) => { resolveTabManager(event)?.activateTab(id); return true; });
  ipcMain.handle('tabs:navigate', (event, id: string, input: string) => { resolveTabManager(event)?.navigate(id, input); return true; });
  ipcMain.handle('tabs:back', (event, id: string) => { resolveTabManager(event)?.goBack(id); return true; });
  ipcMain.handle('tabs:forward', (event, id: string) => { resolveTabManager(event)?.goForward(id); return true; });
  ipcMain.handle('tabs:reload', (event, id: string, force?: boolean) => { resolveTabManager(event)?.reload(id, force); return true; });
  ipcMain.handle('tabs:stop', (event, id: string) => { resolveTabManager(event)?.stop(id); return true; });
  ipcMain.handle('tabs:zoom', (event, id: string, factor: number) => { resolveTabManager(event)?.setZoom(id, factor); return true; });
  ipcMain.handle('tabs:find', (event, id: string, text: string, forward?: boolean, matchCase?: boolean) => {
    resolveTabManager(event)?.findInPage(id, text, forward, matchCase);
    return true;
  });
  ipcMain.handle('tabs:find-stop', (event, id: string) => {
    resolveTabManager(event)?.stopFindInPage(id);
    return true;
  });
  ipcMain.handle('tabs:mute', (event, id: string, muted: boolean) => { resolveTabManager(event)?.muteTab(id, muted); return true; });
  ipcMain.handle('tabs:set-mode', (event, id: string, mode: string) => {
    // Private windows are browser-only: no Sayzio/Zio surfaces may be attached.
    if (senderIsPrivate(event)) return false;
    // setTabMode normalizes any raw/legacy mode string itself.
    resolveTabManager(event)?.setTabMode(id, mode);
    return true;
  });
  ipcMain.handle('tabs:get-state', (event, id: string) => resolveTabManager(event)?.getTabState(id) ?? null);
  ipcMain.handle('tabs:get-order', (event) => resolveTabManager(event)?.getTabOrder() ?? []);
  ipcMain.handle('tabs:get-active', (event) => resolveTabManager(event)?.getActiveTabId() ?? null);

  // ── Tab management — new actions ─────────────────────────────────────────
  ipcMain.handle('tabs:pin', (event, id: string, pinned: boolean) => {
    resolveTabManager(event)?.pinTab(id, pinned);
    return true;
  });
  ipcMain.handle('tabs:move', (event, id: string, toIndex: number) => {
    resolveTabManager(event)?.moveTab(id, toIndex);
    return true;
  });
  ipcMain.handle('tabs:duplicate', (event, id: string) => {
    return resolveTabManager(event)?.duplicateTab(id);
  });
  ipcMain.handle('tabs:close-others', (event, id: string) => {
    resolveTabManager(event)?.closeOtherTabs(id);
    return true;
  });
  ipcMain.handle('tabs:close-to-right', (event, id: string) => {
    resolveTabManager(event)?.closeTabsToRight(id);
    return true;
  });
  ipcMain.handle('tabs:mute-all', (event, muted?: boolean) => {
    const target = muted ?? true;
    resolveTabManager(event)?.muteAllTabs(target);
    // Persist the global policy so it survives as the session-level default
    // (never persisted from private windows).
    if (!senderIsPrivate(event)) setMuteAllTabs(target);
    return true;
  });

  // ── Spell check ───────────────────────────────────────────────────────────
  ipcMain.handle('spellcheck:get-enabled', () => {
    try {
      return (getPreference(PREFERENCE_KEYS.SPELLCHECK_ENABLED) ?? '1') === '1';
    } catch {
      return true;
    }
  });
  ipcMain.handle('spellcheck:set-enabled', (event, enabled: boolean) => {
    if (senderIsPrivate(event)) return false;
    try { setPreference(PREFERENCE_KEYS.SPELLCHECK_ENABLED, enabled ? '1' : '0'); } catch { }
    // Apply immediately to the default session and the active profile session.
    try { session.defaultSession.setSpellCheckerEnabled(enabled); } catch { }
    try {
      const tm = resolveTabManager(event);
      if (tm) session.fromPartition(tm.getActivePartition()).setSpellCheckerEnabled(enabled);
    } catch { }
    return true;
  });

  // ── Extensions (unpacked) ────────────────────────────────────────────────
  ipcMain.handle('extensions:list', () => listExtensions());
  ipcMain.handle('extensions:add', async (event) => {
    if (senderIsPrivate(event)) return { ok: false, error: 'Extensions are unavailable in private windows.' };
    const win = BrowserWindow.fromWebContents(event.sender);
    if (!win) return { ok: false, error: 'No window' };
    return addExtensionFromDialog(win);
  });
  ipcMain.handle('extensions:remove', (event, id: string) => {
    if (senderIsPrivate(event)) return false;
    return removeExtension(id);
  });

  // ── Audio policy (per-domain mute memory + global mute) ──────────────────
  ipcMain.handle('audio:muted-domains', () => getMutedDomains());
  ipcMain.handle('audio:set-domain-muted', (event, host: string, muted: boolean) => {
    if (senderIsPrivate(event)) return false;
    if (typeof host !== 'string' || host.length === 0) return false;
    setDomainMuted(host, muted);
    return true;
  });
  ipcMain.handle('audio:get-mute-all', () => getMuteAllTabs());
  ipcMain.handle('audio:set-mute-all', (event, enabled: boolean) => {
    if (senderIsPrivate(event)) return false;
    setMuteAllTabs(enabled);
    return true;
  });
  ipcMain.handle('tabs:reopen-closed', (event) => {
    return resolveTabManager(event)?.reopenClosedTab();
  });
  ipcMain.handle('tabs:recently-closed', (event) => {
    return resolveTabManager(event)?.getRecentlyClosed() ?? [];
  });
  ipcMain.handle('tabs:reopen-from-recent', (event, url: string) => {
    return resolveTabManager(event)?.createTab(url);
  });
  // Restore the last saved browsing session (non-pinned tabs from the
  // previous run). Returns the number of tabs restored.
  ipcMain.handle('tabs:hide-all', (event) => {
    resolveTabManager(event)?.hideAllTabs();
    // Return keyboard focus to the chrome renderer — a tab WebContentsView may
    // still hold focus, which would swallow typing into DOM overlays (auth
    // modal, mode picker).
    try { event.sender.focus(); } catch { }
    return true;
  });
  ipcMain.handle('tabs:restore-session', (event) => {
    const tm = resolveTabManager(event);
    if (!tm || tm.isPrivate) return 0;
    try {
      const raw = getPreference(PREFERENCE_KEYS.SESSION_TABS);
      if (!raw) return 0;
      const snap = JSON.parse(raw) as { urls?: unknown; activeIndex?: unknown };
      const urls = Array.isArray(snap?.urls)
        ? snap.urls.filter((u): u is string => typeof u === 'string' && u.length > 0)
        : [];
      if (urls.length === 0) return 0;
      tm.restoreSessionTabs(urls, typeof snap?.activeIndex === 'number' ? snap.activeIndex : -1);
      return urls.length;
    } catch {
      return 0;
    }
  });

  // Page context extraction
  ipcMain.handle('tabs:extract-context', async (event, id: string) => {
    const wc = resolveTabManager(event)?.getWebContents(id);
    if (!wc) return null;
    try {
      const result = await wc.executeJavaScript(`
        (function() {
          const url = window.location.href;
          const title = document.title;
          const desc = document.querySelector('meta[name="description"]')?.content || null;
          const lang = document.documentElement.lang || null;
          const author = document.querySelector('meta[name="author"]')?.content || null;
          const selection = window.getSelection()?.toString() || null;
          const article = document.querySelector('article, main, [role="main"]') || document.body;
          const clone = article.cloneNode(true);
          clone.querySelectorAll('script,style,nav,header,footer,aside,.ad,.advertisement').forEach(el => el.remove());
          const text = clone.innerText || clone.textContent || '';
          const phonePattern = /(?:\\+?1[-.\\s]?)?\\(?\\d{3}\\)?[-.\\s]?\\d{3}[-.\\s]?\\d{4}|\\+\\d{1,3}[-.\\s]?\\d{6,14}/g;
          const emailPattern = /[a-zA-Z0-9._%+\\-]+@[a-zA-Z0-9.\\-]+\\.[a-zA-Z]{2,}/g;
          const phones = [...new Set((text.match(phonePattern) || []))].slice(0, 20);
          const emails = [...new Set((text.match(emailPattern) || []).map(e => e.toLowerCase()))].slice(0, 20);
          return { url, title, description: desc, text: text.slice(0, 50000), selection, lang, author, publishedAt: null, phones, emails };
        })()
      `);
      return result;
    } catch {
      return null;
    }
  });

  // ── Window mode ──────────────────────────────────────────────────────────
  ipcMain.handle('window:get-mode', (event) => resolveModeManager(event)?.getMode() ?? 'browser');
  ipcMain.handle('window:set-mode', (event, mode: WindowMode) => {
    const mm = resolveModeManager(event);
    if (!mm) return false;
    // Private windows are browser-only — ignore mode changes to other modes
    if (senderIsPrivate(event) && mode !== 'browser') return false;
    mm.setMode(mode);
    if (!senderIsPrivate(event)) setPreference('window_mode', mode);
    const win = BrowserWindow.fromWebContents(event.sender);
    win?.webContents.send('window:mode-changed', mode);
    return true;
  });
  ipcMain.handle('window:get-split-ratio', (event) => resolveModeManager(event)?.getSplitRatio() ?? 0.35);
  ipcMain.handle('window:set-split-ratio', (event, ratio: number) => {
    if (senderIsPrivate(event)) return false;
    const mm = resolveModeManager(event);
    if (!mm) return false;
    mm.setSplitRatio(ratio);
    setPreference('split_ratio', String(ratio));
    return true;
  });
  ipcMain.handle('window:reload-dashboard', (event) => { resolveModeManager(event)?.reloadDashboard(); return true; });
  // Chrome-overlay: hide ALL native views (tabs + dashboard) while a renderer
  // dropdown/menu is open; closing re-applies the current mode.
  ipcMain.handle('window:set-chrome-overlay', (event, open: boolean) => {
    const mm = resolveModeManager(event);
    if (!mm) return false;
    mm.setChromeOverlay(!!open);
    if (open) {
      try { event.sender.focus(); } catch { }
    }
    return true;
  });

  // ── Private mode indicator ───────────────────────────────────────────────
  ipcMain.handle('window:is-private', (event) => senderIsPrivate(event));

  // ── Open new private window (from renderer) ──────────────────────────────
  // Importing createPrivateWindow here would create a circular dep; use a lazy require.
  ipcMain.handle('window:open-private', (_event, url?: string) => {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const { createPrivateWindow } = require('./index') as typeof import('./index');
    let startUrl: string | undefined;
    if (typeof url === 'string' && url.length > 0) {
      try {
        const proto = new URL(url).protocol;
        if (proto === 'http:' || proto === 'https:') startUrl = url;
      } catch {
        // Ignore malformed URLs — open a blank private window instead.
      }
    }
    createPrivateWindow(startUrl);
    return true;
  });

  // ── Open a new normal window (from renderer) ─────────────────────────────
  ipcMain.handle('window:open-new', () => {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const { createWindow } = require('./index') as typeof import('./index');
    createWindow();
    return true;
  });

  // ── Zio panel width / presentation (browser mode) ────────────────────────
  ipcMain.handle('window:get-zio-panel-width', () => {
    const stored = getPreference('zio_panel_width');
    const n = stored ? Number(stored) : DEFAULT_ZIO_PANEL_WIDTH;
    return Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, n));
  });
  ipcMain.handle('window:set-zio-panel-width', (event, width: number) => {
    const clamped = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, width));
    setPreference('zio_panel_width', String(clamped));
    resolveModeManager(event)?.setZioPanelWidth(clamped);
    return true;
  });
  ipcMain.handle('window:get-zio-panel-docked', () => {
    // Default DOCKED: the side-by-side layout keeps the page visible, which
    // is the least surprising first experience. Users who explicitly chose
    // floating ('0') keep their choice.
    const stored = getPreference('zio_panel_docked');
    return stored === null ? true : stored === '1';
  });
  ipcMain.handle('window:set-zio-panel-docked', (event, docked: boolean) => {
    setPreference('zio_panel_docked', docked ? '1' : '0');
    resolveModeManager(event)?.setZioPanelDocked(docked);
    return true;
  });
  // Renderer tells us whether the docked Zio panel is actually rendered right
  // now, so layout space is only reserved while it's on screen. Not a
  // preference — pure per-window runtime state.
  ipcMain.handle('window:set-zio-panel-visible', (event, visible: boolean) => {
    resolveModeManager(event)?.setZioPanelVisible(visible === true);
    return true;
  });

  // ── History ──────────────────────────────────────────────────────────────
  ipcMain.handle('history:record', (event, url: string, title: string | null, favicon?: string) => {
    if (senderIsPrivate(event)) return null;
    return recordVisit(url, title, favicon, resolveProfileId(event));
  });
  ipcMain.handle('history:search', (event, q: string) => searchHistory(q, 20, resolveProfileId(event)));
  ipcMain.handle('history:recent', (event) => getRecentHistory(50, resolveProfileId(event)));
  ipcMain.handle('history:clear', (event) => { clearHistory(resolveProfileId(event)); return true; });
  ipcMain.handle('history:delete', (_, id: string) => deleteHistoryEntry(id));

  // ── Bookmarks ────────────────────────────────────────────────────────────
  ipcMain.handle('bookmarks:add', (event, url: string, title: string, opts?: Record<string, string>) => {
    if (senderIsPrivate(event)) return null;
    return addBookmark(url, title, opts, resolveProfileId(event));
  });
  ipcMain.handle('bookmarks:remove', (event, url: string) => {
    if (senderIsPrivate(event)) return false;
    return removeBookmark(url, resolveProfileId(event));
  });
  ipcMain.handle('bookmarks:is-bookmarked', (event, url: string) => {
    if (senderIsPrivate(event)) return false;
    return isBookmarked(url, resolveProfileId(event));
  });
  ipcMain.handle('bookmarks:all', (event, folder?: string) => {
    if (senderIsPrivate(event)) return [];
    return getAllBookmarks(folder, resolveProfileId(event));
  });
  ipcMain.handle('bookmarks:search', (event, q: string) => {
    if (senderIsPrivate(event)) return [];
    return searchBookmarks(q, 20, resolveProfileId(event));
  });

  // ── Import from other browsers ────────────────────────────────────────────
  // User-configurable kill switch: when the "Allow importing" preference is
  // off, every import channel refuses to detect or read anything.
  const importFeatureEnabled = () => (getPreference(PREFERENCE_KEYS.IMPORT_ENABLED) ?? '1') !== '0';

  ipcMain.handle('import:detect', (event) => {
    if (senderIsPrivate(event) || !importFeatureEnabled()) return [];
    try {
      return detectBrowsers().map(b => ({
        id: b.id,
        name: b.name,
        hasBookmarks: b.hasBookmarks,
        hasHistory: b.hasHistory,
      }));
    } catch {
      return [];
    }
  });

  ipcMain.handle('import:run', (event, browserId: string, want: { bookmarks?: boolean; history?: boolean }) => {
    if (senderIsPrivate(event)) return { ok: false, error: 'Not available in private windows.' };
    if (!importFeatureEnabled()) return { ok: false, error: 'Importing is turned off in Settings.' };
    try {
      const browser = detectBrowsers().find(b => b.id === browserId);
      if (!browser) return { ok: false, error: 'That browser could not be found anymore.' };
      const data = readBrowserData(browser, {
        bookmarks: want?.bookmarks !== false && browser.hasBookmarks,
        history: want?.history !== false && browser.hasHistory,
      });
      const pid = resolveProfileId(event);
      let bookmarksImported = 0;
      for (const b of data.bookmarks) {
        try {
          addBookmark(b.url, b.title, b.folder ? { folder: b.folder } : {}, pid);
          bookmarksImported++;
        } catch { /* skip bad rows */ }
      }
      const historyImported = data.history.length > 0 ? importHistoryEntries(data.history, pid) : 0;
      return { ok: true, bookmarksImported, historyImported };
    } catch (err) {
      return { ok: false, error: err instanceof Error ? err.message : 'Import failed.' };
    }
  });

  ipcMain.handle('import:html-file', async (event) => {
    if (senderIsPrivate(event)) return { ok: false, error: 'Not available in private windows.' };
    if (!importFeatureEnabled()) return { ok: false, error: 'Importing is turned off in Settings.' };
    const win = BrowserWindow.fromWebContents(event.sender);
    const result = await dialog.showOpenDialog(win ?? BrowserWindow.getAllWindows()[0], {
      title: 'Choose a bookmarks HTML file',
      filters: [{ name: 'Bookmarks HTML', extensions: ['html', 'htm'] }],
      properties: ['openFile'],
    });
    if (result.canceled || result.filePaths.length === 0) return { ok: false, canceled: true };
    try {
      const stat = fs.statSync(result.filePaths[0]);
      if (stat.size > 25 * 1024 * 1024) return { ok: false, error: 'That file is too large to be a bookmarks export.' };
      const html = fs.readFileSync(result.filePaths[0], 'utf8');
      const items = parseBookmarksHtml(html);
      const pid = resolveProfileId(event);
      let bookmarksImported = 0;
      for (const b of items) {
        try {
          addBookmark(b.url, b.title, b.folder ? { folder: b.folder } : {}, pid);
          bookmarksImported++;
        } catch { /* skip bad rows */ }
      }
      return { ok: true, bookmarksImported, historyImported: 0 };
    } catch (err) {
      return { ok: false, error: err instanceof Error ? err.message : 'Could not read that file.' };
    }
  });

  // ── Collections ──────────────────────────────────────────────────────────
  ipcMain.handle('collections:all', (event) => getAllCollections(resolveProfileId(event)));
  ipcMain.handle('collections:create', (event, name: string, opts?: Record<string, string>) => {
    const c = createCollection(name, opts);
    createCollectionInDb(c, resolveProfileId(event));
    return c;
  });
  ipcMain.handle('collections:update', (_, id: string, updates: Record<string, string>) => {
    updateCollection(id, updates);
    return true;
  });
  ipcMain.handle('collections:delete', (_, id: string) => { deleteCollection(id); return true; });
  ipcMain.handle('collections:get-links', (_, collectionId: string) => getSavedLinksForCollection(collectionId));
  ipcMain.handle('collections:save-link', (_, collectionId: string, url: string, title: string, opts?: Record<string, unknown>) => {
    const link = createSavedLink(collectionId, url, title, opts as Parameters<typeof createSavedLink>[3]);
    saveLinkToCollection(link);
    return link;
  });
  ipcMain.handle('collections:update-ai', (_, id: string, summary: string, tags: string[], context: string, coins: number) => {
    updateSavedLinkAiEnrichment(id, summary, tags, context, coins);
    return true;
  });

  // ── Downloads ────────────────────────────────────────────────────────────
  ipcMain.handle('downloads:recent', () => getRecentDownloads());
  ipcMain.handle('downloads:search', (_, q: string) => searchDownloads(q));
  ipcMain.handle('downloads:open', async (_, filePath: string) => {
    if (!fs.existsSync(filePath)) {
      return { ok: false, error: 'File not found', missing: true };
    }
    const err = await shell.openPath(filePath);
    return err === '' ? { ok: true } : { ok: false, error: err };
  });
  ipcMain.handle('downloads:show', async (_, filePath: string) => {
    if (!fs.existsSync(filePath)) {
      return { ok: false, error: 'File not found', missing: true };
    }
    shell.showItemInFolder(filePath);
    return { ok: true };
  });
  ipcMain.handle('downloads:exists', (_, filePath: string) => fs.existsSync(filePath));
  ipcMain.handle('downloads:choose-path', async () => {
    const result = await dialog.showSaveDialog({ title: 'Save File' });
    return result.canceled ? null : result.filePath;
  });
  ipcMain.handle('downloads:choose-directory', async () => {
    const current = getPreference(PREFERENCE_KEYS.DOWNLOAD_PATH);
    const result = await dialog.showOpenDialog({
      title: 'Choose Download Folder',
      defaultPath: current ?? app.getPath('downloads'),
      properties: ['openDirectory', 'createDirectory'],
    });
    return result.canceled || result.filePaths.length === 0 ? null : result.filePaths[0];
  });
  ipcMain.handle('downloads:default-directory', () => app.getPath('downloads'));
  ipcMain.handle('downloads:pause', (_, id: string) => {
    const item = getActiveItem(id);
    if (!item) return false;
    item.pause();
    return true;
  });
  ipcMain.handle('downloads:resume', (_, id: string) => {
    const item = getActiveItem(id);
    if (!item) return false;
    item.resume();
    return true;
  });
  ipcMain.handle('downloads:cancel', (_, id: string) => {
    const item = getActiveItem(id);
    if (!item) return false;
    item.cancel();
    return true;
  });
  ipcMain.handle('downloads:retry', async (_, url: string) => {
    if (!mainWindow) return false;
    mainWindow.webContents.downloadURL(url);
    return true;
  });
  ipcMain.handle('downloads:remove', (_, id: string) => {
    deleteDownload(id);
    return true;
  });
  ipcMain.handle('downloads:clear', () => {
    clearAllDownloads();
    return true;
  });

  // ── Reading list ─────────────────────────────────────────────────────────
  ipcMain.handle('reading-list:add', (_, url: string, title: string, favicon?: string) => {
    return addToReadingList(url, title, favicon);
  });
  ipcMain.handle('reading-list:is-saved', (_, url: string) => isInReadingList(url));
  ipcMain.handle('reading-list:all', () => getReadingList());
  ipcMain.handle('reading-list:unread-count', () => getUnreadCount());
  ipcMain.handle('reading-list:mark-read', (_, id: string, isRead: boolean) => {
    markReadingListItemRead(id, isRead);
    return true;
  });
  ipcMain.handle('reading-list:remove', (_, id: string) => {
    removeFromReadingList(id);
    return true;
  });

  // ── Sync ─────────────────────────────────────────────────────────────────
  //
  // Private-mode privacy boundary for account sync:
  //  - PRIMARY GATE: `sync:queue-push` rejects writes from private windows, so
  //    private browsing data never lands in the `sync_queue` in the first place.
  //  - SECONDARY (belt-and-suspenders) GATE: `sync:flush` also refuses to drain
  //    the queue when invoked from a private window, so even if a future code
  //    path enqueued a row from a private context, a private window's flush
  //    timer could never push it to the server. Flushing is only ever driven
  //    by non-private windows (or the main-process retry runner).
  ipcMain.handle('sync:state', (_, entity: string) => getSyncState(entity));
  ipcMain.handle('sync:queue-push', (event, entity: SyncEntityKind, payloadJson: string, error?: string) => {
    // Primary private-mode gate: never enqueue sync data from private windows.
    if (senderIsPrivate(event)) return null;
    const item = enqueueSyncPush(entity, payloadJson, error ?? null);
    syncRetryRunner.notify();
    return item.id;
  });
  ipcMain.handle('sync:pending-count', (event) => senderIsPrivate(event) ? 0 : countSyncQueue());
  ipcMain.handle('sync:pending-by-profile', (event) => senderIsPrivate(event) ? [] : countSyncQueueByProfile());
  ipcMain.handle('sync:flush', async (event) => {
    // Secondary private-mode gate (see block comment above): private windows
    // must never trigger a queue drain to the server.
    if (senderIsPrivate(event)) return { flushed: 0, remaining: 0 };
    const flushed = await syncRetryRunner.flushAll();
    return { flushed, remaining: countSyncQueue() };
  });

  // ── Clipboard ────────────────────────────────────────────────────────────
  ipcMain.handle('clipboard:write', (_, text: string) => { clipboard.writeText(text); return true; });
  ipcMain.handle('clipboard:read', () => clipboard.readText());

  // ── External links ───────────────────────────────────────────────────────
  ipcMain.handle('shell:open-external', async (_, url: string) => {
    const allowed = ['https:', 'http:', 'mailto:', 'tel:'];
    try {
      const proto = new URL(url).protocol;
      if (allowed.includes(proto)) { await shell.openExternal(url); return true; }
    } catch { }
    return false;
  });

  // ── Form autofill ─────────────────────────────────────────────────────────
  ipcMain.handle('tabs:autofill-form', async (event, id: string, card: AutofillCard) => {
    const wc = resolveTabManager(event)?.getWebContents(id);
    if (!wc) return { filled: 0, filled_fields: [] };
    try {
      const result = await wc.executeJavaScript(buildAutofillScript(card));
      return result ?? { filled: 0, filled_fields: [] };
    } catch {
      return { filled: 0, filled_fields: [] };
    }
  });

  // ── Cookies (via Electron session API) ──────────────────────────────────
  ipcMain.handle('cookies:get-for-site', async (_, url: string) => {
    try {
      const origin = new URL(url).hostname;
      const cookies = await session.defaultSession.cookies.get({ domain: origin });
      return cookies.map(c => ({
        name: c.name,
        value: c.value,
        domain: c.domain,
        path: c.path,
        secure: c.secure,
        httpOnly: c.httpOnly,
        expirationDate: c.expirationDate,
        session: c.session,
      }));
    } catch {
      return [];
    }
  });

  ipcMain.handle('cookies:get-all', async () => {
    try {
      const cookies = await session.defaultSession.cookies.get({});
      return cookies.map(c => ({
        name: c.name,
        value: c.value,
        domain: c.domain,
        path: c.path,
        secure: c.secure,
        httpOnly: c.httpOnly,
        expirationDate: c.expirationDate,
        session: c.session,
      }));
    } catch {
      return [];
    }
  });

  ipcMain.handle('cookies:delete', async (_, name: string, url: string) => {
    try {
      await session.defaultSession.cookies.remove(url, name);
      return true;
    } catch {
      return false;
    }
  });

  ipcMain.handle('cookies:clear-for-site', async (_, url: string) => {
    try {
      const origin = new URL(url).hostname;
      const cookies = await session.defaultSession.cookies.get({ domain: origin });
      await Promise.all(
        cookies.map(c =>
          session.defaultSession.cookies.remove(
            `${c.secure ? 'https' : 'http'}://${c.domain?.replace(/^\./, '') ?? origin}${c.path ?? '/'}`,
            c.name,
          ).catch(() => { /* ignore per-cookie failures */ }),
        ),
      );
      return true;
    } catch {
      return false;
    }
  });

  ipcMain.handle('cookies:clear-all', async () => {
    try {
      // Use clearStorageData for a full cookie wipe
      await session.defaultSession.clearStorageData({ storages: ['cookies'] });
      return true;
    } catch {
      return false;
    }
  });

  // ── Passwords (safeStorage-encrypted SQLite store) ───────────────────────
  ipcMain.handle('passwords:save', (_, origin: string, username: string, plainPassword: string) => {
    const enc = encryptPassword(plainPassword);
    const row = savePassword(origin, username, enc);
    return { id: row.id, origin: row.origin, username: row.username, created_at: row.created_at, updated_at: row.updated_at };
  });

  ipcMain.handle('passwords:list', () => {
    const rows = getAllSavedPasswords();
    return rows.map(r => ({ id: r.id, origin: r.origin, username: r.username, created_at: r.created_at, updated_at: r.updated_at }));
  });

  ipcMain.handle('passwords:get-for-origin', (_, origin: string) => {
    const rows = getPasswordsForOrigin(origin);
    return rows.map(r => ({ id: r.id, origin: r.origin, username: r.username, created_at: r.created_at, updated_at: r.updated_at }));
  });

  ipcMain.handle('passwords:reveal', (_, id: string) => {
    const rows = getAllSavedPasswords();
    const row = rows.find(r => r.id === id);
    if (!row) return null;
    return decryptPassword(row.password_enc);
  });

  ipcMain.handle('passwords:delete', (_, id: string) => deletePassword(id));
  ipcMain.handle('passwords:delete-all', () => { deleteAllPasswords(); return true; });

  // ── Password credential detection (injected into tab pages) ──────────────
  // Injects a listener script that captures form-submitted credentials and
  // signals back via window.__zioPendingCredential.
  ipcMain.handle('tabs:inject-password-detector', async (event, id: string) => {
    const wc = resolveTabManager(event)?.getWebContents(id);
    if (!wc) return false;
    try {
      await wc.executeJavaScript(`
        (function() {
          if (window.__zioPwDetectorInstalled) return;
          window.__zioPwDetectorInstalled = true;
          window.__zioPendingCredential = null;
          document.addEventListener('submit', function(evt) {
            try {
              var form = evt.target;
              if (!(form instanceof HTMLFormElement)) return;
              var pwInput = form.querySelector('input[type="password"]');
              if (!pwInput || !pwInput.value) return;
              var userInput = form.querySelector(
                'input[type="email"], input[type="text"], input[name*="user"], input[name*="login"], input[name*="email"], input[name*="username"]'
              );
              window.__zioPendingCredential = {
                origin: window.location.origin,
                username: userInput ? userInput.value : '',
                password: pwInput.value
              };
            } catch(e) {}
          }, true);
        })()
      `);
      return true;
    } catch {
      return false;
    }
  });

  // Read (and clear) any pending credential the detector captured.
  ipcMain.handle('tabs:pop-pending-credential', async (event, id: string) => {
    const wc = resolveTabManager(event)?.getWebContents(id);
    if (!wc) return null;
    try {
      const cred = await wc.executeJavaScript(`
        (function() {
          var c = window.__zioPendingCredential || null;
          window.__zioPendingCredential = null;
          return c;
        })()
      `);
      return cred as { origin: string; username: string; password: string } | null;
    } catch {
      return null;
    }
  });

  // ── Screenshot capture ────────────────────────────────────────────────────

  /**
   * Capture the active tab as a PNG, returned as a base64 data URL.
   * fullPage=true resizes the WebContentsView to full scroll height, captures,
   * then restores the original bounds.
   */
  ipcMain.handle('screenshot:capture', async (event, tabId: string, fullPage: boolean) => {
    const tm = resolveTabManager(event);
    if (!tm) return null;
    const png = await tm.captureTab(tabId, fullPage);
    if (!png) return null;
    return `data:image/png;base64,${png.toString('base64')}`;
  });

  /**
   * Open a save-file dialog and write the PNG data URL to disk.
   * Returns the saved file path, or null if the user cancelled.
   */
  ipcMain.handle('screenshot:save-to-disk', async (_, dataUrl: string, suggestedName?: string) => {
    const win = BrowserWindow.getAllWindows()[0];
    const result = await dialog.showSaveDialog(win ?? null!, {
      title: 'Save Screenshot',
      defaultPath: suggestedName ?? `screenshot-${Date.now()}.png`,
      filters: [{ name: 'PNG Image', extensions: ['png'] }],
    });
    if (result.canceled || !result.filePath) return null;
    const base64 = dataUrl.replace(/^data:image\/png;base64,/, '');
    const buf = Buffer.from(base64, 'base64');
    fs.writeFileSync(result.filePath, buf);
    return result.filePath;
  });

  /**
   * Write a PNG data URL to the system clipboard as a native image.
   */
  ipcMain.handle('screenshot:copy-to-clipboard', (_, dataUrl: string) => {
    try {
      const base64 = dataUrl.replace(/^data:image\/png;base64,/, '');
      const buf = Buffer.from(base64, 'base64');
      const img = nativeImage.createFromBuffer(buf);
      clipboard.writeImage(img);
      return true;
    } catch {
      return false;
    }
  });

  // ── Clear browsing data (with range + type selection) ────────────────────
  ipcMain.handle('browsing-data:clear', async (event, options: {
    range: '15min' | 'hour' | 'day' | 'week' | '4weeks' | 'all';
    clearHistory: boolean;
    clearCookies: boolean;
    clearCache: boolean;
    clearDownloads?: boolean;
    clearPermissions?: boolean;
  }) => {
    if (senderIsPrivate(event)) return { ok: false, deletedCount: 0 };
    try {
      const {
        range,
        clearHistory: doHistory,
        clearCookies: doCookies,
        clearCache: doCache,
        clearDownloads: doDownloads,
        clearPermissions: doPermissions,
      } = options ?? {};

      const sinceIso = rangeToSinceIso(range);

      let deletedCount = 0;

      // 1. History — soft-delete local SQLite rows by range.
      if (doHistory) {
        const deleted = clearHistoryByRange(sinceIso);
        deletedCount = deleted.length;

        // 2. Propagate tombstones to the server so other devices see the wipe.
        if (deleted.length > 0) {
          try {
            const token = retrieveToken();
            const baseUrl = getPreference(PREFERENCE_KEYS.SAYZIO_API_BASE_URL);
            if (token && baseUrl) {
              const { ApiClient } = await import('../shared/api-client');
              const client = new ApiClient({ baseUrl, token });
              // Use the bulk purge endpoint — far cheaper than per-entry tombstones.
              await client.purgeHistory(sinceIso);
            }
          } catch {
            // Non-fatal: the locally deleted records are already marked deleted=1
            // and will be picked up as tombstones on the next incremental sync.
          }
        }
      }

      // 3. Cookies & site data via Electron session API — across the default
      // session AND every profile partition session (tabs run in partitions).
      if (doCookies) {
        for (const sess of allDataSessions()) {
          await sess.clearStorageData({
            storages: ['cookies', 'localstorage', 'indexdb', 'websql', 'serviceworkers'],
          }).catch(() => {});
        }
      }

      // 4. Cache files.
      if (doCache) {
        for (const sess of allDataSessions()) {
          await sess.clearStorageData({
            storages: ['cachestorage', 'shadercache'],
          }).catch(() => {});
          await sess.clearCache().catch(() => {});
        }
      }

      // 5. Download records (local list only; downloaded files stay on disk).
      if (doDownloads) {
        clearDownloadsByRange(sinceIso);
      }

      // 6. Site permissions (all — permissions have no timestamps to range on).
      if (doPermissions) {
        clearAllSitePermissions();
      }

      return { ok: true, deletedCount };
    } catch {
      return { ok: false, deletedCount: 0 };
    }
  });

  // ── Version info ─────────────────────────────────────────────────────────
  ipcMain.handle('app:version', () => {
    const { app } = require('electron') as typeof import('electron');
    return { version: app.getVersion(), name: app.getName() };
  });

  // ── Profiles ─────────────────────────────────────────────────────────────

  /** Return all locally known profiles (personal + any synced workspace profiles). */
  ipcMain.handle('profiles:list', () => listProfiles());

  /** Return the currently active profile ID for the calling window. */
  ipcMain.handle('profiles:get-active', (event) => resolveProfileId(event));

  /**
   * Switch to a different profile — scoped to the CALLING WINDOW only:
   *  1. Update this window's entry in the per-window profile registry
   *     (scopes all future DB reads from this window).
   *  2. Persist the choice in preferences (used as the initial profile for
   *     newly opened windows; existing windows are unaffected).
   *  3. Update this window's tab manager session partition.
   *  4. Notify this window's renderer via an IPC push.
   */
  ipcMain.handle('profiles:switch', (event, profileId: string) => {
    const win = BrowserWindow.fromWebContents(event.sender);
    if (win) windowProfileRegistry.set(win.id, profileId);
    setPreference('active_profile', profileId);
    resolveTabManager(event)?.setActiveProfilePartition(profileId);
    // Make sure privacy + tracker-blocking webRequest hooks cover the
    // newly activated profile session (idempotent per session).
    try {
      const sess = session.fromPartition(sessionPartitionForProfile(profileId));
      installPrivacyHooks(sess);
      installTrackerHooks(sess);
    } catch { /* best-effort */ }
    // Make sure the newly active profile's cookie jar carries a Sayzio web
    // session (no-op if already seeded or no token is stored). Never from a
    // private window — that would mutate a persistent cookie jar.
    if (!senderIsPrivate(event)) {
      void seedSayzioWebSession(sessionPartitionForProfile(profileId));
    }
    win?.webContents.send('profile:changed', profileId);
    return { ok: true, profileId };
  });

  /**
   * Upsert a profile derived from a workspace API item.
   * Called by the renderer after fetching /api/v1/workspaces.
   */
  ipcMain.handle('profiles:upsert-from-workspace', (_, ws: { id: number | string; name: string; is_personal?: boolean }) => {
    const profile = profileFromWorkspace(ws);
    upsertProfile(profile);
    return profile;
  });

  /**
   * Pre-warm a session partition so cookies load before the user's first tab
   * in that profile. Returns the partition string so the renderer can confirm.
   */
  ipcMain.handle('profiles:warm-session', (event, profileId: string) => {
    const partition = sessionPartitionForProfile(profileId);
    // Accessing the session via fromPartition creates + registers it.
    const { session: electronSession } = require('electron') as typeof import('electron');
    const sess = electronSession.fromPartition(partition);
    // Cover the pre-warmed session with privacy + tracker hooks so protections
    // apply from the very first request (idempotent per session).
    try {
      installPrivacyHooks(sess);
      installTrackerHooks(sess);
    } catch { /* best-effort */ }
    // Seed a Sayzio web session into the pre-warmed cookie jar — never from
    // a private window (would mutate a persistent partition).
    if (!senderIsPrivate(event)) void seedSayzioWebSession(partition);
    return partition;
  });

  // ── Sayzio links cache ────────────────────────────────────────────────────

  /**
   * Return the locally cached Sayzio links. Works offline and when signed out
   * (returns whatever was cached last, or [] after sign-out clears the cache).
   * Private windows get nothing.
   */
  ipcMain.handle('sayzio-links:cached', (event) => {
    if (senderIsPrivate(event)) return [];
    return getCachedSayzioLinks();
  });

  /**
   * Refresh the Sayzio links cache from the API in the background.
   * No-ops when signed out or when the cache is still fresh (unless forced).
   * Returns the up-to-date cached list either way.
   */
  ipcMain.handle('sayzio-links:refresh', async (event, force?: boolean) => {
    if (senderIsPrivate(event)) return [];
    const token = retrieveToken();
    if (!token) return getCachedSayzioLinks();

    const SAYZIO_LINKS_ENTITY = 'sayzio_links';
    const { lastSyncAt } = getSyncState(SAYZIO_LINKS_ENTITY, DEFAULT_PROFILE_ID);
    if (!force && !isSyncDue(lastSyncAt, SYNC_INTERVALS.BACKGROUND_MS)) {
      return getCachedSayzioLinks();
    }

    const prefs = getAllPreferences();
    const apiBase = prefs['sayzio_api_base_url'] ?? 'https://sayzio.app';
    try {
      const resp = await fetch(`${apiBase}/api/v1/links?per_page=50`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
          'X-App-Platform': 'desktop',
        },
      });
      if (!resp.ok) {
        setSyncState(SAYZIO_LINKS_ENTITY, lastSyncAt, `HTTP ${resp.status}`, DEFAULT_PROFILE_ID);
        return getCachedSayzioLinks();
      }
      const json = await resp.json() as { data?: { items?: Array<Record<string, unknown>> } };
      const items = json?.data?.items;
      if (Array.isArray(items)) {
        replaceSayzioLinksCache(items.map(item => ({
          id: Number(item['id']),
          type: String(item['type'] ?? 'short'),
          alias: String(item['alias'] ?? ''),
          title: (item['title'] as string | null) ?? null,
          long_url: (item['long_url'] as string | null) ?? null,
          short_url: String(item['short_url'] ?? ''),
        })).filter(l => Number.isFinite(l.id) && l.short_url));
        setSyncState(SAYZIO_LINKS_ENTITY, new Date().toISOString(), null, DEFAULT_PROFILE_ID);
      }
    } catch (err) {
      // Offline or network error — keep serving the cache.
      setSyncState(SAYZIO_LINKS_ENTITY, lastSyncAt, String(err), DEFAULT_PROFILE_ID);
    }
    return getCachedSayzioLinks();
  });

  // ── Device lab biolinks ───────────────────────────────────────────────────

  /**
   * Fetch the authenticated user's biolinks from the Sayzio API.
   * Returns an array of { id, alias, title, public_url } objects or [] if not signed in.
   */
  ipcMain.handle('device-lab:list-biolinks', async () => {
    const token = retrieveToken();
    if (!token) return [];
    const prefs = getAllPreferences();
    const apiBase = prefs['sayzio_api_base_url'] ?? 'https://sayzio.app';
    try {
      const resp = await fetch(`${apiBase}/api/v1/links?type=biolink&limit=50`, {
        headers: {
          Authorization: `Bearer ${token}`,
          Accept: 'application/json',
        },
      });
      if (!resp.ok) return [];
      const json = await resp.json() as { data?: { items?: unknown[] } };
      const items = json?.data?.items ?? [];
      return (items as Array<Record<string, unknown>>).map(item => ({
        id: item['id'],
        alias: item['alias'],
        title: item['title'] ?? item['alias'],
        public_url: item['public_url'] ?? item['full_short_url'],
      }));
    } catch {
      return [];
    }
  });

  // ── Site permissions ──────────────────────────────────────────────────────
  ipcMain.handle('permissions:get-all', () => getAllSitePermissions());
  ipcMain.handle('permissions:set', (_, origin: string, permission: string, decision: 'allow' | 'block') => {
    setSitePermission(origin, permission, decision);
    return true;
  });
  ipcMain.handle('permissions:revoke', (_, origin: string, permission: string) => {
    revokeSitePermission(origin, permission);
    return true;
  });
  ipcMain.handle('permissions:clear-all', () => {
    clearAllSitePermissions();
    return true;
  });
  ipcMain.handle('permissions:respond', (_, requestId: string, decision: 'allow' | 'block', remember: boolean, origin: string, permission: string) => {
    resolvePermissionRequest(requestId, decision, remember, origin, permission);
    return true;
  });

  // ── Named sessions (save / restore sets of tabs) ─────────────────────────
  ipcMain.handle('sessions:list', (event) => {
    if (senderIsPrivate(event)) return [];
    try {
      return listNamedSessions().map(row => {
        let tabCount = 0;
        try {
          const snap = JSON.parse(row.snapshot) as { urls?: unknown };
          if (Array.isArray(snap?.urls)) tabCount = snap.urls.length;
        } catch { /* corrupt snapshot — show 0 tabs */ }
        return { id: row.id, name: row.name, tabCount, updated_at: row.updated_at };
      });
    } catch {
      return [];
    }
  });
  ipcMain.handle('sessions:save', (event, name: string) => {
    if (senderIsPrivate(event)) return false;
    const tm = resolveTabManager(event);
    if (!tm) return false;
    const trimmed = (name ?? '').trim().slice(0, 80);
    if (!trimmed) return false;
    const snapshot = tm.getSessionSnapshot();
    if (snapshot.urls.length === 0) return false;
    try {
      saveNamedSession(randomUUID(), trimmed, JSON.stringify(snapshot));
      return true;
    } catch {
      return false;
    }
  });
  ipcMain.handle('sessions:restore', (event, id: string) => {
    if (senderIsPrivate(event)) return false;
    const tm = resolveTabManager(event);
    if (!tm) return false;
    let row;
    try {
      row = getNamedSession(id);
    } catch {
      return false;
    }
    if (!row) return false;
    try {
      const snap = JSON.parse(row.snapshot) as { urls?: unknown; activeIndex?: unknown };
      const urls = Array.isArray(snap?.urls)
        ? snap.urls.filter((u): u is string => typeof u === 'string' && u.length > 0)
        : [];
      if (urls.length === 0) return false;
      tm.restoreSessionTabs(urls, typeof snap?.activeIndex === 'number' ? snap.activeIndex : -1);
      return true;
    } catch {
      return false;
    }
  });
  ipcMain.handle('sessions:delete', (event, id: string) => {
    if (senderIsPrivate(event)) return false;
    try {
      deleteNamedSession(id);
      return true;
    } catch {
      return false;
    }
  });

  // ── Tracker blocking ──────────────────────────────────────────────────────
  ipcMain.handle('tracker:is-enabled', () => isTrackerBlockingEnabled());
  ipcMain.handle('tracker:set-enabled', (_, enabled: boolean) => {
    setTrackerBlockingEnabled(enabled);
    setPreference('tracker_blocking_enabled', enabled ? '1' : '0');
    return true;
  });
  ipcMain.handle('tracker:get-count', (_, tabId: string) => getBlockedCount(tabId));
  ipcMain.handle('tracker:reset-count', (_, tabId: string) => {
    resetBlockedCount(tabId);
    return true;
  });

  // ── Privacy Dashboard: weekly tracker stats ───────────────────────────────
  ipcMain.handle('tracker:stats', (event) => {
    if (senderIsPrivate(event)) return { weekTotal: 0, todayTotal: 0, byDay: [], topTrackers: [] };
    try {
      return getTrackerStats();
    } catch {
      return { weekTotal: 0, todayTotal: 0, byDay: [], topTrackers: [] };
    }
  });

  // ── Browsing-data counts (for the Delete-browsing-data dialog) ───────────
  ipcMain.handle('browsing-data:counts', async (event, range: string) => {
    if (senderIsPrivate(event)) {
      return { historyCount: 0, cookieCount: 0, cacheBytes: 0, downloadCount: 0, permissionCount: 0 };
    }
    const sinceIso = rangeToSinceIso(range);
    let historyCount = 0;
    let cookieCount = 0;
    let cacheBytes = 0;
    let downloadCount = 0;
    let permissionCount = 0;
    try { historyCount = countHistorySince(sinceIso); } catch { /* best-effort */ }
    try { downloadCount = countDownloadsSince(sinceIso); } catch { /* best-effort */ }
    try { permissionCount = getAllSitePermissions().length; } catch { /* best-effort */ }
    for (const sess of allDataSessions()) {
      try {
        const cookies = await sess.cookies.get({});
        cookieCount += cookies.length;
      } catch { /* best-effort */ }
      try { cacheBytes += await sess.getCacheSize(); } catch { /* best-effort */ }
    }
    return { historyCount, cookieCount, cacheBytes, downloadCount, permissionCount };
  });

  // ── Forget this site (one-click per-host wipe) ────────────────────────────
  ipcMain.handle('site:forget', async (event, host: string) => {
    if (senderIsPrivate(event)) return { ok: false, historyDeleted: 0 };
    try {
      const target = String(host ?? '').trim().toLowerCase().replace(/^www\./, '');
      if (!isValidForgetHost(target)) return { ok: false, historyDeleted: 0 };

      // 1. History (soft-delete + best-effort cloud tombstones on next sync).
      const deleted = deleteHistoryByHost(target);

      // 2. Cookies for the host and its subdomains, plus local storage /
      // caches for the origin — across every persistent session.
      for (const sess of allDataSessions()) {
        try {
          const cookies = await sess.cookies.get({});
          for (const cookie of cookies) {
            const domain = (cookie.domain ?? '').replace(/^\./, '').toLowerCase();
            if (domain === target || domain.endsWith('.' + target)) {
              const url = `${cookie.secure ? 'https' : 'http'}://${domain}${cookie.path ?? '/'}`;
              await sess.cookies.remove(url, cookie.name).catch(() => {});
            }
          }
        } catch { /* best-effort */ }

        try {
          await sess.clearStorageData({
            origin: `https://${target}`,
            storages: ['cookies', 'localstorage', 'indexdb', 'websql', 'serviceworkers', 'cachestorage'],
          });
          await sess.clearStorageData({
            origin: `http://${target}`,
            storages: ['cookies', 'localstorage', 'indexdb', 'websql', 'serviceworkers', 'cachestorage'],
          });
        } catch { /* best-effort */ }
      }

      // 4. Site permissions for matching origins.
      let permissionsRemoved = 0;
      try {
        for (const row of getAllSitePermissions()) {
          try {
            const h = new URL(row.origin).hostname.toLowerCase();
            if (h === target || h.endsWith('.' + target)) {
              revokeSitePermission(row.origin, row.permission);
              permissionsRemoved++;
            }
          } catch { /* skip unparsable origins */ }
        }
      } catch { /* best-effort */ }

      // 5. Saved passwords for matching origins.
      let passwordsRemoved = 0;
      try {
        for (const row of getAllSavedPasswords()) {
          try {
            const h = new URL(row.origin).hostname.toLowerCase();
            if (h === target || h.endsWith('.' + target)) {
              deletePassword(row.id);
              passwordsRemoved++;
            }
          } catch { /* skip unparsable origins */ }
        }
      } catch { /* best-effort */ }

      return { ok: true, historyDeleted: deleted.length, permissionsRemoved, passwordsRemoved };
    } catch {
      return { ok: false, historyDeleted: 0 };
    }
  });

  // ── Safety Check ──────────────────────────────────────────────────────────
  ipcMain.handle('safety:check', (event) => {
    const result = {
      passwords: { total: 0, weak: 0, reused: 0 },
      permissions: { allowed: 0 },
      trackerBlocking: false,
      doNotTrack: false,
    };
    if (senderIsPrivate(event)) return result;
    try {
      const rows = getAllSavedPasswords();
      result.passwords.total = rows.length;
      const seen = new Map<string, number>();
      for (const row of rows) {
        try {
          const plain = decryptPassword(row.password_enc);
          if (plain === null) continue;
          if (plain.length > 0 && plain.length < 8) result.passwords.weak++;
          seen.set(plain, (seen.get(plain) ?? 0) + 1);
        } catch { /* skip undecryptable rows */ }
      }
      for (const count of seen.values()) {
        if (count > 1) result.passwords.reused += count;
      }
    } catch { /* best-effort */ }
    try {
      result.permissions.allowed = getAllSitePermissions().filter(r => r.decision === 'allow').length;
    } catch { /* best-effort */ }
    try { result.trackerBlocking = isTrackerBlockingEnabled(); } catch { /* best-effort */ }
    try { result.doNotTrack = getPreference(PREFERENCE_KEYS.DO_NOT_TRACK) === '1'; } catch { /* best-effort */ }
    return result;
  });
}
