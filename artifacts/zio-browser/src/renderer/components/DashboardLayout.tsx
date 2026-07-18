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
import { useAuthStore } from '../store/auth-store';
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
  const { user } = useAuthStore();

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
        padding: `0 12px 0 ${process.platform === 'darwin' ? '80px' : '12px'}`,
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

        <div style={{ WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
          <ModeSwitcher currentMode={mode} onSetMode={onSetMode} />
        </div>

        {user ? (
          <div style={{
            width: 26,
            height: 26,
            borderRadius: '50%',
            background: 'var(--color-primary)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: 11,
            fontWeight: 700,
            color: '#fff',
            cursor: 'pointer',
            flexShrink: 0,
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties} title={user.name}>
            {(user.name ?? 'U').charAt(0).toUpperCase()}
          </div>
        ) : (
          <button
            onClick={onOpenAuth}
            style={{
              padding: '3px 10px',
              borderRadius: 10,
              background: 'var(--color-bg-elevated)',
              border: '1px solid var(--color-border)',
              fontSize: 12,
              whiteSpace: 'nowrap',
              WebkitAppRegion: 'no-drag',
            } as React.CSSProperties}
          >Sign in</button>
        )}
      </div>

      {/* The rest of the area is transparent — the dashboard WebContentsView
          is positioned here by the main process (WindowModeManager). */}
      <div style={{ flex: 1 }} />

      {authModalOpen && <AuthModal onClose={onCloseAuth} />}
    </div>
  );
}
