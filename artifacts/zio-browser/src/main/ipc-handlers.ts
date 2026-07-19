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
import type { TabManager } from './tab-manager';
import type { WindowModeManager } from './window-mode-manager';
import { SyncRetryRunner } from './sync-retry';
import type { SyncEntityKind } from '../shared/sync-engine';
import { isSyncDue, SYNC_INTERVALS } from '../shared/sync-engine';
import {
  initDb,
  getPreference,
  setPreference,
  getAllPreferences,
  recordVisit,
  searchHistory,
  getRecentHistory,
  clearHistory,
  clearHistoryByRange,
  deleteHistoryEntry,
  addBookmark,
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
} from './tracker-blocker';
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
import { profileFromWorkspace, sessionPartitionForProfile, DEFAULT_PROFILE_ID } from '../shared/profile-store';
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

/**
 * Return true when the IPC event was sent from a private/incognito window.
 */
function senderIsPrivate(event: Electron.IpcMainInvokeEvent): boolean {
  const win = BrowserWindow.fromWebContents(event.sender);
  return win !== null && isPrivateWindow(win);
}

// ── Handler registration ─────────────────────────────────────────────────────

let _handlersRegistered = false;

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
  syncRetryRunner.start();

  // ── DB init ──────────────────────────────────────────────────────────────
  ipcMain.handle('db:init', () => { initDb(); return { ok: true }; });

  // ── Preferences ─────────────────────────────────────────────────────────
  ipcMain.handle('prefs:get', (_, key: PrefKey) => getPreference(key));
  ipcMain.handle('prefs:set', (_, key: PrefKey, value: string) => { setPreference(key, value); return true; });
  ipcMain.handle('prefs:all', () => getAllPreferences());

  // ── Theme ────────────────────────────────────────────────────────────────
  ipcMain.handle('theme:get', () => nativeTheme.shouldUseDarkColors ? 'dark' : 'light');
  ipcMain.handle('theme:set', (_, mode: 'system' | 'light' | 'dark') => {
    nativeTheme.themeSource = mode;
    return nativeTheme.shouldUseDarkColors ? 'dark' : 'light';
  });

  // ── Auth ─────────────────────────────────────────────────────────────────
  ipcMain.handle('auth:store-token', (_, token: string) => { storeToken(token); return true; });
  ipcMain.handle('auth:get-token', () => retrieveToken());
  ipcMain.handle('auth:clear', () => { clearToken(); clearUser(); clearSayzioLinksCache(); return true; });
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
    const stored = getPreference('zio_panel_docked');
    return stored === '1';
  });
  ipcMain.handle('window:set-zio-panel-docked', (event, docked: boolean) => {
    setPreference('zio_panel_docked', docked ? '1' : '0');
    resolveModeManager(event)?.setZioPanelDocked(docked);
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
  ipcMain.handle('bookmarks:add', (event, url: string, title: string, opts?: Record<string, string>) =>
    addBookmark(url, title, opts, resolveProfileId(event)),
  );
  ipcMain.handle('bookmarks:remove', (event, url: string) => removeBookmark(url, resolveProfileId(event)));
  ipcMain.handle('bookmarks:is-bookmarked', (event, url: string) => isBookmarked(url, resolveProfileId(event)));
  ipcMain.handle('bookmarks:all', (event, folder?: string) => getAllBookmarks(folder, resolveProfileId(event)));
  ipcMain.handle('bookmarks:search', (event, q: string) => searchBookmarks(q, 20, resolveProfileId(event)));

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
  ipcMain.handle('browsing-data:clear', async (_, options: {
    range: 'hour' | 'day' | 'week' | '4weeks' | 'all';
    clearHistory: boolean;
    clearCookies: boolean;
    clearCache: boolean;
  }) => {
    try {
      const { range, clearHistory: doHistory, clearCookies: doCookies, clearCache: doCache } = options ?? {};

      // Compute the lower-bound ISO timestamp for range-based clears.
      const sinceIso: string | null = (() => {
        if (range === 'all') return null;
        const msMap: Record<string, number> = {
          hour: 60 * 60 * 1000,
          day: 24 * 60 * 60 * 1000,
          week: 7 * 24 * 60 * 60 * 1000,
          '4weeks': 28 * 24 * 60 * 60 * 1000,
        };
        const ms = msMap[range];
        return ms ? new Date(Date.now() - ms).toISOString() : null;
      })();

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

      // 3. Cookies & site data via Electron session API.
      if (doCookies) {
        await session.defaultSession.clearStorageData({
          storages: ['cookies', 'localstorage', 'indexdb', 'websql', 'serviceworkers'],
        });
      }

      // 4. Cache files.
      if (doCache) {
        await session.defaultSession.clearStorageData({
          storages: ['cachestorage', 'shadercache'],
        });
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
  ipcMain.handle('profiles:warm-session', (_, profileId: string) => {
    const partition = sessionPartitionForProfile(profileId);
    // Accessing the session via fromPartition creates + registers it.
    const { session: electronSession } = require('electron') as typeof import('electron');
    void electronSession.fromPartition(partition);
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
    const apiBase = prefs['sayzio_api_base_url'] ?? 'https://1in.me';
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
    const apiBase = prefs['sayzio_api_base_url'] ?? 'https://1in.me';
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
}
