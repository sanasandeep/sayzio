/**
 * New Tab page — shown when no URL is loaded.
 * A Zio "home base": time-aware greeting scene with the animated mascot,
 * a command bar with Sayzio quick actions, folders (local collections),
 * "continue where you left off" session groups, and a daily privacy strip.
 * In private/incognito mode shows a minimalist private-mode splash instead.
 */
import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import type { HistoryEntry } from '../../main/db';
import type { Collection, SavedLink } from '../../shared/collection-store';
import { ApiClient } from '../../shared/api-client';
import type { ApiProject } from '../../shared/api-client';
import { ProfileBadge } from './ProfileBadge';
import { resolveFavicon } from '../../shared/favicon';
import { FaviconImg } from './FaviconImg';
import { useAuthStore } from '../store/auth-store';
import { SayzioIcon, GmailIcon, GitHubIcon, LinkedInIcon, XIcon, YouTubeIcon } from './BrandIcons';
import zioMascot from '../assets/zio-mascot-icon.png';

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

// ── Time-of-day phases (greeting scene) ───────────────────────────────────────

type DayPhase = 'dawn' | 'day' | 'dusk' | 'night';

function dayPhase(d: Date): DayPhase {
  const h = d.getHours();
  if (h >= 5 && h < 9) return 'dawn';
  if (h >= 9 && h < 17) return 'day';
  if (h >= 17 && h < 21) return 'dusk';
  return 'night';
}

const PHASE_STYLES: Record<DayPhase, { wash: string; greeting: string; accent: string }> = {
  dawn:  { wash: 'radial-gradient(90% 55% at 50% 0%, rgba(255,170,120,0.14) 0%, rgba(255,120,160,0.06) 45%, transparent 75%)', greeting: 'Good morning',   accent: '#fdba74' },
  day:   { wash: 'radial-gradient(90% 55% at 50% 0%, rgba(96,165,250,0.13) 0%, rgba(56,189,248,0.05) 45%, transparent 75%)',  greeting: 'Good afternoon', accent: '#60a5fa' },
  dusk:  { wash: 'radial-gradient(90% 55% at 50% 0%, rgba(192,132,252,0.15) 0%, rgba(244,114,182,0.06) 45%, transparent 75%)', greeting: 'Good evening',   accent: '#c084fc' },
  night: { wash: 'radial-gradient(90% 55% at 50% 0%, rgba(122,92,255,0.14) 0%, rgba(79,124,255,0.05) 45%, transparent 75%)',   greeting: 'Good night',     accent: '#8da2ff' },
};

// Folder card palette, cycled by index — mirrors the Sayzio Folders page look.
const FOLDER_COLORS = ['#ec4899', '#f59e0b', '#22c55e', '#3b82f6', '#a855f7', '#14b8a6', '#ef4444', '#6366f1'];

interface SessionGroup {
  host: string;
  entries: HistoryEntry[];
}

// ── Normal new tab page ───────────────────────────────────────────────────────

