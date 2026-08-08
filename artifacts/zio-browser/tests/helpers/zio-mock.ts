/**
 * Shared window.zio mock for renderer component tests.
 *
 * Five toolbar/tab-strip suites used to each carry their own copy-pasted
 * `buildZioMock()`; every new preload API (e.g. `notes.countForHost`) then
 * had to be hand-added to every copy or those suites broke. This helper is
 * the single source: it builds a FULL default mock covering every preload
 * namespace the toolbar components touch, and tests inject their spies via
 * per-namespace `overrides` (deep-merged one level into each namespace).
 *
 * When a new preload API lands, add its default stub HERE — the suites pick
 * it up automatically.
 *
 * Usage:
 *   const { zio, ipcHandlers, emit } = buildZioMock({
 *     overrides: { tabs: { navigate: navigateSpy } },
 *   });
 *   (window as unknown as Record<string, unknown>).zio = zio;
 *
 * Pass your own `ipcHandlers` map when listener registrations must persist
 * across tests (the tab store wires IPC exactly once per process).
 */
import { vi } from 'vitest';

export type IpcHandler = (...args: unknown[]) => void;
export type IpcHandlerMap = Map<string, Set<IpcHandler>>;

/** A vi.fn stub resolving to `v`. */
export function resolved<T>(v: T) {
  return vi.fn(() => Promise.resolve(v));
}

export interface ZioMockOptions {
  /**
   * Per-namespace overrides, deep-merged one level into the defaults.
   * Top-level scalar keys (e.g. `platform`, `on`, `off`) replace outright;
   * object namespaces (e.g. `tabs`, `auth`) merge key-by-key so a test can
   * swap in a single spy without restating the whole namespace.
   */
  overrides?: Record<string, unknown>;
  /** Reuse an existing IPC handler map (persists across tests when needed). */
  ipcHandlers?: IpcHandlerMap;
}

export interface ZioMockResult {
  /** The mock to assign to `window.zio`. */
  zio: Record<string, unknown>;
  /** Channel → handlers registered through the mock's `on`/`off`. */
  ipcHandlers: IpcHandlerMap;
  /** Fire all handlers registered on `channel`. */
  emit: (channel: string, ...args: unknown[]) => void;
}

function isPlainObject(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v) && typeof v !== 'function';
}

