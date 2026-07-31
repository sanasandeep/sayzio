/**
 * SettingsPanel — Chrome-style settings panel with a searchable left nav.
 * Sections: General, Privacy & Security, Site Settings, Search engine,
 * On startup, Downloads, Sessions, Passwords, Extensions, Shortcuts.
 */
import { useState, useEffect, useCallback, useMemo } from 'react';
import { ClearDataDialog } from './ClearDataDialog';
import { KEYBOARD_SHORTCUTS } from '../../shared/command-palette';
import {
  PINNABLE_TOOLS, PINNABLE_TOOL_INFO, MAX_PINNED_TOOLS,
} from '../../shared/toolbar-pins';
import { usePinnedTools } from '../hooks/use-pinned-tools';

interface Props {
  onClose: () => void;
}

type SectionId =
  | 'general'
  | 'privacy'
  | 'sites'
  | 'search'
  | 'startup'
  | 'downloads'
  | 'sessions'
  | 'passwords'
  | 'extensions'
  | 'shortcuts';

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

/** Nav entries with search keywords so the filter box can find sections. */
const SECTIONS: Array<{ id: SectionId; icon: string; label: string; keywords: string }> = [
  { id: 'general', icon: '⚙️', label: 'General', keywords: 'appearance theme dark light spell check translate language import bookmarks history chrome edge brave firefox other browser toolbar pin pinned tools reading list dialer device lab screenshot' },
  { id: 'privacy', icon: '🛡️', label: 'Privacy & Security', keywords: 'tracker blocking do not track cookies clear browsing data delete safety check forget site dashboard privacy' },
  { id: 'sites', icon: '🌐', label: 'Site Settings', keywords: 'permissions camera microphone location notifications allow block sites' },
  { id: 'search', icon: '🔍', label: 'Search engine', keywords: 'google bing duckduckgo brave default search address bar' },
  { id: 'startup', icon: '🚀', label: 'On startup', keywords: 'startup launch continue restore tabs new tab open' },
  { id: 'downloads', icon: '⬇️', label: 'Downloads', keywords: 'download folder location save files ask' },
  { id: 'sessions', icon: '🗂️', label: 'Sessions', keywords: 'saved sessions tabs restore named workspace' },
  { id: 'passwords', icon: '🔑', label: 'Passwords', keywords: 'saved passwords credentials sign in autofill' },
  { id: 'extensions', icon: '🧩', label: 'Extensions', keywords: 'chrome extensions unpacked addons plugins' },
  { id: 'shortcuts', icon: '⌨️', label: 'Shortcuts', keywords: 'keyboard shortcuts hotkeys command palette keys' },
];

export function SettingsPanel({ onClose }: Props) {
  const [section, setSection] = useState<SectionId>('general');
  const [query, setQuery] = useState('');

  const visibleSections = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return SECTIONS;
    return SECTIONS.filter(s =>
      s.label.toLowerCase().includes(q) || s.keywords.includes(q),
    );
  }, [query]);

  // If the current section is filtered out, jump to the first visible one.
  useEffect(() => {
    if (visibleSections.length > 0 && !visibleSections.some(s => s.id === section)) {
      setSection(visibleSections[0].id);
    }
  }, [visibleSections, section]);

  const active = SECTIONS.find(s => s.id === section);

  return (
    <div style={{
      position: 'absolute',
      inset: 0,
      width: '100%',
      height: '100%',
      background: 'var(--color-bg-surface)',
      display: 'flex',
      flexDirection: 'column',
      zIndex: 20,
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

      {/* Body: left nav + content */}
      <div style={{ flex: 1, display: 'flex', minHeight: 0 }}>
        {/* Left nav */}
        <div style={{
          width: 168,
          flexShrink: 0,
          borderRight: '1px solid var(--color-border)',
          display: 'flex',
          flexDirection: 'column',
          overflow: 'hidden',
        }}>
          <div style={{ padding: '10px 10px 6px' }}>
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search settings"
              style={{
                width: '100%',
                fontSize: 12,
                padding: '5px 8px',
                borderRadius: 8,
                background: 'var(--color-bg-elevated)',
                color: 'var(--color-text)',
                border: '1px solid var(--color-border)',
                boxSizing: 'border-box',
              }}
            />
          </div>
          <div style={{ flex: 1, overflowY: 'auto', padding: '2px 6px 10px' }}>
            {visibleSections.length === 0 && (
              <div style={{ fontSize: 11, color: 'var(--color-text-muted)', padding: '8px 6px' }}>
                No matching settings.
              </div>
            )}
            {visibleSections.map(s => (
              <button
                key={s.id}
                onClick={() => setSection(s.id)}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 8,
                  width: '100%',
                  textAlign: 'left',
                  fontSize: 12,
                  fontWeight: section === s.id ? 700 : 500,
                  padding: '7px 10px',
                  borderRadius: 8,
                  marginBottom: 2,
                  background: section === s.id
                    ? 'color-mix(in srgb, var(--color-primary) 14%, transparent)'
                    : 'transparent',
                  color: section === s.id ? 'var(--color-primary)' : 'var(--color-text)',
                  border: 'none',
                }}
              >
                <span style={{ fontSize: 13, width: 18, textAlign: 'center' }}>{s.icon}</span>
                <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.label}</span>
              </button>
            ))}
          </div>
        </div>

        {/* Content */}
        <div style={{ flex: 1, minWidth: 0, overflowY: 'auto' }}>
          {active && (
            <div style={{
              padding: '12px 16px 4px',
              fontSize: 13,
              fontWeight: 700,
              color: 'var(--color-text)',
            }}>{active.label}</div>
          )}
          {section === 'general' && <GeneralSection />}
          {section === 'privacy' && <PrivacySection />}
          {section === 'sites' && <SiteSettingsSection />}
          {section === 'search' && <SearchEngineSection />}
          {section === 'startup' && <StartupSection />}
          {section === 'downloads' && <DownloadsSection />}
          {section === 'sessions' && <SessionsSection />}
          {section === 'passwords' && <PasswordsSection />}
          {section === 'extensions' && <ExtensionsSection />}
          {section === 'shortcuts' && <ShortcutsSection />}
        </div>
      </div>
    </div>
  );
}

