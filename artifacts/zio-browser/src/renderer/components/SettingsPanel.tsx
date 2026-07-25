/**
 * SettingsPanel — sidebar panel with browser settings.
 * Sections: General (spell check, translation language, tracker blocking),
 * Passwords (saved-password manager), Extensions (unpacked extension loader).
 */
import { useState, useEffect, useCallback } from 'react';
import { ClearDataDialog } from './ClearDataDialog';
import { KEYBOARD_SHORTCUTS } from '../../shared/command-palette';

interface Props {
  onClose: () => void;
}

type SettingsTab = 'general' | 'sessions' | 'passwords' | 'extensions' | 'shortcuts';

type ThemeMode = 'system' | 'dark' | 'light';

/** Apply the resolved theme to the window chrome. */
export function applyResolvedTheme(resolved: 'dark' | 'light') {
  document.documentElement.classList.toggle('light-mode', resolved === 'light');
}

const TRANSLATE_LANGS: Array<{ code: string; label: string }> = [
  { code: 'en', label: 'English' },
  { code: 'es', label: 'Spanish' },
  { code: 'fr', label: 'French' },
  { code: 'de', label: 'German' },
  { code: 'pt', label: 'Portuguese' },
  { code: 'it', label: 'Italian' },
  { code: 'hi', label: 'Hindi' },
  { code: 'ar', label: 'Arabic' },
  { code: 'zh-CN', label: 'Chinese (Simplified)' },
  { code: 'ja', label: 'Japanese' },
  { code: 'ko', label: 'Korean' },
  { code: 'ru', label: 'Russian' },
];

export function SettingsPanel({ onClose }: Props) {
  const [tab, setTab] = useState<SettingsTab>('general');

  return (
    <div style={{
      width: 340,
      height: '100%',
      background: 'var(--color-bg-surface)',
      borderLeft: '1px solid var(--color-border)',
      display: 'flex',
      flexDirection: 'column',
      flexShrink: 0,
    }}>
      {/* Header */}
      <div style={{
        height: 44,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '0 16px',
        borderBottom: '1px solid var(--color-border)',
        flexShrink: 0,
      }}>
        <span style={{ fontWeight: 600, fontSize: 14 }}>⚙️ Settings</span>
        <button
          onClick={onClose}
          style={{ fontSize: 16, color: 'var(--color-text-muted)', padding: '2px 6px', borderRadius: 4 }}
          title="Close settings"
        >✕</button>
      </div>

      {/* Tabs */}
      <div style={{
        display: 'flex',
        gap: 4,
        padding: '8px 12px',
        borderBottom: '1px solid var(--color-border)',
        flexShrink: 0,
      }}>
        {([['general', 'General'], ['sessions', 'Sessions'], ['passwords', 'Passwords'], ['extensions', 'Extensions'], ['shortcuts', 'Shortcuts']] as Array<[SettingsTab, string]>).map(([key, label]) => (
          <button
            key={key}
            onClick={() => setTab(key)}
            style={{
              fontSize: 12,
              fontWeight: 600,
              padding: '4px 10px',
              borderRadius: 8,
              background: tab === key ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
              color: tab === key ? '#fff' : 'var(--color-text-muted)',
              border: '1px solid var(--color-border)',
              transition: 'all 0.12s',
            }}
          >{label}</button>
        ))}
      </div>

      {/* Content */}
      <div style={{ flex: 1, overflowY: 'auto' }}>
        {tab === 'general' && <GeneralSection />}
        {tab === 'sessions' && <SessionsSection />}
        {tab === 'passwords' && <PasswordsSection />}
        {tab === 'extensions' && <ExtensionsSection />}
        {tab === 'shortcuts' && <ShortcutsSection />}
      </div>
    </div>
  );
}

// ── General ───────────────────────────────────────────────────────────────────

