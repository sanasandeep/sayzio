/**
 * DownloadToast — slim, non-blocking toast shown at the bottom-right of the
 * window when a download starts. Auto-dismisses after ~4 s unless hovered.
 */
import { useState, useEffect, useRef, useCallback } from 'react';

interface StartedPayload {
  id: string;
  url: string;
  filename: string;
  savePath: string;
  totalBytes: number | null;
  mimeType: string | null;
  isPrivate: boolean;
}

interface Toast {
  key: number;
  filename: string;
}

const AUTO_DISMISS_MS = 4000;

interface Props {
  onOpenDownloads: () => void;
}

export function DownloadToast({ onOpenDownloads }: Props) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextKey = useRef(0);
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());

  const dismiss = useCallback((key: number) => {
    const t = timers.current.get(key);
    if (t) clearTimeout(t);
    timers.current.delete(key);
    setToasts(prev => prev.filter(toast => toast.key !== key));
  }, []);

  const scheduleDismiss = useCallback((key: number) => {
    const t = timers.current.get(key);
    if (t) clearTimeout(t);
    timers.current.set(key, setTimeout(() => dismiss(key), AUTO_DISMISS_MS));
  }, [dismiss]);

  const pauseDismiss = useCallback((key: number) => {
    const t = timers.current.get(key);
    if (t) clearTimeout(t);
    timers.current.delete(key);
  }, []);

  useEffect(() => {
    const onStarted = (...args: unknown[]) => {
      const payload = args[0] as StartedPayload;
      const key = nextKey.current++;
      setToasts(prev => [...prev, { key, filename: payload.filename }]);
      scheduleDismiss(key);
    };
    window.zio.on('download:started', onStarted);
    const timerMap = timers.current;
    return () => {
      window.zio.off('download:started', onStarted);
      timerMap.forEach(t => clearTimeout(t));
      timerMap.clear();
    };
  }, [scheduleDismiss]);

  if (toasts.length === 0) return null;

  return (
    <div style={{
      position: 'fixed',
      bottom: 16,
      right: 16,
      zIndex: 300,
      display: 'flex',
      flexDirection: 'column',
      gap: 8,
      alignItems: 'flex-end',
      pointerEvents: 'none',
    }}>
      {toasts.map(toast => (
        <div
          key={toast.key}
          onMouseEnter={() => pauseDismiss(toast.key)}
          onMouseLeave={() => scheduleDismiss(toast.key)}
          style={{
            pointerEvents: 'auto',
            display: 'flex',
            alignItems: 'center',
            gap: 10,
            maxWidth: 380,
            padding: '10px 12px',
            borderRadius: 10,
            background: 'var(--color-surface, #1e2030)',
            border: '1px solid var(--color-border, rgba(255,255,255,0.12))',
            boxShadow: '0 8px 24px rgba(0,0,0,0.35)',
            color: 'var(--color-text, #e5e7eb)',
            fontSize: 13,
          }}
        >
          <span aria-hidden="true" style={{ fontSize: 16, lineHeight: 1 }}>⬇️</span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div style={{
              whiteSpace: 'nowrap',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              fontWeight: 600,
            }} title={toast.filename}>
              {toast.filename}
            </div>
            <div style={{ opacity: 0.7, fontSize: 12 }}>Download started</div>
          </div>
          <button
            onClick={() => { dismiss(toast.key); onOpenDownloads(); }}
            style={{
              flexShrink: 0,
              background: 'none',
              border: 'none',
              color: 'var(--color-primary, #60a5fa)',
              cursor: 'pointer',
              fontSize: 13,
              fontWeight: 600,
              padding: '4px 6px',
            }}
          >
            View
          </button>
          <button
            aria-label="Dismiss"
            onClick={() => dismiss(toast.key)}
            style={{
              flexShrink: 0,
              background: 'none',
              border: 'none',
              color: 'inherit',
              opacity: 0.6,
              cursor: 'pointer',
              fontSize: 14,
              padding: '4px 6px',
              lineHeight: 1,
            }}
          >
            ✕
          </button>
        </div>
      ))}
    </div>
  );
}
