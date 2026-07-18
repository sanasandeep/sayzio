/**
 * BrowserToolsView — the "Browser" tab in the Zio panel.
 * Shows History, Cookies, Passwords, and Downloads with local browser-management actions.
 * All data stays on-device; nothing is sent to the Sayzio backend.
 */
import { useState, useEffect, useCallback, useRef } from 'react';

// ── Shared types ──────────────────────────────────────────────────────────────

interface HistoryEntry {
  id: string;
  url: string;
  title: string | null;
  last_visited: string;
  visit_count: number;
}

interface CookieInfo {
  name: string;
  value: string;
  domain: string;
  path: string;
  secure?: boolean;
  httpOnly?: boolean;
  session?: boolean;
  expirationDate?: number;
}

interface PasswordEntry {
  id: string;
  origin: string;
  username: string;
  created_at: string;
  updated_at: string;
}

interface DownloadEntry {
  id: string;
  url: string;
  filename: string;
  save_path: string | null;
  state: string;
  total_bytes: number | null;
  received_bytes: number;
  created_at: string;
  completed_at: string | null;
}

type BrowserSection = 'history' | 'cookies' | 'passwords' | 'downloads';

interface ConfirmState {
  message: string;
  onConfirm: () => void;
}

// ── Root component ────────────────────────────────────────────────────────────

interface Props {
  currentUrl: string | null;
  /** When set, jump directly to this section (from a chat assistant intent). */
  focusSection?: BrowserSection | null;
  onFocusSectionConsumed?: () => void;
}