// ── General ───────────────────────────────────────────────────────────────────

function GeneralSection() {
  const [spellcheck, setSpellcheck] = useState<boolean | null>(null);
  const [spellcheckNote, setSpellcheckNote] = useState<string | null>(null);
  const [translateLang, setTranslateLang] = useState('en');
  const [themeMode, setThemeMode] = useState<ThemeMode>('system');

  useEffect(() => {
    void window.zio.spellcheck.getEnabled().then(setSpellcheck).catch(() => setSpellcheck(true));
    void window.zio.prefs.get('translate_target_lang')
      .then((v) => { if (typeof v === 'string' && v) setTranslateLang(v); })
      .catch(() => {});
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

  return (
    <div style={sectionBodyStyle}>
      <SettingRow
        title="Appearance"
        description="Choose a dark or light look, or follow your computer's setting."
      >
        <select
          value={themeMode}
          onChange={(e) => void changeTheme(e.target.value as ThemeMode)}
          style={selectStyle}
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
          style={selectStyle}
        >
          {TRANSLATE_LANGS.map(l => <option key={l.code} value={l.code}>{l.label}</option>)}
        </select>
      </SettingRow>

      <ToolbarBlock />

      <ImportBlock />
    </div>
  );
}

// ── Pinned toolbar tools ──────────────────────────────────────────────────────

export function ToolbarBlock() {
  // Shared hook keeps this surface in sync with the ChromeBar "⋯" overflow
  // menu via the zio:pinned-tools-changed window event and enforces the cap.
  const { pinned, capReached, togglePin: toggle, movePin: move } = usePinnedTools();

  return (
    <div style={cardStyle}>
      <div style={cardTitleStyle}>Toolbar</div>
      <div style={mutedTextStyle}>
        Pin up to {MAX_PINNED_TOOLS} tools from the “⋯” menu onto the toolbar for one-click access.
        {pinned.length > 1 ? ' Use the arrows to change the order they appear on the toolbar.' : ''}
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginTop: 8 }}>
        {PINNABLE_TOOLS.map((tool) => {
          const info = PINNABLE_TOOL_INFO[tool];
          const pinIndex = pinned.indexOf(tool);
          const isPinned = pinIndex !== -1;
          const disabled = !isPinned && capReached;
          const showArrows = isPinned && pinned.length > 1;
          return (
            <div
              key={tool}
              title={disabled ? `Toolbar is full — unpin another tool first (max ${MAX_PINNED_TOOLS})` : undefined}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 10,
                padding: '6px 8px',
                borderRadius: 8,
                opacity: disabled ? 0.45 : 1,
              }}
            >
              <label
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 10,
                  flex: 1,
                  minWidth: 0,
                  cursor: disabled ? 'default' : 'pointer',
                }}
              >
                <input
                  type="checkbox"
                  checked={isPinned}
                  disabled={disabled}
                  onChange={() => toggle(tool)}
                />
                <span style={{ fontSize: 14, width: 20, textAlign: 'center' }}>{info.icon}</span>
                <span style={{ fontSize: 12, fontWeight: 600 }}>{info.label}</span>
                <span style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>{info.description}</span>
              </label>
              {showArrows && (
                <span style={{ display: 'flex', gap: 2, flexShrink: 0 }}>
                  <button
                    type="button"
                    onClick={() => move(tool, -1)}
                    disabled={pinIndex === 0}
                    aria-label={`Move ${info.label} earlier on the toolbar`}
                    title="Move earlier on the toolbar"
                    style={pinReorderBtnStyle(pinIndex === 0)}
                  >
                    ↑
                  </button>
                  <button
                    type="button"
                    onClick={() => move(tool, 1)}
                    disabled={pinIndex === pinned.length - 1}
                    aria-label={`Move ${info.label} later on the toolbar`}
                    title="Move later on the toolbar"
                    style={pinReorderBtnStyle(pinIndex === pinned.length - 1)}
                  >
                    ↓
                  </button>
                </span>
              )}
            </div>
          );
        })}
      </div>
      {capReached && (
        <div style={{ ...mutedTextStyle, marginTop: 6 }}>
          Toolbar is full — unpin a tool to pin a different one.
        </div>
      )}
    </div>
  );
}

