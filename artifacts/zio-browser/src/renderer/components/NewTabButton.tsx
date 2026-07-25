/**
 * NewTabButton — a visible "+" button for the dashboard/split headers.
 *
 * Clicking opens a small menu offering the three window modes so the user can
 * choose WHERE the new tab opens:
 *   • Browser  — creates a new tab and switches to browser mode
 *   • Dashboard — switches to dashboard mode
 *   • Split    — creates a new tab and switches to split mode
 *
 * Uses the chrome-overlay mechanism (hide all native views while the menu is
 * open, restore on dismiss) — same pattern as ModeSwitcher.
 */
import { useState, useRef, useEffect } from 'react';
import type { WindowMode } from '../../shared/window-mode';
import {
  WINDOW_MODES,
  WINDOW_MODE_LABELS,
  WINDOW_MODE_ICONS,
} from '../../shared/window-mode';

interface Props {
  currentMode: WindowMode;
  onSetMode: (mode: WindowMode) => void;
}

const MODE_HINTS: Record<WindowMode, string> = {
  browser: 'Open a new tab in the full browser',
  dashboard: 'Go to the Sayzio dashboard',
  split: 'New tab beside the dashboard',
};

export function NewTabButton({ currentMode, onSetMode }: Props) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const wasOpen = useRef(false);
  const picked = useRef(false);

  useEffect(() => {
    if (open) {
      wasOpen.current = true;
      picked.current = false;
      void window.zio.window.setChromeOverlay(true);
    } else if (wasOpen.current) {
      wasOpen.current = false;
      if (!picked.current) {
        void window.zio.window.setChromeOverlay(false);
      }
    }
  }, [open]);

  // Release the overlay if this component unmounts while its menu is open.
  useEffect(() => () => {
    if (wasOpen.current && !picked.current) {
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

  const handlePick = (mode: WindowMode) => {
    picked.current = true;
    setOpen(false);
    void (async () => {
      if (mode === 'browser' || mode === 'split') {
        // Create the tab first so it is ready when the mode switches.
        await window.zio.tabs.create();
      }
      // onSetMode round-trips through the main process, which restores and
      // re-bounds all native views (implicitly closing the overlay).
      onSetMode(mode);
    })();
  };

  return (
    <div ref={ref} style={{ position: 'relative', WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
      <button
        onClick={() => setOpen(prev => !prev)}
        title="New tab (Cmd/Ctrl+T)"
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 4,
          padding: '4px 10px',
          borderRadius: 8,
          background: open ? 'var(--color-bg-elevated)' : 'transparent',
          border: '1px solid var(--color-border)',
          color: 'var(--color-text-muted)',
          fontSize: 12,
          fontWeight: 500,
          cursor: 'pointer',
          transition: 'all 0.15s',
          whiteSpace: 'nowrap',
        }}
      >
        <span style={{ fontSize: 14, lineHeight: 1 }}>＋</span>
        <span>New Tab</span>
      </button>

      {open && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 6px)',
          right: 0,
          width: 230,
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 12,
          boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
          zIndex: 1000,
          overflow: 'hidden',
        }}>
          <div style={{
            padding: '8px 14px 6px',
            fontSize: 10,
            fontWeight: 700,
            letterSpacing: 1,
            textTransform: 'uppercase',
            color: 'var(--color-text-muted)',
            borderBottom: '1px solid var(--color-border)',
          }}>Open new tab in</div>

          {WINDOW_MODES.map(mode => (
            <button
              key={mode}
              onClick={() => handlePick(mode)}
              style={{
                width: '100%',
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '10px 14px',
                background: 'transparent',
                borderBottom: '1px solid var(--color-border)',
                color: 'var(--color-text)',
                cursor: 'pointer',
                textAlign: 'left',
                transition: 'background 0.12s',
              }}
            >
              <span style={{ fontSize: 16, flexShrink: 0 }}>{WINDOW_MODE_ICONS[mode]}</span>
              <div>
                <div style={{ fontSize: 13, fontWeight: currentMode === mode ? 600 : 400 }}>
                  {WINDOW_MODE_LABELS[mode]}
                </div>
                <div style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
                  {MODE_HINTS[mode]}
                </div>
              </div>
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
