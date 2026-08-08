/**
 * Tab manager for Zio Browser.
 * Manages WebContentsView instances, tab state, and navigation.
 *
 * Each tab uses the session associated with the active browser profile so
 * workspace profiles are fully session-isolated.
 */
import { BrowserWindow, WebContentsView, Menu, clipboard, dialog, session, type WebContents } from 'electron';
import { parseOmniboxInput, type SearchEngineConfig, DEFAULT_SEARCH_ENGINE } from '../shared/omnibox';
import { sessionPartitionForProfile, DEFAULT_PROFILE_ID } from '../shared/profile-store';
import { isInternalPageUrl, internalPageTitle } from '../shared/internal-pages';
import { isCapturableUrl, SCREENSHOT_MAX_WIDTH, SCREENSHOT_MAX_BYTES } from '../shared/context-extractor';
import {
  buildVkFocusReporterScript,
  parseVkFocusMessage,
  type VkFocusPayload,
} from '../shared/virtual-keyboard';
import {
  type TabMode,
  type TabPane,
  parseTabMode,
  normalizeTabMode,
  tabModeIncludes,
  dashboardModeFor,
  SAYZIO_DASHBOARD_URL,
  SAYZIO_HOME_URL,
  SAYZIO_BASE_HOST,
  TAB_SPLIT_RATIO,
  MIN_TAB_SPLIT_RATIO,
  MAX_TAB_SPLIT_RATIO,
  TAB_SPLIT_DIVIDER_WIDTH,
  TAB_SPLIT_FOCUS_FRAME,
} from '../shared/window-mode';

/** Background color applied to all native views to avoid white/blank flashes. */
const VIEW_BG_COLOR = '#101014';

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
  mode: TabMode;
  /** Left-pane share of the tab area when the mode is a two-native-pane split. */
  splitRatio: number;
  /** Current URL of the primary (left) pane — always published, regardless of pane focus. */
  primaryUrl?: string;
  /** Current URL of the second (right) pane in a Website+Website split. */
  secondUrl?: string;
  /** Which pane the shared toolbar controls in a Website+Website split. */
  focusedPane?: 'primary' | 'second';
}

export interface RecentlyClosedEntry {
  url: string;
  title: string;
  favicon: string | null;
}

/**
 * Per-tab layout info persisted alongside the URL list (parallel array).
 * `null` for a plain single-pane Website tab.
 */
export interface SessionTabLayout {
  /** The tab's view mode (any non-'browser' mode is persisted). */
  mode: string;
  /** Left-pane share of the tab area (0.2–0.8). */
  splitRatio?: number;
  /** URL of the second (right) pane in a Website+Website split. */
  secondUrl?: string;
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
  /**
   * Per-tab layout entries parallel to `urls` (same index). Optional so
   * snapshots written by older versions still restore.
   */
  layouts?: (SessionTabLayout | null)[];
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
  /** Per-tab view mode (single pane or a two-pane split). */
  mode: TabMode;
  /**
   * Canonical URL of a renderer-drawn internal page (about:sayzio / about:zio)
   * currently shown by this tab, or null. The native webContents never loads
   * these, so state reads must prefer this over wc.getURL().
   */
  internalUrl: string | null;
  /** Lazily created Sayzio dashboard view (pane: 'dashboard'). */
  dashboardView: WebContentsView | null;
  /** Lazily created second independent browser view ('browser+browser' mode). */
  secondView: WebContentsView | null;
  /** Left-pane share of the tab area in a two-native-pane split (0.2–0.8). */
  splitRatio: number;
  /**
   * Which browser pane the toolbar (address bar / nav buttons) controls when
   * the mode is 'browser+browser'. Follows the last-focused pane.
   */
  focusedPane: 'primary' | 'second';
  /**
   * True while the tab shows the renderer-drawn New Tab page (about:newtab).
   * The native WebContentsView must stay DETACHED in that state — an attached
   * (blank) view sits above the DOM and silently swallows every click on the
   * quick links / recent buttons. Cleared on first real navigation.
   */
  isNewTabPage: boolean;
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
  /** True while destroyAll() runs — disables the last-tab replacement guard. */
  private destroyingAll = false;
  private activeTabId: TabId | null = null;
  private tabOrder: TabId[] = [];
  private pinnedTabs = new Set<TabId>();
  private recentlyClosed: RecentlyClosedEntry[] = [];
  private win: BrowserWindow;
  private searchEngine: SearchEngineConfig = DEFAULT_SEARCH_ENGINE;
  /** Active session partition — changes when the user switches profiles. */
  private activePartition: string = sessionPartitionForProfile(DEFAULT_PROFILE_ID);
  /**
   * insertCSS keys for the "hide embedded site assistant" rule, per Sayzio
   * view. Present ⇒ the hide CSS is currently inserted in that view.
   */
  private assistantHideCssKeys = new WeakMap<WebContentsView, string>();
  private onTabStateChange?: (tabId: TabId, state: Partial<TabState>) => void;
  private onTabCreated?: (tabId: TabId) => void;
  private onTabClosed?: (tabId: TabId) => void;
  private onActiveTabChange?: (tabId: TabId) => void;
  private onNavigate?: (tabId: TabId, url: string, title: string) => void;
  private onAddToBiolink?: (url: string, title: string) => void;
  private onShortenPage?: (url: string, title: string) => void;
  private onCreateQr?: (url: string, title: string) => void;
  private onAutofillPage?: (tabId: TabId) => void;
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
  /**
   * Returns the pixel width to reserve on the right of the tab area for the
   * renderer-drawn Ask Zio panel when the active tab is in zio-split mode.
   */
  private resolveZioPanelReserve?: () => number;
  /**
   * Returns the pixel width to reserve on the LEFT of the tab area for the
   * renderer-drawn Sayzio sidebar rail (0 when hidden/private).
   */
  private resolveSayzioRailReserve?: () => number;
  /** Last content-area bounds applied via resizeTabs (browser/split layouts). */
  private contentBounds: { x: number; y: number; width: number; height: number } | null = null;
  /** True while a renderer chrome-overlay holds native views detached. */
  private overlaySuppressed = false;
  /** Returns whether spell checking is currently enabled (preference-backed). */
  private resolveSpellcheckEnabled?: () => boolean;
  /** Returns the target language code for "Translate this page" (e.g. 'en'). */
  private resolveTranslateLang?: () => string;
  /**
   * Returns the stored per-site settings for a URL's origin (zoom factor,
   * auto-play policy, pop-up policy) or null when none are stored. Never
   * consulted for private windows.
   */
  private resolveSiteSettings?: (url: string) => { zoom: number | null; autoplay: string | null; popups: string | null } | null;
  /** Persist a user-driven zoom change for the site owning `url`. */
  private onZoomPersist?: (url: string, factor: number) => void;
  /** A pop-up was blocked by the per-site pop-up policy ('block-notify'). */
  private onPopupBlocked?: (pageUrl: string, popupUrl: string) => void;
  /** Pixel height reserved at the bottom of the tab area for the virtual keyboard. */
  private keyboardReserve = 0;
  /** Returns whether the virtual keyboard feature is enabled (preference-backed). */
  private resolveVkEnabled?: () => boolean;
  /** A tab page reported which kind of editable field is focused. */
  private onVkFocus?: (payload: VkFocusPayload) => void;
  /**
   * Cached tab thumbnails for the Tab Overview grid. Background tabs cannot
   * be captured while detached (their views are not painted), so the last
   * visible frame is snapshotted just before a tab deactivates.
   */
  private thumbnailCache = new Map<TabId, { dataUrl: string; at: number }>();

  constructor(win: BrowserWindow, options: TabManagerOptions = {}) {
    this.win = win;
    this.tabSession = options.privateSession ?? session.defaultSession;
    this.isPrivate = options.privateSession !== undefined;
    // Failsafe: re-apply the active tab's layout whenever the window regains
    // focus. If a native view was ever left detached (e.g. a chrome-overlay
    // release was lost mid-flight), the content area stays blank forever with
    // no user-visible way to recover — a focus relayout is idempotent and
    // no-ops while an overlay legitimately holds the views detached.
    // (Guarded: unit tests pass a minimal BrowserWindow stub without .on.)
    if (typeof win.on === 'function') {
      win.on('focus', () => {
        try { this.layoutActiveTab(); } catch { /* window mid-teardown */ }
      });
    }
  }

  /**
   * Reload a native view's page if its renderer process dies unexpectedly.
   * Without this, a crashed renderer leaves the pane permanently blank while
   * the chrome keeps working — the user sees an empty content area with no
   * way to recover short of closing the tab. Guards against crash loops
   * (max 3 automatic reloads per minute per view).
   */
  private wireRenderProcessRecovery(view: WebContentsView): void {
    const wc = view.webContents;
    let recentCrashes: number[] = [];
    wc.on('render-process-gone', (_event, details) => {
      // 'clean-exit' and 'killed' are deliberate teardown, not crashes.
      if (details.reason === 'clean-exit' || details.reason === 'killed') return;
      const now = Date.now();
      recentCrashes = recentCrashes.filter(t => now - t < 60_000);
      recentCrashes.push(now);
      if (recentCrashes.length > 3) return; // crash loop — stop retrying
      setTimeout(() => {
        try {
          if (wc.isDestroyed()) return;
          wc.reload();
          this.layoutActiveTab();
        } catch { /* view mid-teardown */ }
      }, 250);
    });
  }