/** Small ↑/↓ button used to reorder pinned toolbar tools. */
function pinReorderBtnStyle(disabled: boolean): React.CSSProperties {
  return {
    width: 22,
    height: 22,
    padding: 0,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    fontSize: 11,
    lineHeight: 1,
    borderRadius: 6,
    border: '1px solid var(--color-border)',
    background: 'transparent',
    color: disabled ? 'var(--color-text-muted)' : 'var(--color-text)',
    opacity: disabled ? 0.35 : 1,
    cursor: disabled ? 'default' : 'pointer',
  };
}

// ── Import bookmarks & history ────────────────────────────────────────────────

interface DetectedImportBrowser { id: string; name: string; hasBookmarks: boolean; hasHistory: boolean }

function ImportBlock() {
  const [enabled, setEnabled] = useState<boolean | null>(null);
  const [browsers, setBrowsers] = useState<DetectedImportBrowser[] | null>(null);
  const [wantBookmarks, setWantBookmarks] = useState(true);
  const [wantHistory, setWantHistory] = useState(true);
  const [busyId, setBusyId] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    void window.zio.prefs.get('import_enabled').then((v) => setEnabled(v !== '0')).catch(() => setEnabled(true));
  }, []);

  useEffect(() => {
    if (enabled !== true) return;
    setBrowsers(null);
    void window.zio.browserImport.detect().then(setBrowsers).catch(() => setBrowsers([]));
  }, [enabled]);

  const toggleEnabled = useCallback(async () => {
    if (enabled === null) return;
    const next = !enabled;
    setEnabled(next);
    setMessage(null);
    try { await window.zio.prefs.set('import_enabled', next ? '1' : '0'); } catch { setEnabled(!next); }
  }, [enabled]);

  const summarize = (r: { ok: boolean; bookmarksImported?: number; historyImported?: number; error?: string; canceled?: boolean }) => {
    if (r.canceled) return null;
    if (!r.ok) return r.error ?? 'Import failed.';
    const parts: string[] = [];
    parts.push(`${r.bookmarksImported ?? 0} bookmark${(r.bookmarksImported ?? 0) === 1 ? '' : 's'}`);
    if ((r.historyImported ?? 0) > 0) parts.push(`${r.historyImported} history item${r.historyImported === 1 ? '' : 's'}`);
    return `Done — imported ${parts.join(' and ')}.`;
  };

  const runImport = useCallback(async (id: string) => {
    if (!wantBookmarks && !wantHistory) {
      setMessage('Pick at least one thing to import.');
      return;
    }
    setBusyId(id);
    setMessage(null);
    try {
      const r = await window.zio.browserImport.run(id, { bookmarks: wantBookmarks, history: wantHistory });
      setMessage(summarize(r));
    } catch {
      setMessage('Import failed.');
    } finally {
      setBusyId(null);
    }
  }, [wantBookmarks, wantHistory]);

  const runHtmlImport = useCallback(async () => {
    setBusyId('__html__');
    setMessage(null);
    try {
      const r = await window.zio.browserImport.fromHtmlFile();
      setMessage(summarize(r));
    } catch {
      setMessage('Import failed.');
    } finally {
      setBusyId(null);
    }
  }, []);

  return (
    <div style={cardStyle}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
        <div style={cardTitleStyle}>Import bookmarks &amp; history</div>
        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 12, cursor: 'pointer', whiteSpace: 'nowrap' }}>
          <input type="checkbox" checked={enabled === true} disabled={enabled === null} onChange={() => void toggleEnabled()} />
          Allow importing
        </label>
      </div>
      <div style={mutedTextStyle}>
        Bring your bookmarks and browsing history over from another browser on this computer.
        Your other browser is never changed.
      </div>

      {enabled === false && (
        <div style={{ ...mutedTextStyle, marginTop: 8 }}>
          Importing is turned off. Zio will not look at or read data from other browsers until you turn it back on.
        </div>
      )}

      {enabled === true && (<>
      <div style={{ display: 'flex', gap: 16, margin: '10px 0 6px' }}>
        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, cursor: 'pointer' }}>
          <input type="checkbox" checked={wantBookmarks} onChange={() => setWantBookmarks(v => !v)} />
          Bookmarks
        </label>
        <label style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 13, cursor: 'pointer' }}>
          <input type="checkbox" checked={wantHistory} onChange={() => setWantHistory(v => !v)} />
          Browsing history
        </label>
      </div>

      {browsers === null && <div style={mutedTextStyle}>Looking for other browsers…</div>}
      {browsers !== null && browsers.length === 0 && (
        <div style={mutedTextStyle}>No other browsers were found on this computer. You can still import a bookmarks file below.</div>
      )}
      {browsers !== null && browsers.map(b => (
        <div key={b.id} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '8px 0', gap: 12 }}>
          <div style={{ minWidth: 0 }}>
            <div style={{ fontSize: 13, fontWeight: 500 }}>{b.name}</div>
            <div style={{ ...mutedTextStyle, fontSize: 12 }}>
              {[b.hasBookmarks ? 'bookmarks' : null, b.hasHistory ? 'history' : null].filter(Boolean).join(' + ')}
            </div>
          </div>
          <button
            style={{ ...smallBtnStyle, opacity: busyId ? 0.6 : 1 }}
            disabled={busyId !== null}
            onClick={() => void runImport(b.id)}
          >
            {busyId === b.id ? 'Importing…' : 'Import'}
          </button>
        </div>
      ))}

      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', paddingTop: 10, gap: 12, borderTop: '1px solid rgba(128,128,128,0.15)', marginTop: 6 }}>
        <div style={{ ...mutedTextStyle, fontSize: 12 }}>
          Or import a bookmarks HTML file exported from any browser.
        </div>
        <button
          style={{ ...smallBtnStyle, opacity: busyId ? 0.6 : 1 }}
          disabled={busyId !== null}
          onClick={() => void runHtmlImport()}
        >
          {busyId === '__html__' ? 'Importing…' : 'Choose file…'}
        </button>
      </div>

      {message && <div style={{ ...mutedTextStyle, marginTop: 8, fontSize: 12 }}>{message}</div>}
      </>)}
    </div>
  );
}