function GeneralSection() {
  const [spellcheck, setSpellcheck] = useState<boolean | null>(null);
  const [spellcheckNote, setSpellcheckNote] = useState<string | null>(null);
  const [translateLang, setTranslateLang] = useState('en');
  const [trackerEnabled, setTrackerEnabled] = useState<boolean | null>(null);
  const [themeMode, setThemeMode] = useState<ThemeMode>('system');
  const [clearDialogOpen, setClearDialogOpen] = useState(false);

  useEffect(() => {
    void window.zio.spellcheck.getEnabled().then(setSpellcheck).catch(() => setSpellcheck(true));
    void window.zio.prefs.get('translate_target_lang')
      .then((v) => { if (typeof v === 'string' && v) setTranslateLang(v); })
      .catch(() => {});
    void window.zio.tracker.isEnabled().then((v: boolean) => setTrackerEnabled(v)).catch(() => setTrackerEnabled(null));
    void window.zio.prefs.get('theme')
      .then((v) => { if (v === 'light' || v === 'dark' || v === 'system') setThemeMode(v); })
      .catch(() => {});
  }, []);

  const changeTheme = useCallback(async (mode: ThemeMode) => {
    setThemeMode(mode);
    try {
      const resolved = await window.zio.theme.set(mode) as 'dark' | 'light';
      applyResolvedTheme(resolved);
      await window.zio.prefs.set('theme', mode);
    } catch { /* non-fatal */ }
  }, []);

  const toggleSpellcheck = useCallback(async () => {
    if (spellcheck === null) return;
    const next = !spellcheck;
    setSpellcheck(next);
    setSpellcheckNote(null);
    try {
      const ok = await window.zio.spellcheck.setEnabled(next);
      if (ok === false) {
        setSpellcheck(!next);
        setSpellcheckNote('Not available in private windows.');
      }
    } catch {
      setSpellcheck(!next);
    }
  }, [spellcheck]);

  const changeLang = useCallback(async (code: string) => {
    setTranslateLang(code);
    try { await window.zio.prefs.set('translate_target_lang', code); } catch { /* non-fatal */ }
  }, []);

  const toggleTracker = useCallback(async () => {
    if (trackerEnabled === null) return;
    const next = !trackerEnabled;
    setTrackerEnabled(next);
    try { await window.zio.tracker.setEnabled(next); } catch { setTrackerEnabled(!next); }
  }, [trackerEnabled]);

  return (
    <div style={{ padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: 18 }}>
      <SettingRow
        title="Appearance"
        description="Choose a dark or light look, or follow your computer's setting."
      >
        <select
          value={themeMode}
          onChange={(e) => void changeTheme(e.target.value as ThemeMode)}
          style={{
            fontSize: 12,
            padding: '4px 8px',
            borderRadius: 8,
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            border: '1px solid var(--color-border)',
            maxWidth: 160,
          }}
        >
          <option value="system">System</option>
          <option value="dark">Dark</option>
          <option value="light">Light</option>
        </select>
      </SettingRow>

      <SettingRow
        title="Spell check"
        description={spellcheckNote ?? 'Underline misspelled words as you type. Right-click a word for suggestions. Applies to new pages after reload.'}
      >
        <Toggle checked={spellcheck === true} disabled={spellcheck === null} onChange={() => void toggleSpellcheck()} />
      </SettingRow>

      <SettingRow
        title="Translate pages into"
        description="Language used by the right-click “Translate this page” action."
      >
        <select
          value={translateLang}
          onChange={(e) => void changeLang(e.target.value)}
          style={{
            fontSize: 12,
            padding: '4px 8px',
            borderRadius: 8,
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            border: '1px solid var(--color-border)',
            maxWidth: 160,
          }}
        >
          {TRANSLATE_LANGS.map(l => <option key={l.code} value={l.code}>{l.label}</option>)}
        </select>
      </SettingRow>

      {trackerEnabled !== null && (
        <SettingRow
          title="Tracker blocking"
          description="Block known trackers and ads while you browse."
        >
          <Toggle checked={trackerEnabled} onChange={() => void toggleTracker()} />
        </SettingRow>
      )}

      <SettingRow
        title="Clear browsing data"
        description="Delete history, cookies and cached files for a time range you pick."
      >
        <button
          onClick={() => setClearDialogOpen(true)}
          style={{
            fontSize: 12,
            fontWeight: 600,
            padding: '5px 12px',
            borderRadius: 8,
            background: 'color-mix(in srgb, var(--color-danger, #ef4444) 12%, var(--color-bg-elevated))',
            border: '1px solid color-mix(in srgb, var(--color-danger, #ef4444) 30%, transparent)',
            color: 'var(--color-danger, #ef4444)',
            whiteSpace: 'nowrap',
          }}
        >Clear…</button>
      </SettingRow>

      {clearDialogOpen && <ClearDataDialog onClose={() => setClearDialogOpen(false)} />}
    </div>
  );
}

