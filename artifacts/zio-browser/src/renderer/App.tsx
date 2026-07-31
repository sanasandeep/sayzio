import { useState, useEffect, useCallback, useRef } from 'react';
import { ChromeBar } from './components/ChromeBar';
import { ZioPanel } from './components/ZioPanel';
import { DialerPanel } from './components/DialerPanel';
import { NewTabPage } from './components/NewTabPage';
import { AboutPage } from './components/AboutPages';
import { AuthModal } from './components/AuthModal';
import { ModePicker } from './components/ModePicker';
import { DashboardLayout } from './components/DashboardLayout';
import { SplitLayout } from './components/SplitLayout';
import { FindBar } from './components/FindBar';
import { DownloadsPanel } from './components/DownloadsPanel';
import { DownloadToast } from './components/DownloadToast';
import { MessageToast } from './components/MessageToast';
import { DeviceLab } from './components/DeviceLab';
import { TabSearchPopover } from './components/TabSearchPopover';
import { ClearDataDialog } from './components/ClearDataDialog';
import { CommandPalette } from './components/CommandPalette';
import { ScreenshotSheet } from './components/ScreenshotSheet';
import { PermissionPrompt } from './components/PermissionPrompt';
import type { PendingPermission } from './components/PermissionPrompt';
import { SiteSettingsPanel } from './components/SiteSettingsPanel';
import { ReadingListPanel } from './components/ReadingListPanel';
import { SettingsPanel } from './components/SettingsPanel';
import { SplitUrlBars } from './components/SplitUrlBars';
import { useChromeOverlay } from './hooks/use-chrome-overlay';
import { useTabStore } from './store/tab-store';
import { useAuthStore } from './store/auth-store';
import { useModeStore } from './store/mode-store';
import { useFindStore } from './store/find-store';
import { useProfileStore } from './store/profile-store';
import { useDownloadStore } from './store/download-store';
import type { WindowMode } from '../shared/window-mode';
import {
  MIN_ZIO_PANEL_WIDTH,
  MAX_ZIO_PANEL_WIDTH,
  ZIO_PANEL_DIVIDER_WIDTH,
  normalizeTabMode,
  parseTabMode,
  tabModeIncludes,
  tabModeWithout,
  TAB_SPLIT_RATIO,
  MIN_TAB_SPLIT_RATIO,
  MAX_TAB_SPLIT_RATIO,
  TAB_SPLIT_DIVIDER_WIDTH,
  SPLIT_URL_BAR_HEIGHT,
} from '../shared/window-mode';

const FIRST_LAUNCH_KEY = 'zio_mode_picker_shown';