// ── Privacy & Security ────────────────────────────────────────────────────────

interface TrackerStats {
  weekTotal: number;
  todayTotal: number;
  byDay: Array<{ day: string; count: number }>;
  topTrackers: Array<{ host: string; count: number }>;
}

interface SafetyResult {
  passwords: { total: number; weak: number; reused: number };
  permissions: { allowed: number };
  trackerBlocking: boolean;
  doNotTrack: boolean;
}

function PrivacySection() {
  const [trackerEnabled, setTrackerEnabled] = useState<boolean | null>(null);
  const [dnt, setDnt] = useState<boolean | null>(null);
  const [block3p, setBlock3p] = useState<boolean | null>(null);
  const [clearDialogOpen, setClearDialogOpen] = useState(false);
  const [stats, setStats] = useState<TrackerStats | null>(null);
  const [safety, setSafety] = useState<SafetyResult | null>(null);
  const [safetyRunning, setSafetyRunning] = useState(false);
  const [forgetHost, setForgetHost] = useState('');
  const [forgetNote, setForgetNote] = useState<string | null>(null);
  const [retentionDays, setRetentionDays] = useState<string | null>(null);

  useEffect(() => {
    void window.zio.prefs.get('history_days_retention').then((v) => setRetentionDays(v && parseInt(v, 10) > 0 ? v : '0')).catch(() => setRetentionDays('0'));
    void window.zio.tracker.isEnabled().then((v: boolean) => setTrackerEnabled(v)).catch(() => setTrackerEnabled(null));
    void window.zio.prefs.get('do_not_track').then((v) => setDnt(v === '1')).catch(() => setDnt(false));
    void window.zio.prefs.get('block_third_party_cookies').then((v) => setBlock3p(v === '1')).catch(() => setBlock3p(false));
    void window.zio.privacy.trackerStats().then(setStats).catch(() => setStats(null));
  }, []);

  const toggleTracker = useCallback(async () => {
    if (trackerEnabled === null) return;
    const next = !trackerEnabled;
    setTrackerEnabled(next);
    try { await window.zio.tracker.setEnabled(next); } catch { setTrackerEnabled(!next); }
  }, [trackerEnabled]);

  const toggleDnt = useCallback(async () => {
    if (dnt === null) return;
    const next = !dnt;
    setDnt(next);
    try { await window.zio.prefs.set('do_not_track', next ? '1' : '0'); } catch { setDnt(!next); }
  }, [dnt]);

  const toggleBlock3p = useCallback(async () => {
    if (block3p === null) return;
    const next = !block3p;
    setBlock3p(next);
    try { await window.zio.prefs.set('block_third_party_cookies', next ? '1' : '0'); } catch { setBlock3p(!next); }
  }, [block3p]);

  const changeRetention = useCallback(async (value: string) => {
    const prev = retentionDays;
    setRetentionDays(value);
    try { await window.zio.prefs.set('history_days_retention', value); } catch { setRetentionDays(prev); }
  }, [retentionDays]);

  const runSafetyCheck = useCallback(async () => {
    setSafetyRunning(true);
    try {
      setSafety(await window.zio.privacy.safetyCheck());
    } catch {
      setSafety(null);
    } finally {
      setSafetyRunning(false);
    }
  }, []);

  const forgetSite = useCallback(async () => {
    let host = forgetHost.trim().toLowerCase();
    if (!host) return;
    // Accept full URLs too.
    try { if (host.includes('://')) host = new URL(host).hostname; } catch { /* keep as typed */ }
    host = host.replace(/^www\./, '');
    setForgetNote(null);
    try {
      const res = await window.zio.privacy.forgetSite(host);
      if (res.ok) {
        setForgetHost('');
        setForgetNote(`Done — removed ${res.historyDeleted} history ${res.historyDeleted === 1 ? 'entry' : 'entries'}, cookies and site data for ${host}.`);
      } else {
        setForgetNote('Could not forget that site.');
      }
    } catch {
      setForgetNote('Could not forget that site.');
    }
  }, [forgetHost]);

  const maxDay = stats ? Math.max(1, ...stats.byDay.map(d => d.count)) : 1;

  return (
    <div style={sectionBodyStyle}>
      {trackerEnabled !== null && (
        <SettingRow title="Tracker blocking" description="Block known trackers and ads while you browse.">
          <Toggle checked={trackerEnabled} onChange={() => void toggleTracker()} />
        </SettingRow>
      )}

      <SettingRow title="Send “Do Not Track”" description="Ask websites not to track you. Sites decide whether to honor it.">
        <Toggle checked={dnt === true} disabled={dnt === null} onChange={() => void toggleDnt()} />
      </SettingRow>

      <SettingRow title="Block third-party cookies" description="Stop sites you're not visiting from setting cookies. Some sign-in flows may break.">
        <Toggle checked={block3p === true} disabled={block3p === null} onChange={() => void toggleBlock3p()} />
      </SettingRow>

      <SettingRow title="Auto-delete history" description="Automatically delete browsing history older than the time you choose. Runs at startup and in the background.">
        <select
          style={selectStyle}
          value={retentionDays ?? '0'}
          disabled={retentionDays === null}
          onChange={(e) => void changeRetention(e.target.value)}
        >
          <option value="0">Never</option>
          <option value="7">After 7 days</option>
          <option value="30">After 30 days</option>
          <option value="90">After 90 days</option>
          <option value="180">After 180 days</option>
          <option value="365">After 1 year</option>
        </select>
      </SettingRow>

      <SettingRow title="Delete browsing data" description="Delete history, cookies, cache, downloads and permissions for a time range you pick.">
        <button onClick={() => setClearDialogOpen(true)} style={dangerBtnStyle}>Delete…</button>
      </SettingRow>

      {/* Privacy Dashboard */}
      <div style={cardStyle}>
        <div style={cardTitleStyle}>📊 Privacy Dashboard</div>
        {stats === null ? (
          <div style={mutedTextStyle}>No tracker activity recorded yet.</div>
        ) : (
          <>
            <div style={{ display: 'flex', gap: 16, marginBottom: 10 }}>
              <div>
                <div style={{ fontSize: 18, fontWeight: 700 }}>{stats.weekTotal}</div>
                <div style={mutedTextStyle}>blocked this week</div>
              </div>
              <div>
                <div style={{ fontSize: 18, fontWeight: 700 }}>{stats.todayTotal}</div>
                <div style={mutedTextStyle}>blocked today</div>
              </div>
            </div>
            {stats.byDay.length > 0 && (
              <div style={{ display: 'flex', alignItems: 'flex-end', gap: 4, height: 40, marginBottom: 10 }}>
                {stats.byDay.map(d => (
                  <div key={d.day} title={`${d.day}: ${d.count}`} style={{
                    flex: 1,
                    height: Math.max(2, Math.round((d.count / maxDay) * 40)),
                    borderRadius: 3,
                    background: 'var(--gradient-primary)',
                    opacity: d.count === 0 ? 0.2 : 0.85,
                  }} />
                ))}
              </div>
            )}
            {stats.topTrackers.length > 0 && (
              <div>
                <div style={{ ...mutedTextStyle, marginBottom: 4 }}>Most-blocked trackers</div>
                {stats.topTrackers.slice(0, 5).map(t => (
                  <div key={t.host} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 11, padding: '2px 0' }}>
                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.host}</span>
                    <span style={{ color: 'var(--color-text-muted)', flexShrink: 0, marginLeft: 8 }}>{t.count}</span>
                  </div>
                ))}
              </div>
            )}
          </>
        )}
      </div>

      {/* Safety Check */}
      <div style={cardStyle}>
        <div style={cardTitleStyle}>✅ Safety Check</div>
        <div style={{ ...mutedTextStyle, marginBottom: 8 }}>
          Review your passwords, permissions and privacy protections in one go.
        </div>
        <button onClick={() => void runSafetyCheck()} disabled={safetyRunning} style={primaryBtnStyle}>
          {safetyRunning ? 'Checking…' : 'Run Safety Check'}
        </button>
        {safety && (
          <div style={{ marginTop: 10, display: 'flex', flexDirection: 'column', gap: 6 }}>
            <SafetyLine
              ok={safety.passwords.weak === 0 && safety.passwords.reused === 0}
              text={safety.passwords.total === 0
                ? 'No saved passwords to check.'
                : `${safety.passwords.total} saved passwords — ${safety.passwords.weak} weak, ${safety.passwords.reused} reused.`}
            />
            <SafetyLine
              ok={true}
              text={`${safety.permissions.allowed} site permission${safety.permissions.allowed === 1 ? '' : 's'} currently allowed.`}
            />
            <SafetyLine ok={safety.trackerBlocking} text={safety.trackerBlocking ? 'Tracker blocking is on.' : 'Tracker blocking is off — consider turning it on.'} />
            <SafetyLine ok={safety.doNotTrack} text={safety.doNotTrack ? '“Do Not Track” is on.' : '“Do Not Track” is off.'} />
          </div>
        )}
      </div>

      {/* Forget this site */}
      <div style={cardStyle}>
        <div style={cardTitleStyle}>🧹 Forget this site</div>
        <div style={{ ...mutedTextStyle, marginBottom: 8 }}>
          Remove all history, cookies, site data, permissions and saved passwords for one website.
        </div>
        <div style={{ display: 'flex', gap: 6 }}>
          <input
            value={forgetHost}
            onChange={(e) => setForgetHost(e.target.value)}
            onKeyDown={(e) => { if (e.key === 'Enter') void forgetSite(); }}
            placeholder="example.com"
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
          <button onClick={() => void forgetSite()} disabled={!forgetHost.trim()} style={dangerBtnStyle}>Forget</button>
        </div>
        {forgetNote && <div style={{ ...mutedTextStyle, marginTop: 6 }}>{forgetNote}</div>}
      </div>

      {clearDialogOpen && <ClearDataDialog onClose={() => setClearDialogOpen(false)} />}
    </div>
  );
}

