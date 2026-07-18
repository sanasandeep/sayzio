/**
 * IPC handlers — bridge between the renderer process and the main process.
 * All sensitive operations (DB, auth, downloads) run here.
 */
import { ipcMain, shell, dialog, clipboard, nativeTheme, BrowserWindow } from 'electron';
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
  getSyncState,
  enqueueSyncPush,
  countSyncQueue,
} from './db';
import { buildAutofillScript } from '../shared/form-autofill';
import type { AutofillCard } from '../shared/form-autofill';
import { storeToken, retrieveToken, clearToken, storeUser, retrieveUser, clearUser } from './auth-store';
import { createCollection, createSavedLink } from '../shared/collection-store';
import type { PREFERENCE_KEYS } from '../shared/db-schema';
import type { WindowMode } from '../shared/window-mode';

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
  ipcMain.handle('tabs:find', (_, id: string, text: string, forward?: boolean) => { tabManager.findInPage(id, text, forward); return true; });
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

  // ── History ──────────────────────────────────────────────────────────────
  ipcMain.handle('history:record', (_, url: string, title: string | null, favicon?: string) => {
    return recordVisit(url, title, favicon);
  });
  ipcMain.handle('history:search', (_, q: string) => searchHistory(q));
  ipcMain.handle('history:recent', () => getRecentHistory());
  ipcMain.handle('history:clear', () => { clearHistory(); return true; });

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
  ipcMain.handle('downloads:open', async (_, path: string) => {
    await shell.openPath(path);
    return true;
  });
  ipcMain.handle('downloads:show', async (_, path: string) => {
    shell.showItemInFolder(path);
    return true;
  });
  ipcMain.handle('downloads:choose-path', async () => {
    const result = await dialog.showSaveDialog({ title: 'Save File' });
    return result.canceled ? null : result.filePath;
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
  // Injects the autofill script into the active tab's page.
  // The card data comes from the renderer (already fetched from the Sayzio API)
  // so no credentials pass through this handler.
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

  // ── Version info ─────────────────────────────────────────────────────────
  ipcMain.handle('app:version', () => {
    const { app } = require('electron') as typeof import('electron');
    return { version: app.getVersion(), name: app.getName() };
  });
}
