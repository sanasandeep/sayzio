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
import { useTabStore } from './store/tab-store';
import { useAuthStore } from './store/auth-store';
import { useModeStore } from './store/mode-store';
import { useFindStore } from './store/find-store';
import type { WindowMode } from '../shared/window-mode';
import {
  MIN_ZIO_PANEL_WIDTH,
  MAX_ZIO_PANEL_WIDTH,
  ZIO_PANEL_DIVIDER_WIDTH,
} from '../shared/window-mode';

const FIRST_LAUNCH_KEY = 'zio_mode_picker_shown';

export default function App() {
  const [zioPanelOpen, setZioPanelOpen] = useState(false);
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const [showModePicker, setShowModePicker] = useState(false);
  const [downloadsPanelOpen, setDownloadsPanelOpen] = useState(false);
  const [activeDownloadCount, setActiveDownloadCount] = useState(0);

  const { tabs, activeTabId, initTabs } = useTabStore();
  const { init: initAuth, user } = useAuthStore();
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

  useEffect(() => {
    void Promise.all([initAuth(), initTabs(), initMode()]).then(() => {
      const shown = localStorage.getItem(FIRST_LAUNCH_KEY);
      if (!shown) {
        setShowModePicker(true);
      }
    });
  }, [initAuth, initTabs, initMode]);

  // Track active download count for the chrome badge
  useEffect(() => {
    const onStarted = () => {
      setActiveDownloadCount(n => n + 1);
      // Auto-open the downloads panel when a download begins
      setDownloadsPanelOpen(true);
    };
    const onDone = () => {
      setActiveDownloadCount(n => Math.max(0, n - 1));
    };
    window.zio.on('download:started', onStarted);
    window.zio.on('download:done', onDone);
    return () => {
      window.zio.off('download:started', onStarted);
      window.zio.off('download:done', onDone);
    };
  }, []);

  const handlePickMode = useCallback((picked: WindowMode) => {
    localStorage.setItem(FIRST_LAUNCH_KEY, '1');
    setShowModePicker(false);
    void setMode(picked);
  }, [setMode]);

  // Close find bar when switching away from browser mode
  useEffect(() => {
    if (mode !== 'browser' && findOpen) {
      closeFind(activeTabId);
    }
  }, [mode, findOpen, activeTabId, closeFind]);

  const activeTab = activeTabId ? tabs[activeTabId] : null;
  const showNewTab = !activeTab || activeTab.url === '' || activeTab.url === 'about:newtab';

  const handleToggleZio = useCallback(() => {
    if (!user) {
      setAuthModalOpen(true);
      return;
    }
    setZioPanelOpen(prev => !prev);
  }, [user]);

  const handleToggleDownloads = useCallback(() => {
    setDownloadsPanelOpen(prev => !prev);
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

  // Show mode picker before content is ready
  if (!isInitialized) {
    return <div style={{ width: '100%', height: '100%', background: 'var(--color-bg)' }} />;
  }

  if (showModePicker) {
    return <ModePicker defaultMode={mode} onPick={handlePickMode} />;
  }

  // ── Dashboard mode ────────────────────────────────────────────────────────
  if (mode === 'dashboard') {
    return (
      <DashboardLayout
        mode={mode}
        onSetMode={(m) => void setMode(m)}
        authModalOpen={authModalOpen}
        onOpenAuth={() => setAuthModalOpen(true)}
        onCloseAuth={() => setAuthModalOpen(false)}
      />
    );
  }

  // ── Split mode ────────────────────────────────────────────────────────────
  if (mode === 'split') {
    return (
      <SplitLayout
        mode={mode}
        splitRatio={splitRatio}
        onSetMode={(m) => void setMode(m)}
        onSetSplitRatio={(r) => void setSplitRatio(r)}
        authModalOpen={authModalOpen}
        onOpenAuth={() => setAuthModalOpen(true)}
        onCloseAuth={() => setAuthModalOpen(false)}
      />
    );
  }

  // ── Browser mode ──────────────────────────────────────────────────────────
  // Layout:
  //   - Docked Zio panel: drag-resizable right pane, tab views resized by main process
  //   - Overlay Zio panel: floating card over the page, tab views full-width
  const showDockedPanel = zioPanelOpen && zioPanelDocked;
  const showOverlayPanel = zioPanelOpen && !zioPanelDocked;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      <ChromeBar
        zioPanelOpen={zioPanelOpen}
        onToggleZio={handleToggleZio}
        onOpenAuth={() => setAuthModalOpen(true)}
        showModeSwitcher={true}
        downloadsPanelOpen={downloadsPanelOpen}
        onToggleDownloads={handleToggleDownloads}
        activeDownloadCount={activeDownloadCount}
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
              <NewTabPage onNavigate={(url) => {
                if (activeTabId) {
                  void window.zio.tabs.navigate(activeTabId, url);
                }
              }} />
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
          <DownloadsPanel onClose={() => setDownloadsPanelOpen(false)} />
        </div>
      )}

      {authModalOpen && (
        <AuthModal onClose={() => setAuthModalOpen(false)} />
      )}
    </div>
  );
}