function SafetyLine({ ok, text }: { ok: boolean; text: string }) {
  return (
    <div style={{ display: 'flex', gap: 6, fontSize: 12, alignItems: 'flex-start' }}>
      <span>{ok ? '✅' : '⚠️'}</span>
      <span style={{ color: 'var(--color-text)' }}>{text}</span>
    </div>
  );
}

// ── Site Settings (permissions) ───────────────────────────────────────────────

interface PermissionRow {
  origin: string;
  permission: string;
  decision: 'allow' | 'block';
}

const PERMISSION_LABELS: Record<string, string> = {
  media: 'Camera & microphone',
  geolocation: 'Location',
  notifications: 'Notifications',
  midi: 'MIDI devices',
  pointerLock: 'Mouse lock',
  fullscreen: 'Full screen',
  clipboard: 'Clipboard',
  'clipboard-read': 'Clipboard',
  'clipboard-sanitized-write': 'Clipboard',
};

function SiteSettingsSection() {
  const [rows, setRows] = useState<PermissionRow[]>([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const list = (await window.zio.permissions.getAll()) as PermissionRow[];
      setRows(Array.isArray(list) ? list : []);
    } catch {
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void load(); }, [load]);

  const revoke = useCallback(async (origin: string, permission: string) => {
    try {
      await window.zio.permissions.revoke(origin, permission);
      setRows(prev => prev.filter(r => !(r.origin === origin && r.permission === permission)));
    } catch { /* keep row on failure */ }
  }, []);

  const clearAll = useCallback(async () => {
    if (!window.confirm('Reset all site permissions? Sites will ask again next time.')) return;
    try {
      await window.zio.permissions.clearAll();
      setRows([]);
    } catch { /* non-fatal */ }
  }, []);

  return (
    <div style={sectionBodyStyle}>
      <div style={mutedTextStyle}>
        Choices you've made when sites asked for camera, location, notifications and other permissions.
      </div>

      {loading ? (
        <div style={mutedTextStyle}>Loading…</div>
      ) : rows.length === 0 ? (
        <div style={{ ...mutedTextStyle, padding: '16px 0', textAlign: 'center' }}>
          <div style={{ fontSize: 24, marginBottom: 6 }}>🌐</div>
          No saved site permissions yet.
        </div>
      ) : (
        <>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            {rows.map(r => (
              <div key={`${r.origin}|${r.permission}`} style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                padding: '7px 10px',
                borderRadius: 8,
                border: '1px solid var(--color-border)',
                background: 'var(--color-bg-elevated)',
              }}>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 12, fontWeight: 600, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                    {r.origin}
                  </div>
                  <div style={{ fontSize: 11, color: 'var(--color-text-muted)' }}>
                    {PERMISSION_LABELS[r.permission] ?? r.permission} — {r.decision === 'allow' ? 'Allowed' : 'Blocked'}
                  </div>
                </div>
                <span style={{ fontSize: 12 }}>{r.decision === 'allow' ? '✅' : '🚫'}</span>
                <button onClick={() => void revoke(r.origin, r.permission)} style={smallBtnStyle} title="Remove — the site will ask again">✕</button>
              </div>
            ))}
          </div>
          <div>
            <button onClick={() => void clearAll()} style={dangerBtnStyle}>Reset all permissions</button>
          </div>
        </>
      )}
    </div>
  );
}

