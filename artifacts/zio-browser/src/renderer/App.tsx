import { useState, useEffect, useCallback, useRef } from 'react';
import { ChromeBar } from './components/ChromeBar';
import { ZioPanel } from './components/ZioPanel';
import { NewTabPage } from './components/NewTabPage';
import { AuthModal } from './components/AuthModal';
import { ModePicker } from './components/ModePicker';
import { DashboardLayout } from './components/DashboardLayout';
import { SplitLayout } from './components/SplitLayout';
import { FindBar } from './components/FindBar';
import { DownloadsPanel } from './components/DownloadsPanel';
import { DownloadToast } from './components/DownloadToast';
import { DeviceLab } from './components/DeviceLab';
import { TabSearchPopover } from './components/TabSearchPopover';
import { ClearDataDialog } from './components/ClearDataDialog';
import { CommandPalette } from './components/CommandPalette';
import { ScreenshotSheet } from './components/ScreenshotSheet';
import { PermissionPrompt } from './components/PermissionPrompt';
import type { PendingPermission } from './components/PermissionPrompt';
import { SiteSettingsPanel } from './components/SiteSettingsPanel';
import { ReadingListPanel } from './components/ReadingListPanel';
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
} from '../shared/window-mode';

const FIRST_LAUNCH_KEY = 'zio_mode_picker_shown';

export default function App() {
  const [zioPanelOpen, setZioPanelOpen] = useState(false);
  const [readingListOpen, setReadingListOpen] = useState(false);
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const [showModePicker, setShowModePicker] = useState(false);
  const [isPrivate, setIsPrivate] = useState(false);
  const [deviceLabOpen, setDeviceLabOpen] = useState(false);
  const [deviceLabUrl, setDeviceLabUrl] = useState<string | undefined>(undefined);
  const [tabSearchOpen, setTabSearchOpen] = useState(false);
  const [clearDataShortcut, setClearDataShortcut] = useState(false);
  const [paletteOpen, setPaletteOpen] = useState(false);

  // ── Screenshot state ──────────────────────────────────────────────────────
  const [screenshotCapturing, setScreenshotCapturing] = useState(false);
  const [screenshotData, setScreenshotData] = useState<{
    dataUrl: string;
    pageTitle: string;
    pageUrl: string;
  } | null>(null);
  const [siteSettingsOpen, setSiteSettingsOpen] = useState(false);
  const [pendingPermission, setPendingPermission] = useState<PendingPermission | null>(null);
  const { tabs, tabOrder, activeTabId, initTabs, reopenClosedTab } = useTabStore();
  const { init: initAuth, user, token } = useAuthStore();
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

  // While the mode picker is visible, detach all tab WebContentsViews.
  // Native views sit ABOVE the renderer DOM, so a restored tab view would
  // otherwise silently swallow clicks on the picker cards.
  useEffect(() => {
    if (showModePicker) {
      void window.zio.tabs.hideAll();
    }
  }, [showModePicker]);

  // While the auth modal is open, detach all tab WebContentsViews for the same
  // reason: a native tab view sitting above the DOM would keep keyboard focus
  // and swallow typing into the modal's inputs. When the modal closes, re-apply
  // the current mode so the active tab view (and its bounds) are restored.
  const authModalWasOpen = useRef(false);
  useEffect(() => {
    if (authModalOpen) {
      authModalWasOpen.current = true;
      void window.zio.tabs.hideAll();
    } else if (authModalWasOpen.current) {
      authModalWasOpen.current = false;
      if (!showModePicker) {
        // setMode round-trips to main, which re-applies view bounds.
        void setMode(mode);
      }
    }
  }, [authModalOpen, showModePicker, mode, setMode]);

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

    // Also listen for the main-process menu shortcut
    const handleIpcOpen = () => setPaletteOpen(true);
    window.zio.on('palette:open', handleIpcOpen);
    document.addEventListener('keydown', handleKeyDown);

    // Listen for the shortcuts-open custom event dispatched by the palette's
    // "Keyboard Shortcuts" command so we can re-open in shortcuts view
    const handleShortcutsOpen = () => setPaletteOpen(true);
    document.addEventListener('zio:shortcuts-open', handleShortcutsOpen);

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      window.zio.off('palette:open', handleIpcOpen);
      document.removeEventListener('zio:shortcuts-open', handleShortcutsOpen);
    };
  }, [paletteOpen]);

  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const showNewTab = !activeTab || activeTab.url === '' || activeTab.url === 'about:newtab';

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
  }, []);

  // ── Zio panel divider drag (browser mode, docked) ─────────────────────────
  const handleDividerMouseDown = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    isDraggingRef.current = true;

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
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }, [setZioPanelWidth]);

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

  // ── Dashboard mode ────────────────────────────────────────────────────────
  // Private windows never show dashboard mode.
  if (mode === 'dashboard' && !isPrivate) {
    return (
      <>
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
  const showDockedPanel = zioPanelOpen && zioPanelDocked && !isPrivate;
  const showOverlayPanel = zioPanelOpen && !zioPanelDocked && !isPrivate;

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
            borderBottom: '1px solid rgba(201, 179, 255, 0.15)',
            WebkitAppRegion: 'drag',
            userSelect: 'none',
          } as React.CSSProperties}
        >
          <span style={{ fontSize: 12, fontWeight: 600, color: '#c9b3ff' }}>
            🔒 Private – Zio Browser
          </span>
        </div>
      )}
      <ChromeBar
        zioPanelOpen={zioPanelOpen}
        onToggleZio={handleToggleZio}
        onOpenAuth={() => setAuthModalOpen(true)}
        onOpenTabSearch={handleOpenTabSearch}
        showModeSwitcher={!isPrivate}
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
      />

      {/* Content area */}
      <div ref={containerRef} style={{ flex: 1, display: 'flex', overflow: 'hidden', position: 'relative' }}>

        {/* Web content / new tab page (left side when docked, full-width when overlay) */}
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
        </div>

        {/* ── Docked Zio panel (push layout) ─────────────────────────────── */}
        {showDockedPanel && (
          <>
            {/* Drag divider */}
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
              title="Drag to resize Zio panel"
            />
            <ZioPanel
              pageContext={activeTab ? { url: activeTab.url, title: activeTab.title } : null}
              onClose={() => setZioPanelOpen(false)}
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