export default function App() {
  const [zioPanelOpen, setZioPanelOpen] = useState(false);
  const [readingListOpen, setReadingListOpen] = useState(false);
  const [dialerPanelOpen, setDialerPanelOpen] = useState(false);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const [showModePicker, setShowModePicker] = useState(false);
  const [isPrivate, setIsPrivate] = useState(false);
  const [deviceLabOpen, setDeviceLabOpen] = useState(false);
  const [deviceLabUrl, setDeviceLabUrl] = useState<string | undefined>(undefined);
  const [tabSearchOpen, setTabSearchOpen] = useState(false);
  const [clearDataShortcut, setClearDataShortcut] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);
  // Live ratio while dragging the tab-split divider (null when not dragging).
  const [tabDragRatio, setTabDragRatio] = useState<number | null>(null);
  const tabDragRatioRef = useRef<number | null>(null);

  // ── Screenshot state ──────────────────────────────────────────────────────
  const [screenshotCapturing, setScreenshotCapturing] = useState(false);
  const [screenshotData, setScreenshotData] = useState<{
    dataUrl: string;
    pageTitle: string;
    pageUrl: string;
  } | null>(null);
  const [siteSettingsOpen, setSiteSettingsOpen] = useState(false);
  const [pendingPermission, setPendingPermission] = useState<PendingPermission | null>(null);
  const { tabs, tabOrder, activeTabId, initTabs, reopenClosedTab, setTabMode } = useTabStore();
  const { init: initAuth, user, token, refreshUser } = useAuthStore();
  const {
    mode,
    splitRatio,
    zioPanelWidth,
    zioPanelDocked,
    isInitialized,
    setMode,
    setSplitRatio,
    setZioPanelWidth,
    setZioPanelDocked,
    init: initMode,
  } = useModeStore();

  // Drag state for the Zio panel divider (browser mode, docked)
  const isDraggingRef = useRef(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const { isOpen: findOpen, closeFind } = useFindStore();
  const { init: initProfiles } = useProfileStore();
  const {
    activeDownloadCount,
    panelOpen: downloadsPanelOpen,
    togglePanel: handleToggleDownloads,
    openPanel: openDownloadsPanel,
    closePanel: closeDownloadsPanel,
  } = useDownloadStore();

  useEffect(() => {
    void Promise.all([
      initAuth(),
      initTabs(),
      initMode(),
      window.zio.window.isPrivate(),
    ]).then(async ([,,, priv]) => {
      const isPriv = Boolean(priv);
      setIsPrivate(isPriv);

      // Show the mode picker on first launch (no persisted mode choice)
      // Don't show the mode picker for private windows — they're browser-only.
      const shown = localStorage.getItem(FIRST_LAUNCH_KEY);
      if (!shown && !isPriv) {
        setShowModePicker(true);
      }

      // Restore the saved theme (dark / light / system)
      try {
        const savedTheme = await window.zio.prefs.get('theme');
        const mode = savedTheme === 'light' || savedTheme === 'dark' ? savedTheme : 'system';
        const resolved = await window.zio.theme.set(mode) as 'dark' | 'light';
        document.documentElement.classList.toggle('light-mode', resolved === 'light');
      } catch { /* keep default dark */ }

      // Restore the active profile from preferences
      const savedProfileId = await window.zio.prefs.get('active_profile') as string | null;
      if (savedProfileId && savedProfileId !== 'default') {
        await window.zio.profiles.switch(savedProfileId);
        await window.zio.profiles.warmSession(savedProfileId);
      }

      // Sync workspace profiles (token may be available now)
      const tok = await window.zio.auth.getToken() as string | null;
      void initProfiles(tok);
    }).catch((err) => {
      // Fail open: never leave the window on the blank pre-init gate because
      // one startup IPC rejected (e.g. main-process DB unavailable).
      console.error('App init failed — continuing with defaults:', err);
    });
  }, [initAuth, initTabs, initMode, initProfiles]);

  // Once a token is available, re-fetch the profile so a name/avatar changed
  // on the website replaces the stale cached copy.
  const refreshedUserRef = useRef(false);
  useEffect(() => {
    if (token && !refreshedUserRef.current) {
      refreshedUserRef.current = true;
      void refreshUser();
    }
  }, [token, refreshUser]);

  // When auth changes (sign-in/out), refresh workspace profiles
  useEffect(() => {
    if (token) {
      void initProfiles(token);
    }
  }, [token, initProfiles]);

  // Ctrl+Shift+Delete → open the clear browsing data dialog
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'Delete') {
        e.preventDefault();
        setClearDataShortcut(true);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Listen for permission requests from the main process
  useEffect(() => {
    const listener = (...args: unknown[]) => {
      const req = args[0] as PendingPermission;
      if (req && req.requestId) {
        setPendingPermission(req);
      }
    };
    window.zio.on('permission:request', listener);
    return () => window.zio.off('permission:request', listener);
  }, []);

  // While the mode picker is visible, detach ALL native views (tab views AND
  // the dashboard view) via the ref-counted chrome overlay. Native views sit
  // ABOVE the renderer DOM, so a lingering view would otherwise cover the
  // picker cards and silently swallow clicks.
  const modePickerWasOpen = useRef(false);
  useEffect(() => {
    if (showModePicker) {
      modePickerWasOpen.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (modePickerWasOpen.current) {
      modePickerWasOpen.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [showModePicker]);

  // While the auth modal is open, detach ALL native views (tab views AND the
  // dashboard view) via the ref-counted chrome overlay. A native view sitting
  // above the DOM would cover/clip the modal (in dashboard and split modes the
  // dashboard view otherwise stays on top of it), keep keyboard focus, and
  // swallow typing into the modal's inputs. Releasing the overlay re-applies
  // the current mode in main, which reattaches and re-bounds everything.
  const authModalWasOpen = useRef(false);
  useEffect(() => {
    if (authModalOpen) {
      authModalWasOpen.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (authModalWasOpen.current) {
      authModalWasOpen.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [authModalOpen]);

  const handlePickMode = useCallback((picked: WindowMode) => {
    localStorage.setItem(FIRST_LAUNCH_KEY, '1');
    setShowModePicker(false);
    // setMode always round-trips to the main process, which re-applies view
    // bounds — re-attaching the active tab view hidden while the picker was up.
    void setMode(picked);
  }, [setMode]);

  // Close find bar when switching away from browser mode
  useEffect(() => {
    if (mode !== 'browser' && findOpen) {
      closeFind(activeTabId);
    }
  }, [mode, findOpen, activeTabId, closeFind]);

  // Keyboard shortcuts: Ctrl/Cmd+Shift+A → tab search, Ctrl/Cmd+Shift+T → reopen closed tab
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const ctrl = e.ctrlKey || e.metaKey;
      if (!ctrl) return;

      if (e.shiftKey && (e.key === 'A' || e.key === 'a')) {
        e.preventDefault();
        setTabSearchOpen(v => !v);
        return;
      }

      if (e.shiftKey && (e.key === 'T' || e.key === 't')) {
        e.preventDefault();
        void reopenClosedTab();
        return;
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [reopenClosedTab]);

  // Listen for main-process tab:search-open event (sent from menu shortcut)
  useEffect(() => {
    const listener = () => setTabSearchOpen(true);
    window.zio.on('tab:search-open', listener);
    return () => window.zio.off('tab:search-open', listener);
  }, []);

  // ── Command palette global shortcut ───────────────────────────────────────

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      const isMod = e.ctrlKey || e.metaKey;
      if (isMod && e.key === 'k') {
        e.preventDefault();
        setPaletteOpen(prev => !prev);
      }
      if (e.key === 'Escape' && paletteOpen) {
        setPaletteOpen(false);
      }
    };

    // Also listen for the main-process menu shortcuts
    const handleIpcOpen = () => setPaletteOpen(true);
    const handleSettingsOpen = () => setSettingsOpen(true);
    window.zio.on('palette:open', handleIpcOpen);
    window.zio.on('settings:open', handleSettingsOpen);
    document.addEventListener('keydown', handleKeyDown);

    // Listen for the shortcuts-open custom event dispatched by the palette's
    // "Keyboard Shortcuts" command so we can re-open in shortcuts view
    const handleShortcutsOpen = () => setPaletteOpen(true);
    document.addEventListener('zio:shortcuts-open', handleShortcutsOpen);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      window.zio.off('palette:open', handleIpcOpen);
      window.zio.off('settings:open', handleSettingsOpen);
      document.removeEventListener('zio:shortcuts-open', handleShortcutsOpen);
    };
  }, [paletteOpen]);

  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const activeTabMode = normalizeTabMode(activeTab?.mode) ?? 'browser';
  // Only show the New Tab page when the tab actually shows its browser pane.
  const showNewTab =
    (!activeTab || activeTab.url === '' || activeTab.url === 'about:newtab') &&
    (!activeTab || tabModeIncludes(activeTabMode, 'browser'));
  // Renderer-drawn internal About pages (native view stays detached, like New Tab).
  const aboutPage: 'sayzio' | 'zio' | null =
    activeTab && tabModeIncludes(activeTabMode, 'browser')
      ? activeTab.url === 'about:sayzio' ? 'sayzio'
        : activeTab.url === 'about:zio' ? 'zio'
          : null
      : null;

  // ── Zio panel presentation flags (browser mode) ───────────────────────────
  // A tab in "Ask Zio + Website" split mode forces the docked Zio panel open.
  const activeTabZioSplit = !isPrivate && tabModeIncludes(activeTabMode, 'zio');
  // While the Settings panel is open it owns the content area — hiding the
  // docked Zio panel prevents the two DOM surfaces from fighting over it
  // (the old behavior left Settings squeezed/hidden in split-view tabs).
  const showDockedPanel = ((zioPanelOpen && zioPanelDocked && !isPrivate) || activeTabZioSplit) && !settingsOpen;
  const showOverlayPanel = zioPanelOpen && !zioPanelDocked && !isPrivate && !activeTabZioSplit && !settingsOpen;

  // ── Tab split (two native panes) divider ──────────────────────────────────
  const activeTabPanes = parseTabMode(activeTabMode);
  const activeTabTwoNativePanes = !!activeTab && activeTabPanes.right !== null && activeTabPanes.right !== 'zio';
  const activeTabSplitRatio =
    tabDragRatio ??
    (typeof activeTab?.splitRatio === 'number' ? activeTab.splitRatio : TAB_SPLIT_RATIO);

  // DOM panels rendered in the content area (settings, reading list, site
  // settings, the floating Zio card) sit BELOW the native WebContentsViews,
  // so they'd be invisible/unclickable over a loaded page. Hold the
  // ref-counted chrome overlay while any of them is open.
  useChromeOverlay(settingsOpen);
  useChromeOverlay(readingListOpen);
  useChromeOverlay(dialerPanelOpen);
  useChromeOverlay(siteSettingsOpen);
  useChromeOverlay(showOverlayPanel);

  // Tell the main process when the DOCKED Zio panel is actually visible so it
  // reserves the right-hand strip only then (a reserved strip with no panel
  // rendered — or a panel with no reserved strip — looks broken and swallows
  // clicks).
  const dockedZioVisible = mode === 'browser' && showDockedPanel;
  useEffect(() => {
    void window.zio.window.setZioPanelVisible(dockedZioVisible);
  }, [dockedZioVisible]);

  const handleToggleZio = useCallback(() => {
    // Zio AI panel is disabled in private windows.
    if (isPrivate) return;
    if (!user) {
      setAuthModalOpen(true);
      return;
    }
    setZioPanelOpen(prev => !prev);
    setReadingListOpen(false);
  }, [user, isPrivate]);

  // ── Screenshot handler ────────────────────────────────────────────────────
  const handleScreenshot = useCallback(async (fullPage: boolean) => {
    if (!activeTabId || screenshotCapturing) return;
    setScreenshotCapturing(true);
    try {
      const dataUrl = await window.zio.screenshot.capture(activeTabId, fullPage);
      if (!dataUrl) return;
      const tab = activeTabId ? tabs[activeTabId] : null;
      setScreenshotData({
        dataUrl,
        pageTitle: tab?.title ?? '',
        pageUrl: tab?.url ?? '',
      });
    } finally {
      setScreenshotCapturing(false);
    }
  }, [activeTabId, screenshotCapturing, tabs]);

  const handleToggleReadingList = useCallback(() => {
    setReadingListOpen(prev => !prev);
    setZioPanelOpen(false);
    setDialerPanelOpen(false);
  }, []);

  const handleToggleDialer = useCallback(() => {
    // The Dialer pane needs an account (search + phone handoff are per-user).
    if (isPrivate) return;
    if (!user) {
      setAuthModalOpen(true);
      return;
    }
    setDialerPanelOpen(prev => !prev);
    setReadingListOpen(false);
  }, [user, isPrivate]);

  // ── Zio panel divider drag (browser mode, docked) ─────────────────────────
  // Native views swallow mouse events the moment the cursor leaves the thin
  // divider, so — like the tab-split divider below — hold the chrome overlay
  // for the duration of the drag (views detach, snapshot backdrop shows) and
  // release it on mouseup. Width updates live so the panel tracks the cursor.
  const handleDividerMouseDown = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    isDraggingRef.current = true;
    void window.zio.window.setChromeOverlay(true);

    const onMove = (ev: MouseEvent) => {
      if (!isDraggingRef.current || !containerRef.current) return;
      const rect = containerRef.current.getBoundingClientRect();
      // The divider sits between (content area) and (Zio panel).
      // Panel is on the right; its width = rect.right - ev.clientX.
      const newWidth = rect.right - ev.clientX - ZIO_PANEL_DIVIDER_WIDTH;
      const clamped = Math.max(MIN_ZIO_PANEL_WIDTH, Math.min(MAX_ZIO_PANEL_WIDTH, newWidth));
      void setZioPanelWidth(clamped);
    };

    const onUp = () => {
      isDraggingRef.current = false;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      void window.zio.window.setChromeOverlay(false);
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }, [setZioPanelWidth]);

  // ── Tab split divider drag (two native panes) ─────────────────────────────
  // Native views swallow mouse events, so during a drag we hold the chrome
  // overlay (views detach, snapshots show) and commit the ratio on mouseup.
  const handleTabSplitDividerMouseDown = useCallback((e: React.MouseEvent) => {
    if (!activeTabId) return;
    e.preventDefault();
    void window.zio.window.setChromeOverlay(true);
    const tabId = activeTabId;

    const onMove = (ev: MouseEvent) => {
      if (!containerRef.current) return;
      const rect = containerRef.current.getBoundingClientRect();
      if (rect.width <= 0) return;
      const ratio = Math.min(
        MAX_TAB_SPLIT_RATIO,
        Math.max(MIN_TAB_SPLIT_RATIO, (ev.clientX - rect.left) / rect.width),
      );
      tabDragRatioRef.current = ratio;
      setTabDragRatio(ratio);
    };

    const onUp = () => {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      const ratio = tabDragRatioRef.current;
      tabDragRatioRef.current = null;
      setTabDragRatio(null);
      if (ratio !== null) {
        void window.zio.tabs.setSplitRatio(tabId, ratio);
      }
      void window.zio.window.setChromeOverlay(false);
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }, [activeTabId]);

  const handleOpenDeviceLab = useCallback(() => {
    if (!user) {
      setAuthModalOpen(true);
      return;
    }
    setDeviceLabUrl(undefined);
    setDeviceLabOpen(true);
  }, [user]);

  const handleCloseDeviceLab = useCallback(() => {
    setDeviceLabOpen(false);
    setDeviceLabUrl(undefined);
  }, []);

  // Context menu → "Preview in Device Lab" on any page URL
  useEffect(() => {
    const onPreviewUrl = (...args: unknown[]) => {
      const url = typeof args[0] === 'string' ? args[0] : '';
      if (!url) return;
      setDeviceLabUrl(url);
      setDeviceLabOpen(true);
    };
    window.zio.on('device-lab:preview-url', onPreviewUrl);
    return () => window.zio.off('device-lab:preview-url', onPreviewUrl);
  }, []);

  // Static page snapshots shown while a chrome menu holds native views
  // detached, so the page doesn't visually vanish behind an open dropdown.
  // Split-view tabs produce one snapshot PER pane.
  const [overlayBackdrop, setOverlayBackdrop] = useState<Array<{
    dataUrl: string;
    bounds: { x: number; y: number; width: number; height: number };
  }> | null>(null);
  useEffect(() => {
    const onBackdrop = (payload: unknown) => {
      if (Array.isArray(payload)) {
        const items = payload.filter(
          (p): p is { dataUrl: string; bounds: { x: number; y: number; width: number; height: number } } =>
            !!p && typeof p === 'object' && typeof (p as { dataUrl?: unknown }).dataUrl === 'string',
        );
        setOverlayBackdrop(items.length > 0 ? items : null);
      } else {
        setOverlayBackdrop(null);
      }
    };
    window.zio.on('chrome-overlay:backdrop', onBackdrop);
    return () => window.zio.off('chrome-overlay:backdrop', onBackdrop);
  }, []);

  const handleOpenTabSearch = useCallback(() => {
    setTabSearchOpen(true);
  }, []);

  // Current origin for the site settings panel
  const currentOrigin = (() => {
    try {
      const url = activeTab?.url ?? '';
      if (!url || url === 'about:newtab') return null;
      return new URL(url).origin;
    } catch {
      return null;
    }
  })();

  // Show mode picker before content is ready
  if (!isInitialized) {
    return <div style={{ width: '100%', height: '100%', background: isPrivate ? '#0d0d1a' : 'var(--color-bg)' }} />;
  }

  if (showModePicker) {
    return <ModePicker defaultMode={mode} onPick={handlePickMode} />;
  }

  // Static snapshot of the page shown while a chrome menu detaches native
  // views — rendered in ALL window modes (browser, split, dashboard).
  const overlayBackdropImg = overlayBackdrop ? (
    <>
      {overlayBackdrop.map((snap, i) => (
        <img
          key={i}
          src={snap.dataUrl}
          alt=""
          aria-hidden="true"
          style={{
            position: 'fixed',
            left: snap.bounds.x,
            top: snap.bounds.y,
            width: snap.bounds.width,
            height: snap.bounds.height,
            objectFit: 'cover',
            zIndex: 0,
            pointerEvents: 'none',
          }}
        />
      ))}
    </>
  ) : null;

  // ── Dashboard mode ────────────────────────────────────────────────────────
  // Private windows never show dashboard mode.
  if (mode === 'dashboard' && !isPrivate) {
    return (
      <>
        {overlayBackdropImg}
        <DashboardLayout
          mode={mode}
          onSetMode={(m) => void setMode(m)}
          authModalOpen={authModalOpen}
          onOpenAuth={() => setAuthModalOpen(true)}
          onCloseAuth={() => setAuthModalOpen(false)}
        />
        {deviceLabOpen && <DeviceLab onClose={handleCloseDeviceLab} initialUrl={deviceLabUrl} />}
        {pendingPermission && (
          <PermissionPrompt
            request={pendingPermission}
            onDismiss={() => setPendingPermission(null)}
          />
        )}
      </>
    );
  }

  // ── Split mode ────────────────────────────────────────────────────────────
  if (mode === 'split' && !isPrivate) {
    return (
      <>
        {overlayBackdropImg}
        <SplitLayout
          mode={mode}
          splitRatio={splitRatio}
          onSetMode={(m) => void setMode(m)}
          onSetSplitRatio={(r) => void setSplitRatio(r)}
          authModalOpen={authModalOpen}
          onOpenAuth={() => setAuthModalOpen(true)}
          onCloseAuth={() => setAuthModalOpen(false)}
        />
        {deviceLabOpen && <DeviceLab onClose={handleCloseDeviceLab} initialUrl={deviceLabUrl} />}
        {pendingPermission && (
          <PermissionPrompt
            request={pendingPermission}
            onDismiss={() => setPendingPermission(null)}
          />
        )}
      </>
    );
  }

  // ── Browser mode ──────────────────────────────────────────────────────────
  // Layout:
  //   - Docked Zio panel: drag-resizable right pane, tab views resized by main process
  //   - Overlay Zio panel: floating card over the page, tab views full-width
  // ── Browser mode (default; always used for private windows) ───────────────
  // Zio panel presentation flags are computed above the early returns.

  return (
    <div style={{
      display: 'flex',
      flexDirection: 'column',
      height: '100%',
      background: isPrivate ? '#0d0d1a' : undefined,
    }}>
      {isPrivate && window.zio.platform !== 'darwin' && (
        // Windows/Linux private windows are frameless (titleBarStyle: 'hidden'
        // + titleBarOverlay in the main process). This row is the drag region;
        // the OS draws the window controls over its right edge.
        <div
          style={{
            height: 36,
            flexShrink: 0,
            display: 'flex',
            alignItems: 'center',
            paddingLeft: 12,
            // Leave room under the native titleBarOverlay window controls
            paddingRight: 150,
            background: '#0d0d1a',
            borderBottom: '1px solid rgba(147, 197, 253, 0.15)',
            WebkitAppRegion: 'drag',
            userSelect: 'none',
          } as React.CSSProperties}
        >
          <span style={{ fontSize: 12, fontWeight: 600, color: '#93c5fd' }}>
            🔒 Private – Zio Browser
          </span>
        </div>
      )}
      <ChromeBar
        zioPanelOpen={zioPanelOpen}
        onToggleZio={handleToggleZio}
        onOpenAuth={() => setAuthModalOpen(true)}
        onOpenTabSearch={handleOpenTabSearch}
        downloadsPanelOpen={downloadsPanelOpen}
        onToggleDownloads={handleToggleDownloads}
        activeDownloadCount={activeDownloadCount}
        isPrivate={isPrivate}
        onOpenDeviceLab={handleOpenDeviceLab}
        onScreenshot={handleScreenshot}
        screenshotCapturing={screenshotCapturing}
        onOpenSiteSettings={() => setSiteSettingsOpen(true)}
        readingListOpen={readingListOpen}
        onToggleReadingList={handleToggleReadingList}
        dialerPanelOpen={dialerPanelOpen}
        onToggleDialer={handleToggleDialer}
        onOpenSettings={() => setSettingsOpen(prev => !prev)}
        settingsOpen={settingsOpen}
      />

      {/* Content area */}
      {overlayBackdropImg}

      <div ref={containerRef} style={{ flex: 1, display: 'flex', overflow: 'hidden', position: 'relative' }}>

        {/* Web content / new tab page (left side when docked, full-width when overlay). */}
        <div style={{
          flex: 1,
          display: 'flex',
          overflow: 'hidden',
          position: 'relative',
        }}>
          {showNewTab && (
            <div style={{ flex: 1, display: 'flex', alignItems: 'stretch' }}>
              <NewTabPage
                isPrivate={isPrivate}
                onNavigate={(url) => {
                  if (activeTabId) {
                    void window.zio.tabs.navigate(activeTabId, url);
                  }
                }}
              />
            </div>
          )}
          {aboutPage && (
            <div style={{ flex: 1, display: 'flex', alignItems: 'stretch' }}>
              <AboutPage
                page={aboutPage}
                onNavigate={(url) => {
                  if (activeTabId) {
                    void window.zio.tabs.navigate(activeTabId, url);
                  }
                }}
              />
            </div>
          )}
        </div>

        {/* ── Dual address bars for Website + Website (one per pane) ────── */}
        {activeTabMode === 'browser+browser' && activeTab && activeTabId && !settingsOpen && (
          <SplitUrlBars
            tabId={activeTabId}
            primaryUrl={activeTab.primaryUrl ?? activeTab.url ?? ''}
            secondUrl={activeTab.secondUrl ?? ''}
            splitRatio={activeTabSplitRatio}
          />
        )}

        {/* ── Tab split divider (two native panes, e.g. Website+Website) ──
            Sits over the gap the main process leaves between the two native
            views; dragging it resizes the split (ratio persisted per tab). */}
        {activeTabTwoNativePanes && !settingsOpen && (
          <div
            onMouseDown={handleTabSplitDividerMouseDown}
            style={{
              position: 'absolute',
              top: activeTabMode === 'browser+browser' ? SPLIT_URL_BAR_HEIGHT : 0,
              bottom: 0,
              left: `calc(${activeTabSplitRatio * 100}% - ${Math.ceil(TAB_SPLIT_DIVIDER_WIDTH / 2)}px)`,
              width: TAB_SPLIT_DIVIDER_WIDTH,
              cursor: 'col-resize',
              background: 'var(--color-border)',
              zIndex: 10,
            }}
            title="Drag to resize split"
          />
        )}

        {/* ── Docked Zio panel (push layout) ─────────────────────────────── */}
        {showDockedPanel && (
          <>
            {/* Drag divider — visible rail with a centered grip, plus an
                invisible widened hit area so it's easy to grab. */}
            <div
              onMouseDown={handleDividerMouseDown}
              style={{
                width: ZIO_PANEL_DIVIDER_WIDTH,
                flexShrink: 0,
                background: 'var(--color-border)',
                cursor: 'col-resize',
                transition: 'background 0.15s',
                position: 'relative',
                zIndex: 10,
              }}
              onMouseEnter={(e) => { (e.currentTarget as HTMLDivElement).style.background = 'var(--color-primary, #6366f1)'; }}
              onMouseLeave={(e) => { if (!isDraggingRef.current) (e.currentTarget as HTMLDivElement).style.background = 'var(--color-border)'; }}
              title="Drag to resize Zio panel"
            >
              {/* Widened invisible hit zone (±5px each side) */}
              <div style={{ position: 'absolute', top: 0, bottom: 0, left: -5, right: -5, cursor: 'col-resize' }} />
              {/* Grip dots */}
              <div style={{
                position: 'absolute',
                top: '50%',
                left: '50%',
                transform: 'translate(-50%, -50%)',
                display: 'flex',
                flexDirection: 'column',
                gap: 3,
                pointerEvents: 'none',
              }}>
                {[0, 1, 2].map(i => (
                  <span key={i} style={{ width: 3, height: 3, borderRadius: '50%', background: 'var(--color-text-muted, #888)' }} />
                ))}
              </div>
            </div>
            <ZioPanel
              pageContext={activeTab ? { url: activeTab.url, title: activeTab.title } : null}
              onClose={() => {
                setZioPanelOpen(false);
                // Closing the panel while the tab includes the Ask Zio pane
                // drops that pane (keeping whatever else the tab showed).
                if (activeTabZioSplit && activeTabId) {
                  void setTabMode(activeTabId, tabModeWithout(activeTabMode, 'zio'));
                }
              }}
              presentation="docked"
              panelWidth={zioPanelWidth}
              onSetDocked={(d) => void setZioPanelDocked(d)}
            />
          </>
        )}

        {/* ── Overlay Zio panel (floating card) ──────────────────────────── */}
        {showOverlayPanel && (
          <ZioPanel
            pageContext={activeTab ? { url: activeTab.url, title: activeTab.title } : null}
            onClose={() => setZioPanelOpen(false)}
            presentation="overlay"
            panelWidth={zioPanelWidth}
            onSetDocked={(d) => void setZioPanelDocked(d)}
          />
        )}

        {settingsOpen && (
          <SettingsPanel onClose={() => setSettingsOpen(false)} />
        )}

        {readingListOpen && (
          <ReadingListPanel
            onClose={() => setReadingListOpen(false)}
            onNavigate={(url) => {
              if (activeTabId) {
                void window.zio.tabs.navigate(activeTabId, url);
              }
            }}
          />
        )}

        {dialerPanelOpen && !isPrivate && (
          <DialerPanel
            onClose={() => setDialerPanelOpen(false)}
            onNavigate={(url) => {
              if (activeTabId) {
                void window.zio.tabs.navigate(activeTabId, url);
              }
            }}
          />
        )}

        {/* Find bar — overlays the top-right corner of the browser content */}
        {findOpen && mode === 'browser' && (
          <FindBar activeTabId={activeTabId} />
        )}
      </div>

      {/* Downloads panel — anchored to top-right of the chrome bar */}
      {downloadsPanelOpen && (
        <div style={{
          position: 'fixed',
          top: 'var(--chrome-height)',
          right: 12,
          zIndex: 200,
        }}>
          <DownloadsPanel onClose={closeDownloadsPanel} />
        </div>
      )}

      {/* Download started toast — bottom-right, non-blocking */}
      <DownloadToast onOpenDownloads={openDownloadsPanel} />

      {/* Generic message toast (main-process notices) — bottom-center */}
      <MessageToast />

      {authModalOpen && !isPrivate && (
        <AuthModal onClose={() => setAuthModalOpen(false)} />
      )}

      {/* Device Lab overlays the entire window */}
      {deviceLabOpen && <DeviceLab onClose={handleCloseDeviceLab} initialUrl={deviceLabUrl} />}

      {/* Tab search popover */}
      {tabSearchOpen && (
        <TabSearchPopover onClose={() => setTabSearchOpen(false)} />
      )}

      {/* Clear browsing data — Ctrl+Shift+Delete shortcut */}
      {clearDataShortcut && (
        <ClearDataDialog
          onClose={() => setClearDataShortcut(false)}
          onCleared={() => {
            // Nothing extra needed here — the dialog's own success state is shown
          }}
        />
      )}

      {/* Command Palette — global Ctrl/Cmd+K overlay */}
      {paletteOpen && (
        <CommandPalette
          onClose={() => setPaletteOpen(false)}
          tabs={tabs}
          tabOrder={tabOrder}
          activeTabId={activeTabId}
          user={user}
          mode={mode}
          isPrivate={isPrivate}
          onSetMode={(m) => { setPaletteOpen(false); void setMode(m as WindowMode); }}
        />
      )}

      {/* Screenshot preview sheet */}
      {screenshotData && (
        <ScreenshotSheet
          dataUrl={screenshotData.dataUrl}
          pageTitle={screenshotData.pageTitle}
          pageUrl={screenshotData.pageUrl}
          onClose={() => setScreenshotData(null)}
          onOpenAuth={() => { setScreenshotData(null); setAuthModalOpen(true); }}
        />
      )}

      {/* Permission prompt — shown over everything */}
      {pendingPermission && (
        <PermissionPrompt
          request={pendingPermission}
          onDismiss={() => setPendingPermission(null)}
        />
      )}

      {/* Site settings panel */}
      {siteSettingsOpen && (
        <SiteSettingsPanel
          currentOrigin={currentOrigin}
          onClose={() => setSiteSettingsOpen(false)}
        />
      )}
    </div>
  );
}
