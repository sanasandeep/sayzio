/**
 * ModePicker — shown when opening a new window, lets the user pick a mode.
 * Also remembers the last-used mode so the default is pre-selected.
 */
import { useState } from 'react';
import type { WindowMode } from '../../shared/window-mode';
import {
  WINDOW_MODES,
  WINDOW_MODE_LABELS,
  WINDOW_MODE_DESCRIPTIONS,
  WINDOW_MODE_ICONS,
} from '../../shared/window-mode';
import zioBrowserLogo from '../assets/zio-browser-logo.png';

interface Props {
  defaultMode: WindowMode;
  onPick: (mode: WindowMode) => void;
}

export function ModePicker({ defaultMode, onPick }: Props) {
  const [selected, setSelected] = useState<WindowMode>(defaultMode);

  return (
    <div style={{
      position: 'fixed',
      inset: 0,
      background: 'var(--color-bg)',
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      zIndex: 2000,
    }}>
      {/* Draggable strip so the frameless window can still be moved
          while the mode picker covers the whole window. */}
      <div
        className="drag-region"
        style={{ position: 'absolute', top: 0, left: 0, right: 0, height: 48 }}
      />
      <div style={{ textAlign: 'center', marginBottom: 40 }}>
        <img
          src={zioBrowserLogo}
          alt="Zio Browser"
          style={{ height: 96, marginBottom: 12, display: 'inline-block' }}
          draggable={false}
        />
        <p style={{ fontSize: 16, color: 'var(--color-text-muted)' }}>Choose how you want to use this window</p>
      </div>

      <div style={{ display: 'flex', gap: 16, marginBottom: 40 }}>
        {WINDOW_MODES.map(mode => (
          <button
            key={mode}
            onClick={() => setSelected(mode)}
            style={{
              width: 220,
              padding: '24px 20px',
              borderRadius: 16,
              border: selected === mode
                ? '2px solid var(--color-primary)'
                : '2px solid var(--color-border)',
              background: selected === mode
                ? 'rgba(var(--color-primary-rgb, 99, 102, 241), 0.08)'
                : 'var(--color-bg-surface)',
              cursor: 'pointer',
              textAlign: 'left',
              transition: 'all 0.15s',
            }}
          >
            <div style={{ fontSize: 28, marginBottom: 12 }}>{WINDOW_MODE_ICONS[mode]}</div>
            <div style={{
              fontSize: 15,
              fontWeight: 700,
              marginBottom: 8,
              color: selected === mode ? 'var(--color-primary)' : 'var(--color-text)',
            }}>
              {WINDOW_MODE_LABELS[mode]}
            </div>
            <p style={{
              fontSize: 12,
              color: 'var(--color-text-muted)',
              lineHeight: 1.5,
              margin: 0,
            }}>
              {WINDOW_MODE_DESCRIPTIONS[mode]}
            </p>
            {selected === mode && (
              <div style={{
                marginTop: 14,
                display: 'inline-block',
                padding: '2px 10px',
                borderRadius: 20,
                background: 'var(--gradient-primary)',
                color: '#fff',
                fontSize: 11,
                fontWeight: 600,
              }}>Selected</div>
            )}
          </button>
        ))}
      </div>

      <button
        onClick={() => onPick(selected)}
        style={{
          padding: '12px 48px',
          borderRadius: 12,
          background: 'var(--gradient-primary)',
          color: '#fff',
          fontSize: 15,
          fontWeight: 700,
          border: 'none',
          cursor: 'pointer',
          transition: 'opacity 0.15s',
        }}
      >
        Open in {WINDOW_MODE_LABELS[selected]} Mode →
      </button>

      <p style={{ marginTop: 16, fontSize: 12, color: 'var(--color-text-muted)' }}>
        You can switch modes any time from the toolbar
      </p>
    </div>
  );
}
