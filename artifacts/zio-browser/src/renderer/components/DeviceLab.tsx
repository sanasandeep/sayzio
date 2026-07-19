/**
 * DeviceLab — live preview view that renders one of the user's own biolinks
 * simultaneously in three emulated viewport frames: phone, tablet, desktop.
 *
 * Each frame is an iframe scaled via CSS transform to fill its column, giving
 * an accurate side-by-side layout preview without any server-side rendering.
 *
 * Out of scope (per task): editing the biolink, screenshots, and exporting frames.
 */
import { useState, useEffect, useCallback, useRef } from 'react';

interface Biolink {
  id: unknown;
  alias: unknown;
  title: unknown;
  public_url: unknown;
}

interface DeviceFrame {
  label: string;
  icon: string;
  nativeWidth: number;
  /** Approximate device height to constrain the frame */
  nativeHeight: number;
  userAgent: string;
}

const DEVICE_FRAMES: DeviceFrame[] = [
  {
    label: 'Phone',
    icon: '📱',
    nativeWidth: 375,
    nativeHeight: 812,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  },
  {
    label: 'Tablet',
    icon: '⬛',
    nativeWidth: 768,
    nativeHeight: 1024,
    userAgent: 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  },
  {
    label: 'Desktop',
    icon: '🖥',
    nativeWidth: 1280,
    nativeHeight: 800,
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
  },
];

interface Props {
  onClose: () => void;
  /**
   * When set (e.g. from the tab context menu's "Preview in Device Lab"),
   * the lab previews this URL directly and skips fetching the biolinks list.
   */
  initialUrl?: string;
}

