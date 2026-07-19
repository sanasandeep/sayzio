/**
 * Tab manager for Zio Browser.
 * Manages WebContentsView instances, tab state, and navigation.
 *
 * Each tab uses the session associated with the active browser profile so
 * workspace profiles are fully session-isolated.
 */
import { BrowserWindow, WebContentsView, Menu, clipboard, session, type WebContents } from 'electron';
import { parseOmniboxInput, type SearchEngineConfig, DEFAULT_SEARCH_ENGINE } from '../shared/omnibox';
import { sessionPartitionForProfile, DEFAULT_PROFILE_ID } from '../shared/profile-store';

export interface TabState {
  id: string;
  url: string;
  displayUrl: string;
  title: string;
  favicon: string | null;
  isLoading: boolean;
  canGoBack: boolean;
  canGoForward: boolean;
  isAudible: boolean;
  isMuted: boolean;
  zoomFactor: number;
  pinned: boolean;
}

export interface RecentlyClosedEntry {
  url: string;
  title: string;
  favicon: string | null;
}

export interface SessionSnapshot {
  /** URLs of the non-pinned tabs, in tab-strip order. */
  urls: string[];
  /** Index into `urls` of the active tab, or -1 if the active tab isn't in the list. */
  activeIndex: number;
  /**
   * When the active tab was a pinned tab: its index within the pinned
   * section (matching the persisted pinned-URL order). -1 otherwise.
   */
  activePinnedIndex: number;
}

export interface FindResult {
  tabId: string;
  activeMatchOrdinal: number;
  matches: number;
  finalUpdate: boolean;
}

type TabId = string;

interface ManagedTab {
  id: TabId;
  view: WebContentsView;
  pinned: boolean;
  favicon: string | null;
  /**
   * The user's explicit mute choice for THIS tab in this session
   * (null = no explicit choice; auto-mute policy may apply on navigation).
   */
  muteOverride: boolean | null;
}

const MAX_RECENTLY_CLOSED = 10;

/**
 * Safely check if a WebContents object is still alive.
 */
function isAlive(wc: WebContents): boolean {
  try {
    return !wc.isDestroyed();
  } catch {
    return false;
  }
}

export interface TabManagerOptions {
  /** When set, all tabs use this session instead of the default session. */
  privateSession?: Electron.Session;
}

export class TabManager {
  private tabs: Map<TabId, ManagedTab> = new Map();
  private activeTabId: TabId | null = null;
  private tabOrder: TabId[] = [];
  private pinnedTabs = new Set<TabId>();
  private recentlyClosed: RecentlyClosedEntry[] = [];
  private win: BrowserWindow;
  private searchEngine: SearchEngineConfig = DEFAULT_SEARCH_ENGINE;
  /** Active session partition — changes when the user switches profiles. */
  private activePartition: string = sessionPartitionForProfile(DEFAULT_PROFILE_ID);
  private onTabStateChange?: (tabId: TabId, state: Partial<TabState>) => void;
  private onTabCreated?: (tabId: TabId) => void;
  private onTabClosed?: (tabId: TabId) => void;
  private onActiveTabChange?: (tabId: TabId) => void;
  private onNavigate?: (tabId: TabId, url: string, title: string) => void;
  private onAddToBiolink?: (url: string, title: string) => void;
  private onDeviceLabPreview?: (url: string) => void;
  private onFindResult?: (result: FindResult) => void;
  private readonly tabSession: Electron.Session;
  readonly isPrivate: boolean;
  private onTabOrderChange?: (order: TabId[]) => void;
  private onPinnedUrlsChange?: (urls: string[]) => void;
  private onRecentlyClosedChange?: (entries: RecentlyClosedEntry[]) => void;
  /** Returns true when a URL should be auto-muted (domain memory or global policy). */
  private resolveAutoMute?: (url: string) => boolean;
  /** Fired when the USER explicitly mutes/unmutes a tab (for domain persistence). */
  private onUserMuteChange?: (url: string, muted: boolean) => void;