// ── Search engine ─────────────────────────────────────────────────────────────

const SEARCH_ENGINE_OPTIONS: Array<{ key: string; label: string }> = [
  { key: 'google', label: 'Google' },
  { key: 'bing', label: 'Bing' },
  { key: 'duckduckgo', label: 'DuckDuckGo' },
  { key: 'brave', label: 'Brave Search' },
];

function SearchEngineSection() {
  const [engine, setEngine] = useState('google');

  useEffect(() => {
    void window.zio.prefs.get('search_engine')
      .then((v) => { if (typeof v === 'string' && SEARCH_ENGINE_OPTIONS.some(o => o.key === v)) setEngine(v); })
      .catch(() => {});
  }, []);

  const change = useCallback(async (key: string) => {
    setEngine(key);
    try { await window.zio.prefs.set('search_engine', key); } catch { /* non-fatal */ }
  }, []);

  return (
    <div style={sectionBodyStyle}>
      <div style={mutedTextStyle}>
        The search engine used when you type a search into the address bar. Takes effect right away.
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        {SEARCH_ENGINE_OPTIONS.map(o => (
          <label key={o.key} style={{
            display: 'flex',
            alignItems: 'center',
            gap: 10,
            padding: '9px 12px',
            borderRadius: 8,
            border: `1px solid ${engine === o.key ? 'var(--color-primary)' : 'var(--color-border)'}`,
            background: engine === o.key
              ? 'color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-elevated))'
              : 'var(--color-bg-elevated)',
            cursor: 'pointer',
          }}>
            <input
              type="radio"
              name="search-engine"
              checked={engine === o.key}
              onChange={() => void change(o.key)}
            />
            <span style={{ fontSize: 12, fontWeight: 600 }}>{o.label}</span>
          </label>
        ))}
      </div>
    </div>
  );
}

