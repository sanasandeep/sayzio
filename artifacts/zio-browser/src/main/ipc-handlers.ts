/**
 * IPC handlers — bridge between the renderer process and the main process.
 * All sensitive operations (DB, auth, downloads) run here.
 */
import { ipcMain, shell, dialog, clipboard, nativeTheme, BrowserWindow, session } from 'electron';
import type { TabManager } from './tab-manager';
import type { WindowModeManager } from './window-mode-manager';
import { SyncRetryRunner } from './sync-retry';
import type { SyncEntityKind } from '../shared/sync-engine';
import {
  initDb,
  getPreference,
  setPreference,
  getAllPreferences,
  recordVisit,
  searchHistory,
  getRecentHistory,
  clearHistory,
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
  enqueueSyncPush,
  countSyncQueue,
  savePassword,
  getPasswordsForOrigin,
  getAllSavedPasswords,
  deletePassword,
  deleteAllPasswords,
} from './db';
import { getActiveItem } from './download-manager';
import { buildAutofillScript } from '../shared/form-autofill';
import type { AutofillCard } from '../shared/form-autofill';
import { storeToken, retrieveToken, clearToken, storeUser, retrieveUser, clearUser } from './auth-store';
import { encryptPassword, decryptPassword } from './password-store';
import { createCollection, createSavedLink } from '../shared/collection-store';
import type { PREFERENCE_KEYS } from '../shared/db-schema';
import type { WindowMode } from '../shared/window-mode';
import {
  DEFAULT_ZIO_PANEL_WIDTH,
  MIN_ZIO_PANEL_WIDTH,
  MAX_ZIO_PANEL_WIDTH,
} from '../shared/window-mode';

type PrefKey = typeof PREFERENCE_KEYS[keyof typeof PREFERENCE_KEYS];