  constructor(win: BrowserWindow, options: TabManagerOptions = {}) {
    this.win = win;
    this.tabSession = options.privateSession ?? session.defaultSession;
    this.isPrivate = options.privateSession !== undefined;
  }

  setCallbacks(cbs: {
    onTabStateChange?: (tabId: TabId, state: Partial<TabState>) => void;
    onTabCreated?: (tabId: TabId) => void;
    onTabClosed?: (tabId: TabId) => void;
    onActiveTabChange?: (tabId: TabId) => void;
    onNavigate?: (tabId: TabId, url: string, title: string) => void;
    onAddToBiolink?: (url: string, title: string) => void;
    onDeviceLabPreview?: (url: string) => void;
    onFindResult?: (result: FindResult) => void;
    onTabOrderChange?: (order: TabId[]) => void;
    onPinnedUrlsChange?: (urls: string[]) => void;
    onRecentlyClosedChange?: (entries: RecentlyClosedEntry[]) => void;
    resolveAutoMute?: (url: string) => boolean;
    onUserMuteChange?: (url: string, muted: boolean) => void;
  }): void {
    this.onTabStateChange = cbs.onTabStateChange;
    this.onTabCreated = cbs.onTabCreated;
    this.onTabClosed = cbs.onTabClosed;
    this.onActiveTabChange = cbs.onActiveTabChange;
    this.onNavigate = cbs.onNavigate;
    this.onAddToBiolink = cbs.onAddToBiolink;
    this.onDeviceLabPreview = cbs.onDeviceLabPreview;
    this.onFindResult = cbs.onFindResult;
    this.onTabOrderChange = cbs.onTabOrderChange;
    this.onPinnedUrlsChange = cbs.onPinnedUrlsChange;
    this.onRecentlyClosedChange = cbs.onRecentlyClosedChange;
    this.resolveAutoMute = cbs.resolveAutoMute;
    this.onUserMuteChange = cbs.onUserMuteChange;
  }

  setSearchEngine(engine: SearchEngineConfig): void {
    this.searchEngine = engine;
  }

  /**
   * Switch all new tabs to use the session partition for the given profile.
   * Existing tabs retain their current session (per Chromium design —
   * you cannot change a WebContents' session after creation).
   */
  setActiveProfilePartition(profileId: string): void {
    this.activePartition = sessionPartitionForProfile(profileId);
  }

  getActivePartition(): string {
    return this.activePartition;
  }

  /**
   * Returns the index just after the last pinned tab (the insertion point for new non-pinned tabs).
   */
  private pinnedCount(): number {
    return this.tabOrder.filter(id => this.pinnedTabs.has(id)).length;
  }

  /**
   * Insert a tab ID into tabOrder respecting pinned ordering.
   * Pinned tabs go at the front; non-pinned go after pinned section.
   */
  private insertInOrder(id: TabId, pinned: boolean): void {
    if (pinned) {
      const firstNonPinned = this.tabOrder.findIndex(tid => !this.pinnedTabs.has(tid));
      if (firstNonPinned === -1) {
        this.tabOrder.push(id);
      } else {
        this.tabOrder.splice(firstNonPinned, 0, id);
      }
    } else {
      this.tabOrder.push(id);
    }
  }

  createTab(url?: string, background = false, pinned = false): TabId {
    const id = crypto.randomUUID();

    const tabSession = session.fromPartition(this.activePartition);

    const view = new WebContentsView({
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
        webSecurity: true,
        allowRunningInsecureContent: false,
        session: this.isPrivate ? this.tabSession : tabSession,
      },
    });

    const wc = view.webContents;

    const [w, h] = this.win.getContentSize();
    view.setBounds({ x: 0, y: 72, width: w, height: h - 72 });

