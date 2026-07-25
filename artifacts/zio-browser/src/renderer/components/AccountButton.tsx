/**
 * AccountButton — the account affordance in the dashboard/split headers.
 *
 * Signed out → "Sign in" button opening the auth modal.
 * Signed in  → avatar circle with a dropdown menu (name, email, Sign out).
 *
 * The dropdown extends below the header into the region covered by native
 * WebContentsViews, so we use the chrome-overlay mechanism (hide all native
 * views while open, restore on close) — same pattern as ModeSwitcher.
 */
import { useState, useRef, useEffect } from 'react';
import { useAuthStore } from '../store/auth-store';

interface Props {
  onOpenAuth: () => void;
  /** Compact sizing for tighter headers (split mode). */
  compact?: boolean;
}

export function AccountButton({ onOpenAuth, compact = false }: Props) {
  const { user, clearAuth } = useAuthStore();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const wasOpen = useRef(false);

  useEffect(() => {
    if (open) {
      wasOpen.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (wasOpen.current) {
      wasOpen.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [open]);

  // Release the overlay if this component unmounts while its menu is open.
  useEffect(() => () => {
    if (wasOpen.current) {
      wasOpen.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, []);

  useEffect(() => {
    if (!open) return;
    function handleClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, [open]);

  if (!user) {
    return (
      <button
        onClick={onOpenAuth}
        style={{
          padding: compact ? '3px 8px' : '3px 10px',
          borderRadius: compact ? 8 : 10,
          background: 'var(--color-bg-elevated)',
          border: '1px solid var(--color-border)',
          fontSize: compact ? 11 : 12,
          whiteSpace: 'nowrap',
          WebkitAppRegion: 'no-drag',
        } as React.CSSProperties}
      >Sign in</button>
    );
  }

  const size = compact ? 24 : 26;
  const initial = (user.name ?? user.email ?? 'U').charAt(0).toUpperCase();

  return (
    <div ref={ref} style={{ position: 'relative', WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
      <button
        onClick={() => setOpen(prev => !prev)}
        title={user.name ?? 'Account'}
        style={{
          width: size,
          height: size,
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
          border: open ? '2px solid var(--color-border)' : 'none',
          padding: 0,
        }}
      >
        {initial}
      </button>

      {open && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 6px)',
          right: 0,
          width: 220,
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 12,
          boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
          zIndex: 1000,
          overflow: 'hidden',
        }}>
          <div style={{ padding: '12px 14px', borderBottom: '1px solid var(--color-border)' }}>
            <div style={{
              fontSize: 13,
              fontWeight: 600,
              color: 'var(--color-text)',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              whiteSpace: 'nowrap',
            }}>{user.name ?? 'Account'}</div>
            {user.email && (
              <div style={{
                fontSize: 11,
                color: 'var(--color-text-muted)',
                marginTop: 2,
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
              }}>{user.email}</div>
            )}
          </div>
          <button
            onClick={() => { setOpen(false); void clearAuth(); }}
            style={{
              width: '100%',
              display: 'flex',
              alignItems: 'center',
              gap: 10,
              padding: '10px 14px',
              background: 'transparent',
              color: 'var(--color-text)',
              cursor: 'pointer',
              textAlign: 'left',
              fontSize: 13,
            }}
          >
            <span style={{ fontSize: 14 }}>↪</span>
            Sign out
          </button>
        </div>
      )}
    </div>
  );
}
