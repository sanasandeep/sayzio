/**
 * TabModeSwitcher — toolbar dropdown to pick the ACTIVE TAB's view mode.
 * A tab shows ONE of four primitives (Website / Dashboard / Sayzio / Ask Zio)
 * or a split of any TWO — 10 configurations, grouped as singles then splits.
 * Uses the chrome-overlay pattern (native views sit above the DOM and would
 * swallow clicks on the dropdown).
 */
import { useState, useRef, useEffect } from 'react';
import type { TabMode } from '../../shared/window-mode';
import {
  TAB_MODES,
  TAB_MODE_LABELS,
  TAB_MODE_DESCRIPTIONS,
  TAB_MODE_ICONS,
} from '../../shared/window-mode';

const SINGLE_MODES = TAB_MODES.filter(m => !m.includes('+'));
const SPLIT_MODES = TAB_MODES.filter(m => m.includes('+'));

interface Props {
  currentMode: TabMode;
  onSetMode: (mode: TabMode) => void;
}

export function TabModeSwitcher({ currentMode, onSetMode }: Props) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const wasOpen = useRef(false);

  // Detach native views while the menu is open so it can receive clicks.
  // Unlike the window ModeSwitcher, tab-mode picks go through tabs:set-mode
  // which does NOT reset the chrome overlay in the main process, so the
  // overlay must ALWAYS be released when the menu closes (releasing re-applies
  // the current window mode, which reattaches and re-bounds all views).
  useEffect(() => {
    if (open) {
      wasOpen.current = true;
      void window.zio.window.setChromeOverlay(true);
    } else if (wasOpen.current) {
      wasOpen.current = false;
      void window.zio.window.setChromeOverlay(false);
    }
  }, [open]);

  // Release the overlay if this component unmounts while the menu is open.
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

  const pick = (mode: TabMode) => {
    setOpen(false);
    if (mode !== currentMode) {
      onSetMode(mode);
    }
  };

  return (
    <div ref={ref} style={{ position: 'relative' }}>
      <button
        onClick={() => setOpen(prev => !prev)}
        title={`Tab view: ${TAB_MODE_LABELS[currentMode]}`}
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
          whiteSpace: 'nowrap',
        }}
      >
        <span style={{ fontSize: 14 }}>{TAB_MODE_ICONS[currentMode]}</span>
        <span>{TAB_MODE_LABELS[currentMode]}</span>
        <span style={{ fontSize: 10, opacity: 0.5 }}>▼</span>
      </button>

      {open && (
        <div style={{
          position: 'absolute',
          top: 'calc(100% + 6px)',
          right: 0,
          width: 250,
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 12,
          boxShadow: '0 8px 32px rgba(0,0,0,0.18)',
          zIndex: 1000,
          maxHeight: 'min(480px, calc(100vh - 120px))',
          overflowY: 'auto',
        }}>
          <div style={{
            padding: '8px 14px 6px',
            fontSize: 10,
            fontWeight: 700,
            letterSpacing: 1,
            textTransform: 'uppercase',
            color: 'var(--color-text-muted)',
            borderBottom: '1px solid var(--color-border)',
          }}>Full View</div>

          {SINGLE_MODES.map(mode => (
            <button
              key={mode}
              onClick={() => pick(mode)}
              style={{
                width: '100%',
                display: 'flex',
                alignItems: 'flex-start',
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
              <span style={{ fontSize: 16, flexShrink: 0 }}>{TAB_MODE_ICONS[mode]}</span>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 13, fontWeight: currentMode === mode ? 600 : 400 }}>
                  {TAB_MODE_LABELS[mode]}
                </div>
                <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2 }}>
                  {TAB_MODE_DESCRIPTIONS[mode]}
                </div>
              </div>
              {currentMode === mode && (
                <span style={{ marginLeft: 'auto', fontSize: 14, color: 'var(--color-primary)', flexShrink: 0 }}>✓</span>
              )}
            </button>
          ))}

          <div style={{
            padding: '8px 14px 6px',
            fontSize: 10,
            fontWeight: 700,
            letterSpacing: 1,
            textTransform: 'uppercase',
            color: 'var(--color-text-muted)',
            borderBottom: '1px solid var(--color-border)',
          }}>Split View</div>

          {SPLIT_MODES.map(mode => (
            <button
              key={mode}
              onClick={() => pick(mode)}
              style={{
                width: '100%',
                display: 'flex',
                alignItems: 'flex-start',
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
              <span style={{ fontSize: 16, flexShrink: 0 }}>{TAB_MODE_ICONS[mode]}</span>
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 13, fontWeight: currentMode === mode ? 600 : 400 }}>
                  {TAB_MODE_LABELS[mode]}
                </div>
                <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2 }}>
                  {TAB_MODE_DESCRIPTIONS[mode]}
                </div>
              </div>
              {currentMode === mode && (
                <span style={{ marginLeft: 'auto', fontSize: 14, color: 'var(--color-primary)', flexShrink: 0 }}>✓</span>
              )}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
