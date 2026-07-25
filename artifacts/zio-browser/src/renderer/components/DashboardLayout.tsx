/**
 * DashboardLayout — renders the minimal chrome for Dashboard mode.
 *
 * In dashboard mode the Sayzio dashboard WebContentsView fills almost the
 * entire window. The renderer only supplies a 44 px header with:
 *   • traffic-light clearance (macOS)
 *   • back / reload controls for the dashboard view
 *   • mode switcher
 *   • sign-in avatar
 */
import { useCallback } from 'react';
import { ModeSwitcher } from './ModeSwitcher';
import { AuthModal } from './AuthModal';
import { DownloadsPanel } from './DownloadsPanel';
import { AccountButton } from './AccountButton';
import { NewTabButton } from './NewTabButton';
import { useDownloadStore } from '../store/download-store';
import type { WindowMode } from '../../shared/window-mode';

const DASHBOARD_HEADER_HEIGHT = 44;

interface Props {
  mode: WindowMode;
  onSetMode: (mode: WindowMode) => void;
  authModalOpen: boolean;
  onOpenAuth: () => void;
  onCloseAuth: () => void;
}

export function DashboardLayout({ mode, onSetMode, authModalOpen, onOpenAuth, onCloseAuth }: Props) {
  const {
    activeDownloadCount,
    panelOpen: downloadsPanelOpen,
    togglePanel: toggleDownloadsPanel,
    closePanel: closeDownloadsPanel,
  } = useDownloadStore();

  const handleBack = useCallback(() => {
    void window.zio.window.reloadDashboard();
  }, []);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
      {/* Minimal header */}
      <div style={{
        height: DASHBOARD_HEADER_HEIGHT,
        flexShrink: 0,
        background: 'var(--color-bg-surface)',
        borderBottom: '1px solid var(--color-border)',
        display: 'flex',
        alignItems: 'center',
        gap: 8,
        padding: `0 12px 0 ${window.zio.platform === 'darwin' ? '80px' : '12px'}`,
        WebkitAppRegion: 'drag',
      } as React.CSSProperties}>

        <button
          onClick={handleBack}
          title="Reload Dashboard"
          style={{
            padding: '3px 8px',
            borderRadius: 6,
            fontSize: 14,
            color: 'var(--color-text-muted)',
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        >↻</button>

        <span style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text-muted)', flex: 1, WebkitAppRegion: 'drag' } as React.CSSProperties}>
          Sayzio Dashboard
        </span>

        {/* Downloads button with active-count badge */}
        <button
          onClick={toggleDownloadsPanel}
          title="Downloads"
          style={{
            position: 'relative',
            padding: '3px 8px',
            borderRadius: 6,
            fontSize: 14,
            color: downloadsPanelOpen ? 'var(--color-text)' : 'var(--color-text-muted)',
            background: downloadsPanelOpen ? 'var(--color-bg-elevated)' : 'transparent',
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        >
          ⬇
          {activeDownloadCount > 0 && (
            <span style={{
              position: 'absolute',
              top: -2,
              right: -2,
              minWidth: 14,
              height: 14,
              padding: '0 3px',
              borderRadius: 7,
              background: 'var(--color-primary)',
              color: '#fff',
              fontSize: 9,
              fontWeight: 700,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}>
              {activeDownloadCount}
            </span>
          )}
        </button>

        <div style={{ WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
          <NewTabButton currentMode={mode} onSetMode={onSetMode} />
        </div>

        <div style={{ WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
          <ModeSwitcher currentMode={mode} onSetMode={onSetMode} />
        </div>

        <AccountButton onOpenAuth={onOpenAuth} />
      </div>

      {/* The rest of the area is transparent — the dashboard WebContentsView
          is positioned here by the main process (WindowModeManager). */}
      <div style={{ flex: 1 }} />

      {/* Downloads panel — anchored below the header, top-right */}
      {downloadsPanelOpen && (
        <div style={{
          position: 'fixed',
          top: DASHBOARD_HEADER_HEIGHT,
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
