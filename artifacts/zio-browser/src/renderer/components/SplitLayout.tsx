/**
 * SplitLayout — renders the split-mode window chrome.
 *
 * Layout:
 *   ┌──────────────────┬─┬──────────────────────────────────┐
 *   │  Left header 44px│ │  Right browser chrome 72px       │
 *   ├──────────────────┤ ├──────────────────────────────────┤
 *   │  (Dashboard or   │◀│  Browser tab content             │
 *   │   ZioPanel HTML) │▶│  (WebContentsView, main process) │
 *   └──────────────────┴─┴──────────────────────────────────┘
 *
 * When "Dashboard" left-tab is active the left content area is transparent,
 * revealing the dashboard WebContentsView (positioned by the main process).
 * When "Zio" left-tab is active the ZioPanel React component fills that area
 * with an opaque background, covering the (still positioned) dashboard view.
 */
import { useState, useCallback, useRef, useEffect } from 'react';
import { ChromeBar } from './ChromeBar';
import { ZioPanel } from './ZioPanel';
import { AuthModal } from './AuthModal';
import { ModeSwitcher } from './ModeSwitcher';
import { FindBar } from './FindBar';
import { DownloadsPanel } from './DownloadsPanel';
import { AccountButton } from './AccountButton';
import { NewTabButton } from './NewTabButton';
import { useTabStore } from '../store/tab-store';
import { useFindStore } from '../store/find-store';
import { useDownloadStore } from '../store/download-store';
import type { WindowMode } from '../../shared/window-mode';
import { SPLIT_DIVIDER_WIDTH, MIN_SPLIT_RATIO, MAX_SPLIT_RATIO } from '../../shared/window-mode';
import zioMascot from '../assets/zio-mascot.png';

const DASHBOARD_HEADER_HEIGHT = 44;

type LeftPane = 'dashboard' | 'zio';

interface Props {
  mode: WindowMode;
  splitRatio: number;
  onSetMode: (mode: WindowMode) => void;
  onSetSplitRatio: (ratio: number) => void;
  authModalOpen: boolean;
  onOpenAuth: () => void;
  onCloseAuth: () => void;
}