// ── On startup ────────────────────────────────────────────────────────────────

function StartupSection() {
  const [mode, setMode] = useState<'continue' | 'newtab'>('continue');

  useEffect(() => {
    void window.zio.prefs.get('startup_mode')
      .then((v) => { if (v === 'newtab' || v === 'continue') setMode(v); })
      .catch(() => {});
  }, []);

  const change = useCallback(async (next: 'continue' | 'newtab') => {
    setMode(next);
    try { await window.zio.prefs.set('startup_mode', next); } catch { /* non-fatal */ }
  }, []);

  const options: Array<{ key: 'continue' | 'newtab'; title: string; desc: string }> = [
    { key: 'continue', title: 'Continue where you left off', desc: 'Reopen the tabs you had open last time.' },
    { key: 'newtab', title: 'Open the New Tab page', desc: 'Start fresh each time. Pinned tabs still load.' },
  ];

  return (
    <div style={sectionBodyStyle}>
      <div style={mutedTextStyle}>What Zio Browser shows when you open it.</div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
        {options.map(o => (
          <label key={o.key} style={{
            display: 'flex',
            alignItems: 'flex-start',
            gap: 10,
            padding: '10px 12px',
            borderRadius: 8,
            border: `1px solid ${mode === o.key ? 'var(--color-primary)' : 'var(--color-border)'}`,
            background: mode === o.key
              ? 'color-mix(in srgb, var(--color-primary) 10%, var(--color-bg-elevated))'
              : 'var(--color-bg-elevated)',
            cursor: 'pointer',
          }}>
            <input
              type="radio"
              name="startup-mode"
              checked={mode === o.key}
              onChange={() => void change(o.key)}
              style={{ marginTop: 2 }}
            />
            <span>
              <span style={{ display: 'block', fontSize: 12, fontWeight: 600 }}>{o.title}</span>
              <span style={{ display: 'block', fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2 }}>{o.desc}</span>
            </span>
          </label>
        ))}
      </div>
      <div style={mutedTextStyle}>Changes apply the next time you open the browser.</div>
    </div>
  );
}

// ── Downloads ─────────────────────────────────────────────────────────────────

