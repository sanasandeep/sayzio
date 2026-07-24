/**
 * New Tab page — shown when no URL is loaded.
 * Shows a search bar, quick links, and recent history.
 * In private/incognito mode shows a minimalist private-mode splash instead.
 */
import { useState, useEffect, useCallback } from 'react';
import type { HistoryEntry } from '../../main/db';
import { ProfileBadge } from './ProfileBadge';

interface Props {
  onNavigate: (url: string) => void;
  /** True when the window is running in incognito/private mode. */
  isPrivate?: boolean;
}

// ── Private mode splash ───────────────────────────────────────────────────────

function PrivateNewTabPage({ onNavigate }: Pick<Props, 'onNavigate'>) {
  const [query, setQuery] = useState('');
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const handleSearch = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    if (!query.trim()) return;
    onNavigate(query.trim());
  }, [query, onNavigate]);

  const formatTime = (d: Date) =>
    d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  return (
    <div style={{
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      padding: 40,
      background: '#0d0d1a',
      overflowY: 'auto',
      color: '#e0d7ff',
    }}>
      {/* Clock */}
      <div style={{ textAlign: 'center', marginBottom: 32 }}>
        <div style={{ fontSize: 52, fontWeight: 200, letterSpacing: -2, color: '#e0d7ff' }}>
          {formatTime(currentTime)}
        </div>
      </div>

      {/* Private mode icon + label */}
      <div style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 10,
        marginBottom: 36,
      }}>
        <div style={{ fontSize: 40 }}>🔒</div>
        <div style={{ fontSize: 20, fontWeight: 700, color: '#c9b3ff', letterSpacing: -0.5 }}>
          You&apos;re browsing privately
        </div>
        <div style={{ fontSize: 13, color: 'rgba(200,180,255,0.55)', textAlign: 'center', maxWidth: 440, lineHeight: 1.6 }}>
          Pages you visit won&apos;t appear in history and won&apos;t be synced.
          Downloads are not saved to the download list.
          Bookmarks and link tools still work normally.
        </div>
      </div>

      {/* Search bar */}
      <form onSubmit={handleSearch} style={{ width: '100%', maxWidth: 520, marginBottom: 16 }}>
        <input
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Search or enter URL"
          autoFocus
          style={{
            width: '100%',
            height: 48,
            borderRadius: 24,
            border: '2px solid rgba(140,100,240,0.4)',
            background: 'rgba(30,20,50,0.8)',
            color: '#e0d7ff',
            padding: '0 20px',
            fontSize: 15,
            outline: 'none',
            transition: 'border-color 0.15s',
          }}
          onFocus={e => { e.target.style.borderColor = 'rgba(160,120,255,0.8)'; }}
          onBlur={e => { e.target.style.borderColor = 'rgba(140,100,240,0.4)'; }}
        />
      </form>
    </div>
  );
}

// ── Normal new tab page ───────────────────────────────────────────────────────