export function NewTabPage({ onNavigate, isPrivate = false }: Props) {
  const [query, setQuery] = useState('');
  const [searchFocused, setSearchFocused] = useState(false);
  const [recentHistory, setRecentHistory] = useState<HistoryEntry[]>([]);
  const [currentTime, setCurrentTime] = useState(new Date());
  const [collections, setCollections] = useState<Collection[]>([]);
  // Account folders ("projects") — the same folders the sayzio.app dashboard
  // shows. Non-null once loaded for a signed-in user; they take precedence
  // over local collections so both surfaces show the same folders.
  const [accountFolders, setAccountFolders] = useState<ApiProject[] | null>(null);
  const [openFolderId, setOpenFolderId] = useState<string | null>(null);
  const [folderLinks, setFolderLinks] = useState<SavedLink[]>([]);
  const [folderLinksLoading, setFolderLinksLoading] = useState(false);
  const [creatingFolder, setCreatingFolder] = useState(false);
  const [newFolderName, setNewFolderName] = useState('');
  const [trackerStats, setTrackerStats] = useState<{ todayTotal: number; weekTotal: number } | null>(null);
  const { user, token } = useAuthStore();

  const getClient = useCallback((): ApiClient | null => {
    if (!token) return null;
    return new ApiClient({ baseUrl: 'https://sayzio.app', token });
  }, [token]);

  // Epoch guard: bumped whenever the window flips to private OR the signed-in
  // token changes, so any folders/links request already in flight for the old
  // account (or the normal profile) can't repopulate state afterwards.
  const loadEpochRef = useRef(0);
  useEffect(() => {
    loadEpochRef.current += 1;
    // Token flip (login/logout/account switch): drop anything account-derived
    // immediately; the load effect below repopulates for the new state.
    setAccountFolders(null);
    openFolderRef.current = null;
    setOpenFolderId(null);
    setFolderLinks([]);
  }, [token]);

  const loadCollections = useCallback(async () => {
    const epoch = loadEpochRef.current;
    // Signed in → show the account folders (same as the web dashboard's
    // folders desk) so both surfaces stay in sync. Local collections remain
    // the signed-out/offline fallback.
    const client = getClient();
    if (client) {
      try {
        const res = await client.listProjects();
        if (loadEpochRef.current === epoch) {
          setAccountFolders(res.items);
          setCollections([]);
        }
        return;
      } catch { /* offline / API unavailable — fall back to local */ }
    }
    try {
      const all = await window.zio.collections.all() as Collection[];
      if (loadEpochRef.current === epoch) { setAccountFolders(null); setCollections(all); }
    } catch { /* collections unavailable — hide section */ }
  }, [getClient]);

  useEffect(() => {
    if (isPrivate) {
      loadEpochRef.current += 1;
      // Reset anything loaded while the window was normal so a mode switch
      // never keeps normal-profile data (history/folders/stats) in state.
      setRecentHistory([]);
      setCollections([]);
      setAccountFolders(null);
      setTrackerStats(null);
      setOpenFolderId(null);
      setFolderLinks([]);
      return;
    }
    let cancelled = false;
    const load = async () => {
      try {
        const history = await window.zio.history.recent() as HistoryEntry[];
        if (!cancelled) setRecentHistory(history);
      } catch { /* ignore */ }
      try {
        const stats = await window.zio.privacy.trackerStats();
        if (!cancelled) setTrackerStats({ todayTotal: stats.todayTotal, weekTotal: stats.weekTotal });
      } catch { /* stats unavailable — hide strip */ }
      if (!cancelled) void loadCollections();
    };
    void load();
    // The clock shows hours:minutes only — a 30s tick keeps it accurate
    // without re-rendering the whole page every second.
    const timer = setInterval(() => setCurrentTime(new Date()), 30000);
    return () => { cancelled = true; clearInterval(timer); };
  }, [isPrivate, loadCollections]);

  const handleSearch = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    if (!query.trim()) return;
    onNavigate(query.trim());
  }, [query, onNavigate]);

  // Mirrors openFolderId for in-flight request staleness checks (state reads
  // inside an async closure would be captured stale).
  const openFolderRef = useRef<string | null>(null);

  const openFolder = useCallback(async (id: string) => {
    if (openFolderId === id) {
      openFolderRef.current = null;
      setOpenFolderId(null);
      setFolderLinks([]);
      return;
    }
    const epoch = loadEpochRef.current;
    // Selection guard: a slower earlier folder request must never overwrite
    // the currently selected folder's contents (or leak across private/token
    // flips — epoch covers those).
    const stale = () => loadEpochRef.current !== epoch || openFolderRef.current !== id;
    openFolderRef.current = id;
    setOpenFolderId(id);
    setFolderLinks([]);
    setFolderLinksLoading(true);
    try {
      if (id.startsWith('p:')) {
        // Account folder — list its links from the API and map them onto the
        // SavedLink display shape (destination first, short link fallback).
        const client = getClient();
        const res = client ? await client.listLinks({ project_id: Number(id.slice(2)), per_page: 100 }) : { items: [] };
        if (stale()) return;
        setFolderLinks(res.items.map(l => ({
          id: String(l.id),
          collection_id: id,
          url: l.long_url || l.short_url,
          title: l.title || l.alias,
          favicon_url: null,
        } as unknown as SavedLink)));
      } else {
        const links = await window.zio.collections.getLinks(id) as SavedLink[];
        if (stale()) return;
        setFolderLinks(links);
      }
    } catch {
      if (!stale()) setFolderLinks([]);
    } finally {
      if (!stale()) setFolderLinksLoading(false);
    }
  }, [openFolderId, getClient]);

  const createFolder = useCallback(async () => {
    const name = newFolderName.trim();
    if (!name) return;
    try {
      const client = accountFolders !== null ? getClient() : null;
      if (client) {
        const color = FOLDER_COLORS[accountFolders!.length % FOLDER_COLORS.length];
        await client.createProject({ name: name.slice(0, 120), color });
      } else {
        const color = FOLDER_COLORS[collections.length % FOLDER_COLORS.length];
        await window.zio.collections.create(name, { color });
      }
      setNewFolderName('');
      setCreatingFolder(false);
      await loadCollections();
    } catch { /* keep the input open so the user can retry */ }
  }, [newFolderName, collections.length, accountFolders, getClient, loadCollections]);

  // "Continue where you left off": group recent history by hostname and keep
  // groups with 2+ distinct pages, newest first, top 4.
  const sessionGroups = useMemo<SessionGroup[]>(() => {
    const byHost = new Map<string, HistoryEntry[]>();
    for (const entry of recentHistory) {
      let host: string;
      try { host = new URL(entry.url).hostname.replace(/^www\./, ''); } catch { continue; }
      if (!host) continue;
      const list = byHost.get(host) ?? [];
      if (list.length < 4 && !list.some(e => e.url === entry.url)) list.push(entry);
      byHost.set(host, list);
    }
    return [...byHost.entries()]
      .filter(([, entries]) => entries.length >= 2)
      .slice(0, 4)
      .map(([host, entries]) => ({ host, entries }));
  }, [recentHistory]);

  const reopenSession = useCallback((group: SessionGroup) => {
    const [first, ...rest] = group.entries;
    for (const entry of rest) {
      try { void window.zio.tabs.create(entry.url, true); } catch { /* ignore */ }
    }
    onNavigate(first.url);
  }, [onNavigate]);

  if (isPrivate) {
    return <PrivateNewTabPage onNavigate={onNavigate} />;
  }

  const phase = dayPhase(currentTime);
  const phaseStyle = PHASE_STYLES[phase];
  const firstName = (user?.name ?? '').trim().split(/\s+/)[0] || null;

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

  // Sayzio quick actions surfaced under the command bar.
  const QUICK_ACTIONS = [
    { label: 'Shorten a link', icon: '🔗', url: 'https://sayzio.app/user/links/create' },
    { label: 'QR code', icon: '▦', url: 'https://sayzio.app/user/qr-codes' },
    { label: 'Bio link page', icon: '👤', url: 'https://sayzio.app/user/links/create' },
    { label: 'Dashboard', icon: '📊', url: 'https://sayzio.app/user/dashboard' },
  ];

  const sectionLabel: React.CSSProperties = {
    fontSize: 11, fontWeight: 600, color: 'var(--color-text-muted)',
    marginBottom: 10, textTransform: 'uppercase', letterSpacing: 1,
  };

  const cardBase: React.CSSProperties = {
    borderRadius: 12,
    background: 'var(--color-bg-surface)',
    border: '1px solid var(--color-border)',
    cursor: 'pointer',
  };

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
      background: `${phaseStyle.wash}, var(--color-bg)`,
      overflowY: 'auto',
      position: 'relative',
    }}>
      {/* Active profile ribbon (top corner) */}
      <div style={{ position: 'absolute', top: 16, right: 16 }}>
        <ProfileBadge variant="ribbon" />
      </div>

      {/* Greeting scene: mascot + greeting + clock */}
      <div className="zio-newtab-rise" style={{ textAlign: 'center', marginBottom: 28 }}>
        <div className={`zio-newtab-mascot${searchFocused ? ' is-excited' : ''}`} style={{ display: 'inline-block', marginBottom: 10 }}>
          <div className="zio-newtab-mascot-inner">
            <img src={zioMascot} width={84} height={84} alt="" draggable={false} style={{ display: 'block' }} />
          </div>
        </div>
        <div style={{ fontSize: 24, fontWeight: 700, letterSpacing: -0.5, color: 'var(--color-text)' }}>
          {phaseStyle.greeting}{firstName ? `, ${firstName}` : ''}
        </div>
        <div style={{ fontSize: 40, fontWeight: 200, letterSpacing: -1.5, color: 'var(--color-text)', lineHeight: 1.2 }}>
          {formatTime(currentTime)}
        </div>
        <div style={{ fontSize: 13, color: 'var(--color-text-muted)', marginTop: 2 }}>
          {formatDate(currentTime)}
        </div>
      </div>

      {/* Command bar + Sayzio quick actions */}
      <div className="zio-newtab-rise" style={{ width: '100%', maxWidth: 560, marginBottom: 14, animationDelay: '0.08s' }}>
        <form onSubmit={handleSearch}>
          <input
            value={query}
            onChange={e => setQuery(e.target.value)}
            placeholder="Search the web, enter a URL…"
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
              transition: 'border-color 0.15s, box-shadow 0.15s',
            }}
            onFocus={e => { setSearchFocused(true); e.target.style.borderColor = 'var(--color-primary)'; e.target.style.boxShadow = '0 0 0 4px rgba(99,102,241,0.12)'; }}
            onBlur={e => { setSearchFocused(false); e.target.style.borderColor = 'var(--color-border)'; e.target.style.boxShadow = 'none'; }}
          />
        </form>
        <div style={{ display: 'flex', gap: 8, marginTop: 10, flexWrap: 'wrap', justifyContent: 'center' }}>
          {QUICK_ACTIONS.map(a => (
            <button
              key={a.label}
              onClick={() => onNavigate(a.url)}
              style={{
                ...cardBase,
                display: 'inline-flex',
                alignItems: 'center',
                gap: 6,
                padding: '6px 12px',
                borderRadius: 16,
                fontSize: 12,
                fontWeight: 600,
                color: 'var(--color-text)',
              }}
              onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
              onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-surface)'; }}
            >
              <span aria-hidden="true">{a.icon}</span>{a.label}
            </button>
          ))}
        </div>
      </div>

      {/* Daily privacy strip */}
      {trackerStats && (trackerStats.todayTotal > 0 || trackerStats.weekTotal > 0) && (
        <div className="zio-newtab-rise" style={{
          display: 'inline-flex', alignItems: 'center', gap: 8,
          padding: '6px 14px', borderRadius: 16, marginBottom: 28,
          background: 'var(--color-bg-surface)', border: '1px solid var(--color-border)',
          fontSize: 12, color: 'var(--color-text-muted)', animationDelay: '0.14s',
        }}>
          <span aria-hidden="true">🛡️</span>
          <span>
            <strong style={{ color: phaseStyle.accent }}>{trackerStats.todayTotal}</strong> trackers blocked today
            <span style={{ opacity: 0.6 }}> · {trackerStats.weekTotal} this week</span>
          </span>
        </div>
      )}

      {/* Quick links */}
      <div className="zio-newtab-rise" style={{ display: 'flex', gap: 16, marginBottom: 32, flexWrap: 'wrap', justifyContent: 'center', animationDelay: '0.18s' }}>
        {QUICK_LINKS.map(link => (
          <button
            key={link.url}
            onClick={() => onNavigate(link.url)}
            style={{
              ...cardBase,
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              gap: 6,
              padding: '12px 16px',
              width: 72,
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

      {/* Folders — account folders (synced with the sayzio.app dashboard) when
          signed in, local collections otherwise. Cards use the same 3D
          folder-flip animation as the web dashboard's folders desk. */}
      <style>{`
        .zio-fld { position: relative; width: 74px; height: 56px; margin: 0 auto; perspective: 320px; }
        .zio-fld-back, .zio-fld-front { position: absolute; inset: 0; border-radius: 7px; }
        .zio-fld-back { background: color-mix(in srgb, var(--fc) 72%, #0b1220); }
        .zio-fld-back::before {
          content: ''; position: absolute; top: -7px; left: 0; width: 34%; height: 12px;
          border-radius: 6px 8px 0 0; background: inherit;
        }
        .zio-fld-paper {
          position: absolute; left: 8px; right: 8px; top: 3px; bottom: 6px; border-radius: 4px;
          background: linear-gradient(180deg, #fff, #dbe3ef); box-shadow: 0 1px 3px rgba(2,6,23,0.25);
          transition: transform .28s cubic-bezier(.34,1.4,.5,1);
        }
        .zio-fld-front {
          top: 9px; transform-origin: bottom center; transform-style: preserve-3d;
          background: linear-gradient(180deg, color-mix(in srgb, var(--fc) 92%, #fff), color-mix(in srgb, var(--fc) 82%, #0b1220));
          box-shadow: 0 6px 14px -6px color-mix(in srgb, var(--fc) 55%, transparent);
          transition: transform .28s cubic-bezier(.34,1.4,.5,1);
          display: flex; align-items: flex-end; justify-content: flex-end; padding: 4px 6px;
        }
        .zio-desk-item:hover .zio-fld-front, .zio-desk-item:focus-visible .zio-fld-front, .zio-desk-item.is-open .zio-fld-front { transform: rotateX(-30deg); }
        .zio-desk-item:hover .zio-fld-paper, .zio-desk-item:focus-visible .zio-fld-paper, .zio-desk-item.is-open .zio-fld-paper { transform: translateY(-7px); }
        .zio-fld-count {
          font-size: 10px; font-weight: 800; line-height: 1; color: #fff;
          background: rgba(2,6,23,0.35); border-radius: 999px; padding: 3px 6px;
        }
        @media (prefers-reduced-motion: reduce) {
          .zio-fld-front, .zio-fld-paper { transition: none !important; }
          .zio-desk-item:hover .zio-fld-front, .zio-desk-item.is-open .zio-fld-front { transform: none; }
          .zio-desk-item:hover .zio-fld-paper, .zio-desk-item.is-open .zio-fld-paper { transform: none; }
        }
      `}</style>
      <div className="zio-newtab-rise" style={{ width: '100%', maxWidth: 560, marginBottom: 32, animationDelay: '0.22s' }}>
        <p style={sectionLabel}>Folders</p>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(120px, 1fr))', gap: 10 }}>
          {(accountFolders
            ? accountFolders.map(p => ({ key: `p:${p.id}`, name: p.name, color: p.color, count: p.links_count ?? 0 }))
            : collections.map(c => ({ key: c.id, name: c.name, color: c.color, count: c.item_count ?? 0 }))
          ).map((f, i) => {
            const color = f.color || FOLDER_COLORS[i % FOLDER_COLORS.length];
            const isOpen = openFolderId === f.key;
            return (
              <button
                key={f.key}
                onClick={() => void openFolder(f.key)}
                className={`zio-desk-item${isOpen ? ' is-open' : ''}`}
                style={{
                  ...cardBase,
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  gap: 8,
                  padding: '16px 10px 12px',
                  borderColor: isOpen ? color : 'var(--color-border)',
                  transition: 'all 0.15s',
                }}
              >
                <span className="zio-fld" style={{ ['--fc' as string]: color }}>
                  <span className="zio-fld-back" />
                  <span className="zio-fld-paper" />
                  <span className="zio-fld-front"><span className="zio-fld-count">{f.count}</span></span>
                </span>
                <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--color-text)', maxWidth: '100%', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {f.name}
                </span>
                <span style={{ fontSize: 10.5, color: 'var(--color-text-muted)' }}>
                  {f.count} {f.count === 1 ? 'link' : 'links'}
                </span>
              </button>
            );
          })}

          {/* New folder card */}
          {creatingFolder ? (
            <div style={{
              ...cardBase,
              cursor: 'default',
              display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              gap: 8, padding: '14px 10px', borderStyle: 'dashed',
            }}>
              <input
                value={newFolderName}
                onChange={e => setNewFolderName(e.target.value)}
                onKeyDown={e => {
                  if (e.key === 'Enter') void createFolder();
                  if (e.key === 'Escape') { setCreatingFolder(false); setNewFolderName(''); }
                }}
                placeholder="Folder name"
                autoFocus
                style={{
                  width: '100%', fontSize: 12, padding: '6px 8px', borderRadius: 8,
                  border: '1px solid var(--color-border)', background: 'var(--color-bg)',
                  color: 'var(--color-text)', outline: 'none',
                }}
              />
              <button
                onClick={() => void createFolder()}
                style={{
                  fontSize: 11, fontWeight: 700, color: '#fff', border: 'none', cursor: 'pointer',
                  padding: '5px 12px', borderRadius: 8, background: 'var(--color-primary)',
                }}
              >
                Create
              </button>
            </div>
          ) : (
            <button
              onClick={() => setCreatingFolder(true)}
              style={{
                ...cardBase,
                display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                gap: 6, padding: '14px 10px', borderStyle: 'dashed', background: 'transparent',
              }}
              onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-surface)'; }}
              onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'transparent'; }}
            >
              <span style={{ fontSize: 22, lineHeight: '40px', color: 'var(--color-text-muted)' }}>＋</span>
              <span style={{ fontSize: 12, fontWeight: 600, color: 'var(--color-text-muted)' }}>New Folder</span>
            </button>
          )}
        </div>

        {/* Open folder contents */}
        {openFolderId && (
          <div style={{
            marginTop: 10, borderRadius: 12, border: '1px solid var(--color-border)',
            background: 'var(--color-bg-surface)', padding: 10,
          }}>
            {folderLinksLoading ? (
              <div style={{ fontSize: 12, color: 'var(--color-text-muted)', padding: '6px 4px' }}>Loading…</div>
            ) : folderLinks.length === 0 ? (
              <div style={{ fontSize: 12, color: 'var(--color-text-muted)', padding: '6px 4px' }}>
                No links in this folder yet — save any page into it from the Zio panel.
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 6 }}>
                {folderLinks.map(link => (
                  <button
                    key={link.id}
                    onClick={() => onNavigate(link.url)}
                    style={{
                      display: 'flex', alignItems: 'center', gap: 8, padding: '7px 10px',
                      borderRadius: 8, background: 'transparent', border: 'none',
                      textAlign: 'left', cursor: 'pointer', overflow: 'hidden',
                    }}
                    onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
                    onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'transparent'; }}
                  >
                    <FaviconImg
                      src={resolveFavicon(link.favicon_url, link.url)}
                      size={16}
                      fallback={<FaviconFallback url={link.url} size={16} />}
                    />
                    <span style={{ fontSize: 12, color: 'var(--color-text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                      {link.title || link.url}
                    </span>
                  </button>
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      {/* Continue where you left off */}
      {sessionGroups.length > 0 && (
        <div className="zio-newtab-rise" style={{ width: '100%', maxWidth: 560, marginBottom: 32, animationDelay: '0.26s' }}>
          <p style={sectionLabel}>Continue where you left off</p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 8 }}>
            {sessionGroups.map(group => (
              <button
                key={group.host}
                onClick={() => reopenSession(group)}
                title={`Reopen ${group.entries.length} pages from ${group.host}`}
                style={{
                  ...cardBase,
                  display: 'flex', flexDirection: 'column', gap: 6,
                  padding: '10px 12px', textAlign: 'left', overflow: 'hidden',
                }}
                onMouseEnter={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-elevated)'; }}
                onMouseLeave={e => { (e.currentTarget as HTMLButtonElement).style.background = 'var(--color-bg-surface)'; }}
              >
                <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <FaviconImg
                    src={resolveFavicon(group.entries[0].favicon_url, group.entries[0].url)}
                    size={16}
                    fallback={<FaviconFallback url={group.entries[0].url} size={16} />}
                  />
                  <span style={{ fontSize: 12.5, fontWeight: 700, color: 'var(--color-text)' }}>{group.host}</span>
                  <span style={{
                    marginLeft: 'auto', fontSize: 10.5, fontWeight: 600, flexShrink: 0,
                    color: 'var(--color-primary)',
                  }}>
                    {group.entries.length} pages ↗
                  </span>
                </span>
                <span style={{ fontSize: 11, color: 'var(--color-text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {group.entries.map(e => e.title || e.url).join(' · ')}
                </span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Recent history (single pages) */}
      {recentHistory.length > 0 && (
        <div className="zio-newtab-rise" style={{ width: '100%', maxWidth: 560, animationDelay: '0.3s' }}>
          <p style={sectionLabel}>Recent</p>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: 8 }}>
            {recentHistory.slice(0, 8).map(entry => (
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
        <p style={sectionLabel}>
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