/** Builds the complete default window.zio surface used by renderer components. */
function buildDefaults(ipcHandlers: IpcHandlerMap): Record<string, unknown> {
  return {
    platform: 'linux',
    on: (channel: string, fn: IpcHandler) => {
      if (!ipcHandlers.has(channel)) ipcHandlers.set(channel, new Set());
      ipcHandlers.get(channel)!.add(fn);
    },
    off: (channel: string, fn: IpcHandler) => {
      ipcHandlers.get(channel)?.delete(fn);
    },
    theme: {
      getSystem: resolved('light'),
      getWebsite: resolved('system'),
      setWebsite: resolved('system'),
    },
    tabs: {
      getOrder: resolved([]),
      getActive: resolved(null),
      getState: resolved(null),
      recentlyClosed: resolved([]),
      navigate: resolved(undefined),
      navigatePane: resolved(undefined),
      create: resolved(null),
      close: resolved(undefined),
      activate: resolved(undefined),
      back: resolved(undefined),
      forward: resolved(undefined),
      reload: resolved(undefined),
      stop: resolved(undefined),
      zoom: resolved(undefined),
      find: resolved(undefined),
      findStop: resolved(undefined),
      pin: resolved(undefined),
      mute: resolved(undefined),
      muteAll: resolved(undefined),
      move: resolved(undefined),
      duplicate: resolved(null),
      closeOthers: resolved(undefined),
      closeToRight: resolved(undefined),
      setMode: resolved(undefined),
      setSplitRatio: resolved(undefined),
      reopenClosed: resolved(null),
      reopenFromRecent: resolved(null),
      restoreSession: resolved(undefined),
      hideAll: resolved(undefined),
      focusPane: resolved(undefined),
      navigateDashboard: resolved(true),
      swapPanes: resolved(undefined),
      extractContext: resolved(null),
      captureWebsitePane: resolved(null),
      autofillForm: resolved(undefined),
      injectPasswordDetector: resolved(undefined),
      popPendingCredential: resolved(null),
      captureThumbnails: resolved({}),
    },
    history: {
      search: resolved([]),
      record: resolved(undefined),
      recent: resolved([]),
      clear: resolved(undefined),
      delete: resolved(undefined),
    },
    browserImport: {
      detect: resolved([]),
      run: resolved({ ok: true }),
      fromHtmlFile: resolved({ ok: true }),
    },
    bookmarks: {
      search: resolved([]),
      isBookmarked: resolved(false),
      add: resolved(undefined),
      remove: resolved(undefined),
      all: resolved([]),
    },
    collections: {
      all: resolved([]),
      create: resolved(null),
      update: resolved(undefined),
      delete: resolved(undefined),
      getLinks: resolved([]),
      saveLink: resolved(undefined),
      updateAi: resolved(undefined),
    },
    downloads: {
      recent: resolved([]),
      search: resolved([]),
      open: resolved(undefined),
      openInTab: resolved(undefined),
      show: resolved(undefined),
      exists: resolved(false),
      choosePath: resolved(null),
      chooseDirectory: resolved(null),
      defaultDirectory: resolved(''),
      pause: resolved(undefined),
      resume: resolved(undefined),
      cancel: resolved(undefined),
      retry: resolved(undefined),
      remove: resolved(undefined),
      clear: resolved(undefined),
    },
    cookies: {
      getForSite: resolved([]),
      getAll: resolved([]),
      delete: resolved(undefined),
      clearForSite: resolved(undefined),
      clearAll: resolved(undefined),
    },
    passwords: {
      save: resolved(undefined),
      list: resolved([]),
      getForOrigin: resolved([]),
      reveal: resolved(null),
      delete: resolved(undefined),
      deleteAll: resolved(undefined),
    },
    vk: {
      insertText: resolved(true),
      sendKey: resolved(true),
      setReserve: resolved(true),
      recordWords: resolved(true),
      clearHistory: resolved(true),
      stripShow: resolved(true),
      stripUpdate: resolved(true),
      stripHide: resolved(true),
    },
    spellcheck: {
      getEnabled: resolved(false),
      setEnabled: resolved(true),
    },
    extensions: {
      list: resolved([]),
      add: resolved({ ok: false, error: 'mock' }),
      remove: resolved(true),
    },
    browsingData: {
      clear: resolved(undefined),
      counts: resolved({
        historyCount: 0,
        cookieCount: 0,
        cacheBytes: 0,
        downloadCount: 0,
        permissionCount: 0,
      }),
    },
    privacy: {
      trackerStats: resolved({ weekTotal: 0, todayTotal: 0, byDay: [], topTrackers: [] }),
      safetyCheck: resolved({
        passwords: { total: 0, weak: 0, reused: 0 },
        permissions: { allowed: 0 },
        trackerBlocking: false,
        doNotTrack: false,
      }),
      forgetSite: resolved({ ok: true, historyDeleted: 0 }),
    },
    notes: {
      countForHost: resolved(0),
      list: resolved([]),
      save: resolved(undefined),
      remove: resolved(undefined),
      flush: resolved(undefined),
    },
    readingList: {
      isSaved: resolved(false),
      unreadCount: resolved(0),
      add: resolved(undefined),
      all: resolved([]),
      markRead: resolved(undefined),
      remove: resolved(undefined),
    },
    sync: {
      pendingCount: resolved(0),
      pendingByProfile: resolved([]),
      state: resolved(null),
      queuePush: resolved(undefined),
      flush: resolved(undefined),
      planStatus: resolved({ gate: { blocked: false, feature: null, recommended_plan: null, blocked_at: null }, rejected: null }),
    },
    screenshot: {
      capture: resolved(null),
      saveToDisk: resolved(null),
      copyToClipboard: resolved(true),
    },
    clipboard: {
      write: resolved(undefined),
      read: resolved(''),
    },
    shell: {
      openExternal: resolved(undefined),
    },
    app: {
      version: resolved('0.0.0-test'),
    },
    tracker: {
      isEnabled: resolved(false),
      setEnabled: resolved(undefined),
      getCount: resolved(0),
      resetCount: resolved(undefined),
    },
    audio: {
      getMuteAll: resolved(false),
      setMuteAll: resolved(true),
      mutedDomains: resolved([]),
      setDomainMuted: resolved(true),
    },
    window: {
      getMode: resolved('normal'),
      setMode: resolved(undefined),
      getSplitRatio: resolved(0.5),
      setSplitRatio: resolved(undefined),
      reloadDashboard: resolved(undefined),
      setChromeOverlay: resolved(undefined),
      getZioPanelWidth: resolved(360),
      setZioPanelWidth: resolved(undefined),
      getZioPanelDocked: resolved(false),
      setZioPanelDocked: resolved(undefined),
      setZioPanelVisible: resolved(undefined),
      setSayzioRailReserve: resolved(true),
      isPrivate: resolved(false),
      openPrivate: resolved(undefined),
      openNew: resolved(true),
    },
    prefs: {
      get: resolved(null),
      set: resolved(undefined),
      all: resolved({}),
    },
    profiles: {
      list: resolved([]),
      getActive: resolved(null),
      switch: resolved(undefined),
      upsertFromWorkspace: resolved(undefined),
      warmSession: resolved(undefined),
    },
    sayzioLinks: {
      cached: resolved([]),
      refresh: resolved([]),
    },
    deviceLab: {
      listBiolinks: resolved([]),
    },
    permissions: {
      getAll: resolved([]),
      set: resolved(undefined),
      revoke: resolved(undefined),
      clearAll: resolved(undefined),
      respond: resolved(undefined),
    },
    siteSettings: {
      get: resolved(null),
      set: resolved(true),
    },
    sessions: {
      list: resolved([]),
      save: resolved(true),
      restore: resolved(true),
      remove: resolved(true),
    },
    auth: {
      getToken: resolved(null),
      getUser: resolved(null),
      storeToken: resolved(true),
      storeUser: resolved(true),
      clear: resolved(true),
    },
    adblock: {
      isEnabled: resolved(true),
      getState: resolved({
        active: true,
        reason: 'global',
        adminLocked: false,
        strength: 'balanced',
        globalEnabled: true,
        timedPauseUntil: null,
        pausedUntilRestart: false,
      }),
      getLists: resolved({ allow: [], block: [] }),
      pauseTimed: resolved(undefined),
      pausePage: resolved(undefined),
      resume: resolved(undefined),
      addListDomain: resolved(undefined),
      removeListDomain: resolved(undefined),
      setEnabled: resolved(undefined),
      getStrength: resolved('balanced'),
      setStrength: resolved(undefined),
      getAdminPolicy: resolved({ policy: { version: 0, allow: [], block: [] }, fetchedAt: null }),
      refreshAdminPolicy: resolved({ policy: { version: 0, allow: [], block: [] }, fetchedAt: null }),
      isHostAdminControlled: resolved(false),
    },
  };
}

export function buildZioMock(options: ZioMockOptions = {}): ZioMockResult {
  const ipcHandlers: IpcHandlerMap = options.ipcHandlers ?? new Map();
  const zio = buildDefaults(ipcHandlers);

  for (const [key, value] of Object.entries(options.overrides ?? {})) {
    const current = zio[key];
    if (isPlainObject(current) && isPlainObject(value)) {
      zio[key] = { ...current, ...value };
    } else {
      zio[key] = value;
    }
  }

  return {
    zio,
    ipcHandlers,
    emit: (channel: string, ...args: unknown[]) => {
      ipcHandlers.get(channel)?.forEach(fn => fn(...args));
    },
  };
}