// ── Shortcuts ─────────────────────────────────────────────────────────────────

function ShortcutsSection() {
  const categories = [...new Set(KEYBOARD_SHORTCUTS.map(s => s.category))];
  return (
    <div style={{ padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: 16 }}>
      {categories.map(cat => (
        <div key={cat}>
          <div style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', letterSpacing: 0.5, color: 'var(--color-text-muted)', marginBottom: 6 }}>
            {cat}
          </div>
          {KEYBOARD_SHORTCUTS.filter(s => s.category === cat).map(s => (
            <div key={s.label} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '5px 0', gap: 8 }}>
              <span style={{ fontSize: 12, color: 'var(--color-text)' }}>{s.label}</span>
              <span style={{ display: 'flex', gap: 4, flexShrink: 0 }}>
                {s.keys.map((k, i) => (
                  <kbd key={i} style={{
                    fontSize: 10,
                    fontWeight: 600,
                    padding: '2px 6px',
                    borderRadius: 5,
                    background: 'var(--color-bg-elevated)',
                    border: '1px solid var(--color-border)',
                    color: 'var(--color-text-muted)',
                  }}>{k}</kbd>
                ))}
              </span>
            </div>
          ))}
        </div>
      ))}
      <div style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
        Tip: press <kbd style={{ fontSize: 10, padding: '1px 5px', borderRadius: 4, background: 'var(--color-bg-elevated)', border: '1px solid var(--color-border)' }}>Ctrl/Cmd + K</kbd> anywhere to open the command palette.
      </div>
    </div>
  );
}

// ── Sessions ──────────────────────────────────────────────────────────────────

interface NamedSessionRow {
  id: string;
  name: string;
  tabCount: number;
  updated_at: string;
}

