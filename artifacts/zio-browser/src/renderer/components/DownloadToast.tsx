/**
 * DownloadToast — slim, non-blocking toast shown at the bottom-right of the
 * window when a download starts. Auto-dismisses after ~4 s unless hovered.
 * Text-based downloads (.txt/.md/.json/.csv/.log or text MIME types) get a
 * "View in browser" action once complete, opening the saved file in a new tab
 * instead of the OS app.
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
  isText?: boolean;
}

interface DonePayload {
  id: string;
  state: 'completed' | 'interrupted' | 'cancelled';
  savePath: string;
  filename: string;
  isPrivate: boolean;
  isText?: boolean;
}

interface Toast {
  key: number;
  downloadId: string;
  filename: string;
  isText: boolean;
  completed: boolean;
  savePath: string;
}

const AUTO_DISMISS_MS = 4000;
/** Text downloads linger a bit longer once complete so "View in browser" is reachable. */
const TEXT_DONE_DISMISS_MS = 8000;

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

  const scheduleDismiss = useCallback((key: number, ms: number = AUTO_DISMISS_MS) => {
    const t = timers.current.get(key);
    if (t) clearTimeout(t);
    timers.current.set(key, setTimeout(() => dismiss(key), ms));
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
      setToasts(prev => [...prev, {
        key,
        downloadId: payload.id,
        filename: payload.filename,
        isText: payload.isText === true,
        completed: false,
        savePath: payload.savePath,
      }]);
      scheduleDismiss(key);
    };
    const onDone = (...args: unknown[]) => {
      const payload = args[0] as DonePayload;
      // Only text downloads get an upgraded "complete → view" toast state.
      if (payload.state !== 'completed' || payload.isText !== true) return;
      setToasts(prev => {
        const existing = prev.find(t => t.downloadId === payload.id);
        if (existing) {
          scheduleDismiss(existing.key, TEXT_DONE_DISMISS_MS);
          return prev.map(t => t.downloadId === payload.id
            ? { ...t, completed: true, savePath: payload.savePath, isText: true }
            : t);
        }
        // The start toast already auto-dismissed — show a fresh completion toast.
        const key = nextKey.current++;
        scheduleDismiss(key, TEXT_DONE_DISMISS_MS);
        return [...prev, {
          key,
          downloadId: payload.id,
          filename: payload.filename,
          isText: true,
          completed: true,
          savePath: payload.savePath,
        }];
      });
    };
    window.zio.on('download:started', onStarted);
    window.zio.on('download:done', onDone);
    const timerMap = timers.current;
    return () => {
      window.zio.off('download:started', onStarted);
      window.zio.off('download:done', onDone);
      timerMap.forEach(t => clearTimeout(t));
      timerMap.clear();
    };
  }, [scheduleDismiss]);

  const handleViewInTab = useCallback((key: number, savePath: string) => {
    dismiss(key);
    void window.zio.downloads.openInTab(savePath);
  }, [dismiss]);

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
          onMouseLeave={() => scheduleDismiss(toast.key, toast.completed ? TEXT_DONE_DISMISS_MS : AUTO_DISMISS_MS)}
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
          <span aria-hidden="true" style={{ fontSize: 16, lineHeight: 1 }}>
            {toast.completed ? '📄' : '⬇️'}
          </span>
          <div style={{ minWidth: 0, flex: 1 }}>
            <div style={{
              whiteSpace: 'nowrap',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              fontWeight: 600,
            }} title={toast.filename}>
              {toast.filename}
            </div>
            <div style={{ opacity: 0.7, fontSize: 12 }}>
              {toast.completed ? 'Download complete' : 'Download started'}
            </div>
          </div>
          {toast.completed && toast.isText && toast.savePath ? (
            <button
              onClick={() => handleViewInTab(toast.key, toast.savePath)}
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
              title="Open this text file in a browser tab"
            >
              View in browser
            </button>
          ) : (
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
          )}
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