    // Wire up events
    wc.on('did-navigate', (_, navUrl) => {
      if (isAlive(wc)) {
        wc.stopFindInPage('clearSelection');
      }
      this.onFindResult?.({ tabId: id, activeMatchOrdinal: 0, matches: 0, finalUpdate: true });
      this.onTabStateChange?.(id, {
        url: navUrl,
        canGoBack: wc.canGoBack(),
        canGoForward: wc.canGoForward(),
        isLoading: false,
      });
      // Auto-mute from domain memory / global policy — unless the user made an
      // explicit choice for this tab in this session.
      const tab = this.tabs.get(id);
      if (
        tab && tab.muteOverride === null &&
        isAlive(wc) && !wc.isAudioMuted() &&
        this.resolveAutoMute?.(navUrl)
      ) {
        wc.setAudioMuted(true);
        this.onTabStateChange?.(id, { isMuted: true });
      }
    });

    wc.on('did-navigate-in-page', (_, navUrl) => {
      this.onTabStateChange?.(id, { url: navUrl });
    });

    wc.on('page-title-updated', (_, title) => {
      this.onTabStateChange?.(id, { title });
      if (isAlive(wc)) {
        this.onNavigate?.(id, wc.getURL(), title);
      }
    });

    wc.on('page-favicon-updated', (_, favicons) => {
      const favicon = favicons[0] ?? null;
      const tab = this.tabs.get(id);
      if (tab) tab.favicon = favicon;
      this.onTabStateChange?.(id, { favicon });
    });

    wc.on('did-start-loading', () => {
      this.onTabStateChange?.(id, { isLoading: true });
    });

    wc.on('did-stop-loading', () => {
      this.onTabStateChange?.(id, {
        isLoading: false,
        canGoBack: wc.canGoBack(),
        canGoForward: wc.canGoForward(),
      });
    });

    wc.on('zoom-changed', (_, direction) => {
      const zf = wc.getZoomFactor() + (direction === 'in' ? 0.1 : -0.1);
      wc.setZoomFactor(Math.max(0.25, Math.min(5.0, zf)));
      this.onTabStateChange?.(id, { zoomFactor: wc.getZoomFactor() });
    });

    wc.on('audio-state-changed', ({ audible }) => {
      this.onTabStateChange?.(id, { isAudible: audible });
    });