function SessionsSection() {
  const [rows, setRows] = useState<NamedSessionRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [name, setName] = useState('');
  const [note, setNote] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const list = await window.zio.sessions.list();
      setRows(Array.isArray(list) ? list : []);
    } catch {
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const saveCurrent = useCallback(async () => {
    const trimmed = name.trim();
    if (!trimmed) return;
    setNote(null);
    try {
      const ok = await window.zio.sessions.save(trimmed);
      if (ok) {
        setName('');
        setNote('Session saved.');
        void load();
      } else {
        setNote('Nothing to save — open some tabs first.');
      }
    } catch {
      setNote('Could not save the session.');
    }
  }, [name, load]);

  const restore = useCallback(async (id: string) => {
    setNote(null);
    try {
      const ok = await window.zio.sessions.restore(id);
      setNote(ok ? 'Tabs reopened.' : 'Could not restore that session.');
    } catch {
      setNote('Could not restore that session.');
    }
  }, []);

  const remove = useCallback(async (id: string) => {
    try {
      await window.zio.sessions.remove(id);
      setRows(prev => prev.filter(r => r.id !== id));
    } catch { /* keep the row on failure */ }
  }, []);

  const btnStyle: React.CSSProperties = {
    fontSize: 11,
    fontWeight: 600,
    padding: '3px 8px',
    borderRadius: 6,
    border: '1px solid var(--color-border)',
    background: 'var(--color-bg-elevated)',
    color: 'var(--color-text)',
  };

  return (
    <div style={{ padding: '12px 16px', display: 'flex', flexDirection: 'column', gap: 12 }}>
      <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>
        Save the tabs you have open now as a named session, then reopen them any time.
      </div>

      <div style={{ display: 'flex', gap: 6 }}>
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          onKeyDown={(e) => { if (e.key === 'Enter') void saveCurrent(); }}
          placeholder="Session name (e.g. Work, Research)"
          style={{
            flex: 1,
            fontSize: 12,
            padding: '6px 10px',
            borderRadius: 8,
            background: 'var(--color-bg-elevated)',
            color: 'var(--color-text)',
            border: '1px solid var(--color-border)',
          }}
        />
        <button
          onClick={() => void saveCurrent()}
          disabled={!name.trim()}
          style={{
            ...btnStyle,
            background: name.trim() ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
            color: name.trim() ? '#fff' : 'var(--color-text-muted)',
          }}
        >Save tabs</button>
      </div>

      {note && <div style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>{note}</div>}

      {loading ? (
        <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>Loading…</div>
      ) : rows.length === 0 ? (
        <div style={{ fontSize: 12, color: 'var(--color-text-muted)' }}>No saved sessions yet.</div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {rows.map(row => (
            <div
              key={row.id}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '8px 10px',
                borderRadius: 8,
                border: '1px solid var(--color-border)',
                background: 'var(--color-bg-elevated)',
              }}
            >
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {row.name}
                </div>
                <div style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
                  {row.tabCount} {row.tabCount === 1 ? 'tab' : 'tabs'}
                </div>
              </div>
              <button onClick={() => void restore(row.id)} style={btnStyle} title="Reopen these tabs">Open</button>
              <button
                onClick={() => void remove(row.id)}
                style={{ ...btnStyle, color: 'var(--color-danger, #e5484d)' }}
                title="Delete this session"
              >✕</button>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ── Passwords ─────────────────────────────────────────────────────────────────

interface SavedPasswordRow {
  id: string;
  origin: string;
  username: string;
  created_at?: string;
  updated_at?: string;
}

function PasswordsSection() {
  const [rows, setRows] = useState<SavedPasswordRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [revealed, setRevealed] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    try {
      const list = (await window.zio.passwords.list()) as SavedPasswordRow[];
      setRows(list);
    } catch {
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const toggleReveal = useCallback(async (id: string) => {
    setRevealed(prev => {
      if (prev[id] !== undefined) {
        const next = { ...prev };
        delete next[id];
        return next;
      }
      return prev;
    });
    if (revealed[id] !== undefined) return;
    try {
      const pw = (await window.zio.passwords.reveal(id)) as string | null;
      if (pw !== null) setRevealed(prev => ({ ...prev, [id]: pw }));
    } catch { /* non-fatal */ }
  }, [revealed]);

  const handleDelete = useCallback(async (id: string) => {
    try {
      await window.zio.passwords.delete(id);
      setRows(prev => prev.filter(r => r.id !== id));
      setRevealed(prev => { const next = { ...prev }; delete next[id]; return next; });
    } catch { /* non-fatal */ }
  }, []);

  const handleDeleteAll = useCallback(async () => {
    if (!window.confirm('Delete all saved passwords? This cannot be undone.')) return;
    try {
      await window.zio.passwords.deleteAll();
      setRows([]);
      setRevealed({});
    } catch { /* non-fatal */ }
  }, []);

  return (
    <div style={{ padding: '12px 0' }}>
      <div style={{ padding: '0 16px 10px', fontSize: 12, color: 'var(--color-text-muted)', lineHeight: 1.5 }}>
        Passwords you chose to save while signing in to websites. They are stored encrypted on this device.
      </div>

      {loading && (
        <div style={{ padding: '16px', color: 'var(--color-text-muted)', fontSize: 13 }}>Loading…</div>
      )}

      {!loading && rows.length === 0 && (
        <div style={{ padding: '28px 20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
          <div style={{ fontSize: 26, marginBottom: 8 }}>🔑</div>
          <div style={{ fontWeight: 600, marginBottom: 4 }}>No saved passwords</div>
          <div style={{ lineHeight: 1.5 }}>When you sign in to a site, Zio can offer to save your password here.</div>
        </div>
      )}

      {!loading && rows.map(row => (
        <div key={row.id} style={{
          display: 'flex',
          alignItems: 'center',
          gap: 10,
          padding: '8px 16px',
          borderBottom: '1px solid var(--color-border)',
        }}>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{
              fontSize: 13, fontWeight: 600, color: 'var(--color-text)',
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>{row.origin}</div>
            <div style={{
              fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1,
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>
              {row.username}
              {revealed[row.id] !== undefined && (
                <span style={{ marginLeft: 8, fontFamily: 'monospace', color: 'var(--color-text)' }}>
                  {revealed[row.id]}
                </span>
              )}
            </div>
          </div>
          <button
            onClick={() => void toggleReveal(row.id)}
            title={revealed[row.id] !== undefined ? 'Hide password' : 'Show password'}
            style={smallBtnStyle}
          >{revealed[row.id] !== undefined ? '🙈' : '👁'}</button>
          <button
            onClick={() => void handleDelete(row.id)}
            title="Delete this password"
            style={smallBtnStyle}
          >✕</button>
        </div>
      ))}

      {!loading && rows.length > 0 && (
        <div style={{ padding: '12px 16px' }}>
          <button
            onClick={() => void handleDeleteAll()}
            style={{
              fontSize: 12,
              padding: '5px 12px',
              borderRadius: 8,
              background: 'var(--color-bg-elevated)',
              color: 'var(--color-danger, #e5484d)',
              border: '1px solid var(--color-border)',
            }}
          >Delete all passwords</button>
        </div>
      )}
    </div>
  );
}

// ── Extensions ────────────────────────────────────────────────────────────────

interface ExtensionRow {
  id: string;
  name: string;
  version: string;
  path: string;
  builtin?: boolean;
  missing?: boolean;
}

function ExtensionsSection() {
  const [exts, setExts] = useState<ExtensionRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const list = await window.zio.extensions.list();
      setExts(list);
    } catch {
      setExts([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const handleAdd = useCallback(async () => {
    setAdding(true);
    setError(null);
    try {
      const res = await window.zio.extensions.add();
      if (res.ok) {
        setExts(prev => [...prev.filter(e => e.id !== res.extension.id), res.extension]);
      } else if (res.error !== 'cancelled') {
        setError(res.error);
      }
    } catch {
      setError('Could not load the extension.');
    } finally {
      setAdding(false);
    }
  }, []);

  const handleRemove = useCallback(async (id: string) => {
    try {
      await window.zio.extensions.remove(id);
      setExts(prev => prev.filter(e => e.id !== id));
    } catch { /* non-fatal */ }
  }, []);

  return (
    <div style={{ padding: '12px 0' }}>
      <div style={{ padding: '0 16px 10px', fontSize: 12, color: 'var(--color-text-muted)', lineHeight: 1.5 }}>
        Load unpacked Chrome extensions from a folder on your computer. Extensions apply to new tabs after a restart.
      </div>

      <div style={{ padding: '0 16px 12px' }}>
        <button
          onClick={() => void handleAdd()}
          disabled={adding}
          style={{
            fontSize: 12,
            fontWeight: 600,
            padding: '6px 14px',
            borderRadius: 8,
            background: 'var(--color-primary)',
            color: '#fff',
            border: 'none',
            opacity: adding ? 0.6 : 1,
          }}
        >{adding ? 'Choosing folder…' : '+ Load unpacked extension'}</button>
        {error && (
          <div style={{ marginTop: 8, fontSize: 12, color: 'var(--color-danger, #e5484d)', lineHeight: 1.4 }}>
            {error}
          </div>
        )}
      </div>

      {loading && (
        <div style={{ padding: '8px 16px', color: 'var(--color-text-muted)', fontSize: 13 }}>Loading…</div>
      )}

      {!loading && exts.length === 0 && (
        <div style={{ padding: '20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
          <div style={{ fontSize: 26, marginBottom: 8 }}>🧩</div>
          No extensions installed yet.
        </div>
      )}

      {!loading && exts.map(ext => (
        <div key={ext.id} style={{
          display: 'flex',
          alignItems: 'center',
          gap: 10,
          padding: '8px 16px',
          borderBottom: '1px solid var(--color-border)',
        }}>
          <span style={{ fontSize: 16, flexShrink: 0, opacity: ext.missing ? 0.5 : 1 }}>🧩</span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{
              fontSize: 13, fontWeight: 600, color: ext.missing ? 'var(--color-text-muted)' : 'var(--color-text)',
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>
              {ext.name}{!ext.missing && <span style={{ fontWeight: 400, color: 'var(--color-text-muted)' }}> v{ext.version}</span>}
              {ext.missing && (
                <span style={{
                  marginLeft: 6,
                  fontSize: 9,
                  fontWeight: 700,
                  padding: '1px 6px',
                  borderRadius: 6,
                  background: 'var(--color-danger, #e5484d)',
                  color: '#fff',
                  verticalAlign: 'middle',
                }}>MISSING</span>
              )}
              {ext.builtin && (
                <span style={{
                  marginLeft: 6,
                  fontSize: 9,
                  fontWeight: 700,
                  padding: '1px 6px',
                  borderRadius: 6,
                  background: 'var(--color-primary)',
                  color: '#fff',
                  verticalAlign: 'middle',
                }}>BUILT-IN</span>
              )}
            </div>
            <div style={{
              fontSize: 10, color: 'var(--color-text-muted)', marginTop: 1,
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>{ext.builtin ? 'Ships with Zio Browser' : ext.missing ? `Folder not found: ${ext.path}` : ext.path}</div>
          </div>
          {!ext.builtin && (
            <button
              onClick={() => void handleRemove(ext.id)}
              title="Remove extension"
              style={smallBtnStyle}
            >✕</button>
          )}
        </div>
      ))}
    </div>
  );
}

// ── Shared bits ───────────────────────────────────────────────────────────────

const smallBtnStyle: React.CSSProperties = {
  fontSize: 12,
  padding: '2px 6px',
  borderRadius: 4,
  background: 'var(--color-bg)',
  border: '1px solid var(--color-border)',
  color: 'var(--color-text-muted)',
  flexShrink: 0,
};

function SettingRow({ title, description, children }: {
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)' }}>{title}</div>
        <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2, lineHeight: 1.45 }}>
          {description}
        </div>
      </div>
      <div style={{ flexShrink: 0, paddingTop: 2 }}>{children}</div>
    </div>
  );
}

function Toggle({ checked, disabled, onChange }: {
  checked: boolean;
  disabled?: boolean;
  onChange: () => void;
}) {
  return (
    <button
      onClick={onChange}
      disabled={disabled}
      role="switch"
      aria-checked={checked}
      style={{
        width: 36,
        height: 20,
        borderRadius: 10,
        background: checked ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
        border: '1px solid var(--color-border)',
        position: 'relative',
        cursor: disabled ? 'default' : 'pointer',
        opacity: disabled ? 0.5 : 1,
        transition: 'background 0.15s',
        padding: 0,
      }}
    >
      <span style={{
        position: 'absolute',
        top: 2,
        left: checked ? 18 : 2,
        width: 14,
        height: 14,
        borderRadius: '50%',
        background: '#fff',
        transition: 'left 0.15s',
        boxShadow: '0 1px 3px rgba(0,0,0,0.3)',
      }} />
    </button>
  );
}