export function BrowserToolsView({ currentUrl, focusSection, onFocusSectionConsumed }: Props) {
  const [section, setSection] = useState<BrowserSection>('history');
  const [confirm, setConfirm] = useState<ConfirmState | null>(null);

  // Jump to the section requested by the chat assistant
  useEffect(() => {
    if (focusSection) {
      setSection(focusSection);
      onFocusSectionConsumed?.();
    }
  }, [focusSection, onFocusSectionConsumed]);

  const requestConfirm = useCallback((message: string, onConfirm: () => void) => {
    setConfirm({ message, onConfirm });
  }, []);

  const sections: { id: BrowserSection; label: string }[] = [
    { id: 'history', label: '🕐 History' },
    { id: 'cookies', label: '🍪 Cookies' },
    { id: 'passwords', label: '🔑 Passwords' },
    { id: 'downloads', label: '⬇ Downloads' },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      {/* Sub-nav */}
      <div style={{
        display: 'flex',
        gap: 2,
        padding: '8px 12px',
        borderBottom: '1px solid var(--color-border)',
        flexWrap: 'wrap',
      }}>
        {sections.map(s => (
          <button
            key={s.id}
            onClick={() => setSection(s.id)}
            style={{
              padding: '4px 10px',
              borderRadius: 8,
              fontSize: 11,
              fontWeight: section === s.id ? 600 : 400,
              background: section === s.id ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: section === s.id ? '#fff' : 'var(--color-text-muted)',
              border: '1px solid var(--color-border)',
              whiteSpace: 'nowrap',
              transition: 'all 0.12s',
            }}
          >{s.label}</button>
        ))}
      </div>

      {/* Confirm dialog */}
      {confirm && (
        <ConfirmBanner
          message={confirm.message}
          onConfirm={() => { confirm.onConfirm(); setConfirm(null); }}
          onCancel={() => setConfirm(null)}
        />
      )}

      {/* Section content */}
      <div style={{ flex: 1, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
        {section === 'history' && <HistorySection onConfirm={requestConfirm} />}
        {section === 'cookies' && <CookiesSection currentUrl={currentUrl} onConfirm={requestConfirm} />}
        {section === 'passwords' && <PasswordsSection currentUrl={currentUrl} onConfirm={requestConfirm} />}
        {section === 'downloads' && <DownloadsSection />}
      </div>

      {/* Clear all browsing data */}
      <ClearAllBar onConfirm={requestConfirm} />
    </div>
  );
}

// ── Confirm banner ────────────────────────────────────────────────────────────

function ConfirmBanner({ message, onConfirm, onCancel }: { message: string; onConfirm: () => void; onCancel: () => void }) {
  return (
    <div style={{
      margin: '8px 12px',
      padding: '10px 12px',
      borderRadius: 10,
      background: 'color-mix(in srgb, var(--color-danger, #ef4444) 10%, var(--color-bg-elevated))',
      border: '1px solid color-mix(in srgb, var(--color-danger, #ef4444) 30%, transparent)',
    }}>
      <p style={{ fontSize: 12, marginBottom: 8, color: 'var(--color-text)' }}>{message}</p>
      <div style={{ display: 'flex', gap: 8 }}>
        <button
          onClick={onConfirm}
          style={{
            flex: 1,
            padding: '5px 10px',
            borderRadius: 8,
            background: 'var(--color-danger, #ef4444)',
            color: '#fff',
            fontSize: 11,
            fontWeight: 600,
          }}
        >Confirm</button>
        <button
          onClick={onCancel}
          style={{
            flex: 1,
            padding: '5px 10px',
            borderRadius: 8,
            background: 'var(--color-bg)',
            border: '1px solid var(--color-border)',
            color: 'var(--color-text-muted)',
            fontSize: 11,
          }}
        >Cancel</button>
      </div>
    </div>
  );
}

// ── History section ───────────────────────────────────────────────────────────

function HistorySection({ onConfirm }: { onConfirm: (msg: string, cb: () => void) => void }) {
  const [entries, setEntries] = useState<HistoryEntry[]>([]);
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback(async (q?: string) => {
    setLoading(true);
    try {
      const rows = q && q.trim()
        ? await window.zio.history.search(q.trim()) as HistoryEntry[]
        : await window.zio.history.recent() as HistoryEntry[];
      setEntries(rows);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleSearch = (val: string) => {
    setQuery(val);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => void load(val), 240);
  };

  const handleDelete = useCallback((id: string) => {
    void window.zio.history.delete(id).then(() => {
      setEntries(prev => prev.filter(e => e.id !== id));
    });
  }, []);

  const handleClearAll = () => {
    onConfirm('Clear your entire browsing history? This cannot be undone.', () => {
      void window.zio.history.clear().then(() => setEntries([]));
    });
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      <div style={{ padding: '8px 12px', display: 'flex', gap: 8, alignItems: 'center' }}>
        <input
          value={query}
          onChange={e => handleSearch(e.target.value)}
          placeholder="Search history…"
          style={searchInputStyle}
        />
        <button
          onClick={handleClearAll}
          title="Clear all history"
          style={dangerSmallBtn}
        >Clear all</button>
      </div>

      <div style={{ flex: 1, overflowY: 'auto' }}>
        {loading && <EmptyMsg>Loading…</EmptyMsg>}
        {!loading && entries.length === 0 && (
          <EmptyMsg>{query ? 'No results.' : 'No browsing history yet.'}</EmptyMsg>
        )}
        {entries.map(e => (
          <div key={e.id} style={listRowStyle}>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 12, color: 'var(--color-text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {e.title || e.url}
              </div>
              <div style={{ fontSize: 10, color: 'var(--color-text-muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', marginTop: 1 }}>
                {e.url}
              </div>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 2, flexShrink: 0 }}>
              <span style={{ fontSize: 10, color: 'var(--color-text-muted)' }}>
                {new Date(e.last_visited).toLocaleDateString()}
              </span>
              <button onClick={() => handleDelete(e.id)} style={deleteRowBtn} title="Remove from history">✕</button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Cookies section ───────────────────────────────────────────────────────────

function CookiesSection({ currentUrl, onConfirm }: { currentUrl: string | null; onConfirm: (msg: string, cb: () => void) => void }) {
  const [cookies, setCookies] = useState<CookieInfo[]>([]);
  const [showAll, setShowAll] = useState(false);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const rows = showAll || !currentUrl
        ? await window.zio.cookies.getAll() as CookieInfo[]
        : await window.zio.cookies.getForSite(currentUrl) as CookieInfo[];
      setCookies(rows);
    } finally {
      setLoading(false);
    }
  }, [showAll, currentUrl]);

  useEffect(() => { void load(); }, [load]);

  const handleDelete = useCallback((name: string, domain: string) => {
    const url = currentUrl ?? `https://${domain}`;
    void window.zio.cookies.delete(name, url).then(() => {
      setCookies(prev => prev.filter(c => !(c.name === name && c.domain === domain)));
    });
  }, [currentUrl]);

  const handleClearSite = () => {
    if (!currentUrl) return;
    const host = (() => { try { return new URL(currentUrl).hostname; } catch { return currentUrl; } })();
    onConfirm(`Clear all cookies for ${host}?`, () => {
      void window.zio.cookies.clearForSite(currentUrl).then(() => { void load(); });
    });
  };

  const handleClearAll = () => {
    onConfirm('Clear all cookies from all sites? You will be signed out everywhere.', () => {
      void window.zio.cookies.clearAll().then(() => setCookies([]));
    });
  };

  const siteLabel = (() => {
    if (!currentUrl) return 'All sites';
    try { return new URL(currentUrl).hostname; } catch { return currentUrl; }
  })();

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      <div style={{ padding: '8px 12px', display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
        <button
          onClick={() => setShowAll(p => !p)}
          style={{ ...smallToggleBtn, background: showAll ? 'var(--color-bg-elevated)' : 'var(--color-primary)', color: showAll ? 'var(--color-text-muted)' : '#fff' }}
        >{showAll ? 'All sites' : siteLabel}</button>
        {currentUrl && !showAll && (
          <button onClick={handleClearSite} style={dangerSmallBtn}>Clear site</button>
        )}
        <button onClick={handleClearAll} style={dangerSmallBtn}>Clear all</button>
        <span style={{ fontSize: 10, color: 'var(--color-text-muted)', marginLeft: 'auto' }}>
          {cookies.length} cookie{cookies.length === 1 ? '' : 's'}
        </span>
      </div>

      <div style={{ flex: 1, overflowY: 'auto' }}>
        {loading && <EmptyMsg>Loading…</EmptyMsg>}
        {!loading && cookies.length === 0 && (
          <EmptyMsg>No cookies found{!showAll && currentUrl ? ' for this site' : ''}.</EmptyMsg>
        )}
        {cookies.map((c, i) => (
          <div key={i} style={listRowStyle}>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 12, fontFamily: 'monospace', color: 'var(--color-text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                {c.name}
              </div>
              <div style={{ fontSize: 10, color: 'var(--color-text-muted)', display: 'flex', gap: 6, marginTop: 2, flexWrap: 'wrap' }}>
                <span>{c.domain}</span>
                {c.secure && <span style={{ color: 'var(--color-primary)' }}>secure</span>}
                {c.httpOnly && <span>httpOnly</span>}
                {c.session && <span>session</span>}
              </div>
            </div>
            <button
              onClick={() => handleDelete(c.name, c.domain)}
              style={deleteRowBtn}
              title="Delete cookie"
            >✕</button>
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Passwords section ─────────────────────────────────────────────────────────

function PasswordsSection({ currentUrl, onConfirm }: { currentUrl: string | null; onConfirm: (msg: string, cb: () => void) => void }) {
  const [passwords, setPasswords] = useState<PasswordEntry[]>([]);
  const [revealed, setRevealed] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const rows = await window.zio.passwords.list() as PasswordEntry[];
      setPasswords(rows);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleReveal = useCallback(async (id: string) => {
    if (revealed[id]) {
      setRevealed(prev => { const n = { ...prev }; delete n[id]; return n; });
      return;
    }
    const plain = await window.zio.passwords.reveal(id) as string | null;
    if (plain) {
      setRevealed(prev => ({ ...prev, [id]: plain }));
    }
  }, [revealed]);

  const handleDelete = useCallback((id: string, origin: string) => {
    onConfirm(`Delete saved login for ${origin}?`, () => {
      void window.zio.passwords.delete(id).then(() => {
        setPasswords(prev => prev.filter(p => p.id !== id));
        setRevealed(prev => { const n = { ...prev }; delete n[id]; return n; });
      });
    });
  }, [onConfirm]);

  const handleDeleteAll = () => {
    onConfirm('Delete all saved passwords? This cannot be undone.', () => {
      void window.zio.passwords.deleteAll().then(() => { setPasswords([]); setRevealed({}); });
    });
  };

  const savePasswordsEnabled = true; // controlled by prefs in a real flow

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      <div style={{ padding: '8px 12px', display: 'flex', gap: 6, alignItems: 'center' }}>
        <span style={{ fontSize: 11, color: 'var(--color-text-muted)', flex: 1 }}>
          {passwords.length} saved login{passwords.length === 1 ? '' : 's'}
        </span>
        {passwords.length > 0 && (
          <button onClick={handleDeleteAll} style={dangerSmallBtn}>Delete all</button>
        )}
      </div>

      <div style={{ flex: 1, overflowY: 'auto' }}>
        {loading && <EmptyMsg>Loading…</EmptyMsg>}
        {!loading && passwords.length === 0 && (
          <EmptyMsg>
            {savePasswordsEnabled
              ? 'No saved passwords. Zio will offer to save passwords when you log into sites.'
              : 'Password saving is disabled in settings.'}
          </EmptyMsg>
        )}
        {passwords.map(p => (
          <div key={p.id} style={{ ...listRowStyle, flexDirection: 'column', alignItems: 'stretch', gap: 4 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--color-text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {p.origin}
                </div>
                <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1 }}>{p.username}</div>
              </div>
              <div style={{ display: 'flex', gap: 6, flexShrink: 0 }}>
                <button
                  onClick={() => void handleReveal(p.id)}
                  style={{ ...smallToggleBtn, fontSize: 11 }}
                  title={revealed[p.id] ? 'Hide password' : 'Reveal password'}
                >{revealed[p.id] ? '🙈 Hide' : '👁 Show'}</button>
                <button
                  onClick={() => handleDelete(p.id, p.origin)}
                  style={deleteRowBtn}
                  title="Delete saved password"
                >✕</button>
              </div>
            </div>
            {revealed[p.id] && (
              <div style={{
                fontSize: 11,
                fontFamily: 'monospace',
                background: 'var(--color-bg)',
                border: '1px solid var(--color-border)',
                borderRadius: 6,
                padding: '4px 8px',
                color: 'var(--color-text)',
                userSelect: 'text',
                wordBreak: 'break-all',
              }}>
                {revealed[p.id]}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Downloads section ─────────────────────────────────────────────────────────

function DownloadsSection() {
  const [downloads, setDownloads] = useState<DownloadEntry[]>([]);
  const [loading, setLoading] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const rows = await window.zio.downloads.recent() as DownloadEntry[];
      setDownloads(rows);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleOpen = (savePath: string | null) => {
    if (savePath) void window.zio.downloads.open(savePath);
  };
  const handleShow = (savePath: string | null) => {
    if (savePath) void window.zio.downloads.show(savePath);
  };

  const stateLabel = (state: string) => {
    switch (state) {
      case 'completed': return '✓';
      case 'progressing': return '↓';
      case 'interrupted': return '⚠';
      case 'cancelled': return '✕';
      default: return '…';
    }
  };

  const stateColor = (state: string) => {
    switch (state) {
      case 'completed': return 'var(--color-success, #22c55e)';
      case 'interrupted': return 'var(--color-danger, #ef4444)';
      case 'cancelled': return 'var(--color-text-muted)';
      default: return 'var(--color-primary)';
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', flex: 1, overflow: 'hidden' }}>
      <div style={{ padding: '8px 12px', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
        <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>Recent downloads</span>
        <button onClick={() => void load()} style={smallToggleBtn} title="Refresh">↻</button>
      </div>

      <div style={{ flex: 1, overflowY: 'auto' }}>
        {loading && <EmptyMsg>Loading…</EmptyMsg>}
        {!loading && downloads.length === 0 && <EmptyMsg>No downloads yet.</EmptyMsg>}
        {downloads.map(d => (
          <div key={d.id} style={listRowStyle}>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <span style={{ fontSize: 12, color: stateColor(d.state), flexShrink: 0 }}>{stateLabel(d.state)}</span>
                <span style={{ fontSize: 12, color: 'var(--color-text)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {d.filename}
                </span>
              </div>
              {d.total_bytes != null && d.state === 'progressing' && (
                <div style={{ height: 3, borderRadius: 2, background: 'var(--color-border)', marginTop: 4, overflow: 'hidden' }}>
                  <div style={{
                    height: '100%',
                    width: `${Math.round((d.received_bytes / d.total_bytes) * 100)}%`,
                    background: 'var(--color-primary)',
                  }} />
                </div>
              )}
              <div style={{ fontSize: 10, color: 'var(--color-text-muted)', marginTop: 2 }}>
                {d.completed_at
                  ? new Date(d.completed_at).toLocaleDateString()
                  : new Date(d.created_at).toLocaleDateString()}
                {d.total_bytes != null && ` · ${formatBytes(d.total_bytes)}`}
              </div>
            </div>
            {d.save_path && d.state === 'completed' && (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 4, flexShrink: 0 }}>
                <button onClick={() => handleOpen(d.save_path)} style={smallToggleBtn} title="Open file">↗ Open</button>
                <button onClick={() => handleShow(d.save_path)} style={smallToggleBtn} title="Show in folder">📁</button>
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Clear all bar ─────────────────────────────────────────────────────────────

function ClearAllBar({ onConfirm }: { onConfirm: (msg: string, cb: () => void) => void }) {
  const handleClear = () => {
    onConfirm(
      'Clear all browsing data — history, cookies, and cache? You will be signed out of all sites.',
      () => { void window.zio.browsingData.clear(); },
    );
  };

  return (
    <div style={{
      padding: '10px 12px',
      borderTop: '1px solid var(--color-border)',
    }}>
      <button
        onClick={handleClear}
        style={{
          width: '100%',
          padding: '7px 12px',
          borderRadius: 8,
          background: 'color-mix(in srgb, var(--color-danger, #ef4444) 12%, var(--color-bg-elevated))',
          border: '1px solid color-mix(in srgb, var(--color-danger, #ef4444) 30%, transparent)',
          color: 'var(--color-danger, #ef4444)',
          fontSize: 12,
          fontWeight: 600,
          cursor: 'pointer',
        }}
      >🗑 Clear all browsing data</button>
    </div>
  );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function EmptyMsg({ children }: { children: React.ReactNode }) {
  return (
    <div style={{ padding: '24px 16px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 12 }}>
      {children}
    </div>
  );
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

// ── Shared micro-styles ───────────────────────────────────────────────────────

const listRowStyle: React.CSSProperties = {
  display: 'flex',
  alignItems: 'center',
  gap: 10,
  padding: '8px 12px',
  borderBottom: '1px solid color-mix(in srgb, var(--color-border) 50%, transparent)',
};

const deleteRowBtn: React.CSSProperties = {
  width: 22,
  height: 22,
  borderRadius: 6,
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  fontSize: 10,
  color: 'var(--color-text-muted)',
  background: 'transparent',
  flexShrink: 0,
  transition: 'all 0.12s',
};

const dangerSmallBtn: React.CSSProperties = {
  padding: '3px 8px',
  borderRadius: 6,
  fontSize: 11,
  background: 'color-mix(in srgb, var(--color-danger, #ef4444) 12%, var(--color-bg-elevated))',
  border: '1px solid color-mix(in srgb, var(--color-danger, #ef4444) 30%, transparent)',
  color: 'var(--color-danger, #ef4444)',
  whiteSpace: 'nowrap' as const,
  cursor: 'pointer',
};

const smallToggleBtn: React.CSSProperties = {
  padding: '3px 8px',
  borderRadius: 6,
  fontSize: 11,
  background: 'var(--color-bg-elevated)',
  border: '1px solid var(--color-border)',
  color: 'var(--color-text-muted)',
  whiteSpace: 'nowrap' as const,
  cursor: 'pointer',
};

const searchInputStyle: React.CSSProperties = {
  flex: 1,
  height: 28,
  borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg)',
  color: 'var(--color-text)',
  padding: '0 10px',
  fontSize: 12,
  outline: 'none',
};