function DownloadsSection() {
  const [dir, setDir] = useState<string | null>(null);
  const [defaultDir, setDefaultDir] = useState<string>('');
  const [ask, setAsk] = useState<boolean | null>(null);

  useEffect(() => {
    void window.zio.prefs.get('download_path').then((v) => { if (typeof v === 'string' && v) setDir(v); }).catch(() => {});
    void window.zio.downloads.defaultDirectory().then((v: string) => setDefaultDir(v)).catch(() => {});
    void window.zio.prefs.get('download_ask').then((v) => setAsk(v === '1')).catch(() => setAsk(false));
  }, []);

  const chooseFolder = useCallback(async () => {
    try {
      const picked = (await window.zio.downloads.chooseDirectory()) as string | null;
      if (picked) {
        setDir(picked);
        await window.zio.prefs.set('download_path', picked);
      }
    } catch { /* non-fatal */ }
  }, []);

  const resetFolder = useCallback(async () => {
    setDir(null);
    try { await window.zio.prefs.set('download_path', ''); } catch { /* non-fatal */ }
  }, []);

  const toggleAsk = useCallback(async () => {
    if (ask === null) return;
    const next = !ask;
    setAsk(next);
    try { await window.zio.prefs.set('download_ask', next ? '1' : '0'); } catch { setAsk(!next); }
  }, [ask]);

  return (
    <div style={sectionBodyStyle}>
      <SettingRow title="Save files to" description={dir ?? defaultDir ?? 'Default downloads folder'}>
        <div style={{ display: 'flex', gap: 6 }}>
          <button onClick={() => void chooseFolder()} style={secondaryBtnStyle}>Change…</button>
          {dir && <button onClick={() => void resetFolder()} style={secondaryBtnStyle} title="Use the default downloads folder">Reset</button>}
        </div>
      </SettingRow>

      <SettingRow title="Ask where to save each file" description="Choose the location for every download instead of saving automatically.">
        <Toggle checked={ask === true} disabled={ask === null} onChange={() => void toggleAsk()} />
      </SettingRow>
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
            background: 'var(--gradient-primary)',
            color: '#fff',
            border: 'none',
            opacity: adding ? 0.6 : 1,
          }}
        >{adding ? 'Choosing…' : 'Load unpacked extension…'}</button>
        {error && <div style={{ marginTop: 8, fontSize: 12, color: 'var(--color-danger, #e5484d)' }}>{error}</div>}
      </div>

      {loading && (
        <div style={{ padding: '16px', color: 'var(--color-text-muted)', fontSize: 13 }}>Loading…</div>
      )}

      {!loading && exts.length === 0 && (
        <div style={{ padding: '20px', textAlign: 'center', color: 'var(--color-text-muted)', fontSize: 13 }}>
          <div style={{ fontSize: 26, marginBottom: 8 }}>🧩</div>
          <div style={{ fontWeight: 600, marginBottom: 4 }}>No extensions loaded</div>
          <div style={{ lineHeight: 1.5 }}>Pick a folder containing an unpacked Chrome extension (with a manifest.json).</div>
        </div>
      )}

      {!loading && exts.map(ext => (
        <div key={ext.id} style={{
          display: 'flex',
          alignItems: 'center',
          gap: 10,
          padding: '8px 16px',
          borderBottom: '1px solid var(--color-border)',
          opacity: ext.missing ? 0.5 : 1,
        }}>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{
              fontSize: 13, fontWeight: 600, color: 'var(--color-text)',
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>
              {ext.name}
              <span style={{ fontWeight: 400, color: 'var(--color-text-muted)', marginLeft: 6, fontSize: 11 }}>v{ext.version}</span>
              {ext.builtin && <span style={{ fontWeight: 600, color: 'var(--color-primary)', marginLeft: 6, fontSize: 10 }}>BUILT-IN</span>}
              {ext.missing && <span style={{ fontWeight: 600, color: 'var(--color-danger, #e5484d)', marginLeft: 6, fontSize: 10 }}>MISSING</span>}
            </div>
            <div style={{
              fontSize: 11, color: 'var(--color-text-muted)', marginTop: 1,
              overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            }}>{ext.path}</div>
          </div>
          {!ext.builtin && (
            <button
              onClick={() => void handleRemove(ext.id)}
              title="Remove this extension"
              style={smallBtnStyle}
            >✕</button>
          )}
        </div>
      ))}
    </div>
  );
}

// ── Shared bits ───────────────────────────────────────────────────────────────

function SettingRow({ title, description, children }: {
  title: string;
  description: string;
  children: React.ReactNode;
}) {
  return (
    <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--color-text)' }}>{title}</div>
        <div style={{ fontSize: 11, color: 'var(--color-text-muted)', marginTop: 2, lineHeight: 1.5 }}>{description}</div>
      </div>
      <div style={{ flexShrink: 0 }}>{children}</div>
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
        border: '1px solid var(--color-border)',
        background: checked ? 'var(--color-primary)' : 'var(--color-bg-elevated)',
        position: 'relative',
        transition: 'background 0.15s',
        opacity: disabled ? 0.5 : 1,
        flexShrink: 0,
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
        boxShadow: '0 1px 2px rgba(0,0,0,0.3)',
      }} />
    </button>
  );
}

const sectionBodyStyle: React.CSSProperties = {
  padding: '12px 16px',
  display: 'flex',
  flexDirection: 'column',
  gap: 18,
};

const selectStyle: React.CSSProperties = {
  fontSize: 12,
  padding: '4px 8px',
  borderRadius: 8,
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  border: '1px solid var(--color-border)',
  maxWidth: 160,
};

const cardStyle: React.CSSProperties = {
  padding: '12px 14px',
  borderRadius: 10,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg-elevated)',
};

const cardTitleStyle: React.CSSProperties = {
  fontSize: 13,
  fontWeight: 700,
  marginBottom: 8,
};

const mutedTextStyle: React.CSSProperties = {
  fontSize: 11,
  color: 'var(--color-text-muted)',
  lineHeight: 1.5,
};

const primaryBtnStyle: React.CSSProperties = {
  fontSize: 12,
  fontWeight: 600,
  padding: '6px 14px',
  borderRadius: 8,
  background: 'var(--gradient-primary)',
  color: '#fff',
  border: 'none',
};

const secondaryBtnStyle: React.CSSProperties = {
  fontSize: 12,
  fontWeight: 600,
  padding: '5px 12px',
  borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  whiteSpace: 'nowrap',
};

const dangerBtnStyle: React.CSSProperties = {
  fontSize: 12,
  fontWeight: 600,
  padding: '5px 12px',
  borderRadius: 8,
  background: 'color-mix(in srgb, var(--color-danger, #ef4444) 12%, var(--color-bg-elevated))',
  border: '1px solid color-mix(in srgb, var(--color-danger, #ef4444) 30%, transparent)',
  color: 'var(--color-danger, #ef4444)',
  whiteSpace: 'nowrap',
};

const smallBtnStyle: React.CSSProperties = {
  fontSize: 13,
  color: 'var(--color-text-muted)',
  padding: '4px 6px',
  borderRadius: 6,
  flexShrink: 0,
};
