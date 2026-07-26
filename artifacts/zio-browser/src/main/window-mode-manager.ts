/**
 * WindowModeManager — owns the dashboard WebContentsView and applies
 * correct view bounds to all child views based on the current window mode.
 *
 * Modes:
 *   dashboard — Sayzio dashboard fills the window; minimal renderer chrome.
 *   split     — Left pane (dashboard + Zio tools), right pane (full browser).
 *   browser   — Standard browser; Zio panel on demand (existing behaviour).
 */
import { BrowserWindow, WebContentsView, session } from 'electron';
import {
  type WindowMode,
  SAYZIO_DASHBOARD_URL,
  SAYZIO_BASE_HOST,
  DEFAULT_SPLIT_RATIO,
  MIN_SPLIT_RATIO,
  MAX_SPLIT_RATIO,
  DEFAULT_ZIO_PANEL_WIDTH,
  MIN_ZIO_PANEL_WIDTH,
  MAX_ZIO_PANEL_WIDTH,
  ZIO_PANEL_DIVIDER_WIDTH,
} from '../shared/window-mode';
import type { TabManager } from './tab-manager';

export const CHROME_HEIGHT = 72;
export const DASHBOARD_HEADER_HEIGHT = 44;
export const SPLIT_DIVIDER_WIDTH = 4;

export class WindowModeManager {
  private win: BrowserWindow;
  private tabManager: TabManager;
  private mode: WindowMode;
  private splitRatio: number;
  private zioPanelWidth: number;
  private zioPanelDocked: boolean;
  /**
   * Whether the renderer is CURRENTLY drawing the docked Zio panel. The
   * docked preference alone must not reserve layout space — otherwise a
   * closed panel leaves a dead right-hand strip.
   */
  private zioPanelVisible = false;
  private dashboardView: WebContentsView | null = null;
  /** Number of renderer dropdowns currently holding the chrome overlay open. */
  private overlayCount = 0;
  private onModeChange?: (mode: WindowMode) => void;