export function NewTabPage({ onNavigate, isPrivate = false }: Props) {
  const [query, setQuery] = useState('');
  const [recentHistory, setRecentHistory] = useState<HistoryEntry[]>([]);
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const load = async () => {
      try {
        const history = await window.zio.history.recent() as HistoryEntry[];
        setRecentHistory(history.slice(0, 8));
      } catch {
        // ignore
      }
    };
    void load();
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const handleSearch = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    if (!query.trim()) return;
    onNavigate(query.trim());
  }, [query, onNavigate]);

  if (isPrivate) {
    return <PrivateNewTabPage onNavigate={onNavigate} />;
  }

  const formatTime = (d: Date) =>
    d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  const formatDate = (d: Date) =>
    d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });

  const WHY_ZIO = [
    { icon: '🛡️', title: 'Private by design', desc: 'Built-in tracker blocking and true private windows — no ads following you around.' },
    { icon: '🗂️', title: 'Three ways to work', desc: 'Browser, Dashboard, or Split mode — switch between browsing and your workspace instantly.' },
    { icon: '👤', title: 'Profiles that stay separate', desc: 'Work and personal logins live in fully separate profiles, one click apart.' },
    { icon: '⚡', title: 'Sayzio tools built in', desc: 'Shorten links, build biolinks, and create QR codes from any page you visit.' },
  ];

  const QUICK_LINKS = [
    { title: 'Sayzio', url: 'https://1in.me', icon: '⚡' },
    { title: 'Gmail', url: 'https://mail.google.com', icon: '📧' },
    { title: 'GitHub', url: 'https://github.com', icon: '🐙' },
    { title: 'LinkedIn', url: 'https://linkedin.com', icon: '💼' },
    { title: 'Twitter/X', url: 'https://x.com', icon: '🐦' },
    { title: 'YouTube', url: 'https://youtube.com', icon: '▶️' },
  ];

  return (
    <div style={{
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      justifyContent: 'center',
      padding: 40,
      background: 'var(--color-bg)',
      overflowY: 'auto',
      position: 'relative',
    }}>
      {/* Active profile ribbon (top corner) */}
      <div style={{ position: 'absolute', top: 16, right: 16 }}>
        <ProfileBadge variant="ribbon" />
      </div>

      {/* Clock */}
      <div style={{ textAlign: 'center', marginBottom: 40 }}>
        <div style={{ fontSize: 56, fontWeight: 200, letterSpacing: -2, color: 'var(--color-text)' }}>
          {formatTime(currentTime)}
        </div>
        <div style={{ fontSize: 14, color: 'var(--color-text-muted)', marginTop: 4 }}>
          {formatDate(currentTime)}
        </div>
      </div>

      {/* Search bar */}
      <form onSubmit={handleSearch} style={{ width: '100%', maxWidth: 560, marginBottom: 40 }}>
        <input
          value={query}
          onChange={e => setQuery(e.target.value)}
          placeholder="Search the web or enter a URL"
          autoFocus
          style={{
            width: '100%',
            height: 48,
            borderRadius: 24,
            border: '2px solid var(--color-border)',
            background: 'var(--color-bg-surface)',
            color: 'var(--color-text)',
            padding: '0 20px',
            fontSize: 15,
            outline: 'none',
            transition: 'border-color 0.15s',
          }}
          onFocus={e => { e.target.style.borderColor = 'var(--color-primary)'; }}
          onBlur={e => { e.target.style.borderColor = 'var(--color-border)'; }}
        />
      </form>

      {/* Quick links */}
      <div style={{ display: 'flex', gap: 16, marginBottom: 40, flexWrap: 'wrap', justifyContent: 'center' }}>
        {QUICK_LINKS.map(link => (
          <button
            key={link.url}
            onClick={() => onNavigate(link.url)}
            style={{
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              gap: 6,
              padding: '12px 16px',
              borderRadius: 12,
              background: 'var(--color-bg-surface)',
              border: '1px solid var(--color-border)',
              width: 72,
              cursor: 'pointer',
              transition: 'all 0.15s',
            }}
            onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
            onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-surface)'; }}
          >
            <span style={{ fontSize: 24 }}>{link.icon}</span>
            <span style={{ fontSize: 11, color: 'var(--color-text-muted)', textAlign: 'center' }}>{link.title}</span>
          </button>
        ))}
      </div>

      {/* Recent history */}
      {recentHistory.length > 0 && (
        <div style={{ width: '100%', maxWidth: 560 }}>
          <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 10, textTransform: 'uppercase', letterSpacing: 1 }}>
            Recent
          </p>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
            {recentHistory.map(entry => (
              <button
                key={entry.id}
                onClick={() => onNavigate(entry.url)}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 8,
                  padding: '8px 12px',
                  borderRadius: 10,
                  background: 'var(--color-bg-surface)',
                  border: '1px solid var(--color-border)',
                  textAlign: 'left',
                  cursor: 'pointer',
                  overflow: 'hidden',
                }}
              >
                {entry.favicon_url ? (
                  <img src={entry.favicon_url} width={16} height={16} style={{ borderRadius: 2, flexShrink: 0 }} alt="" />
                ) : (
                  <div style={{ width: 16, height: 16, borderRadius: 2, background: 'var(--color-border)', flexShrink: 0 }} />
                )}
                <span style={{
                  fontSize: 12,
                  color: 'var(--color-text)',
                  overflow: 'hidden',
                  textOverflow: 'ellipsis',
                  whiteSpace: 'nowrap',
                }}>
                  {entry.title ?? entry.url}
                </span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Why Zio? */}
      <div style={{ width: '100%', maxWidth: 560, marginTop: 40 }}>
        <p style={{ fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)', marginBottom: 10, textTransform: 'uppercase', letterSpacing: 1 }}>
          Why Zio?
        </p>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
          {WHY_ZIO.map(f => (
            <div
              key={f.title}
              style={{
                display: 'flex',
                gap: 10,
                padding: '12px 14px',
                borderRadius: 12,
                background: 'var(--color-bg-surface)',
                border: '1px solid var(--color-border)',
                alignItems: 'flex-start',
              }}
            >
              <span style={{ fontSize: 20, lineHeight: '24px' }}>{f.icon}</span>
              <span>
                <span style={{ display: 'block', fontSize: 12, fontWeight: 600, color: 'var(--color-text)', marginBottom: 2 }}>
                  {f.title}
                </span>
                <span style={{ display: 'block', fontSize: 11, color: 'var(--color-text-muted)', lineHeight: 1.5 }}>
                  {f.desc}
                </span>
              </span>
            </div>
          ))}
        </div>
        <div style={{ textAlign: 'center', marginTop: 14 }}>
          <button
            onClick={() => onNavigate('https://sayzio.app')}
            style={{
              background: 'none',
              border: 'none',
              cursor: 'pointer',
              fontSize: 12,
              color: 'var(--color-primary)',
              fontWeight: 600,
              padding: '6px 10px',
            }}
          >
            Learn more at sayzio.app →
          </button>
        </div>
      </div>
    </div>
  );
}
