/**
 * Preload script — exposes safe IPC bridges to the renderer process.
 * Runs in the renderer's context but has access to Node.js APIs.
 *
 * Security: all exposed APIs go through contextBridge.exposeInMainWorld
 * with explicit allow-lists. The renderer cannot access Electron or Node directly.
 */
import { contextBridge, ipcRenderer } from 'electron';

// Type for IPC listener cleanup
type IpcListener = (...args: unknown[]) => void;

const api = {
  // ── DB / preferences ─────────────────────────────────────────────────────
  prefs: {
    get: (key: string) => ipcRenderer.invoke('prefs:get', key),
    set: (key: string, value: string) => ipcRenderer.invoke('prefs:set', key, value),
    all: () => ipcRenderer.invoke('prefs:all'),
  },

  // ── Theme ─────────────────────────────────────────────────────────────────
  theme: {
    get: () => ipcRenderer.invoke('theme:get'),
    set: (mode: 'system' | 'light' | 'dark') => ipcRenderer.invoke('theme:set', mode),
  },

  // ── Auth ──────────────────────────────────────────────────────────────────
  auth: {
    storeToken: (token: string) => ipcRenderer.invoke('auth:store-token', token),
    getToken: () => ipcRenderer.invoke('auth:get-token'),
    clear: () => ipcRenderer.invoke('auth:clear'),
    storeUser: (user: Record<string, unknown>) => ipcRenderer.invoke('auth:store-user', user),
    getUser: () => ipcRenderer.invoke('auth:get-user'),
  },

  // ── Tabs ──────────────────────────────────────────────────────────────────
  tabs: {
    create: (url?: string, background?: boolean) => ipcRenderer.invoke('tabs:create', url, background),
    close: (id: string) => ipcRenderer.invoke('tabs:close', id),
    activate: (id: string) => ipcRenderer.invoke('tabs:activate', id),
    navigate: (id: string, input: string) => ipcRenderer.invoke('tabs:navigate', id, input),
    back: (id: string) => ipcRenderer.invoke('tabs:back', id),
    forward: (id: string) => ipcRenderer.invoke('tabs:forward', id),
    reload: (id: string, force?: boolean) => ipcRenderer.invoke('tabs:reload', id, force),
    stop: (id: string) => ipcRenderer.invoke('tabs:stop', id),
    zoom: (id: string, factor: number) => ipcRenderer.invoke('tabs:zoom', id, factor),
    find: (id: string, text: string, forward?: boolean) => ipcRenderer.invoke('tabs:find', id, text, forward),
    findStop: (id: string) => ipcRenderer.invoke('tabs:find-stop', id),
    mute: (id: string, muted: boolean) => ipcRenderer.invoke('tabs:mute', id, muted),
    getState: (id: string) => ipcRenderer.invoke('tabs:get-state', id),
    getOrder: () => ipcRenderer.invoke('tabs:get-order'),
    getActive: () => ipcRenderer.invoke('tabs:get-active'),
    extractContext: (id: string) => ipcRenderer.invoke('tabs:extract-context', id),
  },

  // ── History ───────────────────────────────────────────────────────────────
  history: {
    record: (url: string, title: string | null, favicon?: string) => ipcRenderer.invoke('history:record', url, title, favicon),
    search: (q: string) => ipcRenderer.invoke('history:search', q),
    recent: () => ipcRenderer.invoke('history:recent'),
    clear: () => ipcRenderer.invoke('history:clear'),
  },

  // ── Bookmarks ─────────────────────────────────────────────────────────────
  bookmarks: {
    add: (url: string, title: string, opts?: Record<string, string>) => ipcRenderer.invoke('bookmarks:add', url, title, opts),
    remove: (url: string) => ipcRenderer.invoke('bookmarks:remove', url),
    isBookmarked: (url: string) => ipcRenderer.invoke('bookmarks:is-bookmarked', url),
    all: (folder?: string) => ipcRenderer.invoke('bookmarks:all', folder),
    search: (q: string) => ipcRenderer.invoke('bookmarks:search', q),
  },

  // ── Collections ───────────────────────────────────────────────────────────
  collections: {
    all: () => ipcRenderer.invoke('collections:all'),
    create: (name: string, opts?: Record<string, string>) => ipcRenderer.invoke('collections:create', name, opts),
    update: (id: string, updates: Record<string, string>) => ipcRenderer.invoke('collections:update', id, updates),
    delete: (id: string) => ipcRenderer.invoke('collections:delete', id),
    getLinks: (collectionId: string) => ipcRenderer.invoke('collections:get-links', collectionId),
    saveLink: (collectionId: string, url: string, title: string, opts?: Record<string, unknown>) =>
      ipcRenderer.invoke('collections:save-link', collectionId, url, title, opts),
    updateAi: (id: string, summary: string, tags: string[], context: string, coins: number) =>
      ipcRenderer.invoke('collections:update-ai', id, summary, tags, context, coins),
  },

  // ── Downloads ─────────────────────────────────────────────────────────────
  downloads: {
    recent: () => ipcRenderer.invoke('downloads:recent'),
    open: (filePath: string) => ipcRenderer.invoke('downloads:open', filePath),
    show: (filePath: string) => ipcRenderer.invoke('downloads:show', filePath),
    choosePath: () => ipcRenderer.invoke('downloads:choose-path'),
  },

  // ── Sync ──────────────────────────────────────────────────────────────────
  sync: {
    state: (entity: string) => ipcRenderer.invoke('sync:state', entity),
  },

  // ── Clipboard ─────────────────────────────────────────────────────────────
  clipboard: {
    write: (text: string) => ipcRenderer.invoke('clipboard:write', text),
    read: () => ipcRenderer.invoke('clipboard:read'),
  },

  // ── Shell ─────────────────────────────────────────────────────────────────
  shell: {
    openExternal: (url: string) => ipcRenderer.invoke('shell:open-external', url),
  },

  // ── App ───────────────────────────────────────────────────────────────────
  app: {
    version: () => ipcRenderer.invoke('app:version'),
  },

  // ── Events (from main → renderer) ────────────────────────────────────────
  on: (channel: string, listener: IpcListener) => {
    const ALLOWED_CHANNELS = new Set([
      'tab:state-changed',
      'tab:created',
      'tab:closed',
      'tab:activated',
      'tab:navigated',
      'download:started',
      'download:progress',
      'download:done',
      'find:open',
    ]);
    if (!ALLOWED_CHANNELS.has(channel)) return;
    ipcRenderer.on(channel, (_, ...args) => listener(...args));
  },

  off: (channel: string, listener: IpcListener) => {
    ipcRenderer.removeListener(channel, listener as Parameters<typeof ipcRenderer.removeListener>[1]);
  },
};

contextBridge.exposeInMainWorld('zio', api);

export type ZioApi = typeof api;