/** Normalize a manually typed URL — assume https:// when no scheme given. */
function normalizeUrl(input: string): string {
  const trimmed = input.trim();
  if (!trimmed) return '';
  if (/^https?:\/\//i.test(trimmed)) return trimmed;
  return `https://${trimmed}`;
}

export function DeviceLab({ onClose, initialUrl }: Props) {
  const [biolinks, setBiolinks] = useState<Biolink[]>([]);
  const [selectedUrl, setSelectedUrl] = useState<string>('');
  const [previewUrl, setPreviewUrl] = useState<string>(initialUrl ?? '');
  const [urlInput, setUrlInput] = useState<string>(initialUrl ?? '');
  const [loading, setLoading] = useState(!initialUrl);
  const [error, setError] = useState<string | null>(null);
  const [refreshKey, setRefreshKey] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const [frameWidth, setFrameWidth] = useState(0);

  // When opened for a specific URL (context menu), follow updates to it
  useEffect(() => {
    if (initialUrl) {
      setPreviewUrl(initialUrl);
      setUrlInput(initialUrl);
      setSelectedUrl('');
      setLoading(false);
      setError(null);
      setRefreshKey(k => k + 1);
    }
  }, [initialUrl]);

  // Load the user's biolinks once — skipped when a URL was passed in directly
  useEffect(() => {
    if (initialUrl) return;
    const load = async () => {
      setLoading(true);
      setError(null);
      try {
        const items = await window.zio.deviceLab.listBiolinks() as Biolink[];
        setBiolinks(items);
        if (items.length > 0 && items[0]?.public_url) {
          const url = String(items[0].public_url);
          setSelectedUrl(url);
          setPreviewUrl(url);
          setUrlInput(url);
        }
      } catch {
        setError('Could not load biolinks. Make sure you are signed in.');
      } finally {
        setLoading(false);
      }
    };
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Measure available frame column width
  useEffect(() => {
    const measure = () => {
      if (containerRef.current) {
        const w = containerRef.current.clientWidth;
        // 3 columns with 16px gaps and 24px side padding each side
        setFrameWidth(Math.floor((w - 80) / 3));
      }
    };
    measure();
    const ro = new ResizeObserver(measure);
    if (containerRef.current) ro.observe(containerRef.current);
    return () => ro.disconnect();
  }, []);

  const handleSelectBiolink = useCallback((url: string) => {
    setSelectedUrl(url);
    setPreviewUrl(url);
    setUrlInput(url);
    setRefreshKey(k => k + 1);
  }, []);

  const handleUrlSubmit = useCallback(() => {
    const url = normalizeUrl(urlInput);
    if (!url) return;
    setSelectedUrl('');
    setPreviewUrl(url);
    setUrlInput(url);
    setError(null);
    setRefreshKey(k => k + 1);
  }, [urlInput]);

  const handleRefreshAll = useCallback(() => {
    setRefreshKey(k => k + 1);
  }, []);

  return (
    <div style={{
      position: 'fixed',
      inset: 0,
      background: 'var(--color-bg)',
      display: 'flex',
      flexDirection: 'column',
      zIndex: 500,
    }}>
      {/* Header */}
      <div style={{
        height: 52,
        flexShrink: 0,
        background: 'var(--color-bg-surface)',
        borderBottom: '1px solid var(--color-border)',
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: '0 16px',
        WebkitAppRegion: 'drag',
      } as React.CSSProperties}>
        <span style={{ fontSize: 16, WebkitAppRegion: 'no-drag' } as React.CSSProperties}>🔬</span>
        <span style={{ fontWeight: 600, fontSize: 14, color: 'var(--color-text)', flex: 1, WebkitAppRegion: 'no-drag' } as React.CSSProperties}>
          Device Lab
        </span>

        {/* Manual URL entry — works for any page, not just biolinks */}
        <input
          type="text"
          value={urlInput}
          onChange={e => setUrlInput(e.target.value)}
          onKeyDown={e => { if (e.key === 'Enter') handleUrlSubmit(); }}
          placeholder="Enter a URL to preview…"
          spellCheck={false}
          style={{
            padding: '4px 8px',
            borderRadius: 8,
            border: '1px solid var(--color-border)',
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            fontSize: 12,
            width: 260,
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        />
        <button
          onClick={handleUrlSubmit}
          title="Preview this URL"
          style={{
            padding: '4px 10px',
            borderRadius: 8,
            border: '1px solid var(--color-border)',
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            fontSize: 12,
            cursor: 'pointer',
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        >Go</button>

        {/* Biolink picker (optional — only when biolinks were loaded) */}
        {biolinks.length > 0 && (
          <select
            value={selectedUrl}
            onChange={e => handleSelectBiolink(e.target.value)}
            style={{
              padding: '4px 8px',
              borderRadius: 8,
              border: '1px solid var(--color-border)',
              background: 'var(--color-bg-elevated)',
              color: 'var(--color-text)',
              fontSize: 12,
              maxWidth: 220,
              WebkitAppRegion: 'no-drag',
            } as React.CSSProperties}
          >
            {biolinks.map((bl, i) => (
              <option key={i} value={String(bl.public_url ?? '')}>
                {String(bl.title ?? bl.alias ?? `Biolink ${i + 1}`)}
              </option>
            ))}
          </select>
        )}

        {/* Refresh all */}
        <button
          onClick={handleRefreshAll}
          title="Refresh all frames"
          style={{
            padding: '4px 10px',
            borderRadius: 8,
            border: '1px solid var(--color-border)',
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            fontSize: 12,
            cursor: 'pointer',
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        >↻ Refresh all</button>

        {/* Close */}
        <button
          onClick={onClose}
          title="Close Device Lab"
          style={{
            padding: '4px 10px',
            borderRadius: 8,
            border: '1px solid var(--color-border)',
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            fontSize: 12,
            cursor: 'pointer',
            WebkitAppRegion: 'no-drag',
          } as React.CSSProperties}
        >✕ Close</button>
      </div>

      {/* Body */}
      <div ref={containerRef} style={{
        flex: 1,
        overflow: 'hidden',
        display: 'flex',
        flexDirection: 'column',
        padding: 20,
        gap: 12,
      }}>
        {loading && (
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-text-muted)', fontSize: 14 }}>
            Loading your biolinks…
          </div>
        )}

        {!loading && error && (
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: 12 }}>
            <span style={{ fontSize: 32 }}>⚠️</span>
            <p style={{ color: 'var(--color-text-muted)', fontSize: 14, textAlign: 'center', maxWidth: 360 }}>{error}</p>
          </div>
        )}

        {!loading && !error && biolinks.length === 0 && !previewUrl && (
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: 12 }}>
            <span style={{ fontSize: 40 }}>🔗</span>
            <p style={{ color: 'var(--color-text-muted)', fontSize: 14 }}>
              No biolinks found. Create one on Sayzio first.
            </p>
          </div>
        )}

        {!loading && !error && previewUrl && frameWidth > 0 && (
          <>
            {/* Device labels */}
            <div style={{ display: 'flex', gap: 16 }}>
              {DEVICE_FRAMES.map(frame => (
                <div key={frame.label} style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 6 }}>
                  <span style={{ fontSize: 16 }}>{frame.icon}</span>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)' }}>{frame.label}</div>
                    <div style={{ fontSize: 10, color: 'var(--color-text-muted)' }}>{frame.nativeWidth}px viewport</div>
                  </div>
                </div>
              ))}
            </div>

            {/* Frames row */}
            <div style={{
              display: 'flex',
              gap: 16,
              flex: 1,
              overflow: 'hidden',
            }}>
              {DEVICE_FRAMES.map(frame => (
                <FrameColumn
                  key={`${frame.label}-${refreshKey}`}
                  frame={frame}
                  url={previewUrl}
                  columnWidth={frameWidth}
                />
              ))}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

interface FrameColumnProps {
  frame: DeviceFrame;
  url: string;
  columnWidth: number;
}

function FrameColumn({ frame, url, columnWidth }: FrameColumnProps) {
  const scale = columnWidth / frame.nativeWidth;
  const scaledHeight = frame.nativeHeight * scale;

  return (
    <div style={{
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      gap: 8,
      overflow: 'hidden',
    }}>
      {/* Frame wrapper — clips the scaled iframe */}
      <div style={{
        width: columnWidth,
        height: scaledHeight,
        overflow: 'hidden',
        border: '1px solid var(--color-border)',
        borderRadius: 10,
        background: '#fff',
        flexShrink: 0,
        position: 'relative',
      }}>
        <iframe
          src={url}
          title={`${frame.label} preview`}
          style={{
            width: frame.nativeWidth,
            height: frame.nativeHeight,
            border: 'none',
            transformOrigin: '0 0',
            transform: `scale(${scale})`,
            display: 'block',
            pointerEvents: 'none',
          }}
          sandbox="allow-scripts allow-same-origin"
        />
      </div>
    </div>
  );
}
