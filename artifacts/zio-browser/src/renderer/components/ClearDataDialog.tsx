/**
 * ClearDataDialog — modal dialog for "Clear browsing data".
 *
 * Time ranges: last hour / 24 h / 7 days / 4 weeks / all time.
 * Data types:  history, cookies & site data, cached files.
 *
 * Clearing history also emits tombstones through the sync pipeline so the
 * wipe propagates to other devices (handled in the main process).
 */
import { useState, useEffect, useCallback, useRef } from 'react';

export type ClearRange = 'hour' | 'day' | 'week' | '4weeks' | 'all';

const RANGES: { value: ClearRange; label: string }[] = [
  { value: 'hour',   label: 'Last hour' },
  { value: 'day',    label: 'Last 24 hours' },
  { value: 'week',   label: 'Last 7 days' },
  { value: '4weeks', label: 'Last 4 weeks' },
  { value: 'all',    label: 'All time' },
];

interface Props {
  onClose: () => void;
  onCleared?: () => void;
}

type Phase = 'form' | 'confirm' | 'clearing' | 'done';

export function ClearDataDialog({ onClose, onCleared }: Props) {
  const [range, setRange] = useState<ClearRange>('all');
  const [clearHistory, setClearHistory] = useState(true);
  const [clearCookies, setClearCookies] = useState(true);
  const [clearCache, setClearCache] = useState(true);
  const [phase, setPhase] = useState<Phase>('form');
  const [deletedCount, setDeletedCount] = useState(0);
  const overlayRef = useRef<HTMLDivElement>(null);

  const nothingSelected = !clearHistory && !clearCookies && !clearCache;

  const rangeLabel = RANGES.find(r => r.value === range)?.label ?? '';

  const handleConfirm = useCallback(() => {
    setPhase('confirm');
  }, []);

  const handleClear = useCallback(async () => {
    setPhase('clearing');
    try {
      const result = await window.zio.browsingData.clear({
        range,
        clearHistory,
        clearCookies,
        clearCache,
      }) as { ok: boolean; deletedCount: number };
      setDeletedCount(result.deletedCount ?? 0);
    } catch {
      setDeletedCount(0);
    }
    setPhase('done');
    onCleared?.();
  }, [range, clearHistory, clearCookies, clearCache, onCleared]);

  const handleDone = useCallback(() => {
    onClose();
  }, [onClose]);

  // Close on Escape key
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && phase !== 'clearing') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [phase, onClose]);

  // Click-outside to close
  const handleOverlayClick = (e: React.MouseEvent) => {
    if (e.target === overlayRef.current && phase !== 'clearing') onClose();
  };

  const summaryLines: string[] = [];
  if (clearHistory) summaryLines.push('browsing history');
  if (clearCookies) summaryLines.push('cookies & site data');
  if (clearCache) summaryLines.push('cached files');

  return (
    <div
      ref={overlayRef}
      onClick={handleOverlayClick}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0,0,0,0.55)',
        zIndex: 9000,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backdropFilter: 'blur(2px)',
      }}
    >
      <div
        style={{
          width: 420,
          maxWidth: 'calc(100vw - 32px)',
          background: 'var(--color-bg-elevated)',
          border: '1px solid var(--color-border)',
          borderRadius: 14,
          boxShadow: '0 24px 64px rgba(0,0,0,0.45)',
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
        }}
      >
        {/* Header */}
        <div style={{
          padding: '16px 20px',
          borderBottom: '1px solid var(--color-border)',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}>
          <h2 style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text)', margin: 0 }}>
            Clear browsing data
          </h2>
          {phase !== 'clearing' && (
            <button
              onClick={onClose}
              style={{
                width: 26,
                height: 26,
                borderRadius: '50%',
                background: 'var(--color-bg)',
                border: '1px solid var(--color-border)',
                color: 'var(--color-text-muted)',
                fontSize: 14,
                lineHeight: 1,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }}
              aria-label="Close"
            >✕</button>
          )}
        </div>

        {/* Body */}
        <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: 18 }}>

          {/* ── FORM phase ── */}
          {(phase === 'form') && (
            <>
              {/* Time range */}
              <div>
                <label style={sectionLabel}>Time range</label>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 2, marginTop: 8 }}>
                  {RANGES.map(r => (
                    <label key={r.value} style={radioRow}>
                      <input
                        type="radio"
                        name="range"
                        value={r.value}
                        checked={range === r.value}
                        onChange={() => setRange(r.value)}
                        style={{ accentColor: 'var(--color-primary)', marginRight: 10 }}
                      />
                      <span style={{ fontSize: 13, color: 'var(--color-text)' }}>{r.label}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* Data types */}
              <div>
                <label style={sectionLabel}>What to clear</label>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 8 }}>
                  <CheckRow
                    checked={clearHistory}
                    onChange={setClearHistory}
                    label="Browsing history"
                    sub="Local visit log and sync cloud history for the selected period."
                  />
                  <CheckRow
                    checked={clearCookies}
                    onChange={setClearCookies}
                    label="Cookies & site data"
                    sub="Signs you out of most sites."
                  />
                  <CheckRow
                    checked={clearCache}
                    onChange={setClearCache}
                    label="Cached files"
                    sub="Frees disk space; sites may load slightly slower next visit."
                  />
                </div>
              </div>

              {nothingSelected && (
                <p style={{ fontSize: 12, color: 'var(--color-danger, #ef4444)', margin: 0 }}>
                  Select at least one data type to clear.
                </p>
              )}
            </>
          )}

          {/* ── CONFIRM phase ── */}
          {phase === 'confirm' && (
            <div>
              <div style={{
                background: 'color-mix(in srgb, var(--color-danger, #ef4444) 8%, var(--color-bg))',
                border: '1px solid color-mix(in srgb, var(--color-danger, #ef4444) 25%, transparent)',
                borderRadius: 10,
                padding: '14px 16px',
              }}>
                <p style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)', marginBottom: 8 }}>
                  Confirm clearing
                </p>
                <p style={{ fontSize: 12, color: 'var(--color-text-muted)', marginBottom: 0 }}>
                  This will permanently delete{' '}
                  <strong style={{ color: 'var(--color-text)' }}>{summaryLines.join(', ')}</strong>
                  {' '}from{' '}
                  <strong style={{ color: 'var(--color-text)' }}>{rangeLabel.toLowerCase()}</strong>.
                  {clearHistory && ' Cloud-synced history will also be removed from your account.'}
                  {clearCookies && ' You will be signed out of most sites.'}
                  {' '}This cannot be undone.
                </p>
              </div>
            </div>
          )}

          {/* ── CLEARING phase ── */}
          {phase === 'clearing' && (
            <div style={{ textAlign: 'center', padding: '12px 0' }}>
              <div style={{ fontSize: 32, marginBottom: 12 }}>🗑</div>
              <p style={{ fontSize: 13, color: 'var(--color-text-muted)', margin: 0 }}>Clearing data…</p>
            </div>
          )}

          {/* ── DONE phase ── */}
          {phase === 'done' && (
            <div style={{ textAlign: 'center', padding: '12px 0' }}>
              <div style={{ fontSize: 32, marginBottom: 12 }}>✅</div>
              <p style={{ fontSize: 14, fontWeight: 600, color: 'var(--color-text)', marginBottom: 4 }}>
                Done
              </p>
              <p style={{ fontSize: 12, color: 'var(--color-text-muted)', margin: 0 }}>
                {summaryLines.length > 0
                  ? `Cleared ${summaryLines.join(', ')}`
                  : 'Browsing data cleared.'}
                {clearHistory && deletedCount > 0
                  ? ` (${deletedCount} history item${deletedCount === 1 ? '' : 's'} removed)`
                  : ''}.
              </p>
            </div>
          )}
        </div>

        {/* Footer */}
        {phase !== 'clearing' && (
          <div style={{
            padding: '12px 20px',
            borderTop: '1px solid var(--color-border)',
            display: 'flex',
            gap: 8,
            justifyContent: 'flex-end',
          }}>
            {phase === 'done' ? (
              <button onClick={handleDone} style={primaryBtn}>
                Close
              </button>
            ) : phase === 'confirm' ? (
              <>
                <button onClick={() => setPhase('form')} style={secondaryBtn}>Back</button>
                <button onClick={() => void handleClear()} style={dangerBtn}>
                  Clear data
                </button>
              </>
            ) : (
              <>
                <button onClick={onClose} style={secondaryBtn}>Cancel</button>
                <button
                  onClick={handleConfirm}
                  disabled={nothingSelected}
                  style={nothingSelected ? { ...dangerBtn, opacity: 0.4, cursor: 'not-allowed' } : dangerBtn}
                >
                  Continue
                </button>
              </>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

// ── Sub-components ────────────────────────────────────────────────────────────

function CheckRow({
  checked,
  onChange,
  label,
  sub,
}: {
  checked: boolean;
  onChange: (v: boolean) => void;
  label: string;
  sub: string;
}) {
  return (
    <label style={{
      display: 'flex',
      gap: 10,
      alignItems: 'flex-start',
      padding: '8px 10px',
      borderRadius: 8,
      background: checked ? 'color-mix(in srgb, var(--color-primary) 6%, var(--color-bg))' : 'var(--color-bg)',
      border: `1px solid ${checked ? 'color-mix(in srgb, var(--color-primary) 25%, transparent)' : 'var(--color-border)'}`,
      cursor: 'pointer',
      transition: 'all 0.12s',
    }}>
      <input
        type="checkbox"
        checked={checked}
        onChange={e => onChange(e.target.checked)}
        style={{ accentColor: 'var(--color-primary)', marginTop: 2, flexShrink: 0 }}
      />
      <div>
        <div style={{ fontSize: 13, fontWeight: 500, color: 'var(--color-text)' }}>{label}</div>
        <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2 }}>{sub}</div>
      </div>
    </label>
  );
}

// ── Styles ────────────────────────────────────────────────────────────────────

const sectionLabel: React.CSSProperties = {
  fontSize: 11,
  fontWeight: 600,
  letterSpacing: '0.06em',
  textTransform: 'uppercase',
  color: 'var(--color-text-muted)',
};

const radioRow: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  padding: '5px 8px',
  borderRadius: 6,
  cursor: 'pointer',
};

const baseBtn: React.CSSProperties = {
  padding: '7px 16px',
  borderRadius: 8,
  fontSize: 13,
  fontWeight: 500,
  lineHeight: 1.4,
  transition: 'opacity 0.1s',
  cursor: 'pointer',
};

const primaryBtn: React.CSSProperties = {
  ...baseBtn,
  background: 'var(--color-primary)',
  color: '#fff',
  border: 'none',
};

const dangerBtn: React.CSSProperties = {
  ...baseBtn,
  background: 'var(--color-danger, #ef4444)',
  color: '#fff',
  border: 'none',
};

const secondaryBtn: React.CSSProperties = {
  ...baseBtn,
  background: 'var(--color-bg)',
  border: '1px solid var(--color-border)',
  color: 'var(--color-text-muted)',
};
