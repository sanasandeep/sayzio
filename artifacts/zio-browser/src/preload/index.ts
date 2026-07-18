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
    find: (id: string, text: string, forward?: boolean, matchCase?: boolean) => ipcRenderer.invoke('tabs:find', id, text, forward, matchCase),
    findStop: (id: string) => ipcRenderer.invoke('tabs:find-stop', id),
    mute: (id: string, muted: boolean) => ipcRenderer.invoke('tabs:mute', id, muted),
    getState: (id: string) => ipcRenderer.invoke('tabs:get-state', id),
    getOrder: () => ipcRenderer.invoke('tabs:get-order'),
    getActive: () => ipcRenderer.invoke('tabs:get-active'),
    extractContext: (id: string) => ipcRenderer.invoke('tabs:extract-context', id),
    autofillForm: (id: string, card: Record<string, string | undefined>) =>
      ipcRenderer.invoke('tabs:autofill-form', id, card),
    injectPasswordDetector: (id: string) => ipcRenderer.invoke('tabs:inject-password-detector', id),
    popPendingCredential: (id: string) =>
      ipcRenderer.invoke('tabs:pop-pending-credential', id),
  },

  // ── Window mode ───────────────────────────────────────────────────────────
  window: {
    getMode: () => ipcRenderer.invoke('window:get-mode'),
    setMode: (mode: string) => ipcRenderer.invoke('window:set-mode', mode),
    getSplitRatio: () => ipcRenderer.invoke('window:get-split-ratio'),
    setSplitRatio: (ratio: number) => ipcRenderer.invoke('window:set-split-ratio', ratio),
    reloadDashboard: () => ipcRenderer.invoke('window:reload-dashboard'),
    getZioPanelWidth: () => ipcRenderer.invoke('window:get-zio-panel-width'),
    setZioPanelWidth: (width: number) => ipcRenderer.invoke('window:set-zio-panel-width', width),
    getZioPanelDocked: () => ipcRenderer.invoke('window:get-zio-panel-docked'),
    setZioPanelDocked: (docked: boolean) => ipcRenderer.invoke('window:set-zio-panel-docked', docked),
  },

  // ── History ───────────────────────────────────────────────────────────────
  history: {
    record: (url: string, title: string | null, favicon?: string) => ipcRenderer.invoke('history:record', url, title, favicon),
    search: (q: string) => ipcRenderer.invoke('history:search', q),
    recent: () => ipcRenderer.invoke('history:recent'),
    clear: () => ipcRenderer.invoke('history:clear'),
    delete: (id: string) => ipcRenderer.invoke('history:delete', id),
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
    search: (q: string) => ipcRenderer.invoke('downloads:search', q),
    open: (filePath: string) => ipcRenderer.invoke('downloads:open', filePath),
    show: (filePath: string) => ipcRenderer.invoke('downloads:show', filePath),
    choosePath: () => ipcRenderer.invoke('downloads:choose-path'),
    pause: (id: string) => ipcRenderer.invoke('downloads:pause', id),
    resume: (id: string) => ipcRenderer.invoke('downloads:resume', id),
    cancel: (id: string) => ipcRenderer.invoke('downloads:cancel', id),
    retry: (url: string) => ipcRenderer.invoke('downloads:retry', url),
    remove: (id: string) => ipcRenderer.invoke('downloads:remove', id),
    clear: () => ipcRenderer.invoke('downloads:clear'),
  },

  // ── Cookies ───────────────────────────────────────────────────────────────
  cookies: {
    getForSite: (url: string) => ipcRenderer.invoke('cookies:get-for-site', url),
    getAll: () => ipcRenderer.invoke('cookies:get-all'),
    delete: (name: string, url: string) => ipcRenderer.invoke('cookies:delete', name, url),
    clearForSite: (url: string) => ipcRenderer.invoke('cookies:clear-for-site', url),
    clearAll: () => ipcRenderer.invoke('cookies:clear-all'),
  },

  // ── Passwords ─────────────────────────────────────────────────────────────
  passwords: {
    save: (origin: string, username: string, plainPassword: string) =>
      ipcRenderer.invoke('passwords:save', origin, username, plainPassword),
    list: () => ipcRenderer.invoke('passwords:list'),
    getForOrigin: (origin: string) => ipcRenderer.invoke('passwords:get-for-origin', origin),
    reveal: (id: string) => ipcRenderer.invoke('passwords:reveal', id),
    delete: (id: string) => ipcRenderer.invoke('passwords:delete', id),
    deleteAll: () => ipcRenderer.invoke('passwords:delete-all'),
  },

  // ── Browsing data ─────────────────────────────────────────────────────────
  browsingData: {
    clear: () => ipcRenderer.invoke('browsing-data:clear'),
  },

  // ── Sync ──────────────────────────────────────────────────────────────────
  sync: {
    state: (entity: string) => ipcRenderer.invoke('sync:state', entity),
    queuePush: (entity: string, payloadJson: string, error?: string) =>
      ipcRenderer.invoke('sync:queue-push', entity, payloadJson, error),
    pendingCount: () => ipcRenderer.invoke('sync:pending-count'),
    flush: () => ipcRenderer.invoke('sync:flush'),
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
      'tab:find-result',
      'download:started',
      'download:progress',
      'download:done',
      'find:open',
      // Link tools — context menu "Add to my biolink" trigger
      'biolink:add-page',
      'window:mode-changed',
      'sync:queue-changed',
      // Downloads panel
      'download:paused',
      'download:resumed',
      'download:cancelled',
      // Password offer — main process detected a login form submission
      'password:detected',
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
