/**
 * ModeSwitcher — compact toolbar button + dropdown to switch window modes.
 * Lives in the chrome bar; switches the current window live.
 */
import { useState, useRef, useEffect } from 'react';
import type { WindowMode } from '../../shared/window-mode';
import {
  WINDOW_MODES,
  WINDOW_MODE_LABELS,
} from '../../shared/window-mode';
import { ModeIcon } from './ModeIcon';

interface Props {
  currentMode: WindowMode;
  onSetMode: (mode: WindowMode) => void;
}

export function ModeSwitcher({ currentMode, onSetMode }: Props) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const wasOpen = useRef(false);

  // The dropdown menu extends below the chrome bar, into the region covered by
  // native WebContentsViews (tab views and, in dashboard/split modes, the
  // dashboard view). Native views sit ABOVE the renderer DOM, so they would
  // swallow clicks on the menu items. Detach ALL native views while the menu
  // is open and ALWAYS release exactly once when it closes — the overlay is
  // ref-counted in main, so skipping the release (even when a mode pick
  // already restored the views via setMode) permanently leaks the count and
  // leaves every later menu close unable to reattach the views.
  useEffect(() => {
    if (open) {
      wasOpen.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (wasOpen.current) {
      wasOpen.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [open]);

  // Release the overlay if this component unmounts while its menu is open
  // (e.g. a layout switch tears down the header).
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

  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button
        onClick={() => setOpen(prev => !prev)}
        title={`Window mode: ${WINDOW_MODE_LABELS[currentMode]}`}
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 5,
          padding: '4px 10px',
          borderRadius: 8,
          background: open ? 'var(--color-bg-elevated)' : 'transparent',
          border: '1px solid var(--color-border)',
          color: 'var(--color-text-muted)',
          fontSize: 12,
          cursor: 'pointer',
          fontWeight: 500,
          transition: 'all 0.15s',
        }}
      >
        <span style={{ display: 'flex', alignItems: 'center' }}><ModeIcon mode={currentMode} size={15} /></span>
        <span>{WINDOW_MODE_LABELS[currentMode]}</span>
        <span style={{ fontSize: 10, opacity: 0.5 }}>▼</span>
      </button>

      {open && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 6px)',
          right: 0,
          width: 200,
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
          }}>Window Mode</div>

          {WINDOW_MODES.map(mode => (
            <button
              key={mode}
              onClick={() => { onSetMode(mode); setOpen(false); }}
              style={{
                width: '100%',
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '10px 14px',
                background: currentMode === mode ? 'rgba(var(--color-primary-rgb, 99,102,241),0.1)' : 'transparent',
                borderBottom: '1px solid var(--color-border)',
                color: currentMode === mode ? 'var(--color-primary)' : 'var(--color-text)',
                cursor: 'pointer',
                textAlign: 'left',
                transition: 'background 0.12s',
              }}
            >
              <span style={{ display: 'flex', alignItems: 'center', flexShrink: 0 }}><ModeIcon mode={mode} size={20} /></span>
              <div>
                <div style={{ fontSize: 13, fontWeight: currentMode === mode ? 600 : 400 }}>
                  {WINDOW_MODE_LABELS[mode]}
                </div>
              </div>
              {currentMode === mode && (
                <span style={{ marginLeft: 'auto', fontSize: 14, color: 'var(--color-primary)' }}>✓</span>
              )}
            </button>
          ))}

          <div style={{ padding: '8px 14px', fontSize: 11, color: 'var(--color-text-muted)' }}>
            Cmd+Shift+1/2/3 to switch
          </div>
        </div>
      )}
    </div>
  );
}