    // ── Context menu ─────────────────────────────────────────────────────────
    wc.on('context-menu', (event, params) => {
      const pageUrl = params.pageURL || wc.getURL();
      const pageTitle = wc.getTitle();
      const targetUrl = params.linkURL || params.srcURL || pageUrl;

      const menuItems: Electron.MenuItemConstructorOptions[] = [];

      if (params.isEditable) {
        menuItems.push({ role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { type: 'separator' });
      } else if (params.selectionText) {
        menuItems.push({ role: 'copy' }, { type: 'separator' });
      }

      if (params.linkURL) {
        menuItems.push(
          { label: 'Open link in new tab', click: () => { this.createTab(params.linkURL); } },
          {
            label: 'Open in Private Window',
            click: () => {
              // Lazy require to avoid a circular dependency with ./index.
              // eslint-disable-next-line @typescript-eslint/no-var-requires
              const { createPrivateWindow } = require('./index') as typeof import('./index');
              createPrivateWindow(params.linkURL);
            },
          },
          { label: 'Copy link address', click: () => { clipboard.writeText(params.linkURL); } },
          { type: 'separator' },
        );
      }

      // ── Sayzio link tools (available in both normal and private windows) ──
      menuItems.push(
        {
          label: 'Add to my biolink…',
          click: () => { this.onAddToBiolink?.(targetUrl, pageTitle); },
        },
        {
          label: 'Preview in Device Lab',
          click: () => { this.onDeviceLabPreview?.(params.linkURL || pageUrl); },
        },
      );

      if (menuItems.length > 0) {
        const menu = Menu.buildFromTemplate(menuItems);
        menu.popup({ window: this.win });
      }
    });

    // ── Find in page results ──────────────────────────────────────────────────
    wc.on('found-in-page', (_, result) => {
      this.onFindResult?.({
        tabId: id,
        activeMatchOrdinal: result.activeMatchOrdinal,
        matches: result.matches,
        finalUpdate: result.finalUpdate,
      });
    });

    wc.setWindowOpenHandler(({ url: openUrl }) => {
      this.createTab(openUrl);
      return { action: 'deny' };
    });

    wc.on('will-navigate', (event, navUrl) => {
      const allowed = ['http:', 'https:', 'file:', 'about:'];
      try {
        const proto = new URL(navUrl).protocol;
        if (!allowed.includes(proto)) {
          event.preventDefault();
        }
      } catch {
        event.preventDefault();
      }
    });

    if (pinned) this.pinnedTabs.add(id);
    const tab: ManagedTab = { id, view, pinned, favicon: null, muteOverride: null };
    this.tabs.set(id, tab);
    this.insertInOrder(id, pinned);

    if (!background || !this.activeTabId) {
      this.win.contentView.addChildView(view);
      if (this.activeTabId) {
        const prev = this.tabs.get(this.activeTabId);
        if (prev) this.win.contentView.removeChildView(prev.view);
      }
      this.activeTabId = id;
      this.onActiveTabChange?.(id);
    }

    this.onTabCreated?.(id);

    const targetUrl = url ?? 'about:newtab';
    // Apply the auto-mute policy up front so new/restored tabs never emit a
    // blip of audio before did-navigate fires.
    if (targetUrl !== 'about:newtab' && this.resolveAutoMute?.(targetUrl)) {
      wc.setAudioMuted(true);
      this.onTabStateChange?.(id, { isMuted: true });
    }
    if (targetUrl !== 'about:newtab') {
      void wc.loadURL(targetUrl);
    }

    return id;
  }

  closeTab(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab) return;

    // Save to recently-closed stack (skip empty/new-tab pages)
    const wc = tab.view.webContents;
    const url = isAlive(wc) ? wc.getURL() : '';
    if (url && url !== 'about:newtab' && url !== 'about:blank' && url !== '') {
      const entry: RecentlyClosedEntry = {
        url,
        title: (isAlive(wc) ? wc.getTitle() : '') || url,
        favicon: tab.favicon,
      };
      this.recentlyClosed.unshift(entry);
      if (this.recentlyClosed.length > MAX_RECENTLY_CLOSED) {
        this.recentlyClosed.pop();
      }
      this.onRecentlyClosedChange?.(this.recentlyClosed);
    }

    const wasActive = this.activeTabId === id;
    const idx = this.tabOrder.indexOf(id);

    if (!tab.view.webContents.isDestroyed()) {
      tab.view.webContents.close();
    }

    try {
      this.win.contentView.removeChildView(tab.view);
    } catch {
      // May already be removed
    }

    this.pinnedTabs.delete(id);
    this.tabs.delete(id);
    this.tabOrder.splice(idx, 1);
    this.onTabClosed?.(id);