export function SplitLayout({
  mode, splitRatio, onSetMode, onSetSplitRatio,
  authModalOpen, onOpenAuth, onCloseAuth,
}: Props) {
  const { tabs, activeTabId } = useTabStore();
  const { isOpen: findOpen } = useFindStore();
  const {
    activeDownloadCount,
    panelOpen: downloadsPanelOpen,
    togglePanel: toggleDownloadsPanel,
    closePanel: closeDownloadsPanel,
  } = useDownloadStore();
  const [leftPane, setLeftPane] = useState<LeftPane>('dashboard');
  const containerRef = useRef<HTMLDivElement>(null);
  const isDragging = useRef(false);

  const activeTab = activeTabId ? tabs[activeTabId] : null;

  // Drag-to-resize the divider
  const handleDividerMouseDown = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    isDragging.current = true;

    const onMove = (ev: MouseEvent) => {
      if (!isDragging.current || !containerRef.current) return;
      const rect = containerRef.current.getBoundingClientRect();
      const ratio = (ev.clientX - rect.left) / rect.width;
      const clamped = Math.max(MIN_SPLIT_RATIO, Math.min(MAX_SPLIT_RATIO, ratio));
      onSetSplitRatio(clamped);
    };

    const onUp = () => {
      isDragging.current = false;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
    };

    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }, [onSetSplitRatio]);

  // Compute left pane width from ratio
  const leftWidth = `${splitRatio * 100}%`;
  const rightWidth = `calc(${(1 - splitRatio) * 100}% - ${SPLIT_DIVIDER_WIDTH}px)`;

  return (
    <div
      ref={containerRef}
      style={{ display: 'flex', height: '100%', overflow: 'hidden' }}
    >
      {/* ── Left pane ── */}
      <div style={{
        width: leftWidth,
        flexShrink: 0,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
      }}>
        {/* Left header */}
        <div style={{
          height: DASHBOARD_HEADER_HEIGHT,
          flexShrink: 0,
          background: 'var(--color-bg-surface)',
          borderBottom: '1px solid var(--color-border)',
          borderRight: '1px solid var(--color-border)',
          display: 'flex',
          alignItems: 'center',
          gap: 6,
          padding: `0 10px 0 ${window.zio.platform === 'darwin' ? '80px' : '10px'}`,
          WebkitAppRegion: 'drag',
        } as React.CSSProperties}>

          {/* Left-pane tab switcher */}
          <div style={{
            display: 'flex',
            gap: 2,
            background: 'var(--color-bg)',
            borderRadius: 8,
            padding: 3,
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}>
            {(['dashboard', 'zio'] as LeftPane[]).map(tab => (
              <button
                key={tab}
                onClick={() => setLeftPane(tab)}
                style={{
                  padding: '3px 10px',
                  borderRadius: 6,
                  fontSize: 12,
                  fontWeight: leftPane === tab ? 600 : 400,
                  background: leftPane === tab ? 'var(--color-bg-surface)' : 'transparent',
                  color: leftPane === tab ? 'var(--color-text)' : 'var(--color-text-muted)',
                  border: leftPane === tab ? '1px solid var(--color-border)' : '1px solid transparent',
                  cursor: 'pointer',
                  transition: 'all 0.12s',
                }}
              >
                {tab === 'dashboard' ? '⊡ Dashboard' : (
                  <><img src={zioMascot} alt="" aria-hidden="true" style={{ width: 14, height: 14, objectFit: 'contain', verticalAlign: 'text-bottom', marginRight: 4 }} />Zio</>
                )}
              </button>
            ))}
          </div>

          <span style={{ flex: 1, WebkitAppRegion: 'drag' } as React.CSSProperties} />

          <div style={{ WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
            <NewTabButton currentMode={mode} onSetMode={onSetMode} />
          </div>

          <div style={{ WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
            <ModeSwitcher currentMode={mode} onSetMode={onSetMode} />
          </div>

          <AccountButton onOpenAuth={onOpenAuth} compact />
        </div>

        {/* Left pane content */}
        <div style={{ flex: 1, overflow: 'hidden', position: 'relative' }}>
          {/* When Dashboard tab: transparent → dashboard WebContentsView shows through */}
          {leftPane === 'dashboard' && (
            <div style={{ width: '100%', height: '100%' }} />
          )}

          {/* When Zio tab: ZioPanel HTML covers the area (opaque background) */}
          {leftPane === 'zio' && (
            <ZioPanel
              pageContext={activeTab ? { url: activeTab.url, title: activeTab.title } : null}
              onClose={() => setLeftPane('dashboard')}
              presentation="embedded"
            />
          )}
        </div>
      </div>

      {/* ── Divider ── */}
      <div
        onMouseDown={handleDividerMouseDown}
        style={{
          width: SPLIT_DIVIDER_WIDTH,
          flexShrink: 0,
          background: 'var(--color-border)',
          cursor: 'col-resize',
          transition: 'background 0.15s',
          position: 'relative',
          zIndex: 10,
        }}
        title="Drag to resize"
      />

      {/* ── Right pane (full browser) ── */}
      <div style={{
        width: rightWidth,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
        flexShrink: 0,
      }}>
        <ChromeBar
          zioPanelOpen={false}
          onToggleZio={() => setLeftPane('zio')}
          onOpenAuth={onOpenAuth}
          onOpenTabSearch={() => { /* tab search is available in browser mode */ }}
          downloadsPanelOpen={downloadsPanelOpen}
          onToggleDownloads={toggleDownloadsPanel}
          activeDownloadCount={activeDownloadCount}
          readingListOpen={false}
          onToggleReadingList={() => { /* reading list is not shown in split right pane */ }}
        />
        {/* Tab content area — transparent; WebContentsViews are positioned here.
            position:relative lets FindBar anchor to the top-right corner. */}
        <div style={{ flex: 1, position: 'relative' }}>
          {findOpen && (
            <FindBar activeTabId={activeTabId} />
          )}
        </div>
      </div>

      {/* Downloads panel — anchored to the top-right, below the chrome bar */}
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

      {authModalOpen && <AuthModal onClose={onCloseAuth} />}
    </div>
  );
}
