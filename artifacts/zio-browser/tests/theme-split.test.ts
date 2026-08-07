/**
 * Chrome theme vs website color scheme split.
 *
 * The chrome "Appearance" setting must never touch nativeTheme.themeSource
 * (which controls the prefers-color-scheme signal EVERY website sees); only
 * the separate "Website appearance" preference maps onto it. Covers:
 *  - website-appearance set/persist/restore (survives restart)
 *  - system-scheme probe never leaves themeSource changed
 *  - chrome background resolution ignores the website override
 *  - OS scheme changes broadcast to all windows (probe re-entrancy guarded)
 */
import { describe, it, expect, beforeAll, vi } from 'vitest';
import { EventEmitter } from 'events';
import { PREFERENCE_KEYS } from '../src/shared/db-schema';

// ── Fake electron with a functional nativeTheme ──────────────────────────────

const themeEmitter = new EventEmitter();
const fakeNativeTheme = {
  _osDark: false,
  _source: 'system' as 'system' | 'light' | 'dark',
  get themeSource() { return this._source; },
  set themeSource(v: 'system' | 'light' | 'dark') {
    this._source = v;
    themeEmitter.emit('updated');
  },
  get shouldUseDarkColors() {
    if (this._source === 'dark') return true;
    if (this._source === 'light') return false;
    return this._osDark;
  },
  on: (event: string, cb: () => void) => { themeEmitter.on(event, cb); },
};

interface FakeWin { webContents: { send: ReturnType<typeof vi.fn> } }
const fakeWindows: FakeWin[] = [];

vi.mock('electron', () => ({
  app: { getPath: () => '/tmp/zio-browser-theme-test' },
  nativeTheme: fakeNativeTheme,
  BrowserWindow: {
    getAllWindows: () => fakeWindows,
  },
}));

let theme: typeof import('../src/main/theme');
let db: typeof import('../src/main/db');

beforeAll(async () => {
  db = await import('../src/main/db');
  db.initDb(':memory:');
  theme = await import('../src/main/theme');
});

describe('website appearance owns nativeTheme.themeSource', () => {
  it('defaults to system', () => {
    expect(theme.getWebsiteAppearance()).toBe('system');
  });

  it('set applies to nativeTheme and persists; restore replays it (restart survival)', () => {
    theme.setWebsiteAppearance('dark');
    expect(fakeNativeTheme.themeSource).toBe('dark');
    expect(db.getPreference(PREFERENCE_KEYS.WEBSITE_APPEARANCE)).toBe('dark');

    // Simulate a restart: Electron resets themeSource, then startup restores.
    fakeNativeTheme._source = 'system';
    theme.restoreWebsiteAppearance();
    expect(fakeNativeTheme.themeSource).toBe('dark');

    // Back to system follows the OS again.
    theme.setWebsiteAppearance('system');
    expect(fakeNativeTheme.themeSource).toBe('system');
    expect(theme.getWebsiteAppearance()).toBe('system');
  });

  it('sanitizes junk to system', () => {
    expect(theme.sanitizeAppearance('bogus')).toBe('system');
    expect(theme.sanitizeAppearance(null)).toBe('system');
    expect(theme.sanitizeAppearance('light')).toBe('light');
  });
});

describe('systemPrefersDark probe', () => {
  it('reports the REAL OS scheme even while websites are overridden dark', () => {
    fakeNativeTheme._osDark = false;
    theme.setWebsiteAppearance('dark');
    expect(fakeNativeTheme.shouldUseDarkColors).toBe(true); // websites see dark
    expect(theme.systemPrefersDark()).toBe(false);          // OS is light
    // Probe must not leave themeSource flipped.
    expect(fakeNativeTheme.themeSource).toBe('dark');
    theme.setWebsiteAppearance('system');
  });
});

describe('chromePrefersDark (window background)', () => {
  it('follows the chrome Appearance pref, not the website override', () => {
    fakeNativeTheme._osDark = false;
    theme.setWebsiteAppearance('dark'); // Gmail dark…
    db.setPreference(PREFERENCE_KEYS.THEME, 'light');
    expect(theme.chromePrefersDark()).toBe(false); // …chrome light

    db.setPreference(PREFERENCE_KEYS.THEME, 'dark');
    theme.setWebsiteAppearance('light'); // pages light…
    expect(theme.chromePrefersDark()).toBe(true); // …chrome dark

    db.setPreference(PREFERENCE_KEYS.THEME, 'system');
    fakeNativeTheme._osDark = true;
    expect(theme.chromePrefersDark()).toBe(true); // system follows the real OS
    fakeNativeTheme._osDark = false;
    expect(theme.chromePrefersDark()).toBe(false);
    theme.setWebsiteAppearance('system');
  });
});

describe('theme bridge broadcast', () => {
  it('sends the real OS scheme to every window on OS changes, once per change', () => {
    const win1: FakeWin = { webContents: { send: vi.fn() } };
    const win2: FakeWin = { webContents: { send: vi.fn() } };
    fakeWindows.push(win1, win2);
    theme.initThemeBridge();

    // Override websites to dark, then flip the OS to dark → chrome 'system'
    // windows must hear about the REAL OS change.
    theme.setWebsiteAppearance('dark');
    win1.webContents.send.mockClear();
    win2.webContents.send.mockClear();

    fakeNativeTheme._osDark = true;
    themeEmitter.emit('updated'); // OS change event

    const calls1 = win1.webContents.send.mock.calls.filter((c) => c[0] === 'theme:system-changed');
    expect(calls1).toEqual([['theme:system-changed', 'dark']]);
    const calls2 = win2.webContents.send.mock.calls.filter((c) => c[0] === 'theme:system-changed');
    expect(calls2).toEqual([['theme:system-changed', 'dark']]);
    theme.setWebsiteAppearance('system');
    fakeWindows.length = 0;
  });
});
