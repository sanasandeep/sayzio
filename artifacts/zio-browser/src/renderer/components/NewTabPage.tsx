/**
 * New Tab page — shown when no URL is loaded.
 * Shows a search bar, quick links, and recent history.
 */
import { useState, useEffect, useCallback } from 'react';
import type { HistoryEntry } from '../../main/db';

interface Props {
  onNavigate: (url: string) => void;
}

export function NewTabPage({ onNavigate }: Props) {
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

  const formatTime = (d: Date) =>
    d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  const formatDate = (d: Date) =>
    d.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric' });

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
    }}>
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
    </div>
  );
}
