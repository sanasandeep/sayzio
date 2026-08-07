/**
 * New Tab page — shown when no URL is loaded.
 * Shows a search bar, quick links, and recent history.
 * In private/incognito mode shows a minimalist private-mode splash instead.
 */
import { useState, useEffect, useCallback } from 'react';
import type { HistoryEntry } from '../../main/db';
import { ProfileBadge } from './ProfileBadge';
import { resolveFavicon } from '../../shared/favicon';
import { FaviconImg } from './FaviconImg';
import { SayzioIcon, GmailIcon, GitHubIcon, LinkedInIcon, XIcon, YouTubeIcon } from './BrandIcons';

/**
 * Neutral fallback for favicons that can't be loaded: the site's first
 * hostname letter in a rounded tile, or a globe glyph when no hostname
 * can be derived. Keeps icon slots from looking like blank gray boxes.
 */
function FaviconFallback({ url, size }: { url: string | null | undefined; size: number }) {
  let letter: string | null = null;
  if (url) {
    try {
      const host = new URL(url).hostname.replace(/^www\./, '');
      if (host) letter = host[0].toUpperCase();
    } catch { /* not a parseable URL */ }
  }
  return (
    <div
      style={{
        width: size,
        height: size,
        borderRadius: size >= 16 ? 3 : 2,
        background: 'var(--color-bg-elevated)',
        border: '1px solid var(--color-border)',
        color: 'var(--color-text-muted)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        fontSize: Math.round(size * 0.62),
        fontWeight: 600,
        lineHeight: 1,
        flexShrink: 0,
        boxSizing: 'border-box',
      }}
      aria-hidden="true"
    >
      {letter ?? (
        <svg width={Math.round(size * 0.75)} height={Math.round(size * 0.75)} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
          <circle cx="12" cy="12" r="10" />
          <path d="M2 12h20M12 2a15.3 15.3 0 0 1 0 20 15.3 15.3 0 0 1 0-20Z" />
        </svg>
      )}
    </div>
  );
}

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
      // 'safe center' keeps the top of the content reachable (scrollable)
      // when the window is shorter than the content instead of cropping it.
      justifyContent: 'safe center',
      padding: 'clamp(16px, 5vw, 40px)',
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
        <div style={{ fontSize: 20, fontWeight: 700, color: '#93c5fd', letterSpacing: -0.5 }}>
          You&apos;re browsing privately
        </div>
        <div style={{ fontSize: 13, color: 'rgba(147,197,253,0.55)', textAlign: 'center', maxWidth: 440, lineHeight: 1.6 }}>
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
            border: '2px solid rgba(59,130,246,0.4)',
            background: 'rgba(15,25,50,0.8)',
            color: '#dbeafe',
            padding: '0 20px',
            fontSize: 15,
            outline: 'none',
            transition: 'border-color 0.15s',
          }}
          onFocus={e => { e.target.style.borderColor = 'rgba(96,165,250,0.8)'; }}
          onBlur={e => { e.target.style.borderColor = 'rgba(59,130,246,0.4)'; }}
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
    { title: 'Sayzio', url: 'https://sayzio.app', icon: <SayzioIcon size={24} /> },
    { title: 'Gmail', url: 'https://mail.google.com', icon: <GmailIcon size={24} /> },
    { title: 'GitHub', url: 'https://github.com', icon: <GitHubIcon size={24} /> },
    { title: 'LinkedIn', url: 'https://linkedin.com', icon: <LinkedInIcon size={24} /> },
    { title: 'Twitter/X', url: 'https://x.com', icon: <XIcon size={24} /> },
    { title: 'YouTube', url: 'https://youtube.com', icon: <YouTubeIcon size={24} /> },
  ];

  return (
    <div style={{
      flex: 1,
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      // 'safe center' keeps the top of the content reachable (scrollable)
      // when the window is shorter than the content instead of cropping it.
      justifyContent: 'safe center',
      padding: 'clamp(16px, 5vw, 40px)',
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
            <span style={{ width: 24, height: 24, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{link.icon}</span>
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
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 8 }}>
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
                <FaviconImg
                  src={resolveFavicon(entry.favicon_url, entry.url)}
                  size={16}
                  fallback={<FaviconFallback url={entry.url} size={16} />}
                />
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
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 8 }}>
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
          <div style={{ marginTop: 6, fontSize: 11.5 }}>
            <button
              onClick={() => onNavigate('about:sayzio')}
              style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--color-text-muted)', padding: '4px 8px' }}
            >
              About Sayzio
            </button>
            <span style={{ color: 'var(--color-text-muted)', opacity: 0.5 }}>·</span>
            <button
              onClick={() => onNavigate('about:zio')}
              style={{ background: 'none', border: 'none', cursor: 'pointer', color: 'var(--color-text-muted)', padding: '4px 8px' }}
            >
              About Zio Browser
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