  setCallbacks(cbs: {
    onTabStateChange?: (tabId: TabId, state: Partial<TabState>) => void;
    onTabCreated?: (tabId: TabId) => void;
    onTabClosed?: (tabId: TabId) => void;
    onActiveTabChange?: (tabId: TabId) => void;
    onNavigate?: (tabId: TabId, url: string, title: string) => void;
    onAddToBiolink?: (url: string, title: string) => void;
    onShortenPage?: (url: string, title: string) => void;
    onCreateQr?: (url: string, title: string) => void;
    onAutofillPage?: (tabId: TabId) => void;
    onDeviceLabPreview?: (url: string) => void;
    onFindResult?: (result: FindResult) => void;
    onTabOrderChange?: (order: TabId[]) => void;
    onPinnedUrlsChange?: (urls: string[]) => void;
    onRecentlyClosedChange?: (entries: RecentlyClosedEntry[]) => void;
    resolveAutoMute?: (url: string) => boolean;
    onUserMuteChange?: (url: string, muted: boolean) => void;
    resolveZioPanelReserve?: () => number;
    resolveSayzioRailReserve?: () => number;
    resolveSpellcheckEnabled?: () => boolean;
    resolveTranslateLang?: () => string;
    resolveSiteSettings?: (url: string) => { zoom: number | null; autoplay: string | null; popups: string | null } | null;
    onZoomPersist?: (url: string, factor: number) => void;
    onPopupBlocked?: (pageUrl: string, popupUrl: string) => void;
    resolveVkEnabled?: () => boolean;
    onVkFocus?: (payload: VkFocusPayload) => void;
  }): void {
    this.onTabStateChange = cbs.onTabStateChange;
    this.onTabCreated = cbs.onTabCreated;
    this.onTabClosed = cbs.onTabClosed;
    this.onActiveTabChange = cbs.onActiveTabChange;
    this.onNavigate = cbs.onNavigate;
    this.onAddToBiolink = cbs.onAddToBiolink;
    this.onShortenPage = cbs.onShortenPage;
    this.onCreateQr = cbs.onCreateQr;
    this.onAutofillPage = cbs.onAutofillPage;
    this.onDeviceLabPreview = cbs.onDeviceLabPreview;
    this.onFindResult = cbs.onFindResult;
    this.onTabOrderChange = cbs.onTabOrderChange;
    this.onPinnedUrlsChange = cbs.onPinnedUrlsChange;
    this.onRecentlyClosedChange = cbs.onRecentlyClosedChange;
    this.resolveAutoMute = cbs.resolveAutoMute;
    this.onUserMuteChange = cbs.onUserMuteChange;
    this.resolveZioPanelReserve = cbs.resolveZioPanelReserve;
    this.resolveSayzioRailReserve = cbs.resolveSayzioRailReserve;
    this.resolveSpellcheckEnabled = cbs.resolveSpellcheckEnabled;
    this.resolveTranslateLang = cbs.resolveTranslateLang;
    this.resolveSiteSettings = cbs.resolveSiteSettings;
    this.onZoomPersist = cbs.onZoomPersist;
    this.onPopupBlocked = cbs.onPopupBlocked;
    this.resolveVkEnabled = cbs.resolveVkEnabled;
    this.onVkFocus = cbs.onVkFocus;
  }

  // ── Virtual keyboard support ───────────────────────────────────────────────

  /**
   * Reserve pixels at the bottom of the tab area for the renderer-drawn
   * docked virtual keyboard, shrinking every native view above it.
   */
  setKeyboardReserve(px: number): void {
    const next = Math.max(0, Math.floor(px) || 0);
    if (next === this.keyboardReserve) return;
    this.keyboardReserve = next;
    this.layoutActiveTab();
  }

  /**
   * Wire virtual-keyboard focus reporting on a tab webContents: inject the
   * page-side reporter on every load (when the feature is enabled) and relay
   * its console-message signals to the renderer. Focus reports only matter
   * for the ACTIVE tab — background views can't receive user focus.
   */
  private wireVkFocusReporting(wc: WebContents, tabId: TabId): void {
    wc.on('dom-ready', () => {
      if (this.resolveVkEnabled?.()) this.injectVkReporter(wc);
    });
    wc.on('console-message', (event) => {
      const payload = parseVkFocusMessage(event.message ?? '');
      if (!payload) return;
      // Suppress the reporter line from normal console handling.
      event.preventDefault?.();
      if (this.activeTabId === tabId) this.onVkFocus?.(payload);
    });
  }

  /** Inject the focus reporter into one page (idempotent via window guard). */
  private injectVkReporter(wc: WebContents): void {
    if (!isAlive(wc)) return;
    const url = wc.getURL();
    if (!/^(https?|file):/.test(url)) return;
    wc.executeJavaScript(buildVkFocusReporterScript()).catch(() => { });
  }

  /**
   * Inject the focus reporter into every live tab page. Called when the
   * virtual-keyboard preference flips on so already-open pages start
   * reporting without a reload.
   */
  injectVkReporterAll(): void {
    for (const tab of this.tabs.values()) {
      for (const view of [tab.view, tab.secondView]) {
        if (view && isAlive(view.webContents)) this.injectVkReporter(view.webContents);
      }
    }
  }

  /**
   * The webContents virtual-keyboard input should be injected into for a tab
   * (the focused pane in a Website+Website split). Null for internal pages.
   */
  getFocusedWebContentsForTab(id: TabId): WebContents | null {
    const tab = this.tabs.get(id);
    if (!tab || this.isFocusedPaneInternal(tab)) return null;
    const wc = this.focusedWebContents(tab);
    return isAlive(wc) ? wc : null;
  }

  /** Stored per-site settings for a URL, or null (always null in private windows). */
  private siteSettingsFor(url: string): { zoom: number | null; autoplay: string | null; popups: string | null } | null {
    if (this.isPrivate || !url) return null;
    try {
      return this.resolveSiteSettings?.(url) ?? null;
    } catch {
      return null;
    }
  }

  /**
   * Enforce the stored auto-play policy on a page by pausing media the policy
   * disallows. 'never' pauses everything; 'stop-with-sound' pauses only media
   * that is playing audibly.
   */
  private enforceAutoplayPolicy(wc: Electron.WebContents): void {
    if (!isAlive(wc)) return;
    const policy = this.siteSettingsFor(wc.getURL())?.autoplay ?? 'allow';
    if (policy !== 'never' && policy !== 'stop-with-sound') return;
    const js = policy === 'never'
      ? `(() => { document.querySelectorAll('video,audio').forEach(m => { try { m.autoplay = false; m.removeAttribute('autoplay'); if (!m.paused) m.pause(); } catch (e) {} }); })();`
      : `(() => { document.querySelectorAll('video,audio').forEach(m => { try { if (!m.paused && !m.muted && m.volume > 0) m.pause(); } catch (e) {} }); })();`;
    wc.executeJavaScript(js, true).catch(() => { });
  }

