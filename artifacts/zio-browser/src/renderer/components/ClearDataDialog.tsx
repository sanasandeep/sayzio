/**
 * ClearDataDialog — modal dialog for "Delete browsing data".
 *
 * Time ranges: last 15 min / hour / 24 h / 7 days / 4 weeks / all time.
 * Data types:  history, cookies & site data, cached files, download history,
 *              site permissions — each with a live count/size for the range.
 *
 * Clearing history also emits tombstones through the sync pipeline so the
 * wipe propagates to other devices (handled in the main process).
 */
import { useState, useEffect, useCallback, useRef } from 'react';

export type ClearRange = '15min' | 'hour' | 'day' | 'week' | '4weeks' | 'all';

const RANGES: { value: ClearRange; label: string }[] = [
  { value: '15min',  label: 'Last 15 minutes' },
  { value: 'hour',   label: 'Last hour' },
  { value: 'day',    label: 'Last 24 hours' },
  { value: 'week',   label: 'Last 7 days' },
  { value: '4weeks', label: 'Last 4 weeks' },
  { value: 'all',    label: 'All time' },
];

interface BrowsingDataCounts {
  historyCount: number;
  cookieCount: number;
  cacheBytes: number;
  downloadCount: number;
  permissionCount: number;
}

function formatBytes(bytes: number): string {
  if (!bytes || bytes <= 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  let i = 0;
  let v = bytes;
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
  return `${v >= 10 || i === 0 ? Math.round(v) : v.toFixed(1)} ${units[i]}`;
}

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
  const [clearDownloads, setClearDownloads] = useState(false);
  const [clearPermissions, setClearPermissions] = useState(false);
  const [counts, setCounts] = useState<BrowsingDataCounts | null>(null);
  const [phase, setPhase] = useState<Phase>('form');
  const [deletedCount, setDeletedCount] = useState(0);
  const overlayRef = useRef<HTMLDivElement>(null);

  const nothingSelected = !clearHistory && !clearCookies && !clearCache && !clearDownloads && !clearPermissions;

  const rangeLabel = RANGES.find(r => r.value === range)?.label ?? '';

  // Per-type counts for the selected range.
  useEffect(() => {
    let cancelled = false;
    setCounts(null);
    void window.zio.browsingData.counts(range)
      .then((c: BrowsingDataCounts) => { if (!cancelled) setCounts(c); })
      .catch(() => { if (!cancelled) setCounts(null); });
    return () => { cancelled = true; };
  }, [range]);

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
        clearDownloads,
        clearPermissions,
      }) as { ok: boolean; deletedCount: number };
      setDeletedCount(result.deletedCount ?? 0);
    } catch {
      setDeletedCount(0);
    }
    setPhase('done');
    onCleared?.();
  }, [range, clearHistory, clearCookies, clearCache, clearDownloads, clearPermissions, onCleared]);

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
  if (clearDownloads) summaryLines.push('download history');
  if (clearPermissions) summaryLines.push('site permissions');

  const historySub = counts
    ? `${counts.historyCount} ${counts.historyCount === 1 ? 'entry' : 'entries'} in this range. Also removes synced cloud history.`
    : 'Local visit log and sync cloud history for the selected period.';
  const cookiesSub = counts
    ? `${counts.cookieCount} cookie${counts.cookieCount === 1 ? '' : 's'}. Signs you out of most sites.`
    : 'Signs you out of most sites.';
  const cacheSub = counts
    ? `About ${formatBytes(counts.cacheBytes)}. Sites may load slightly slower next visit.`
    : 'Frees disk space; sites may load slightly slower next visit.';
  const downloadsSub = counts
    ? `${counts.downloadCount} ${counts.downloadCount === 1 ? 'item' : 'items'} in the list. Downloaded files stay on your computer.`
    : 'Clears the download list. Downloaded files stay on your computer.';
  const permissionsSub = counts
    ? `${counts.permissionCount} saved choice${counts.permissionCount === 1 ? '' : 's'}. Sites will ask again.`
    : 'Resets camera, location and other site choices. Sites will ask again.';

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
          width: 440,
          maxWidth: 'calc(100vw - 32px)',
          maxHeight: 'calc(100vh - 48px)',
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
          flexShrink: 0,
        }}>
          <h2 style={{ fontSize: 15, fontWeight: 700, color: 'var(--color-text)', margin: 0 }}>
            Delete browsing data
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
        <div style={{ padding: '20px', display: 'flex', flexDirection: 'column', gap: 18, overflowY: 'auto' }}>

          {/* ── FORM phase ── */}
          {(phase === 'form') && (
            <>
              {/* Time range chips */}
              <div>
                <label style={sectionLabel}>Time range</label>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
                  {RANGES.map(r => (
                    <button
                      key={r.value}
                      onClick={() => setRange(r.value)}
                      style={{
                        fontSize: 12,
                        fontWeight: 600,
                        padding: '5px 12px',
                        borderRadius: 999,
                        border: `1px solid ${range === r.value ? 'var(--color-primary)' : 'var(--color-border)'}`,
                        background: range === r.value
                          ? 'color-mix(in srgb, var(--color-primary) 15%, var(--color-bg))'
                          : 'var(--color-bg)',
                        color: range === r.value ? 'var(--color-primary)' : 'var(--color-text)',
                        transition: 'all 0.12s',
                      }}
                    >{r.label}</button>
                  ))}
                </div>
              </div>

              {/* Data types */}
              <div>
                <label style={sectionLabel}>What to delete</label>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 8 }}>
                  <CheckRow
                    checked={clearHistory}
                    onChange={setClearHistory}
                    label="Browsing history"
                    sub={historySub}
                  />
                  <CheckRow
                    checked={clearCookies}
                    onChange={setClearCookies}
                    label="Cookies & site data"
                    sub={cookiesSub}
                  />
                  <CheckRow
                    checked={clearCache}
                    onChange={setClearCache}
                    label="Cached files"
                    sub={cacheSub}
                  />
                  <CheckRow
                    checked={clearDownloads}
                    onChange={setClearDownloads}
                    label="Download history"
                    sub={downloadsSub}
                  />
                  <CheckRow
                    checked={clearPermissions}
                    onChange={setClearPermissions}
                    label="Site permissions"
                    sub={permissionsSub}
                  />
                </div>
              </div>

              {nothingSelected && (
                <p style={{ fontSize: 12, color: 'var(--color-danger, #ef4444)', margin: 0 }}>
                  Select at least one data type to delete.
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
                  Confirm deletion
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
              <p style={{ fontSize: 13, color: 'var(--color-text-muted)', margin: 0 }}>Deleting data…</p>
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
                  ? `Deleted ${summaryLines.join(', ')}`
                  : 'Browsing data deleted.'}
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
            flexShrink: 0,
          }}>
            {phase === 'done' ? (
              <button onClick={handleDone} style={primaryBtn}>
                Close
              </button>
            ) : phase === 'confirm' ? (
              <>
                <button onClick={() => setPhase('form')} style={secondaryBtn}>Back</button>
                <button onClick={() => void handleClear()} style={dangerBtn}>
                  Delete data
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
  background: 'var(--gradient-primary)',
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