    if (wasActive && this.tabOrder.length > 0) {
      const newActive = this.tabOrder[Math.min(idx, this.tabOrder.length - 1)];
      if (newActive) this.activateTab(newActive);
    } else if (this.tabOrder.length === 0) {
      this.activeTabId = null;
    }
  }

  /**
   * Pin or unpin a tab. Pinned tabs move to the front of the tab strip.
   */
  pinTab(id: TabId, pinned: boolean): void {
    const tab = this.tabs.get(id);
    if (!tab || tab.pinned === pinned) return;

    const idx = this.tabOrder.indexOf(id);
    this.tabOrder.splice(idx, 1);

    tab.pinned = pinned;
    if (pinned) {
      this.pinnedTabs.add(id);
    } else {
      this.pinnedTabs.delete(id);
    }
    this.insertInOrder(id, pinned);

    this.onTabStateChange?.(id, { pinned });
    this.onTabOrderChange?.(this.getTabOrder());
    this.onPinnedUrlsChange?.(this.getPinnedUrls());
  }

  /**
   * Move a tab to a new index within the tab strip, keeping pinned tabs in
   * the pinned section and normal tabs after it. `toIndex` is the desired
   * index in tabOrder; it is clamped to the tab's section boundaries.
   */
  moveTab(id: TabId, toIndex: number): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    const fromIndex = this.tabOrder.indexOf(id);
    if (fromIndex === -1) return;

    const pinnedCount = this.pinnedCount();
    const min = tab.pinned ? 0 : pinnedCount;
    const max = tab.pinned ? pinnedCount - 1 : this.tabOrder.length - 1;
    const clamped = Math.max(min, Math.min(max, Math.trunc(toIndex)));
    if (clamped === fromIndex) return;

    this.tabOrder.splice(fromIndex, 1);
    this.tabOrder.splice(clamped, 0, id);

    this.onTabOrderChange?.(this.getTabOrder());
    if (tab.pinned) {
      this.onPinnedUrlsChange?.(this.getPinnedUrls());
    }
  }

  /**
   * Duplicate a tab by opening a new tab with the same URL.
   */
  duplicateTab(id: TabId): TabId | null {
    const wc = this.tabs.get(id)?.view.webContents;
    if (!wc) return null;
    const url = wc.getURL();
    if (!url || url === 'about:newtab' || url === 'about:blank') return null;
    return this.createTab(url);
  }

  /**
   * Close all tabs except the given one. Pinned tabs are never closed by this action.
   */
  closeOtherTabs(id: TabId): void {
    const toClose = this.tabOrder.filter(tid => tid !== id && !this.pinnedTabs.has(tid));
    for (const tid of toClose) {
      this.closeTab(tid);
    }
  }

  /**
   * Close all tabs to the right of the given tab. Pinned tabs are never closed.
   */
  closeTabsToRight(id: TabId): void {
    const idx = this.tabOrder.indexOf(id);
    if (idx === -1) return;
    const toClose = this.tabOrder.slice(idx + 1).filter(tid => !this.pinnedTabs.has(tid));
    for (const tid of toClose) {
      this.closeTab(tid);
    }
  }

  /**
   * Mute (or unmute) all open tabs. Does NOT write per-domain mute memory —
   * this is the session-level global action.
   */
  muteAllTabs(muted = true): void {
    for (const [tid] of this.tabs) {
      this.muteTab(tid, muted, false);
    }
  }

  /**
   * Reopen the most recently closed tab.
   */
  reopenClosedTab(): TabId | null {
    const entry = this.recentlyClosed.shift();
    if (!entry) return null;
    this.onRecentlyClosedChange?.(this.recentlyClosed);
    return this.createTab(entry.url);
  }

  getRecentlyClosed(): RecentlyClosedEntry[] {
    return [...this.recentlyClosed];
  }

  /**
   * Return the URLs of all currently pinned tabs (for persistence).
   */
  getPinnedUrls(): string[] {
    const urls: string[] = [];
    for (const id of this.tabOrder) {
      if (!this.pinnedTabs.has(id)) continue;
      const wc = this.tabs.get(id)?.view.webContents;
      if (!wc) continue;
      const url = isAlive(wc) ? wc.getURL() : '';
      if (url && url !== 'about:newtab' && url !== 'about:blank') {
        urls.push(url);
      }
    }
    return urls;
  }

  /**
   * Restore pinned tabs from persisted URLs. Call before opening the default new tab.
   */
  initPinnedUrls(urls: string[]): TabId[] {
    const ids: TabId[] = [];
    for (const url of urls) {
      if (url) ids.push(this.createTab(url, true, true));
    }
    return ids;
  }

  /**
   * Snapshot the non-pinned tabs (URLs in order + active tab index) for
   * session persistence. Pinned tabs are persisted separately.
   */
  getSessionSnapshot(): SessionSnapshot {
    const urls: string[] = [];
    let activeIndex = -1;
    let activePinnedIndex = -1;
    let pinnedIdx = 0;
    for (const id of this.tabOrder) {
      const wc = this.tabs.get(id)?.view.webContents;
      if (!wc || !isAlive(wc)) continue;
      const url = wc.getURL();
      if (this.pinnedTabs.has(id)) {
        // Mirror getPinnedUrls(): only persistable pinned tabs count toward
        // the pinned index so it lines up with the restored pinned order.
        if (url && url !== 'about:newtab' && url !== 'about:blank') {
          if (id === this.activeTabId) activePinnedIndex = pinnedIdx;
          pinnedIdx++;
        }
        continue;
      }
      if (!url || url === 'about:newtab' || url === 'about:blank') continue;
      if (id === this.activeTabId) activeIndex = urls.length;
      urls.push(url);
    }
    return { urls, activeIndex, activePinnedIndex };
  }

  /**
   * Restore a previous session's non-pinned tabs (in order) and activate the
   * saved active tab. Call after pinned tabs have been restored.
   */
  restoreSessionTabs(urls: string[], activeIndex = -1): void {
    const ids: TabId[] = [];
    for (const url of urls) {
      if (url) ids.push(this.createTab(url, true));
    }
    if (ids.length === 0) return;
    const target = ids[activeIndex] ?? ids[ids.length - 1];
    if (target) this.activateTab(target);
  }

  activateTab(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab) return;

    if (this.activeTabId && this.activeTabId !== id) {
      const prev = this.tabs.get(this.activeTabId);
      if (prev) {
        try { this.win.contentView.removeChildView(prev.view); } catch { }
      }
    }

    this.win.contentView.addChildView(tab.view);
    this.activeTabId = id;
    tab.view.webContents.focus();
    this.onActiveTabChange?.(id);
  }

  navigate(id: TabId, input: string): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    const result = parseOmniboxInput(input, this.searchEngine);
    void tab.view.webContents.loadURL(result.navigateUrl);
  }

  goBack(id: TabId): void {
    this.tabs.get(id)?.view.webContents.goBack();
  }

  goForward(id: TabId): void {
    this.tabs.get(id)?.view.webContents.goForward();
  }

  reload(id: TabId, ignoreCache = false): void {
    const wc = this.tabs.get(id)?.view.webContents;
    if (!wc) return;
    if (ignoreCache) wc.reloadIgnoringCache();
    else wc.reload();
  }

  stop(id: TabId): void {
    this.tabs.get(id)?.view.webContents.stop();
  }

  setZoom(id: TabId, factor: number): void {
    const wc = this.tabs.get(id)?.view.webContents;
    if (wc) {
      wc.setZoomFactor(Math.max(0.25, Math.min(5.0, factor)));
    }
  }

  findInPage(id: TabId, text: string, forward = true, matchCase = false): void {
    const wc = this.tabs.get(id)?.view.webContents;
    if (!wc || !text) return;
    wc.findInPage(text, { forward, matchCase });
  }

  stopFindInPage(id: TabId): void {
    const wc = this.tabs.get(id)?.view.webContents;
    if (wc && isAlive(wc)) {
      wc.stopFindInPage('clearSelection');
    }
    this.onFindResult?.({ tabId: id, activeMatchOrdinal: 0, matches: 0, finalUpdate: true });
  }

  /**
   * Mute or unmute a single tab.
   * When `rememberDomain` is true (a direct user action) the choice is
   * recorded as this tab's session override and reported via
   * onUserMuteChange so the host's mute preference can be persisted.
   */
  muteTab(id: TabId, muted: boolean, rememberDomain = true): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    const wc = tab.view.webContents;
    tab.muteOverride = muted;
    if (isAlive(wc)) wc.setAudioMuted(muted);
    this.onTabStateChange?.(id, { isMuted: muted });
    if (rememberDomain && isAlive(wc)) {
      const url = wc.getURL();
      if (url) this.onUserMuteChange?.(url, muted);
    }
  }

  getTabState(id: TabId): TabState | null {
    const tab = this.tabs.get(id);
    if (!tab) return null;
    const wc = tab.view.webContents;
    const url = wc.getURL();
    return {
      id,
      url,
      displayUrl: url,
      title: wc.getTitle(),
      favicon: tab.favicon,
      isLoading: wc.isLoading(),
      canGoBack: wc.canGoBack(),
      canGoForward: wc.canGoForward(),
      isAudible: wc.isCurrentlyAudible(),
      isMuted: wc.isAudioMuted(),
      zoomFactor: wc.getZoomFactor(),
      pinned: tab.pinned,
    };
  }

  getActiveTabId(): TabId | null {
    return this.activeTabId;
  }

  getTabOrder(): TabId[] {
    return [...this.tabOrder];
  }

  getWebContents(id: TabId): WebContents | null {
    return this.tabs.get(id)?.view.webContents ?? null;
  }

  resizeTabs(bounds: { x: number; y: number; width: number; height: number }): void {
    for (const [id, tab] of this.tabs) {
      tab.view.setBounds(bounds);
      if (id !== this.activeTabId) {
        try { this.win.contentView.removeChildView(tab.view); } catch { }
      }
    }
    if (this.activeTabId) {
      const active = this.tabs.get(this.activeTabId);
      if (active) {
        try { this.win.contentView.addChildView(active.view); } catch { }
      }
    }
  }

  /**
   * Move all tab views off-screen (used in dashboard mode where no tabs are visible).
   */
  hideAllTabs(): void {
    for (const [, tab] of this.tabs) {
      try { this.win.contentView.removeChildView(tab.view); } catch { }
    }
  }

  /**
   * Capture the active page as a PNG buffer.
   * - `fullPage = false` (default): captures only the visible viewport.
   * - `fullPage = true`: resizes the view to the page's full scroll height,
   *   captures, then restores the original bounds.
   *
   * Returns null if the tab doesn't exist or capture fails.
   */
  async captureTab(id: TabId, fullPage = false): Promise<Buffer | null> {
    const tab = this.tabs.get(id);
    if (!tab) return null;
    const wc = tab.view.webContents;
    if (!isAlive(wc)) return null;

    const MAX_DIMENSION = 16384;

    try {
      if (!fullPage) {
        const image = await wc.capturePage();
        return image.toPNG();
      }

      // Full-page: get full scroll dimensions via JS, temporarily resize the
      // view so the renderer lays out the full document, then capture.
      const dims = await wc.executeJavaScript(
        '({ w: document.documentElement.scrollWidth, h: document.documentElement.scrollHeight })',
      ) as { w: number; h: number };

      const captureW = Math.min(Math.max(dims.w, 1), MAX_DIMENSION);
      const captureH = Math.min(Math.max(dims.h, 1), MAX_DIMENSION);
      const origBounds = tab.view.getBounds();

      tab.view.setBounds({ x: origBounds.x, y: origBounds.y, width: captureW, height: captureH });
      await new Promise<void>(resolve => setTimeout(resolve, 180));
      const image = await wc.capturePage();
      tab.view.setBounds(origBounds);

      return image.toPNG();
    } catch {
      return null;
    }
  }

  destroyAll(): void {
    for (const [id] of this.tabs) {
      this.closeTab(id);
    }
  }

  /** Return the tab ID that owns the given WebContents ID, or null. */
  getTabIdByWebContentsId(wcId: number): string | null {
    for (const [id, tab] of this.tabs) {
      if (!tab.view.webContents.isDestroyed() && tab.view.webContents.id === wcId) {
        return id;
      }
    }
    return null;
  }
}