  /**
   * Re-apply stored per-site settings (zoom + auto-play) to every open tab on
   * the given origin. Called after the user edits settings in the popover so
   * changes take effect immediately.
   */
  applySiteSettingsToOrigin(origin: string): void {
    if (this.isPrivate) return;
    for (const [tabId, tab] of this.tabs) {
      const wc = tab.view.webContents;
      if (!isAlive(wc)) continue;
      let tabOrigin: string;
      try {
        tabOrigin = new URL(wc.getURL()).origin;
      } catch {
        continue;
      }
      if (tabOrigin !== origin) continue;
      const s = this.siteSettingsFor(wc.getURL());
      const zoom = s?.zoom ?? 1.0;
      try {
        wc.setZoomFactor(Math.max(0.25, Math.min(5.0, zoom)));
        this.onTabStateChange?.(tabId, { zoomFactor: wc.getZoomFactor() });
      } catch { }
      this.enforceAutoplayPolicy(wc);
    }
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

    // Apply the spell-check preference to this tab's session (idempotent).
    try {
      const ses = this.isPrivate ? this.tabSession : tabSession;
      ses.setSpellCheckerEnabled(this.resolveSpellcheckEnabled?.() ?? true);
    } catch { /* spellchecker unavailable on some platforms */ }

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
    this.wireRenderProcessRecovery(view);

    // Solid background so tab switches never flash white/transparent.
    view.setBackgroundColor(VIEW_BG_COLOR);

    const [w, h] = this.win.getContentSize();
    view.setBounds({ x: 0, y: 72, width: w, height: h - 72 });

    // Which pane of the tab this view currently serves. Normally 'primary',
    // but swapPanes() exchanges the tab's view references, so the role must
    // be resolved at event time rather than baked into the closures.
    const paneRole = (): 'primary' | 'second' =>
      this.tabs.get(id)?.secondView === view ? 'second' : 'primary';

    // Wire up events
    wc.on('did-navigate', (_, navUrl) => {
      const navigatedTab = this.tabs.get(id);
      const role = paneRole();
      if (role === 'primary') {
        // First real navigation ends the New Tab page state — attach the view.
        if (navigatedTab?.isNewTabPage && navUrl && navUrl !== 'about:blank' && navUrl !== 'about:newtab') {
          navigatedTab.isNewTabPage = false;
          if (this.activeTabId === id) this.layoutActiveTab();
        }
        // A real navigation ends any renderer-drawn internal page.
        if (navigatedTab?.internalUrl && navUrl && navUrl !== 'about:blank') {
          navigatedTab.internalUrl = null;
        }
      }
      if (isAlive(wc)) {
        wc.stopFindInPage('clearSelection');
      }
      this.onFindResult?.({ tabId: id, activeMatchOrdinal: 0, matches: 0, finalUpdate: true });
      if (role === 'primary') {
        this.onTabStateChange?.(id, {
          url: navUrl,
          primaryUrl: navUrl,
          canGoBack: wc.canGoBack(),
          canGoForward: wc.canGoForward(),
          isLoading: false,
        });
      } else {
        this.onTabStateChange?.(id, { secondUrl: navUrl });
        if (navigatedTab?.focusedPane === 'second') {
          this.onTabStateChange?.(id, {
            url: navUrl,
            displayUrl: navUrl,
            canGoBack: wc.canGoBack(),
            canGoForward: wc.canGoForward(),
            isLoading: false,
          });
        }
      }
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
      // Re-apply the stored per-site zoom for this origin (normal windows only).
      const storedZoom = this.siteSettingsFor(navUrl)?.zoom;
      if (isAlive(wc)) {
        const target = storedZoom && storedZoom > 0 ? storedZoom : 1.0;
        if (Math.abs(wc.getZoomFactor() - target) > 0.001) {
          try {
            wc.setZoomFactor(Math.max(0.25, Math.min(5.0, target)));
            this.onTabStateChange?.(id, { zoomFactor: wc.getZoomFactor() });
          } catch { }
        }
      }
    });

    // Virtual keyboard: focus reporting for this tab's page.
    this.wireVkFocusReporting(wc, id);

    // Enforce the per-site auto-play policy once the page is ready and again
    // whenever media actually starts playing (covers late-started players).
    wc.on('did-finish-load', () => {
      this.enforceAutoplayPolicy(wc);
    });
    wc.on('media-started-playing', () => {
      this.enforceAutoplayPolicy(wc);
    });

    wc.on('did-navigate-in-page', (_, navUrl) => {
      if (paneRole() === 'primary') {
        this.onTabStateChange?.(id, { url: navUrl, primaryUrl: navUrl });
      } else {
        this.onTabStateChange?.(id, { secondUrl: navUrl });
        if (this.tabs.get(id)?.focusedPane === 'second') {
          this.onTabStateChange?.(id, { url: navUrl, displayUrl: navUrl });
        }
      }
    });

    wc.on('page-title-updated', (_, title) => {
      // As the second (unfocused) pane, this view must not clobber the
      // shared toolbar title of the focused pane.
      if (paneRole() === 'primary' || this.tabs.get(id)?.focusedPane === 'second') {
        this.onTabStateChange?.(id, { title });
      }
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
      if (paneRole() === 'primary' || this.tabs.get(id)?.focusedPane === 'second') {
        this.onTabStateChange?.(id, { isLoading: true });
      }
    });

    wc.on('did-stop-loading', () => {
      if (paneRole() === 'primary' || this.tabs.get(id)?.focusedPane === 'second') {
        this.onTabStateChange?.(id, {
          isLoading: false,
          canGoBack: wc.canGoBack(),
          canGoForward: wc.canGoForward(),
        });
      }
    });

    wc.on('zoom-changed', (_, direction) => {
      const zf = wc.getZoomFactor() + (direction === 'in' ? 0.1 : -0.1);
      wc.setZoomFactor(Math.max(0.25, Math.min(5.0, zf)));
      this.onTabStateChange?.(id, { zoomFactor: wc.getZoomFactor() });
      if (!this.isPrivate && isAlive(wc)) {
        this.onZoomPersist?.(wc.getURL(), wc.getZoomFactor());
      }
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
      const isStandardPage = /^(https?|file):/.test(pageUrl);

      // ── Standard navigation ───────────────────────────────────────────────
      menuItems.push(
        {
          label: 'Back',
          enabled: isAlive(wc) && wc.canGoBack(),
          click: () => { if (isAlive(wc)) wc.goBack(); },
        },
        {
          label: 'Forward',
          enabled: isAlive(wc) && wc.canGoForward(),
          click: () => { if (isAlive(wc)) wc.goForward(); },
        },
        {
          label: 'Reload',
          enabled: isStandardPage,
          click: () => { if (isAlive(wc)) wc.reload(); },
        },
        { type: 'separator' },
      );

      // ── Spell check — replacement suggestions + add-to-dictionary ─────────
      if (params.misspelledWord) {
        for (const suggestion of params.dictionarySuggestions.slice(0, 5)) {
          menuItems.push({
            label: suggestion,
            click: () => { if (isAlive(wc)) wc.replaceMisspelling(suggestion); },
          });
        }
        if (params.dictionarySuggestions.length === 0) {
          menuItems.push({ label: 'No spelling suggestions', enabled: false });
        }
        menuItems.push(
          {
            label: `Add "${params.misspelledWord}" to dictionary`,
            click: () => {
              try { wc.session.addWordToSpellCheckerDictionary(params.misspelledWord); } catch { }
            },
          },
          { type: 'separator' },
        );
      }

      if (params.isEditable) {
        menuItems.push({ role: 'cut' }, { role: 'copy' }, { role: 'paste' }, { type: 'separator' });
      } else if (params.selectionText) {
        menuItems.push({ role: 'copy' });
        const selText = params.selectionText.trim().replace(/\s+/g, ' ');
        if (selText) {
          const shortText = selText.length > 40 ? `${selText.slice(0, 40)}…` : selText;
          menuItems.push({
            label: `Search ${this.searchEngine.name} for "${shortText}"`,
            click: () => {
              const searchUrl = this.searchEngine.searchTemplate.replace(
                '{query}',
                encodeURIComponent(selText),
              );
              this.createTab(searchUrl);
            },
          });
        }
        menuItems.push({ type: 'separator' });
      }

      // ── Image actions ─────────────────────────────────────────────────────
      if (params.mediaType === 'image' && params.srcURL) {
        const imageUrl = params.srcURL;
        menuItems.push(
          { label: 'Open image in new tab', click: () => { this.createTab(imageUrl); } },
          { label: 'Copy image', click: () => { if (isAlive(wc)) wc.copyImageAt(params.x, params.y); } },
          { label: 'Copy image address', click: () => { clipboard.writeText(imageUrl); } },
          { label: 'Save image as…', click: () => { if (isAlive(wc)) wc.downloadURL(imageUrl); } },
          { type: 'separator' },
        );
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

      // ── Picture-in-Picture for videos ─────────────────────────────────────
      if (params.mediaType === 'video') {
        menuItems.push(
          {
            label: 'Picture in Picture',
            click: () => {
              if (!isAlive(wc)) return;
              const js = `(function(){
                try {
                  if (document.pictureInPictureElement) {
                    document.exitPictureInPicture().catch(function(){});
                    return;
                  }
                  var el = document.elementFromPoint(${params.x}, ${params.y});
                  var video = (el && el.tagName === 'VIDEO') ? el : null;
                  if (!video) {
                    var vids = Array.prototype.slice.call(document.querySelectorAll('video'));
                    vids.sort(function(a,b){ return (b.clientWidth*b.clientHeight) - (a.clientWidth*a.clientHeight); });
                    video = vids[0] || null;
                  }
                  if (video && video.requestPictureInPicture) {
                    video.requestPictureInPicture().catch(function(){});
                  }
                } catch (e) { }
              })()`;
              void wc.executeJavaScript(js, true).catch(() => { });
            },
          },
          { type: 'separator' },
        );
      }

      // ── Translate this page ───────────────────────────────────────────────
      if (pageUrl && (pageUrl.startsWith('http://') || pageUrl.startsWith('https://')) &&
          !pageUrl.includes('translate.goog') && !pageUrl.startsWith('https://translate.google.com')) {
        const lang = this.resolveTranslateLang?.() || 'en';
        menuItems.push(
          {
            label: 'Translate this page',
            click: () => {
              if (!isAlive(wc)) return;
              const translated = `https://translate.google.com/translate?sl=auto&tl=${encodeURIComponent(lang)}&u=${encodeURIComponent(pageUrl)}`;
              void wc.loadURL(translated);
            },
          },
          { type: 'separator' },
        );
      }

      // ── Sayzio link tools (available in both normal and private windows) ──
      menuItems.push(
        {
          label: params.linkURL ? 'Shorten this link with Sayzio…' : 'Shorten this page with Sayzio…',
          click: () => { this.onShortenPage?.(targetUrl, pageTitle); },
        },
        {
          label: params.linkURL ? 'QR code for this link…' : 'QR code for this page…',
          click: () => { this.onCreateQr?.(targetUrl, pageTitle); },
        },
        {
          label: 'Add to my biolink…',
          click: () => { this.onAddToBiolink?.(targetUrl, pageTitle); },
        },
        {
          label: 'Fill form with my Sayzio card',
          click: () => { this.onAutofillPage?.(id); },
        },
        {
          label: 'Preview in Device Lab',
          click: () => { this.onDeviceLabPreview?.(params.linkURL || pageUrl); },
        },
      );

      // ── Standard page actions ─────────────────────────────────────────────
      menuItems.push(
        { type: 'separator' },
        {
          label: 'Print…',
          enabled: isStandardPage,
          click: () => { if (isAlive(wc)) wc.print(); },
        },
        {
          label: 'Save Page As…',
          enabled: isStandardPage,
          click: () => { void this.savePageAsFor(wc); },
        },
        {
          label: 'View Page Source',
          enabled: isStandardPage,
          click: () => {
            if (isAlive(wc)) {
              const u = wc.getURL();
              if (/^(https?|file):/.test(u)) this.createTab(`view-source:${u}`);
            }
          },
        },
        {
          label: 'Inspect Element',
          click: () => {
            if (!isAlive(wc)) return;
            wc.inspectElement(params.x, params.y);
            if (wc.isDevToolsOpened()) wc.devToolsWebContents?.focus();
          },
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
      const pageUrl = isAlive(wc) ? wc.getURL() : '';
      const policy = this.siteSettingsFor(pageUrl)?.popups ?? 'allow';
      if (policy === 'block') return { action: 'deny' };
      if (policy === 'block-notify') {
        this.onPopupBlocked?.(pageUrl, openUrl);
        return { action: 'deny' };
      }
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

    // In a Website+Website split the toolbar follows the last-focused pane.
    wc.on('focus', () => {
      const t = this.tabs.get(id);
      const role = paneRole();
      if (t && t.mode === 'browser+browser' && t.focusedPane !== role) {
        this.setFocusedPane(t, role);
      }
    });
    // Inserted CSS (unfocused-pane dim) does not survive navigation.
    wc.on('dom-ready', () => {
      const t = this.tabs.get(id);
      if (t && t.mode === 'browser+browser') {
        this.paneDimKeys.delete(view);
        this.refreshPaneDim(t);
      }
    });

    if (pinned) this.pinnedTabs.add(id);
    const isInternal = isInternalPageUrl(url);
    const isNewTabPage = !url || url === 'about:newtab' || isInternal;
    const tab: ManagedTab = { id, view, pinned, favicon: null, muteOverride: null, mode: 'browser', dashboardView: null, secondView: null, splitRatio: TAB_SPLIT_RATIO, focusedPane: 'primary', isNewTabPage, internalUrl: isInternal && url ? url : null };
    this.tabs.set(id, tab);
    this.insertInOrder(id, pinned);

    if (!background || !this.activeTabId) {
      // New Tab pages are drawn by the renderer DOM — keep the (blank) native
      // view detached so it can't cover the quick links / recent buttons.
      if (!isNewTabPage) {
        this.win.contentView.addChildView(view);
      }
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
    if (isInternalPageUrl(url)) {
      // Renderer-drawn internal page: never load in the native view; just
      // publish the url/title so the renderer can draw it.
      this.onTabStateChange?.(id, { url, title: internalPageTitle(url), isLoading: false });
    } else if (targetUrl !== 'about:newtab') {
      void wc.loadURL(targetUrl);
    }

    return id;
  }

  closeTab(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab) return;

    // Last-tab guard: a window must never end up tab-less. When the tab
    // being closed is the only one, create a fresh New Tab FIRST (mirroring
    // the "create new before destroying old" lifecycle rule) so the window
    // always has usable content. Skipped during window teardown (destroyAll),
    // where spawning replacement tabs would leak views.
    if (!this.destroyingAll && this.tabOrder.length === 1 && this.tabOrder[0] === id) {
      this.createTab();
    }

    // Save to recently-closed stack (skip empty/new-tab pages).
    // A renderer-drawn internal page is what the user actually sees, so it
    // wins over any stale wc.getURL() left from a prior real page.
    const wc = tab.view.webContents;
    const url = tab.internalUrl ?? (isAlive(wc) ? wc.getURL() : '');
    if (url && url !== 'about:newtab' && url !== 'about:blank' && url !== '') {
      const entry: RecentlyClosedEntry = {
        url,
        title: tab.internalUrl
          ? internalPageTitle(tab.internalUrl)
          : (isAlive(wc) ? wc.getTitle() : '') || url,
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

    // webContents.close() is a polite window.close(): a view whose document
    // never committed (blank second pane) or is still mid-load (dashboard
    // pane on a closed-while-inactive tab) ignores it, leaking the
    // WebContentsView as an orphaned window. Force-destroy shortly after if
    // the contents are still alive.
    const closeThenDestroy = (wc: Electron.WebContents): void => {
      if (wc.isDestroyed()) return;
      wc.close();
      setTimeout(() => {
        if (!wc.isDestroyed()) {
          (wc as unknown as { destroy?: () => void }).destroy?.();
        }
      }, 250);
    };

    closeThenDestroy(tab.view.webContents);

    try {
      this.win.contentView.removeChildView(tab.view);
    } catch {
      // May already be removed
    }

    for (const extra of [tab.dashboardView, tab.secondView]) {
      if (!extra) continue;
      try { this.win.contentView.removeChildView(extra); } catch { }
      closeThenDestroy(extra.webContents);
    }
    tab.dashboardView = null;
    tab.secondView = null;

    this.pinnedTabs.delete(id);
    this.thumbnailCache.delete(id);
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
    const layouts: (SessionTabLayout | null)[] = [];
    let activeIndex = -1;
    let activePinnedIndex = -1;
    let pinnedIdx = 0;
    for (const id of this.tabOrder) {
      const tab = this.tabs.get(id);
      const wc = tab?.view.webContents;
      if (!tab || !wc || !isAlive(wc)) continue;
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
      // Persist any non-default view mode (mode + divider ratio) so the next
      // launch can rebuild the split. The second pane's URL only exists for
      // the Website+Website split — other right panes (My Files, Ask Zio,
      // Dashboard) are app surfaces recreated from the mode alone.
      let layout: SessionTabLayout | null = null;
      if (tab.mode !== 'browser') {
        const secondWc = tab.secondView?.webContents;
        const secondUrl =
          tab.mode === 'browser+browser' && secondWc && isAlive(secondWc) ? secondWc.getURL() : '';
        layout = {
          mode: tab.mode,
          splitRatio: tab.splitRatio,
          ...(secondUrl && secondUrl !== 'about:blank' ? { secondUrl } : {}),
        };
      }
      layouts.push(layout);
    }
    return { urls, activeIndex, activePinnedIndex, layouts };
  }

  /**
   * Restore a previous session's non-pinned tabs (in order) and activate the
   * saved active tab. Call after pinned tabs have been restored.
   * `layouts` (parallel to `urls`) rebuilds split/pane tabs — mode, divider
   * ratio, and (for Website+Website only) the second pane's URL.
   */
  restoreSessionTabs(urls: string[], activeIndex = -1, layouts?: (SessionTabLayout | null)[]): void {
    const ids: TabId[] = [];
    for (let i = 0; i < urls.length; i++) {
      const url = urls[i];
      if (!url) continue;
      const id = this.createTab(url, true);
      ids.push(id);
      const layout = layouts?.[i];
      const mode = layout ? normalizeTabMode(layout.mode) : null;
      if (layout && mode && mode !== 'browser') {
        this.setTabMode(id, mode);
        if (typeof layout.splitRatio === 'number') {
          this.setTabSplitRatio(id, layout.splitRatio);
        }
        // Only the Website+Website split has a persisted second-pane URL —
        // other right panes are app surfaces recreated by setTabMode alone.
        if (mode === 'browser+browser' && typeof layout.secondUrl === 'string' && layout.secondUrl) {
          this.navigatePane(id, 'second', layout.secondUrl);
          // Loading/attaching the second pane can grab keyboard focus, which
          // the focus-follows handler translates into toolbar control. A
          // freshly restored split should control the PRIMARY pane (the
          // focused pane isn't persisted) — snap it back once the pane's
          // initial load settles.
          const sWc = this.tabs.get(id)?.secondView?.webContents;
          if (sWc && isAlive(sWc)) {
            sWc.once('did-stop-loading', () => {
              const t = this.tabs.get(id);
              if (!t || t.mode !== 'browser+browser') return;
              if (t.focusedPane !== 'primary') this.setFocusedPane(t, 'primary');
              if (this.activeTabId === id && isAlive(t.view.webContents)) {
                t.view.webContents.focus();
              }
            });
          }
        }
      }
    }
    if (ids.length === 0) return;
    const target = ids[activeIndex] ?? ids[ids.length - 1];
    if (target) this.activateTab(target);
  }

  activateTab(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab) return;

    const prevId = this.activeTabId;

    // Snapshot the outgoing tab's last visible frame for the Tab Overview
    // grid — once detached, its view no longer paints and can't be captured.
    if (prevId && prevId !== id) {
      void this.snapshotThumbnail(prevId);
    }

    // Attach the new tab's views FIRST (they render on top), then detach the
    // previous tab's views — avoids a blank flash between tabs.
    this.activeTabId = id;
    this.layoutActiveTab();

    if (prevId && prevId !== id) {
      const prev = this.tabs.get(prevId);
      if (prev) {
        for (const v of [prev.view, prev.dashboardView, prev.secondView]) {
          if (!v) continue;
          try { this.win.contentView.removeChildView(v); } catch { }
        }
      }
    }

    // Don't steal keyboard focus while the renderer draws the New Tab page —
    // its search box needs the focus, and the native view is detached anyway.
    if (!tab.isNewTabPage) {
      tab.view.webContents.focus();
    }
    this.onActiveTabChange?.(id);
  }

  // ── Per-tab view modes ──────────────────────────────────────────────────────

  /**
   * Change the view mode of a tab. Creates the tab's Sayzio webapp view
   * lazily when a Sayzio mode is first selected, and re-lays-out the active
   * tab so the change is visible immediately.
   */
  setTabMode(id: TabId, rawMode: string): void {
    const mode = normalizeTabMode(rawMode);
    if (!mode) return;
    const tab = this.tabs.get(id);
    if (!tab || tab.mode === mode) return;

    tab.mode = mode;

    // Legacy full-view 'sayzio' tabs restore as a website tab pointing at
    // the Sayzio home page (the standalone Sayzio pane was removed).
    if (rawMode === 'sayzio' && mode === 'browser') {
      this.navigate(id, SAYZIO_HOME_URL);
    }

    // Lazily create the Sayzio views the new mode needs.
    const shouldHideAssistant = () => tabModeIncludes(tab.mode, 'zio');
    if (tabModeIncludes(mode, 'dashboard') && !tab.dashboardView) {
      tab.dashboardView = this.createSayzioView(SAYZIO_DASHBOARD_URL, shouldHideAssistant);
    }
    if (mode === 'browser+browser' && !tab.secondView) {
      tab.secondView = this.createSecondBrowserView(tab);
    }
    // Entering the Website+Website split: publish a snapshot of both pane
    // URLs plus the focused pane so the smart address bar and focus frame
    // render current values immediately.
    if (mode === 'browser+browser') {
      const primaryWc = tab.view.webContents;
      const secondWc = tab.secondView?.webContents;
      this.onTabStateChange?.(id, {
        focusedPane: tab.focusedPane,
        primaryUrl: tab.internalUrl ?? (isAlive(primaryWc) ? primaryWc.getURL() : ''),
        ...(secondWc && isAlive(secondWc) ? { secondUrl: secondWc.getURL() } : {}),
      });
    }
    // Leaving the Website+Website split: the toolbar controls the primary
    // pane again.
    if (mode !== 'browser+browser' && tab.focusedPane !== 'primary') {
      this.setFocusedPane(tab, 'primary');
    }
    // Keep the unfocused-pane dim overlay in sync with the new mode.
    this.refreshPaneDim(tab);

    // Hide the embedded site chatbot inside Sayzio panes whenever this tab
    // also shows the Ask Zio pane (two assistants at once is confusing).
    this.updateSiteAssistantVisibility(tab);

    if (id === this.activeTabId) {
      this.layoutActiveTab();
    } else {
      // Detach views the (inactive) tab no longer shows.
      if (tab.dashboardView && !tabModeIncludes(mode, 'dashboard')) {
        try { this.win.contentView.removeChildView(tab.dashboardView); } catch { }
      }
      if (tab.secondView && mode !== 'browser+browser') {
        try { this.win.contentView.removeChildView(tab.secondView); } catch { }
      }
    }

    this.onTabStateChange?.(id, { mode });
  }

  getTabMode(id: TabId): TabMode | null {
    return this.tabs.get(id)?.mode ?? null;
  }

  /**
   * Sayzio rail navigation: open a Sayzio dashboard page in this tab's
   * dashboard pane. If the tab isn't showing a dashboard pane yet, switch it
   * into a split that keeps its current primary content and adds the
   * dashboard (see dashboardModeFor). Only sayzio.app paths are allowed —
   * the dashboard view must never be routed to an external site.
   */
  navigateDashboard(id: TabId, path: string): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    let target: string;
    try {
      const u = new URL(path, `https://${SAYZIO_BASE_HOST}`);
      if (u.host !== SAYZIO_BASE_HOST) return;
      target = u.toString();
    } catch {
      return;
    }
    if (!tabModeIncludes(tab.mode, 'dashboard')) {
      // setTabMode lazily creates the dashboard view and re-lays-out.
      this.setTabMode(id, dashboardModeFor(tab.mode));
    }
    const wc = tab.dashboardView?.webContents;
    if (wc && !wc.isDestroyed()) void wc.loadURL(target);
  }

  /**
   * Set the left-pane ratio of a two-native-pane split tab and re-layout.
   */
  setTabSplitRatio(id: TabId, ratio: number): void {
    const tab = this.tabs.get(id);
    if (!tab || !Number.isFinite(ratio)) return;
    const clamped = Math.min(MAX_TAB_SPLIT_RATIO, Math.max(MIN_TAB_SPLIT_RATIO, ratio));
    if (tab.splitRatio === clamped) return;
    tab.splitRatio = clamped;
    if (id === this.activeTabId) this.layoutActiveTab();
    this.onTabStateChange?.(id, { splitRatio: clamped });
  }

  /**
   * Exchange the two panes of a Website+Website split: the left (primary)
   * view becomes the right (second) view and vice versa, preserving each
   * pane's URL, history and loading state. The toolbar keeps controlling the
   * SAME page — the focused-pane flag flips with its view, so the omnibox
   * Left/Right badge and the focus frame follow the content to its new side.
   */
  swapPanes(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab || tab.mode !== 'browser+browser' || !tab.secondView) return;
    // Renderer-drawn primary surfaces (New Tab / about pages) are anchored to
    // the primary slot — swapping the native views underneath would desync.
    if (tab.isNewTabPage || tab.internalUrl) return;
    const oldPrimary = tab.view;
    tab.view = tab.secondView;
    tab.secondView = oldPrimary;
    // Focus follows the content to its new side.
    tab.focusedPane = tab.focusedPane === 'primary' ? 'second' : 'primary';
    this.refreshPaneDim(tab);
    if (id === this.activeTabId) this.layoutActiveTab();
    const pWc = tab.view.webContents;
    const sWc = tab.secondView.webContents;
    this.onTabStateChange?.(id, {
      focusedPane: tab.focusedPane,
      primaryUrl: isAlive(pWc) ? pWc.getURL() : '',
      secondUrl: isAlive(sWc) ? sWc.getURL() : '',
    });
  }

  /**
   * Switch which browser pane the toolbar controls in 'browser+browser' mode
   * and publish the newly-focused pane's navigation state.
   */
  private setFocusedPane(tab: ManagedTab, pane: 'primary' | 'second'): void {
    if (tab.focusedPane === pane) return;
    tab.focusedPane = pane;
    this.refreshPaneDim(tab);
    const wc = this.focusedWebContents(tab);
    if (!isAlive(wc)) {
      this.onTabStateChange?.(tab.id, { focusedPane: pane });
      return;
    }
    const url = pane === 'primary' ? (tab.internalUrl ?? wc.getURL()) : wc.getURL();
    this.onTabStateChange?.(tab.id, {
      focusedPane: pane,
      url,
      displayUrl: url,
      title: pane === 'primary' && tab.internalUrl ? internalPageTitle(tab.internalUrl) : wc.getTitle(),
      isLoading: wc.isLoading(),
      canGoBack: wc.canGoBack(),
      canGoForward: wc.canGoForward(),
    });
  }

  /**
   * Renderer-driven pane focus switch (focus frame click / keyboard shortcut)
   * for a Website+Website split. Also moves real keyboard focus into the pane
   * so subsequent typing lands there.
   */
  focusPane(id: TabId, pane: 'primary' | 'second'): void {
    const tab = this.tabs.get(id);
    if (!tab || tab.mode !== 'browser+browser') return;
    if (pane === 'second' && !tab.secondView) return;
    this.setFocusedPane(tab, pane);
    const wc = this.focusedWebContents(tab);
    if (isAlive(wc)) wc.focus();
  }

  /** CSS overlay that visually dims the UNFOCUSED pane of a Website+Website split. */
  private static readonly PANE_DIM_CSS =
    'html::before{content:"";position:fixed;inset:0;background:rgba(0,0,0,0.25);z-index:2147483647;pointer-events:none}';

  /** Inserted-CSS keys for the pane-dim overlay, per native view. */
  private paneDimKeys = new Map<WebContentsView, string>();

  /** Monotonic id so each pending dim insertion has a UNIQUE sentinel. */
  private paneDimSeq = 0;

  /**
   * Apply/remove the dim overlay so only the UNFOCUSED pane of a
   * Website+Website split is dimmed. Inserted CSS does not survive
   * navigation, so this is also re-run on each pane's dom-ready.
   */
  private refreshPaneDim(tab: ManagedTab): void {
    const panes: Array<{ view: WebContentsView | null; pane: 'primary' | 'second' }> = [
      { view: tab.view, pane: 'primary' },
      { view: tab.secondView, pane: 'second' },
    ];
    for (const { view, pane } of panes) {
      if (!view) continue;
      const wc = view.webContents;
      if (!isAlive(wc)) continue;
      const shouldDim = tab.mode === 'browser+browser' && tab.focusedPane !== pane;
      const existingKey = this.paneDimKeys.get(view);
      if (shouldDim && !existingKey) {
        // A UNIQUE sentinel per insertion: focus flips and dom-ready refreshes
        // can overlap around one navigation, and a shared 'pending' marker let
        // one resolution adopt another insertion's slot (storing a key for CSS
        // that lived on a torn-down document while removing the live overlay).
        const pending = `pending:${++this.paneDimSeq}`;
        this.paneDimKeys.set(view, pending);
        wc.insertCSS(TabManager.PANE_DIM_CSS)
          .then((key) => {
            if (this.paneDimKeys.get(view) === pending) {
              this.paneDimKeys.set(view, key);
            } else {
              wc.removeInsertedCSS(key).catch(() => { });
            }
          })
          .catch(() => {
            if (this.paneDimKeys.get(view) === pending) this.paneDimKeys.delete(view);
          });
      } else if (!shouldDim && existingKey) {
        this.paneDimKeys.delete(view);
        if (!existingKey.startsWith('pending')) {
          wc.removeInsertedCSS(existingKey).catch(() => { });
        }
      }
    }
  }

  /**
   * The webContents the toolbar (address bar / nav buttons) currently controls.
   */
  private focusedWebContents(tab: ManagedTab): WebContents {
    if (tab.mode === 'browser+browser' && tab.focusedPane === 'second' && tab.secondView && isAlive(tab.secondView.webContents)) {
      return tab.secondView.webContents;
    }
    return tab.view.webContents;
  }

  /**
   * Create the second, fully independent browser view for the
   * Website + Website split. Its navigation state feeds the shared toolbar
   * only while it is the tab's focused pane.
   */
  private createSecondBrowserView(tab: ManagedTab): WebContentsView {
    const view = new WebContentsView({
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
        webSecurity: true,
        allowRunningInsecureContent: false,
        session: this.isPrivate ? this.tabSession : session.fromPartition(this.activePartition),
      },
    });
    view.setBackgroundColor(VIEW_BG_COLOR);
    this.wireRenderProcessRecovery(view);
    const wc = view.webContents;
    const id = tab.id;
    // Which pane of the tab this view currently serves. Normally 'second',
    // but swapPanes() exchanges the tab's view references, so the role must
    // be resolved at event time rather than baked into the closures.
    const paneRole = (): 'primary' | 'second' =>
      this.tabs.get(id)?.view === view ? 'primary' : 'second';
    const emitIfFocused = (state: Partial<TabState>) => {
      const t = this.tabs.get(id);
      if (t && t.mode === 'browser+browser' && t.focusedPane === paneRole()) {
        this.onTabStateChange?.(id, state);
      }
    };
    // The pane's own URL is always published (feeds the split's dedicated
    // per-pane address state); the shared toolbar state only when focused.
    const emitPaneUrl = (navUrl: string) => {
      this.onTabStateChange?.(
        id,
        paneRole() === 'primary' ? { primaryUrl: navUrl } : { secondUrl: navUrl },
      );
    };

    wc.on('did-navigate', (_, navUrl) => {
      emitPaneUrl(navUrl);
      emitIfFocused({
        url: navUrl,
        displayUrl: navUrl,
        canGoBack: wc.canGoBack(),
        canGoForward: wc.canGoForward(),
        isLoading: false,
      });
    });
    wc.on('did-navigate-in-page', (_, navUrl) => {
      emitPaneUrl(navUrl);
      emitIfFocused({ url: navUrl, displayUrl: navUrl });
    });
    wc.on('page-title-updated', (_, title) => {
      emitIfFocused({ title });
    });
    wc.on('did-start-loading', () => {
      emitIfFocused({ isLoading: true });
    });
    wc.on('did-stop-loading', () => {
      emitIfFocused({
        isLoading: false,
        canGoBack: wc.canGoBack(),
        canGoForward: wc.canGoForward(),
      });
    });
    wc.on('focus', () => {
      const t = this.tabs.get(id);
      const role = paneRole();
      if (t && t.mode === 'browser+browser' && t.focusedPane !== role) {
        this.setFocusedPane(t, role);
      }
    });
    // Inserted CSS (unfocused-pane dim) does not survive navigation.
    wc.on('dom-ready', () => {
      const t = this.tabs.get(id);
      if (t && (t.secondView === view || t.view === view) && t.mode === 'browser+browser') {
        this.paneDimKeys.delete(view);
        this.refreshPaneDim(t);
      }
    });

    // Virtual keyboard: the split's second pane reports field focus too.
    this.wireVkFocusReporting(wc, id);

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
    wc.setWindowOpenHandler(({ url: openUrl }) => {
      const pageUrl = isAlive(wc) ? wc.getURL() : '';
      const policy = this.siteSettingsFor(pageUrl)?.popups ?? 'allow';
      if (policy === 'block') return { action: 'deny' };
      if (policy === 'block-notify') {
        this.onPopupBlocked?.(pageUrl, openUrl);
        return { action: 'deny' };
      }
      this.createTab(openUrl);
      return { action: 'deny' };
    });

    // Start on the search engine's home page — an independent surface the
    // user immediately navigates from.
    let startUrl = 'https://www.google.com';
    try {
      startUrl = new URL(this.searchEngine.searchTemplate).origin;
    } catch { /* keep fallback */ }
    void wc.loadURL(startUrl);
    this.onTabStateChange?.(id, { secondUrl: startUrl });
    return view;
  }

  /**
   * Position (and attach/detach) the active tab's views according to its mode
   * within the last-known content bounds.
   */
  private layoutActiveTab(): void {
    // While a renderer chrome-overlay (settings/menus) is held, native views
    // must stay detached — re-attaching here would cover the DOM panel and
    // silently swallow its clicks. The overlay release re-runs the layout.
    if (this.overlaySuppressed) return;
    if (!this.activeTabId) return;
    // The window can be destroyed between a queued layout trigger and its
    // execution (e.g. the startup mode-pick recreates the window while the
    // old window's deferred restore/activate callbacks are still pending).
    // Laying out against a destroyed window throws "Object has been
    // destroyed" — a destroyed window simply has no layout to do.
    if (this.win.isDestroyed()) return;
    const tab = this.tabs.get(this.activeTabId);
    if (!tab) return;

    const [w, h] = this.win.getContentSize();
    let area = this.contentBounds ?? { x: 0, y: 72, width: w, height: Math.max(0, h - 72) };

    // When the renderer is drawing the docked Ask Zio panel (toggle-open or
    // zio-split tab mode), reserve its strip on the right for EVERY layout
    // branch so no native view can cover the panel or its divider.
    const zioReserve = Math.max(0, this.resolveZioPanelReserve?.() ?? 0);
    if (zioReserve > 0) {
      area = { ...area, width: Math.max(0, area.width - zioReserve) };
    }

    // Sayzio sidebar rail: the renderer draws it on the LEFT edge of the
    // content area; shift every native view right so none can cover it.
    const railReserve = Math.max(0, this.resolveSayzioRailReserve?.() ?? 0);
    if (railReserve > 0) {
      area = { ...area, x: area.x + railReserve, width: Math.max(0, area.width - railReserve) };
    }

    // Docked virtual keyboard: reserve its strip at the bottom so no native
    // view can cover the renderer-drawn keys.
    if (this.keyboardReserve > 0) {
      area = { ...area, height: Math.max(0, area.height - this.keyboardReserve) };
    }

    const attach = (v: WebContentsView) => {
      try { this.win.contentView.addChildView(v); } catch { }
    };
    const detach = (v: WebContentsView | null) => {
      if (!v) return;
      try { this.win.contentView.removeChildView(v); } catch { }
    };

    // Resolve the native view backing each pane ('zio' is renderer-drawn → null).
    const viewFor = (pane: TabPane): WebContentsView | null => {
      switch (pane) {
        // New Tab page is renderer-drawn — no native view may cover it.
        case 'browser': return tab.isNewTabPage ? null : tab.view;
        case 'dashboard': return tab.dashboardView;
        // Ask Zio and My Files panes are renderer-drawn — no native view.
        case 'zio': return null;
        case 'files': return null;
      }
    };

    const { left, right } = parseTabMode(tab.mode);
    const leftView = viewFor(left);
    // 'browser+browser' shows the tab's own view left and the independent
    // second browser view right (viewFor would return the same view twice).
    const rightView = right
      ? (tab.mode === 'browser+browser' ? tab.secondView : viewFor(right))
      : null;

    // Compute bounds for the native views this mode shows.
    const placements: Array<{ view: WebContentsView; bounds: Electron.Rectangle }> = [];
    if (right === 'zio') {
      // Left pane native; the Zio panel strip is already excluded from `area`.
      if (leftView) {
        placements.push({
          view: leftView,
          bounds: { x: area.x, y: area.y, width: area.width, height: area.height },
        });
      }
    } else if (right) {
      // Two native panes → split at the tab's persisted ratio with a divider.
      const ratio = Math.min(MAX_TAB_SPLIT_RATIO, Math.max(MIN_TAB_SPLIT_RATIO, tab.splitRatio || TAB_SPLIT_RATIO));
      const leftWidth = Math.max(0, Math.floor(area.width * ratio) - Math.ceil(TAB_SPLIT_DIVIDER_WIDTH / 2));
      const rightX = area.x + leftWidth + TAB_SPLIT_DIVIDER_WIDTH;
      const rightWidth = Math.max(0, area.x + area.width - rightX);
      // Website+Website: inset both panes so the renderer can draw a
      // clickable focus frame around each pane.
      const inset = tab.mode === 'browser+browser' ? TAB_SPLIT_FOCUS_FRAME : 0;
      const insetBounds = (b: Electron.Rectangle): Electron.Rectangle => ({
        x: b.x + inset,
        y: b.y + inset,
        width: Math.max(0, b.width - inset * 2),
        height: Math.max(0, b.height - inset * 2),
      });
      if (leftView) {
        placements.push({ view: leftView, bounds: insetBounds({ x: area.x, y: area.y, width: leftWidth, height: area.height }) });
      }
      if (rightView) {
        placements.push({ view: rightView, bounds: insetBounds({ x: rightX, y: area.y, width: rightWidth, height: area.height }) });
      }
    } else if (leftView) {
      // Single native pane fills the whole area. (mode 'zio' has no native
      // view at all — the renderer's Zio panel fills the tab area.)
      placements.push({ view: leftView, bounds: area });
    }

    // Attach/bound the views this mode shows FIRST (bounds before attach, so
    // nothing flashes at a stale position), then detach the unused ones.
    const shown = new Set(placements.map(p => p.view));
    for (const { view, bounds } of placements) {
      view.setBounds(bounds);
      attach(view);
    }
    for (const v of [tab.view, tab.dashboardView, tab.secondView]) {
      if (v && !shown.has(v)) detach(v);
    }
  }

  /**
   * Create a WebContentsView hosting a Sayzio surface (website or dashboard)
   * for a single tab. Navigation is kept on the Sayzio host; external links
   * open as new browser tabs.
   */
  /** CSS that hides the Sayzio in-page assistant widget. */
  private static readonly HIDE_SITE_ASSISTANT_CSS = '#site-assistant-root{display:none!important}';

  /**
   * Inside Zio Browser, workspace switching is owned by the top-bar profile
   * switcher (which also isolates sessions/history/bookmarks per workspace).
   * Hide the web app's own sidebar workspace switcher in dashboard panes so
   * there aren't two competing switchers (owner request).
   */
  public static readonly HIDE_WORKSPACE_SWITCHER_CSS = '#workspace-switcher{display:none!important}';

  /**
   * Insert/remove the "hide the in-page Zio Bot widget" CSS on this tab's
   * Sayzio views so the embedded site chatbot doesn't double up with the
   * Ask Zio pane when the tab mode includes 'zio'.
   */
  private updateSiteAssistantVisibility(tab: ManagedTab): void {
    const hide = tabModeIncludes(tab.mode, 'zio');
    for (const view of [tab.dashboardView]) {
      if (!view) continue;
      const wc = view.webContents;
      if (!isAlive(wc)) continue;
      const existingKey = this.assistantHideCssKeys.get(view);
      if (hide && !existingKey) {
        // Mark synchronously to avoid double-insert races, then fix up async.
        this.assistantHideCssKeys.set(view, 'pending');
        wc.insertCSS(TabManager.HIDE_SITE_ASSISTANT_CSS)
          .then((key) => {
            if (this.assistantHideCssKeys.get(view) === 'pending') {
              this.assistantHideCssKeys.set(view, key);
            } else {
              // Mode flipped back while inserting — undo.
              wc.removeInsertedCSS(key).catch(() => { });
            }
          })
          .catch(() => { this.assistantHideCssKeys.delete(view); });
      } else if (!hide && existingKey) {
        this.assistantHideCssKeys.delete(view);
        if (existingKey !== 'pending') {
          wc.removeInsertedCSS(existingKey).catch(() => { });
        }
      }
    }
  }

  private createSayzioView(startUrl: string, shouldHideAssistant?: () => boolean): WebContentsView {
    const view = new WebContentsView({
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
        webSecurity: true,
        allowRunningInsecureContent: false,
        session: this.isPrivate ? this.tabSession : session.fromPartition(this.activePartition),
      },
    });

    const wc = view.webContents;
    this.wireRenderProcessRecovery(view);

    wc.on('will-navigate', (event, url) => {
      try {
        const u = new URL(url);
        if (!['http:', 'https:', 'about:'].includes(u.protocol)) {
          event.preventDefault();
          return;
        }
        if (u.hostname === SAYZIO_BASE_HOST || u.hostname.endsWith(`.${SAYZIO_BASE_HOST}`)) {
          return;
        }
        event.preventDefault();
        this.createTab(url);
      } catch {
        event.preventDefault();
      }
    });

    wc.setWindowOpenHandler(({ url }) => {
      try {
        const u = new URL(url);
        if (u.hostname === SAYZIO_BASE_HOST || u.hostname.endsWith(`.${SAYZIO_BASE_HOST}`)) {
          void wc.loadURL(url);
        } else {
          this.createTab(url);
        }
      } catch {
        // ignore
      }
      return { action: 'deny' };
    });

    // Inserted CSS does not survive navigation — re-apply the assistant-hide
    // rule on every page load while the owning tab's mode includes 'zio'.
    wc.on('dom-ready', () => {
      // Dashboard panes never show the in-app workspace switcher — the
      // browser's profile switcher owns workspace switching. (Inserted CSS
      // does not survive navigation; re-apply every load.)
      wc.insertCSS(TabManager.HIDE_WORKSPACE_SWITCHER_CSS).catch(() => { });
      this.assistantHideCssKeys.delete(view);
      if (shouldHideAssistant?.()) {
        this.assistantHideCssKeys.set(view, 'pending');
        wc.insertCSS(TabManager.HIDE_SITE_ASSISTANT_CSS)
          .then((key) => {
            if (this.assistantHideCssKeys.get(view) === 'pending') {
              this.assistantHideCssKeys.set(view, key);
            } else {
              wc.removeInsertedCSS(key).catch(() => { });
            }
          })
          .catch(() => { this.assistantHideCssKeys.delete(view); });
      }
    });

    view.setBackgroundColor(VIEW_BG_COLOR);
    void wc.loadURL(startUrl);
    return view;
  }

  navigate(id: TabId, input: string): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    // In a Website+Website split the address bar drives the focused pane.
    const pane = tab.mode === 'browser+browser' && tab.focusedPane === 'second' ? 'second' : 'primary';
    this.navigatePane(id, pane, input);
  }

  /**
   * Navigate a specific pane of a tab. 'second' targets the independent
   * right-hand view of a Website + Website split (no-op outside that mode);
   * 'primary' always targets the tab's own browser view.
   */
  navigatePane(id: TabId, pane: 'primary' | 'second', input: string): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    if (pane === 'second') {
      if (tab.mode !== 'browser+browser' || !tab.secondView) return;
      const secondWc = tab.secondView.webContents;
      if (isAlive(secondWc)) {
        const result = parseOmniboxInput(input, this.searchEngine);
        void secondWc.loadURL(result.navigateUrl);
      }
      return;
    }
    // Renderer-drawn internal pages (about:sayzio / about:zio): never load in
    // the native view — detach it and publish the url so the renderer draws it.
    // Checked on the RAW input: the omnibox parser would otherwise treat a
    // scheme without "//" (about:zio) as a search query.
    const internalCandidate = input.trim();
    if (isInternalPageUrl(internalCandidate)) {
      const wc = tab.view.webContents;
      if (isAlive(wc) && wc.isLoading()) wc.stop();
      tab.isNewTabPage = true;
      tab.internalUrl = internalCandidate;
      if (this.activeTabId === id) this.layoutActiveTab();
      this.onTabStateChange?.(id, {
        url: internalCandidate,
        title: internalPageTitle(internalCandidate),
        isLoading: false,
      });
      return;
    }
    const result = parseOmniboxInput(input, this.searchEngine);
    // Leaving the New Tab page: re-attach the native view immediately so the
    // page is visible as soon as it starts painting.
    if (tab.isNewTabPage) {
      tab.isNewTabPage = false;
      if (this.activeTabId === id) this.layoutActiveTab();
    }
    tab.internalUrl = null;
    void tab.view.webContents.loadURL(result.navigateUrl);
  }

