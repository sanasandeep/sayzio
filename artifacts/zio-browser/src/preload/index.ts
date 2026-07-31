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

/**
 * on() wraps each renderer listener so the IPC event object is stripped from
 * the args; off() must remove the exact wrapper we registered, so keep a
 * per-channel map from the original listener to its wrapper.
 */
const listenerWrappers = new Map<string, Map<IpcListener, (...args: unknown[]) => void>>();

const api = {
  // ── Platform (for platform-specific chrome, e.g. Windows/Linux title bars) ─
  platform: process.platform,

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
    navigatePane: (id: string, pane: 'primary' | 'second', input: string) =>
      ipcRenderer.invoke('tabs:navigate-pane', id, pane, input),
    back: (id: string) => ipcRenderer.invoke('tabs:back', id),
    forward: (id: string) => ipcRenderer.invoke('tabs:forward', id),
    reload: (id: string, force?: boolean) => ipcRenderer.invoke('tabs:reload', id, force),
    stop: (id: string) => ipcRenderer.invoke('tabs:stop', id),
    zoom: (id: string, factor: number) => ipcRenderer.invoke('tabs:zoom', id, factor),
    find: (id: string, text: string, forward?: boolean, matchCase?: boolean) => ipcRenderer.invoke('tabs:find', id, text, forward, matchCase),
    findStop: (id: string) => ipcRenderer.invoke('tabs:find-stop', id),
    mute: (id: string, muted: boolean) => ipcRenderer.invoke('tabs:mute', id, muted),
    setMode: (id: string, mode: string) => ipcRenderer.invoke('tabs:set-mode', id, mode),
    setSplitRatio: (id: string, ratio: number) => ipcRenderer.invoke('tabs:set-split-ratio', id, ratio),
    getState: (id: string) => ipcRenderer.invoke('tabs:get-state', id),
    getOrder: () => ipcRenderer.invoke('tabs:get-order'),
    getActive: () => ipcRenderer.invoke('tabs:get-active'),
    extractContext: (id: string) => ipcRenderer.invoke('tabs:extract-context', id),
    autofillForm: (id: string, card: Record<string, string | undefined>) =>
      ipcRenderer.invoke('tabs:autofill-form', id, card),
    injectPasswordDetector: (id: string) => ipcRenderer.invoke('tabs:inject-password-detector', id),
    popPendingCredential: (id: string) =>
      ipcRenderer.invoke('tabs:pop-pending-credential', id),
    // ── Tab management ──────────────────────────────────────────────────────
    pin: (id: string, pinned: boolean) => ipcRenderer.invoke('tabs:pin', id, pinned),
    move: (id: string, toIndex: number) => ipcRenderer.invoke('tabs:move', id, toIndex),
    duplicate: (id: string) => ipcRenderer.invoke('tabs:duplicate', id),
    closeOthers: (id: string) => ipcRenderer.invoke('tabs:close-others', id),
    closeToRight: (id: string) => ipcRenderer.invoke('tabs:close-to-right', id),
    muteAll: (muted?: boolean) => ipcRenderer.invoke('tabs:mute-all', muted),
    reopenClosed: () => ipcRenderer.invoke('tabs:reopen-closed'),
    recentlyClosed: () => ipcRenderer.invoke('tabs:recently-closed'),
    reopenFromRecent: (url: string) => ipcRenderer.invoke('tabs:reopen-from-recent', url),
    restoreSession: () => ipcRenderer.invoke('tabs:restore-session'),
    hideAll: () => ipcRenderer.invoke('tabs:hide-all'),
  },

  // ── Window mode ───────────────────────────────────────────────────────────
  window: {
    getMode: () => ipcRenderer.invoke('window:get-mode'),
    setMode: (mode: string) => ipcRenderer.invoke('window:set-mode', mode),
    getSplitRatio: () => ipcRenderer.invoke('window:get-split-ratio'),
    setSplitRatio: (ratio: number) => ipcRenderer.invoke('window:set-split-ratio', ratio),
    reloadDashboard: () => ipcRenderer.invoke('window:reload-dashboard'),
    /** Hide/restore all native views while a chrome dropdown/menu is open. */
    setChromeOverlay: (open: boolean) => ipcRenderer.invoke('window:set-chrome-overlay', open),
    getZioPanelWidth: () => ipcRenderer.invoke('window:get-zio-panel-width'),
    setZioPanelWidth: (width: number) => ipcRenderer.invoke('window:set-zio-panel-width', width),
    getZioPanelDocked: () => ipcRenderer.invoke('window:get-zio-panel-docked'),
    setZioPanelDocked: (docked: boolean) => ipcRenderer.invoke('window:set-zio-panel-docked', docked),
    /** Report whether the docked Zio panel is currently rendered (layout reserve). */
    setZioPanelVisible: (visible: boolean) => ipcRenderer.invoke('window:set-zio-panel-visible', visible),
    /** Returns true when this renderer is running inside a private/incognito window. */
    isPrivate: () => ipcRenderer.invoke('window:is-private') as Promise<boolean>,
    /** Ask the main process to open a new private window, optionally starting at a URL. */
    openPrivate: (url?: string) => ipcRenderer.invoke('window:open-private', url) as Promise<boolean>,
    openNew: () => ipcRenderer.invoke('window:open-new') as Promise<boolean>,
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
  browserImport: {
    detect: () => ipcRenderer.invoke('import:detect') as Promise<Array<{ id: string; name: string; hasBookmarks: boolean; hasHistory: boolean }>>,
    run: (browserId: string, want: { bookmarks?: boolean; history?: boolean }) =>
      ipcRenderer.invoke('import:run', browserId, want) as Promise<{ ok: boolean; bookmarksImported?: number; historyImported?: number; error?: string; canceled?: boolean }>,
    fromHtmlFile: () =>
      ipcRenderer.invoke('import:html-file') as Promise<{ ok: boolean; bookmarksImported?: number; historyImported?: number; error?: string; canceled?: boolean }>,
  },
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
    openInTab: (filePath: string) => ipcRenderer.invoke('downloads:open-in-tab', filePath),
    show: (filePath: string) => ipcRenderer.invoke('downloads:show', filePath),
    exists: (filePath: string) => ipcRenderer.invoke('downloads:exists', filePath),
    choosePath: () => ipcRenderer.invoke('downloads:choose-path'),
    chooseDirectory: () => ipcRenderer.invoke('downloads:choose-directory'),
    defaultDirectory: () => ipcRenderer.invoke('downloads:default-directory'),
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

  // ── Virtual keyboard ──────────────────────────────────────────────────────
  vk: {
    insertText: (text: string) => ipcRenderer.invoke('vk:insert-text', text) as Promise<boolean>,
    sendKey: (key: string) => ipcRenderer.invoke('vk:send-key', key) as Promise<boolean>,
    setReserve: (px: number) => ipcRenderer.invoke('vk:set-reserve', px) as Promise<boolean>,
    recordWords: (words: string[], pairs: Array<[string, string]> = []) =>
      ipcRenderer.invoke('vk:record-words', words, pairs) as Promise<boolean>,
    clearHistory: () => ipcRenderer.invoke('vk:clear-history') as Promise<boolean>,
    stripShow: () => ipcRenderer.invoke('vk:strip-show') as Promise<boolean>,
    stripUpdate: (payload: unknown) => ipcRenderer.invoke('vk:strip-update', payload) as Promise<boolean>,
    stripHide: () => ipcRenderer.invoke('vk:strip-hide') as Promise<boolean>,
  },

  // ── Spell check ───────────────────────────────────────────────────────────
  spellcheck: {
    getEnabled: () => ipcRenderer.invoke('spellcheck:get-enabled') as Promise<boolean>,
    setEnabled: (enabled: boolean) => ipcRenderer.invoke('spellcheck:set-enabled', enabled) as Promise<boolean>,
  },

  // ── Extensions (unpacked) ─────────────────────────────────────────────────
  extensions: {
    list: () => ipcRenderer.invoke('extensions:list') as Promise<Array<{ id: string; name: string; version: string; path: string; builtin?: boolean }>>,
    add: () => ipcRenderer.invoke('extensions:add') as Promise<
      { ok: true; extension: { id: string; name: string; version: string; path: string } } | { ok: false; error: string }
    >,
    remove: (id: string) => ipcRenderer.invoke('extensions:remove', id) as Promise<boolean>,
  },

  // ── Browsing data ─────────────────────────────────────────────────────────
  browsingData: {
    clear: (options: {
      range: '15min' | 'hour' | 'day' | 'week' | '4weeks' | 'all';
      clearHistory: boolean;
      clearCookies: boolean;
      clearCache: boolean;
      clearDownloads?: boolean;
      clearPermissions?: boolean;
    }) => ipcRenderer.invoke('browsing-data:clear', options),
    counts: (range: '15min' | 'hour' | 'day' | 'week' | '4weeks' | 'all') =>
      ipcRenderer.invoke('browsing-data:counts', range) as Promise<{
        historyCount: number;
        cookieCount: number;
        cacheBytes: number;
        downloadCount: number;
        permissionCount: number;
      }>,
  },

  // ── Privacy & safety ──────────────────────────────────────────────────────
  privacy: {
    trackerStats: () => ipcRenderer.invoke('tracker:stats') as Promise<{
      weekTotal: number;
      todayTotal: number;
      byDay: Array<{ day: string; count: number }>;
      topTrackers: Array<{ host: string; count: number }>;
    }>,
    safetyCheck: () => ipcRenderer.invoke('safety:check') as Promise<{
      passwords: { total: number; weak: number; reused: number };
      permissions: { allowed: number };
      trackerBlocking: boolean;
      doNotTrack: boolean;
    }>,
    forgetSite: (host: string) => ipcRenderer.invoke('site:forget', host) as Promise<{
      ok: boolean;
      historyDeleted: number;
      permissionsRemoved?: number;
      passwordsRemoved?: number;
    }>,
  },

  // ── Reading list ──────────────────────────────────────────────────────────
  readingList: {
    add: (url: string, title: string, favicon?: string) => ipcRenderer.invoke('reading-list:add', url, title, favicon),
    isSaved: (url: string) => ipcRenderer.invoke('reading-list:is-saved', url),
    all: () => ipcRenderer.invoke('reading-list:all'),
    unreadCount: () => ipcRenderer.invoke('reading-list:unread-count'),
    markRead: (id: string, isRead: boolean) => ipcRenderer.invoke('reading-list:mark-read', id, isRead),
    remove: (id: string) => ipcRenderer.invoke('reading-list:remove', id),
  },

  // ── Sync ──────────────────────────────────────────────────────────────────
  sync: {
    state: (entity: string) => ipcRenderer.invoke('sync:state', entity),
    queuePush: (entity: string, payloadJson: string, error?: string) =>
      ipcRenderer.invoke('sync:queue-push', entity, payloadJson, error),
    pendingCount: () => ipcRenderer.invoke('sync:pending-count'),
    pendingByProfile: () => ipcRenderer.invoke('sync:pending-by-profile'),
    flush: () => ipcRenderer.invoke('sync:flush'),
  },

  // ── Screenshot ────────────────────────────────────────────────────────────
  screenshot: {
    /** Capture the tab as a PNG data URL. fullPage=true stitches the full scroll height. */
    capture: (tabId: string, fullPage: boolean) =>
      ipcRenderer.invoke('screenshot:capture', tabId, fullPage) as Promise<string | null>,
    /** Open a system save dialog and write the PNG to disk. Returns the file path or null. */
    saveToDisk: (dataUrl: string, suggestedName?: string) =>
      ipcRenderer.invoke('screenshot:save-to-disk', dataUrl, suggestedName) as Promise<string | null>,
    /** Write the PNG to the system clipboard as a native image. */
    copyToClipboard: (dataUrl: string) =>
      ipcRenderer.invoke('screenshot:copy-to-clipboard', dataUrl) as Promise<boolean>,
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

  // ── Profiles ─────────────────────────────────────────────────────────────
  profiles: {
    /** List all locally known profiles (personal + workspace). */
    list: () => ipcRenderer.invoke('profiles:list'),
    /** Return the currently active profile ID. */
    getActive: () => ipcRenderer.invoke('profiles:get-active'),
    /**
     * Switch to a different profile.
     * Persists the choice, updates DB scope, and updates tab session partition.
     * Emits 'profile:changed' back to the renderer.
     */
    switch: (profileId: string) => ipcRenderer.invoke('profiles:switch', profileId),
    /**
     * Upsert a profile from a workspace API response item.
     * Call this after fetching /api/v1/workspaces.
     */
    upsertFromWorkspace: (ws: { id: number | string; name: string; is_personal?: boolean }) =>
      ipcRenderer.invoke('profiles:upsert-from-workspace', ws),
    /** Pre-warm the Electron session partition for the given profile. */
    warmSession: (profileId: string) => ipcRenderer.invoke('profiles:warm-session', profileId),
  },

  // ── Sayzio links (local cache) ────────────────────────────────────────────
  sayzioLinks: {
    /** Read the locally cached Sayzio links (works offline / signed out). */
    cached: () => ipcRenderer.invoke('sayzio-links:cached'),
    /** Refresh the cache from the API when online + authenticated; returns the fresh list. */
    refresh: (force?: boolean) => ipcRenderer.invoke('sayzio-links:refresh', force),
  },

  // ── Device Lab ────────────────────────────────────────────────────────────
  deviceLab: {
    /** Fetch the authenticated user's biolinks from the Sayzio API. */
    listBiolinks: () => ipcRenderer.invoke('device-lab:list-biolinks'),
  },

  // ── Site permissions ──────────────────────────────────────────────────────
  permissions: {
    getAll: () => ipcRenderer.invoke('permissions:get-all'),
    set: (origin: string, permission: string, decision: 'allow' | 'block') =>
      ipcRenderer.invoke('permissions:set', origin, permission, decision),
    revoke: (origin: string, permission: string) =>
      ipcRenderer.invoke('permissions:revoke', origin, permission),
    clearAll: () => ipcRenderer.invoke('permissions:clear-all'),
    respond: (requestId: string, decision: 'allow' | 'block', remember: boolean, origin: string, permission: string) =>
      ipcRenderer.invoke('permissions:respond', requestId, decision, remember, origin, permission),
  },

  // ── Per-site settings ("Settings for this website" popover) ──────────────
  siteSettings: {
    /** Stored settings row for an origin, or null (always null in private windows). */
    get: (origin: string) =>
      ipcRenderer.invoke('site-settings:get', origin) as Promise<{
        origin: string;
        zoom: number | null;
        autoplay: string | null;
        popups: string | null;
        content_blockers: number | null;
        updated_at: string;
      } | null>,
    /** Merge-patch settings for an origin; null values revert to the default. */
    set: (origin: string, patch: {
      zoom?: number | null;
      autoplay?: string | null;
      popups?: string | null;
      contentBlockers?: boolean | null;
    }) => ipcRenderer.invoke('site-settings:set', origin, patch) as Promise<boolean>,
  },

  // ── Named sessions (save / restore sets of tabs) ─────────────────────────
  sessions: {
    /** List saved named sessions (id, name, tabCount, updated_at). */
    list: () => ipcRenderer.invoke('sessions:list') as Promise<Array<{ id: string; name: string; tabCount: number; updated_at: string }>>,
    /** Save the current window's open tabs under a name. */
    save: (name: string) => ipcRenderer.invoke('sessions:save', name) as Promise<boolean>,
    /** Reopen a saved session's tabs in this window. */
    restore: (id: string) => ipcRenderer.invoke('sessions:restore', id) as Promise<boolean>,
    /** Delete a saved session. */
    remove: (id: string) => ipcRenderer.invoke('sessions:delete', id) as Promise<boolean>,
  },

  // ── Audio policy (per-domain mute memory + global mute) ──────────────────
  audio: {
    /** List all hosts with a stored "muted" preference. */
    mutedDomains: () => ipcRenderer.invoke('audio:muted-domains') as Promise<string[]>,
    /** Add/remove a host from the muted-domain list. */
    setDomainMuted: (host: string, muted: boolean) =>
      ipcRenderer.invoke('audio:set-domain-muted', host, muted) as Promise<boolean>,
    /** Session-level "mute all tabs" global policy. */
    getMuteAll: () => ipcRenderer.invoke('audio:get-mute-all') as Promise<boolean>,
    /** Set the global policy only (does not touch currently open tabs). */
    setMuteAll: (enabled: boolean) =>
      ipcRenderer.invoke('audio:set-mute-all', enabled) as Promise<boolean>,
  },

  // ── Tracker blocking ──────────────────────────────────────────────────────
  tracker: {
    isEnabled: () => ipcRenderer.invoke('tracker:is-enabled'),
    setEnabled: (enabled: boolean) => ipcRenderer.invoke('tracker:set-enabled', enabled),
    getCount: (tabId: string) => ipcRenderer.invoke('tracker:get-count', tabId),
    resetCount: (tabId: string) => ipcRenderer.invoke('tracker:reset-count', tabId),
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
      'tab:order-changed',
      'tab:recently-closed-changed',
      'tab:search-open',
      'download:started',
      'download:progress',
      'download:done',
      'find:open',
      // Link tools — context menu "Add to my biolink" trigger
      'biolink:add-page',
      // Link tools — context menu "Shorten this page" / "QR code" triggers
      'link:shorten-page',
      'link:create-qr',
      // Context menu "Fill form with my Sayzio card" trigger
      'autofill:page',
      // Device Lab — context menu "Preview in Device Lab" trigger
      'device-lab:preview-url',
      'window:mode-changed',
      'sync:queue-changed',
      // Downloads panel
      'download:paused',
      'download:resumed',
      'download:cancelled',
      // Password offer — main process detected a login form submission
      'password:detected',
      // Profile events
      'profile:changed',
      // Static page snapshot shown while a chrome menu holds native views detached
      'chrome-overlay:backdrop',
      // Command palette — open from main process menu shortcut
      'palette:open',
      // Settings — open from the main process menu (Cmd/Ctrl+,)
      'settings:open',
      // Bookmarks changed from the main process menu (Bookmark This Page)
      'bookmarks:changed',
      // Permission prompts
      'permission:request',
      // Tracker blocking count updates
      'tracker:blocked-count',
      // Generic message toast (e.g. "Reader mode isn't available")
      'toast:show',
      // Virtual keyboard — page field-focus reports
      'vk:focus',
      // Virtual keyboard — floating strip suggestion selected (index payload)
      'vk:strip-select',
    ]);
    if (!ALLOWED_CHANNELS.has(channel)) return;
    const wrapper = (_: unknown, ...args: unknown[]) => listener(...args);
    let channelMap = listenerWrappers.get(channel);
    if (!channelMap) {
      channelMap = new Map();
      listenerWrappers.set(channel, channelMap);
    }
    channelMap.set(listener, wrapper);
    ipcRenderer.on(channel, wrapper as Parameters<typeof ipcRenderer.on>[1]);
  },

  off: (channel: string, listener: IpcListener) => {
    const channelMap = listenerWrappers.get(channel);
    const wrapper = channelMap?.get(listener);
    if (!wrapper) return;
    channelMap!.delete(listener);
    ipcRenderer.removeListener(channel, wrapper as Parameters<typeof ipcRenderer.removeListener>[1]);
  },
};

contextBridge.exposeInMainWorld('zio', api);

export type ZioApi = typeof api;