  constructor(
    win: BrowserWindow,
    tabManager: TabManager,
    initialMode: WindowMode = 'browser',
    initialSplitRatio: number = DEFAULT_SPLIT_RATIO,
    initialZioPanelWidth: number = DEFAULT_ZIO_PANEL_WIDTH,
    initialZioPanelDocked = true,
  ) {
    this.win = win;
    this.tabManager = tabManager;
    this.mode = initialMode;
    this.splitRatio = Math.max(MIN_SPLIT_RATIO, Math.min(MAX_SPLIT_RATIO, initialSplitRatio));
    this.zioPanelWidth = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, initialZioPanelWidth));
    this.zioPanelDocked = initialZioPanelDocked;
  }

  setModeChangeCallback(cb: (mode: WindowMode) => void): void {
    this.onModeChange = cb;
  }

  getMode(): WindowMode {
    return this.mode;
  }

  getSplitRatio(): number {
    return this.splitRatio;
  }

  getZioPanelWidth(): number {
    return this.zioPanelWidth;
  }

  getZioPanelDocked(): boolean {
    return this.zioPanelDocked;
  }

  /**
   * Switch to a new mode. Handles creating/destroying the dashboard view as needed,
   * repositions all views.
   */
  setMode(mode: WindowMode): void {
    const prev = this.mode;
    this.mode = mode;

    if ((mode === 'dashboard' || mode === 'split') && !this.dashboardView) {
      this.createDashboardView();
    }

    if (mode === 'browser' && this.dashboardView) {
      this.destroyDashboardView();
    }

    if (mode === 'dashboard' || mode === 'split') {
      this.win.contentView.addChildView(this.dashboardView!);
    } else if (prev !== 'browser' && this.dashboardView) {
      try { this.win.contentView.removeChildView(this.dashboardView); } catch { }
    }

    this.applyBounds();
    this.onModeChange?.(mode);
  }

  setSplitRatio(ratio: number): void {
    this.splitRatio = Math.max(MIN_SPLIT_RATIO, Math.min(MAX_SPLIT_RATIO, ratio));
    if (this.mode === 'split') {
      this.applyBounds();
    }
  }

  setZioPanelWidth(width: number): void {
    this.zioPanelWidth = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, width));
    if (this.mode === 'browser') {
      this.applyBounds();
    }
  }

  setZioPanelDocked(docked: boolean): void {
    this.zioPanelDocked = docked;
    if (this.mode === 'browser') {
      this.applyBounds();
    }
  }

  getZioPanelVisible(): boolean {
    return this.zioPanelVisible;
  }

  setZioPanelVisible(visible: boolean): void {
    if (this.zioPanelVisible === visible) return;
    this.zioPanelVisible = visible;
    if (this.mode === 'browser') {
      this.applyBounds();
    }
  }

  /**
   * Recompute and apply bounds for all views based on current mode and window size.
   * Called on resize and mode/ratio changes.
   */
  applyBounds(): void {
    const [w, h] = this.win.getContentSize();

    switch (this.mode) {
      case 'dashboard':
        this.applyDashboardBounds(w, h);
        break;
      case 'split':
        this.applySplitBounds(w, h);
        break;
      case 'browser':
        this.applyBrowserBounds(w, h);
        break;
    }
  }

  private applyDashboardBounds(w: number, h: number): void {
    if (this.dashboardView) {
      this.dashboardView.setBounds({
        x: 0,
        y: DASHBOARD_HEADER_HEIGHT,
        width: w,
        height: Math.max(0, h - DASHBOARD_HEADER_HEIGHT),
      });
    }
    // No tab views in dashboard mode — hide them off-screen
    this.tabManager.hideAllTabs();
  }

  private applySplitBounds(w: number, h: number): void {
    const leftWidth = Math.floor(w * this.splitRatio);
    const rightX = leftWidth + SPLIT_DIVIDER_WIDTH;
    const rightWidth = Math.max(0, w - rightX);

    if (this.dashboardView) {
      this.dashboardView.setBounds({
        x: 0,
        y: DASHBOARD_HEADER_HEIGHT,
        width: leftWidth,
        height: Math.max(0, h - DASHBOARD_HEADER_HEIGHT),
      });
    }

    // Right pane tabs sit below the browser chrome
    this.tabManager.resizeTabs({
      x: rightX,
      y: CHROME_HEIGHT,
      width: rightWidth,
      height: Math.max(0, h - CHROME_HEIGHT),
    });
  }

  private applyBrowserBounds(w: number, h: number): void {
    // When the Zio panel is docked AND actually visible, it occupies the
    // right side. The tabs fill the remaining left portion. Overlay mode —
    // and a docked-but-closed panel — leave tabs full-width.
    const reservedRight = this.zioPanelDocked && this.zioPanelVisible
      ? this.zioPanelWidth + ZIO_PANEL_DIVIDER_WIDTH
      : 0;
    const tabWidth = Math.max(0, w - reservedRight);

    this.tabManager.resizeTabs({
      x: 0,
      y: CHROME_HEIGHT,
      width: tabWidth,
      height: Math.max(0, h - CHROME_HEIGHT),
    });
  }

  /** Create the Sayzio dashboard WebContentsView. */
  private createDashboardView(): void {
    if (this.dashboardView) return;

    this.dashboardView = new WebContentsView({
      webPreferences: {
        nodeIntegration: false,
        contextIsolation: true,
        sandbox: true,
        webSecurity: true,
        allowRunningInsecureContent: false,
        session: session.defaultSession,
      },
    });

    const wc = this.dashboardView.webContents;

    // Route external navigations (links leaving 1in.me) to a new browser tab
    wc.on('will-navigate', (event, url) => {
      try {
        const u = new URL(url);
        const allowed = ['http:', 'https:', 'about:'];
        if (!allowed.includes(u.protocol)) {
          event.preventDefault();
          return;
        }
        // Allow navigation within Sayzio itself
        if (u.hostname === SAYZIO_BASE_HOST || u.hostname.endsWith(`.${SAYZIO_BASE_HOST}`)) {
          return;
        }
        // External link — open in a new browser tab instead
        event.preventDefault();
        this.tabManager.createTab(url);
        // Switch to browser mode so the user can see the tab
        if (this.mode === 'dashboard') {
          this.setMode('browser');
        }
      } catch {
        event.preventDefault();
      }
    });

    // Handle new-window requests (target="_blank") from the dashboard
    wc.setWindowOpenHandler(({ url }) => {
      try {
        const u = new URL(url);
        if (u.hostname === SAYZIO_BASE_HOST || u.hostname.endsWith(`.${SAYZIO_BASE_HOST}`)) {
          void wc.loadURL(url);
        } else {
          this.tabManager.createTab(url);
          if (this.mode === 'dashboard') {
            this.setMode('browser');
          }
        }
      } catch {
        // ignore
      }
      return { action: 'deny' };
    });

    void wc.loadURL(SAYZIO_DASHBOARD_URL);
  }

  private destroyDashboardView(): void {
    if (!this.dashboardView) return;
    try { this.win.contentView.removeChildView(this.dashboardView); } catch { }
    if (!this.dashboardView.webContents.isDestroyed()) {
      this.dashboardView.webContents.close();
    }
    this.dashboardView = null;
  }

  /**
   * Chrome-overlay mode: while a renderer dropdown/menu is open we detach all
   * native views (tab views AND the dashboard view) because they sit above the
   * renderer DOM and would swallow clicks on the menu. Closing the overlay
   * re-applies the current mode, which reattaches and re-bounds everything.
   */
  setChromeOverlay(open: boolean): void {
    if (open) {
      this.overlayCount++;
      this.tabManager.hideAllTabs();
      if (this.dashboardView) {
        try { this.win.contentView.removeChildView(this.dashboardView); } catch { }
      }
    } else {
      this.overlayCount = Math.max(0, this.overlayCount - 1);
      // Only restore views when the LAST open menu closes — several header
      // menus can overlap during a menu-to-menu transition.
      if (this.overlayCount === 0) {
        this.setMode(this.mode);
      }
    }
  }

  getDashboardView(): WebContentsView | null {
    return this.dashboardView;
  }

  /** Reload the dashboard view (e.g. after sign-in via the API). */
  reloadDashboard(): void {
    this.dashboardView?.webContents.reload();
  }

  destroy(): void {
    this.destroyDashboardView();
  }
}