  /** Save the given webContents' page as complete HTML via a save dialog. */
  private async savePageAsFor(wc: WebContents): Promise<void> {
    if (!isAlive(wc)) return;
    const url = wc.getURL();
    if (!/^(https?|file):/.test(url)) return;
    const title = (wc.getTitle() || 'page').replace(/[\\/:*?"<>|]+/g, '_').slice(0, 100).trim() || 'page';
    const { canceled, filePath } = await dialog.showSaveDialog(this.win, {
      defaultPath: `${title}.html`,
      filters: [
        { name: 'Webpage, Complete', extensions: ['html'] },
        { name: 'All Files', extensions: ['*'] },
      ],
    });
    if (canceled || !filePath || !isAlive(wc)) return;
    try { await wc.savePage(filePath, 'HTMLComplete'); } catch { /* save failed — ignore */ }
  }

  /**
   * True when the tab's focused pane is showing a renderer-drawn internal page
   * (about:newtab / about:sayzio / …). The native webContents may still hold a
   * stale prior URL there, so callers must not trust wc.getURL() in that case.
   */
  private isFocusedPaneInternal(tab: ManagedTab): boolean {
    if (tab.mode === 'browser+browser' && tab.focusedPane === 'second' && tab.secondView && isAlive(tab.secondView.webContents)) {
      return false; // secondary pane always hosts a real webContents page
    }
    return Boolean(tab.internalUrl) || tab.isNewTabPage;
  }

  /** Save the tab's active pane page as complete HTML (app-menu entry point). */
  async savePageAs(id: TabId): Promise<void> {
    const tab = this.tabs.get(id);
    if (!tab || this.isFocusedPaneInternal(tab)) return;
    await this.savePageAsFor(this.focusedWebContents(tab));
  }

  /** Open a view-source tab for the tab's current page (same tab manager, so
   * private windows keep the private session). No-op on internal pages. */
  viewPageSource(id: TabId): void {
    const tab = this.tabs.get(id);
    if (!tab || this.isFocusedPaneInternal(tab)) return;
    const wc = this.focusedWebContents(tab);
    if (!isAlive(wc)) return;
    const url = wc.getURL();
    if (!/^(https?|file):/.test(url)) return;
    this.createTab(`view-source:${url}`);
  }

  goBack(id: TabId): void {
    const tab = this.tabs.get(id);
    if (tab) this.focusedWebContents(tab).goBack();
  }

  goForward(id: TabId): void {
    const tab = this.tabs.get(id);
    if (tab) this.focusedWebContents(tab).goForward();
  }

  reload(id: TabId, ignoreCache = false): void {
    const tab = this.tabs.get(id);
    if (!tab) return;
    const wc = this.focusedWebContents(tab);
    if (ignoreCache) wc.reloadIgnoringCache();
    else wc.reload();
  }

  stop(id: TabId): void {
    const tab = this.tabs.get(id);
    if (tab) this.focusedWebContents(tab).stop();
  }

  setZoom(id: TabId, factor: number): void {
    const wc = this.tabs.get(id)?.view.webContents;
    if (wc) {
      wc.setZoomFactor(Math.max(0.25, Math.min(5.0, factor)));
      if (!this.isPrivate && isAlive(wc)) {
        this.onZoomPersist?.(wc.getURL(), wc.getZoomFactor());
      }
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
    const primaryWc = tab.view.webContents;
    // The toolbar reflects the focused pane in a Website+Website split.
    const wc = this.focusedWebContents(tab);
    const onSecond = wc !== primaryWc;
    // A renderer-drawn internal page is the canonical state, not wc.getURL().
    const url = onSecond ? wc.getURL() : (tab.internalUrl ?? wc.getURL());
    return {
      id,
      url,
      displayUrl: url,
      title: !onSecond && tab.internalUrl ? internalPageTitle(tab.internalUrl) : wc.getTitle(),
      favicon: tab.favicon,
      isLoading: !onSecond && tab.internalUrl ? false : wc.isLoading(),
      canGoBack: wc.canGoBack(),
      canGoForward: wc.canGoForward(),
      isAudible: primaryWc.isCurrentlyAudible(),
      isMuted: primaryWc.isAudioMuted(),
      zoomFactor: wc.getZoomFactor(),
      pinned: tab.pinned,
      mode: tab.mode,
      splitRatio: tab.splitRatio,
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
    this.contentBounds = { ...bounds };
    for (const [id, tab] of this.tabs) {
      tab.view.setBounds(bounds);
      if (id !== this.activeTabId) {
        for (const v of [tab.view, tab.dashboardView, tab.secondView]) {
          if (!v) continue;
          try { this.win.contentView.removeChildView(v); } catch { }
        }
      }
    }
    // The active tab's views are positioned according to its per-tab mode.
    this.layoutActiveTab();
  }

  /** Re-apply the active tab's per-mode layout (e.g. after the Zio panel resizes). */
  relayoutActiveTab(): void {
    this.layoutActiveTab();
  }

  /**
   * Toggle chrome-overlay suppression. While true, layoutActiveTab is a no-op
   * so tab/resize/panel events can never re-attach native views on top of an
   * open renderer panel (settings, menus) and swallow its clicks.
   */
  setOverlaySuppressed(suppressed: boolean): void {
    this.overlaySuppressed = suppressed;
  }

  /**
   * Capture the currently visible active tab as a static backdrop snapshot.
   * Used while a renderer chrome overlay (menu/dropdown) detaches native views
   * so the page doesn't visually vanish behind the open menu.
   */
  async captureActiveBackdrop(): Promise<Array<{ dataUrl: string; bounds: { x: number; y: number; width: number; height: number } }> | null> {
    if (!this.activeTabId) return null;
    const tab = this.tabs.get(this.activeTabId);
    if (!tab) return null;

    // Snapshot every native view the tab's mode currently shows (a split tab
    // has up to two) so the backdrop covers the full content area.
    const { left, right } = parseTabMode(tab.mode);
    const viewFor = (pane: TabPane): WebContentsView | null => {
      switch (pane) {
        case 'browser': return tab.isNewTabPage ? null : tab.view;
        case 'dashboard': return tab.dashboardView;
        case 'zio': return null;
        case 'files': return null;
      }
    };
    const views: WebContentsView[] = [];
    const leftView = viewFor(left);
    if (leftView) views.push(leftView);
    if (right) {
      const rightView = tab.mode === 'browser+browser' ? tab.secondView : viewFor(right);
      if (rightView) views.push(rightView);
    }

    const results: Array<{ dataUrl: string; bounds: { x: number; y: number; width: number; height: number } }> = [];
    for (const view of views) {
      const wc = view.webContents;
      if (wc.isDestroyed()) continue;
      const bounds = view.getBounds();
      if (bounds.width <= 0 || bounds.height <= 0 || bounds.x <= -10000) continue;
      try {
        const image = await wc.capturePage();
        if (!image.isEmpty()) results.push({ dataUrl: image.toDataURL(), bounds });
      } catch {
        // best-effort per pane
      }
    }
    return results.length > 0 ? results : null;
  }

  /**
   * Move all tab views off-screen (used in dashboard mode where no tabs are visible).
   */
  hideAllTabs(): void {
    for (const [, tab] of this.tabs) {
      for (const v of [tab.view, tab.dashboardView, tab.secondView]) {
        if (!v) continue;
        try { this.win.contentView.removeChildView(v); } catch { }
      }
    }
  }

  /**
   * Snapshot a tab's currently painted frame into the thumbnail cache
   * (downscaled). Only works while the tab's view is attached/painting —
   * callers invoke this for the active tab or a tab about to be detached.
   */
  async snapshotThumbnail(id: TabId): Promise<string | null> {
    const tab = this.tabs.get(id);
    if (!tab || tab.isNewTabPage) return null;
    const wc = tab.view.webContents;
    if (!isAlive(wc)) return null;
    try {
      const image = await wc.capturePage();
      if (image.isEmpty()) return null;
      const size = image.getSize();
      const scaled = size.width > 640 ? image.resize({ width: 640 }) : image;
      const dataUrl = scaled.toDataURL();
      this.thumbnailCache.set(id, { dataUrl, at: Date.now() });
      return dataUrl;
    } catch {
      return null;
    }
  }

  /**
   * Thumbnails for the Tab Overview grid: capture the active tab fresh
   * (it's the only one currently painting), and return cached snapshots for
   * background tabs. Missing entries are null — the grid falls back to a
   * favicon/title placeholder card.
   */
  async captureThumbnails(): Promise<Record<string, string | null>> {
    if (this.activeTabId) {
      await this.snapshotThumbnail(this.activeTabId);
    }
    const out: Record<string, string | null> = {};
    for (const id of this.tabOrder) {
      out[id] = this.thumbnailCache.get(id)?.dataUrl ?? null;
    }
    return out;
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

  /**
   * Ask Zio vision tier: capture ONLY the active tab's primary website
   * pane (never the Zio panel, dashboard, or a second split pane) as a
   * size-capped JPEG data URL for the assistant's vision request.
   *
   * Refuses internal/renderer-drawn pages and non-http(s) URLs. Downscales
   * to SCREENSHOT_MAX_WIDTH and re-compresses until the decoded payload is
   * under SCREENSHOT_MAX_BYTES; returns null when it can't fit or capture.
   */
  async captureWebsitePaneForAi(id: TabId): Promise<string | null> {
    const tab = this.tabs.get(id);
    if (!tab) return null;
    // Internal pages are never captured — they may show private UI.
    if (tab.internalUrl || tab.isNewTabPage) return null;
    const wc = tab.view.webContents;
    if (!isAlive(wc)) return null;
    const url = wc.getURL();
    if (!isCapturableUrl(url)) return null;

    try {
      const image = await wc.capturePage();
      if (image.isEmpty()) return null;
      let img = image;
      const { width } = img.getSize();
      if (width > SCREENSHOT_MAX_WIDTH) {
        img = img.resize({ width: SCREENSHOT_MAX_WIDTH });
      }
      for (const quality of [70, 50, 35]) {
        const jpeg = img.toJPEG(quality);
        if (jpeg.length <= SCREENSHOT_MAX_BYTES) {
          return `data:image/jpeg;base64,${jpeg.toString('base64')}`;
        }
      }
      return null;
    } catch {
      return null;
    }
  }

  /**
   * Reader mode: extract the main article content from the current page and
   * load a clean, distraction-free rendering of it (as a data: URL, so the
   * normal Back button returns to the original page).
   */
  async enterReaderMode(id: TabId): Promise<boolean> {
    const tab = this.tabs.get(id);
    if (!tab) return false;
    const wc = tab.view.webContents;
    if (!isAlive(wc)) return false;
    const pageUrl = wc.getURL();
    if (!pageUrl.startsWith('http://') && !pageUrl.startsWith('https://')) return false;

    const extractJs = `(function(){
      try {
        var candidates = [];
        var sels = ['article', 'main', '[role="main"]', '#content', '.post-content', '.article-body', '.entry-content', 'body'];
        for (var i = 0; i < sels.length; i++) {
          var els = document.querySelectorAll(sels[i]);
          for (var j = 0; j < els.length; j++) candidates.push(els[j]);
        }
        var best = null, bestLen = 0;
        for (var k = 0; k < candidates.length; k++) {
          var len = (candidates[k].innerText || '').length;
          if (len > bestLen) { bestLen = len; best = candidates[k]; }
          if (candidates[k].tagName === 'ARTICLE' && len > 500) { best = candidates[k]; break; }
        }
        if (!best || bestLen < 200) return null;
        var clone = best.cloneNode(true);
        var strip = clone.querySelectorAll('script,style,noscript,iframe,form,button,input,select,textarea,nav,aside,footer,header,svg,video,audio,[role="navigation"],[role="banner"],[aria-hidden="true"]');
        for (var s = strip.length - 1; s >= 0; s--) strip[s].remove();
        var all = clone.querySelectorAll('*');
        for (var a = 0; a < all.length; a++) {
          var el = all[a];
          var attrs = el.attributes;
          for (var b = attrs.length - 1; b >= 0; b--) {
            var name = attrs[b].name.toLowerCase();
            if (name.indexOf('on') === 0 || name === 'style' || name === 'class' || name === 'id') el.removeAttribute(attrs[b].name);
          }
          if (el.tagName === 'A') {
            var href = el.getAttribute('href') || '';
            if (/^\\s*javascript:/i.test(href)) el.removeAttribute('href');
            else { try { el.setAttribute('href', new URL(href, location.href).href); } catch (e) { el.removeAttribute('href'); } }
          }
          if (el.tagName === 'IMG') {
            var src = el.getAttribute('src') || '';
            try { el.setAttribute('src', new URL(src, location.href).href); } catch (e) { el.remove(); }
          }
        }
        return { title: document.title || '', content: clone.innerHTML, host: location.hostname };
      } catch (e) { return null; }
    })()`;

    let extracted: { title: string; content: string; host: string } | null = null;
    try {
      extracted = await wc.executeJavaScript(extractJs, true) as { title: string; content: string; host: string } | null;
    } catch {
      extracted = null;
    }
    if (!extracted || !extracted.content) return false;

    const escapeHtml = (s: string): string =>
      s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const html = `<!doctype html><html><head><meta charset="utf-8">
<meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src http: https: data:; style-src 'unsafe-inline'">
<title>${escapeHtml(extracted.title)}</title>
<style>
  :root { color-scheme: light dark; }
  body { margin: 0; background: #f7f5f0; color: #1c1b1a; font: 19px/1.7 Georgia, 'Times New Roman', serif; }
  @media (prefers-color-scheme: dark) { body { background: #17171c; color: #e6e2da; } a { color: #8ab4ff; } .rm-meta { color: #9b97a8 !important; } }
  .rm-wrap { max-width: 680px; margin: 0 auto; padding: 48px 24px 80px; }
  .rm-meta { font: 13px/1.5 -apple-system, 'Segoe UI', sans-serif; color: #77716a; letter-spacing: .4px; text-transform: uppercase; margin-bottom: 8px; }
  h1.rm-title { font-size: 34px; line-height: 1.25; margin: 0 0 28px; }
  img { max-width: 100%; height: auto; border-radius: 6px; }
  pre { overflow-x: auto; background: rgba(128,128,128,.12); padding: 12px; border-radius: 6px; font-size: 14px; }
  blockquote { margin: 0; padding-left: 18px; border-left: 3px solid rgba(128,128,128,.4); }
  a { color: #2f5cc4; }
</style></head><body><div class="rm-wrap">
<div class="rm-meta">Reader mode · ${escapeHtml(extracted.host)}</div>
<h1 class="rm-title">${escapeHtml(extracted.title)}</h1>
${extracted.content}
</div></body></html>`;

    try {
      await wc.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`);
      return true;
    } catch {
      return false;
    }
  }

  destroyAll(): void {
    // Window teardown: disable the last-tab guard so closing the final tab
    // doesn't spawn a replacement into a dying window.
    this.destroyingAll = true;
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
