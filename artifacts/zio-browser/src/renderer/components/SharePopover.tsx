/**
 * SharePopover — Safari-style share menu for the toolbar cluster.
 *
 * Actions: Copy Link, Shorten & copy (hands off to the existing Sayzio
 * ShortenPopover flow), and an Email fallback via the system mail client.
 * The parent holds the ref-counted chrome overlay while this is open.
 */
import { useState, useRef, useEffect } from 'react';
import { computeMenuPos, useMenuReanchor, type MenuPos } from '../lib/menu-position';

const MENU_WIDTH = 220;

interface Props {
  anchorRef: React.RefObject<HTMLButtonElement | null>;
  pageUrl: string;
  pageTitle: string;
  /** True when the page can be shortened via Sayzio (real http(s) page). */
  canShorten: boolean;
  onClose: () => void;
  /** Open the existing Shorten/QR popover. */
  onShorten: () => void;
}

const itemStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: 10,
  width: '100%',
  padding: '8px 14px',
  fontSize: 13,
  color: 'var(--color-text)',
  background: 'transparent',
  border: 'none',
  cursor: 'pointer',
  textAlign: 'left',
  whiteSpace: 'nowrap',
};

export function SharePopover({ anchorRef, pageUrl, pageTitle, canShorten, onClose, onShorten }: Props) {
  const menuRef = useRef<HTMLDivElement>(null);
  const [copied, setCopied] = useState(false);

  const [pos, setPos] = useState<MenuPos | null>(() => {
    const rect = anchorRef.current?.getBoundingClientRect();
    return rect ? computeMenuPos(rect, MENU_WIDTH) : null;
  });
  useMenuReanchor(true, anchorRef, MENU_WIDTH, setPos, onClose);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node) &&
          !anchorRef.current?.contains(e.target as Node)) {
        onClose();
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose, anchorRef]);

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [onClose]);

  const hover = {
    onMouseEnter: (e: React.MouseEvent<HTMLButtonElement>) => (e.currentTarget.style.background = 'var(--color-bg-elevated)'),
    onMouseLeave: (e: React.MouseEvent<HTMLButtonElement>) => (e.currentTarget.style.background = 'transparent'),
  };

  const handleCopy = () => {
    void navigator.clipboard.writeText(pageUrl).then(() => {
      setCopied(true);
      window.setTimeout(() => onClose(), 650);
    }).catch(() => onClose());
  };

  const handleEmail = () => {
    const subject = encodeURIComponent(pageTitle || pageUrl);
    const body = encodeURIComponent(pageUrl);
    void window.zio.shell.openExternal(`mailto:?subject=${subject}&body=${body}`);
    onClose();
  };

  return (
    <div
      ref={menuRef}
      data-testid="share-popover"
      style={{
        position: 'fixed',
        top: pos ? pos.top : undefined,
        right: pos ? pos.right : 12,
        maxHeight: pos ? pos.maxHeight : undefined,
        overflowY: 'auto',
        minWidth: MENU_WIDTH,
        maxWidth: 'calc(100vw - 16px)',
        background: 'var(--color-bg-surface)',
        border: '1px solid var(--color-border)',
        borderRadius: 10,
        boxShadow: '0 8px 28px rgba(0,0,0,0.3)',
        zIndex: 9999,
        padding: '4px 0',
        overflowX: 'hidden',
      }}
    >
      <div style={{
        padding: '6px 14px 4px',
        fontSize: 10,
        fontWeight: 700,
        letterSpacing: 1,
        textTransform: 'uppercase',
        color: 'var(--color-text-muted)',
      }}>
        Share
      </div>
      <div style={{
        padding: '0 14px 6px',
        fontSize: 11,
        color: 'var(--color-text-muted)',
        maxWidth: 260,
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        borderBottom: '1px solid var(--color-border)',
      }}>
        {pageTitle || pageUrl}
      </div>

      <button onClick={handleCopy} style={itemStyle} {...hover}>
        <span>{copied ? '✓' : '🔗'}</span>
        <span>{copied ? 'Copied!' : 'Copy link'}</span>
      </button>

      {canShorten && (
        <button onClick={() => { onClose(); onShorten(); }} style={itemStyle} {...hover}>
          <span>✂️</span>
          <span>Shorten &amp; copy with Sayzio</span>
        </button>
      )}

      <button onClick={handleEmail} style={itemStyle} {...hover}>
        <span>✉️</span>
        <span>Email link</span>
      </button>
    </div>
  );
}
