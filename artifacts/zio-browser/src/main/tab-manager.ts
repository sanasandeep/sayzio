/**
 * Tab manager for Zio Browser.
 * Manages WebContentsView instances, tab state, and navigation.
 */
import { BrowserWindow, WebContentsView, session, type WebContents } from 'electron';
import { parseOmniboxInput, type SearchEngineConfig, DEFAULT_SEARCH_ENGINE } from '../shared/omnibox';

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
}

type TabId = string;

interface ManagedTab {
  id: TabId;
  view: WebContentsView;
}

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

export class TabManager {
  private tabs: Map<TabId, ManagedTab> = new Map();
  private activeTabId: TabId | null = null;
  private tabOrder: TabId[] = [];
  private win: BrowserWindow;
  private searchEngine: SearchEngineConfig = DEFAULT_SEARCH_ENGINE;
  private onTabStateChange?: (tabId: TabId, state: Partial<TabState>) => void;
  private onTabCreated?: (tabId: TabId) => void;
  private onTabClosed?: (tabId: TabId) => void;
  private onActiveTabChange?: (tabId: TabId) => void;
  private onNavigate?: (tabId: TabId, url: string, title: string) => void;

  constructor(win: BrowserWindow) {
    this.win = win;
  }

  setCallbacks(cbs: {
    onTabStateChange?: (tabId: TabId, state: Partial<TabState>) => void;
    onTabCreated?: (tabId: TabId) => void;
    onTabClosed?: (tabId: TabId) => void;
    onActiveTabChange?: (tabId: TabId) => void;
    onNavigate?: (tabId: TabId, url: string, title: string) => void;
  }): void {
    this.onTabStateChange = cbs.onTabStateChange;
    this.onTabCreated = cbs.onTabCreated;
    this.onTabClosed = cbs.onTabClosed;
    this.onActiveTabChange = cbs.onActiveTabChange;
    this.onNavigate = cbs.onNavigate;
  }

  setSearchEngine(engine: SearchEngineConfig): void {
    this.searchEngine = engine;
  }

  createTab(url?: string, background = false): TabId {
    const id = crypto.randomUUID();

    const view = new WebContentsView({
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
        webSecurity: true,
        allowRunningInsecureContent: false,
        session: session.defaultSession,
      },
    });

    const wc = view.webContents;

    // Content size will be managed by the main window's resize handler
    const [w, h] = this.win.getContentSize();
    view.setBounds({ x: 0, y: 72, width: w, height: h - 72 });

    // Wire up events
    wc.on('did-navigate', (_, navUrl) => {
      this.onTabStateChange?.(id, {
        url: navUrl,
        canGoBack: wc.canGoBack(),
        canGoForward: wc.canGoForward(),
        isLoading: false,
      });
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
      this.onTabStateChange?.(id, { favicon: favicons[0] ?? null });
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

    // Handle new-window requests (target="_blank" etc.)
    wc.setWindowOpenHandler(({ url: openUrl }) => {
      this.createTab(openUrl);
      return { action: 'deny' };
    });

    // Prevent navigation to dangerous protocols
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

    const tab: ManagedTab = { id, view };
    this.tabs.set(id, tab);
    this.tabOrder.push(id);

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
    if (targetUrl !== 'about:newtab') {
      void wc.loadURL(targetUrl);
    }

    return id;
  }

  closeTab(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab) return;

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

  findInPage(id: TabId, text: string, forward = true): void {
    this.tabs.get(id)?.view.webContents.findInPage(text, { forward });
  }

  stopFindInPage(id: TabId): void {
    this.tabs.get(id)?.view.webContents.stopFindInPage('clearSelection');
  }

  muteTab(id: TabId, muted: boolean): void {
    this.tabs.get(id)?.view.webContents.setAudioMuted(muted);
    this.onTabStateChange?.(id, { isMuted: muted });
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
      favicon: null,
      isLoading: wc.isLoading(),
      canGoBack: wc.canGoBack(),
      canGoForward: wc.canGoForward(),
      isAudible: wc.isCurrentlyAudible(),
      isMuted: wc.isAudioMuted(),
      zoomFactor: wc.getZoomFactor(),
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
    for (const [, tab] of this.tabs) {
      tab.view.setBounds(bounds);
    }
  }

  destroyAll(): void {
    for (const [id] of this.tabs) {
      this.closeTab(id);
    }
  }
}
