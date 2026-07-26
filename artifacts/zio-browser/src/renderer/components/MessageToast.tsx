/**
 * MessageToast — slim, non-blocking toast for generic messages sent from the
 * main process via the 'toast:show' channel. Auto-dismisses after ~4 s.
 */
import { useState, useEffect, useRef, useCallback } from 'react';

interface Toast {
  key: number;
  message: string;
}

const AUTO_DISMISS_MS = 4000;

export function MessageToast() {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextKey = useRef(0);
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());

  const dismiss = useCallback((key: number) => {
    const t = timers.current.get(key);
    if (t) clearTimeout(t);
    timers.current.delete(key);
    setToasts(prev => prev.filter(toast => toast.key !== key));
  }, []);

  useEffect(() => {
    const onShow = (...args: unknown[]) => {
      const message = typeof args[0] === 'string' ? args[0] : '';
      if (!message) return;
      const key = nextKey.current++;
      setToasts(prev => [...prev, { key, message }]);
      timers.current.set(key, setTimeout(() => dismiss(key), AUTO_DISMISS_MS));
    };
    window.zio.on('toast:show', onShow);
    const timerMap = timers.current;
    return () => {
      window.zio.off('toast:show', onShow);
      timerMap.forEach(t => clearTimeout(t));
      timerMap.clear();
    };
  }, [dismiss]);

  if (toasts.length === 0) return null;

  return (
    <div style={{
      position: 'fixed',
      bottom: 16,
      left: '50%',
      transform: 'translateX(-50%)',
      zIndex: 320,
      display: 'flex',
      flexDirection: 'column',
      gap: 8,
      alignItems: 'center',
      pointerEvents: 'none',
    }}>
      {toasts.map(toast => (
        <div
          key={toast.key}
          style={{
            pointerEvents: 'auto',
            display: 'flex',
            alignItems: 'center',
            gap: 10,
            maxWidth: 420,
            padding: '10px 14px',
            borderRadius: 10,
            background: 'var(--color-surface, #1e2030)',
            border: '1px solid var(--color-border, rgba(255,255,255,0.12))',
            boxShadow: '0 8px 24px rgba(0,0,0,0.35)',
            color: 'var(--color-text, #e5e7eb)',
            fontSize: 13,
          }}
        >
          <span style={{ minWidth: 0 }}>{toast.message}</span>
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