export function registerIpcHandlers(tabManager: TabManager, modeManager?: WindowModeManager, mainWindow?: BrowserWindow): void {
  // Background retry loop for failed sync pushes (persisted in sync_queue)
  const syncRetryRunner = new SyncRetryRunner({
    onQueueChanged: (pendingCount) => {
      mainWindow?.webContents.send('sync:queue-changed', pendingCount);
    },
  });
  syncRetryRunner.start();


  // ── DB init ──────────────────────────────────────────────────────────────
  ipcMain.handle('db:init', () => {
    initDb();
    return { ok: true };
  });

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
  ipcMain.handle('auth:clear', () => { clearToken(); clearUser(); return true; });
  ipcMain.handle('auth:store-user', (_, user: Record<string, unknown>) => { storeUser(user); return true; });
  ipcMain.handle('auth:get-user', () => retrieveUser());

  // ── Tabs ─────────────────────────────────────────────────────────────────
  ipcMain.handle('tabs:create', (_, url?: string, background?: boolean) => {
    return tabManager.createTab(url, background);
  });
  ipcMain.handle('tabs:close', (_, id: string) => { tabManager.closeTab(id); return true; });
  ipcMain.handle('tabs:activate', (_, id: string) => { tabManager.activateTab(id); return true; });
  ipcMain.handle('tabs:navigate', (_, id: string, input: string) => { tabManager.navigate(id, input); return true; });
  ipcMain.handle('tabs:back', (_, id: string) => { tabManager.goBack(id); return true; });
  ipcMain.handle('tabs:forward', (_, id: string) => { tabManager.goForward(id); return true; });
  ipcMain.handle('tabs:reload', (_, id: string, force?: boolean) => { tabManager.reload(id, force); return true; });
  ipcMain.handle('tabs:stop', (_, id: string) => { tabManager.stop(id); return true; });
  ipcMain.handle('tabs:zoom', (_, id: string, factor: number) => { tabManager.setZoom(id, factor); return true; });
  ipcMain.handle('tabs:find', (_, id: string, text: string, forward?: boolean, matchCase?: boolean) => { tabManager.findInPage(id, text, forward, matchCase); return true; });
  ipcMain.handle('tabs:find-stop', (_, id: string) => { tabManager.stopFindInPage(id); return true; });
  ipcMain.handle('tabs:mute', (_, id: string, muted: boolean) => { tabManager.muteTab(id, muted); return true; });
  ipcMain.handle('tabs:get-state', (_, id: string) => tabManager.getTabState(id));
  ipcMain.handle('tabs:get-order', () => tabManager.getTabOrder());
  ipcMain.handle('tabs:get-active', () => tabManager.getActiveTabId());

  // Page context extraction — runs JS in the page via executeJavaScript
  ipcMain.handle('tabs:extract-context', async (_, id: string) => {
    const wc = tabManager.getWebContents(id);
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
          
          // Extract readable text using simple article extraction
          const article = document.querySelector('article, main, [role="main"]') || document.body;
          const clone = article.cloneNode(true);
          // Remove scripts, styles, navs
          clone.querySelectorAll('script,style,nav,header,footer,aside,.ad,.advertisement').forEach(el => el.remove());
          const text = clone.innerText || clone.textContent || '';
          
          // Extract phone numbers and emails from the page text
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
  ipcMain.handle('window:get-mode', () => modeManager?.getMode() ?? 'browser');
  ipcMain.handle('window:set-mode', (event, mode: WindowMode) => {
    if (!modeManager) return false;
    modeManager.setMode(mode);
    // Persist as last-used mode
    setPreference('window_mode', mode);
    // Notify the renderer
    const win = BrowserWindow.fromWebContents(event.sender);
    win?.webContents.send('window:mode-changed', mode);
    return true;
  });
  ipcMain.handle('window:get-split-ratio', () => modeManager?.getSplitRatio() ?? 0.35);
  ipcMain.handle('window:set-split-ratio', (_, ratio: number) => {
    if (!modeManager) return false;
    modeManager.setSplitRatio(ratio);
    setPreference('split_ratio', String(ratio));
    return true;
  });
  ipcMain.handle('window:reload-dashboard', () => {
    modeManager?.reloadDashboard();
    return true;
  });

  // ── Zio panel width / presentation (browser mode) ────────────────────────
  ipcMain.handle('window:get-zio-panel-width', () => {
    const stored = getPreference('zio_panel_width');
    const n = stored ? Number(stored) : DEFAULT_ZIO_PANEL_WIDTH;
    return Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, n));
  });
  ipcMain.handle('window:set-zio-panel-width', (_, width: number) => {
    const clamped = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, width));
    setPreference('zio_panel_width', String(clamped));
    modeManager?.setZioPanelWidth(clamped);
    return true;
  });
  ipcMain.handle('window:get-zio-panel-docked', () => {
    const stored = getPreference('zio_panel_docked');
    return stored === '1';
  });
  ipcMain.handle('window:set-zio-panel-docked', (_, docked: boolean) => {
    setPreference('zio_panel_docked', docked ? '1' : '0');
    modeManager?.setZioPanelDocked(docked);
    return true;
  });

  // ── History ──────────────────────────────────────────────────────────────
  ipcMain.handle('history:record', (_, url: string, title: string | null, favicon?: string) => {
    return recordVisit(url, title, favicon);
  });
  ipcMain.handle('history:search', (_, q: string) => searchHistory(q));
  ipcMain.handle('history:recent', () => getRecentHistory());
  ipcMain.handle('history:clear', () => { clearHistory(); return true; });
  ipcMain.handle('history:delete', (_, id: string) => deleteHistoryEntry(id));

  // ── Bookmarks ────────────────────────────────────────────────────────────
  ipcMain.handle('bookmarks:add', (_, url: string, title: string, opts?: Record<string, string>) => {
    return addBookmark(url, title, opts);
  });
  ipcMain.handle('bookmarks:remove', (_, url: string) => removeBookmark(url));
  ipcMain.handle('bookmarks:is-bookmarked', (_, url: string) => isBookmarked(url));
  ipcMain.handle('bookmarks:all', (_, folder?: string) => getAllBookmarks(folder));
  ipcMain.handle('bookmarks:search', (_, q: string) => searchBookmarks(q));

  // ── Collections ──────────────────────────────────────────────────────────
  ipcMain.handle('collections:all', () => getAllCollections());
  ipcMain.handle('collections:create', (_, name: string, opts?: Record<string, string>) => {
    const c = createCollection(name, opts);
    createCollectionInDb(c);
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
    const err = await shell.openPath(filePath);
    return err === '' ? { ok: true } : { ok: false, error: err };
  });
  ipcMain.handle('downloads:show', async (_, filePath: string) => {
    shell.showItemInFolder(filePath);
    return true;
  });
  ipcMain.handle('downloads:choose-path', async () => {
    const result = await dialog.showSaveDialog({ title: 'Save File' });
    return result.canceled ? null : result.filePath;
  });
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

  // ── Sync ─────────────────────────────────────────────────────────────────
  ipcMain.handle('sync:state', (_, entity: string) => getSyncState(entity));
  ipcMain.handle('sync:queue-push', (_, entity: SyncEntityKind, payloadJson: string, error?: string) => {
    const item = enqueueSyncPush(entity, payloadJson, error ?? null);
    syncRetryRunner.notify();
    return item.id;
  });
  ipcMain.handle('sync:pending-count', () => countSyncQueue());
  ipcMain.handle('sync:flush', async () => {
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
      if (allowed.includes(proto)) {
        await shell.openExternal(url);
        return true;
      }
    } catch { }
    return false;
  });

  // ── Form autofill ─────────────────────────────────────────────────────────
  ipcMain.handle('tabs:autofill-form', async (_, id: string, card: AutofillCard) => {
    const wc = tabManager.getWebContents(id);
    if (!wc) return { filled: 0, filled_fields: [] };
    try {
      const script = buildAutofillScript(card);
      const result = await wc.executeJavaScript(script);
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
  ipcMain.handle('tabs:inject-password-detector', async (_, id: string) => {
    const wc = tabManager.getWebContents(id);
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
  ipcMain.handle('tabs:pop-pending-credential', async (_, id: string) => {
    const wc = tabManager.getWebContents(id);
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

  // ── Clear all browsing data ───────────────────────────────────────────────
  ipcMain.handle('browsing-data:clear', async () => {
    try {
      await session.defaultSession.clearStorageData({
        storages: ['cookies', 'localstorage', 'caches', 'shadercache', 'indexdb', 'websql', 'serviceworkers'],
      });
      clearHistory();
      return true;
    } catch {
      return false;
    }
  });

  // ── Version info ─────────────────────────────────────────────────────────
  ipcMain.handle('app:version', () => {
    const { app } = require('electron') as typeof import('electron');
    return { version: app.getVersion(), name: app.getName() };
  });
}
