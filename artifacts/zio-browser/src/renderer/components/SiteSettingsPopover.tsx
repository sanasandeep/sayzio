/**
 * SiteSettingsPopover — Safari-style "Settings for this website" popover,
 * opened from the address-bar icon. Shows per-site controls for the current
 * origin: content blockers, page zoom, auto-play, pop-ups, and the
 * camera / microphone / screen-sharing / location permission dropdowns.
 *
 * Not rendered in private windows (the button is hidden there).
 */
import { useState, useEffect, useCallback } from 'react';

interface Props {
  origin: string;
  onClose: () => void;
}

type PermValue = 'ask' | 'allow' | 'block';

const ZOOM_LEVELS = [0.5, 0.75, 0.85, 1, 1.15, 1.25, 1.5, 1.75, 2, 2.5, 3];

const PERMISSIONS: Array<{ key: string; label: string; icon: string }> = [
  { key: 'camera', label: 'Camera', icon: '📷' },
  { key: 'microphone', label: 'Microphone', icon: '🎤' },
  { key: 'display-capture', label: 'Screen Sharing', icon: '🖥️' },
  { key: 'geolocation', label: 'Location', icon: '📍' },
];

const rowStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'space-between',
  gap: 10,
  padding: '7px 0',
};

const labelStyle: React.CSSProperties = {
  fontSize: 12.5,
  color: 'var(--color-text)',
  display: 'flex',
  alignItems: 'center',
  gap: 7,
};

const selectStyle: React.CSSProperties = {
  fontSize: 12,
  padding: '4px 6px',
  borderRadius: 7,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  maxWidth: 165,
};

export function SiteSettingsPopover({ origin, onClose }: Props) {
  const [zoom, setZoom] = useState(1);
  const [autoplay, setAutoplay] = useState<'allow' | 'stop-with-sound' | 'never'>('allow');
  const [popups, setPopups] = useState<'block-notify' | 'block' | 'allow'>('allow');
  const [contentBlockers, setContentBlockers] = useState<'default' | 'on' | 'off'>('default');
  const [globalTracker, setGlobalTracker] = useState(false);
  const [perms, setPerms] = useState<Record<string, PermValue>>({});
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [row, allPerms, trackerOn] = await Promise.all([
          window.zio.siteSettings.get(origin),
          window.zio.permissions.getAll() as Promise<Array<{ origin: string; permission: string; decision: 'allow' | 'block' }>>,
          window.zio.tracker.isEnabled() as Promise<boolean>,
        ]);
        if (cancelled) return;
        setGlobalTracker(trackerOn);
        if (row) {
          if (typeof row.zoom === 'number') setZoom(row.zoom);
          if (row.autoplay === 'stop-with-sound' || row.autoplay === 'never') setAutoplay(row.autoplay);
          if (row.popups === 'block' || row.popups === 'block-notify') setPopups(row.popups);
          if (row.content_blockers === 1) setContentBlockers('on');
          else if (row.content_blockers === 0) setContentBlockers('off');
        }
        const mine: Record<string, PermValue> = {};
        for (const p of allPerms) {
          if (p.origin === origin) mine[p.permission] = p.decision;
        }
        setPerms(mine);
      } catch {
        // Best-effort — show defaults.
      } finally {
        if (!cancelled) setLoaded(true);
      }
    })();
    return () => { cancelled = true; };
  }, [origin]);

  // Close on Escape.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const hostname = (() => {
    try { return new URL(origin).hostname; } catch { return origin; }
  })();

  const handleZoom = useCallback((value: number) => {
    setZoom(value);
    void window.zio.siteSettings.set(origin, { zoom: value === 1 ? null : value });
  }, [origin]);

  const handleAutoplay = useCallback((value: 'allow' | 'stop-with-sound' | 'never') => {
    setAutoplay(value);
    void window.zio.siteSettings.set(origin, { autoplay: value === 'allow' ? null : value });
  }, [origin]);

  const handlePopups = useCallback((value: 'block-notify' | 'block' | 'allow') => {
    setPopups(value);
    void window.zio.siteSettings.set(origin, { popups: value === 'allow' ? null : value });
  }, [origin]);

  const handleContentBlockers = useCallback((value: 'default' | 'on' | 'off') => {
    setContentBlockers(value);
    void window.zio.siteSettings.set(origin, {
      contentBlockers: value === 'default' ? null : value === 'on',
    });
  }, [origin]);

  const handlePerm = useCallback((permission: string, value: PermValue) => {
    setPerms(prev => ({ ...prev, [permission]: value }));
    if (value === 'ask') void window.zio.permissions.revoke(origin, permission);
    else void window.zio.permissions.set(origin, permission, value);
  }, [origin]);

  return (
    <>
      {/* Click-away backdrop */}
      <div
        style={{ position: 'fixed', inset: 0, zIndex: 8500 }}
        onClick={onClose}
      />
      <div
        role="dialog"
        aria-label={`Settings for ${hostname}`}
        style={{
          position: 'fixed',
          top: 86,
          right: 130,
          width: 320,
          maxHeight: 'calc(100vh - 120px)',
          overflowY: 'auto',
          background: 'var(--color-bg-surface)',
          border: '1px solid var(--color-border)',
          borderRadius: 14,
          boxShadow: '0 12px 40px rgba(0,0,0,0.35)',
          padding: '14px 16px 16px',
          zIndex: 8501,
        }}
      >
        <div style={{
          fontSize: 13,
          fontWeight: 700,
          color: 'var(--color-text)',
          marginBottom: 8,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}>
          <span>When visiting {hostname}:</span>
          <button
            onClick={onClose}
            style={{ fontSize: 15, color: 'var(--color-text-muted)', padding: '0 4px' }}
            title="Close"
          >✕</button>
        </div>

        {!loaded ? (
          <div style={{ fontSize: 12, color: 'var(--color-text-muted)', padding: '10px 0' }}>Loading…</div>
        ) : (
          <>
            <div style={rowStyle}>
              <span style={labelStyle}>🚫 Content blockers</span>
              <select
                style={selectStyle}
                value={contentBlockers}
                onChange={e => handleContentBlockers(e.target.value as 'default' | 'on' | 'off')}
              >
                <option value="default">Default ({globalTracker ? 'On' : 'Off'})</option>
                <option value="on">On for this site</option>
                <option value="off">Off for this site</option>
              </select>
            </div>

            <div style={rowStyle}>
              <span style={labelStyle}>🔍 Page zoom</span>
              <select
                style={selectStyle}
                value={String(zoom)}
                onChange={e => handleZoom(parseFloat(e.target.value))}
              >
                {(ZOOM_LEVELS.includes(zoom) ? ZOOM_LEVELS : [...ZOOM_LEVELS, zoom].sort((a, b) => a - b)).map(z => (
                  <option key={z} value={String(z)}>{Math.round(z * 100)}%</option>
                ))}
              </select>
            </div>

            <div style={rowStyle}>
              <span style={labelStyle}>▶️ Auto-play</span>
              <select
                style={selectStyle}
                value={autoplay}
                onChange={e => handleAutoplay(e.target.value as 'allow' | 'stop-with-sound' | 'never')}
              >
                <option value="allow">Allow all auto-play</option>
                <option value="stop-with-sound">Stop media with sound</option>
                <option value="never">Never auto-play</option>
              </select>
            </div>

            <div style={rowStyle}>
              <span style={labelStyle}>🪟 Pop-up windows</span>
              <select
                style={selectStyle}
                value={popups}
                onChange={e => handlePopups(e.target.value as 'block-notify' | 'block' | 'allow')}
              >
                <option value="allow">Allow</option>
                <option value="block-notify">Block and notify</option>
                <option value="block">Block</option>
              </select>
            </div>

            <div style={{
              borderTop: '1px solid var(--color-border)',
              margin: '8px 0 4px',
              paddingTop: 8,
              fontSize: 11,
              fontWeight: 600,
              color: 'var(--color-text-muted)',
              textTransform: 'uppercase',
              letterSpacing: 0.4,
            }}>
              Permissions
            </div>

            {PERMISSIONS.map(({ key, label, icon }) => (
              <div key={key} style={rowStyle}>
                <span style={labelStyle}>{icon} {label}</span>
                <select
                  style={selectStyle}
                  value={perms[key] ?? 'ask'}
                  onChange={e => handlePerm(key, e.target.value as PermValue)}
                >
                  <option value="ask">Ask</option>
                  <option value="allow">Allow</option>
                  <option value="block">Deny</option>
                </select>
              </div>
            ))}
          </>
        )}
      </div>
    </>
  );
}
