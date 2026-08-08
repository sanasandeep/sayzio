/**
 * Theme split: browser-chrome appearance vs website color scheme.
 *
 * Electron's global `nativeTheme.themeSource` controls the
 * `prefers-color-scheme` signal EVERY renderer sees — including websites.
 * The chrome "Appearance" setting therefore must never touch it; only the
 * separate "Website appearance" preference maps onto `themeSource`.
 *
 * Because overriding `themeSource` hides the real OS scheme, chrome-side
 * "System" resolution uses a momentary flip-probe: set themeSource back to
 * 'system', read `shouldUseDarkColors`, restore. The probe is synchronous and
 * guarded so the re-entrant 'updated' events it fires are ignored.
 */
import { BrowserWindow, nativeTheme } from 'electron';
import { getPreference, setPreference, isDbInitialized } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

export type AppearanceMode = 'system' | 'light' | 'dark';
/** Website appearance additionally supports 'browser' = inherit the chrome Appearance setting. */
export type WebsiteAppearanceMode = AppearanceMode | 'browser';

export function sanitizeAppearance(v: unknown): AppearanceMode {
  return v === 'light' || v === 'dark' ? v : 'system';
}

export function sanitizeWebsiteAppearance(v: unknown): WebsiteAppearanceMode {
  return v === 'browser' ? 'browser' : sanitizeAppearance(v);
}

let probing = false;

/** True when the REAL OS color scheme is dark, regardless of any website-appearance override. */
export function systemPrefersDark(): boolean {
  if (nativeTheme.themeSource === 'system') return nativeTheme.shouldUseDarkColors;
  const prev = nativeTheme.themeSource;
  probing = true;
  try {
    nativeTheme.themeSource = 'system';
    return nativeTheme.shouldUseDarkColors;
  } finally {
    nativeTheme.themeSource = prev;
    probing = false;
  }
}

function safeGetPref(key: (typeof PREFERENCE_KEYS)[keyof typeof PREFERENCE_KEYS]): string | null {
  try {
    if (!isDbInitialized()) return null;
    return getPreference(key);
  } catch {
    return null;
  }
}

/** Current persisted website-appearance mode (defaults to 'system'). */
export function getWebsiteAppearance(): WebsiteAppearanceMode {
  return sanitizeWebsiteAppearance(safeGetPref(PREFERENCE_KEYS.WEBSITE_APPEARANCE));
}

/**
 * Map a website-appearance mode to a concrete nativeTheme.themeSource value.
 * 'browser' inherits the chrome Appearance preference (its 'system' also
 * maps to the OS scheme).
 */
function resolveWebsiteThemeSource(mode: WebsiteAppearanceMode): AppearanceMode {
  if (mode === 'browser') return sanitizeAppearance(safeGetPref(PREFERENCE_KEYS.THEME));
  return mode;
}

/**
 * Apply + persist the website color scheme. This is the ONLY place that may
 * assign `nativeTheme.themeSource`. Applies live to all open tabs (regular
 * and private windows share the global nativeTheme).
 */
export function setWebsiteAppearance(mode: WebsiteAppearanceMode): WebsiteAppearanceMode {
  const clean = sanitizeWebsiteAppearance(mode);
  nativeTheme.themeSource = resolveWebsiteThemeSource(clean);
  try { setPreference(PREFERENCE_KEYS.WEBSITE_APPEARANCE, clean); } catch { /* best-effort */ }
  return clean;
}

/** Restore the persisted website appearance at startup (default: system). */
export function restoreWebsiteAppearance(): void {
  nativeTheme.themeSource = resolveWebsiteThemeSource(getWebsiteAppearance());
}

/**
 * Re-apply the website theme when the chrome Appearance preference changes,
 * so a website mode of 'browser' (inherit) follows the chrome live.
 */
export function reapplyWebsiteAppearanceForChromeChange(): void {
  if (getWebsiteAppearance() !== 'browser') return;
  nativeTheme.themeSource = resolveWebsiteThemeSource('browser');
}

/**
 * Resolve what the CHROME should look like for the saved Appearance
 * preference — light/dark direct, 'system' follows the real OS scheme.
 * Used for the pre-paint window background color.
 */
export function chromePrefersDark(): boolean {
  const mode = sanitizeAppearance(safeGetPref(PREFERENCE_KEYS.THEME));
  if (mode === 'light') return false;
  if (mode === 'dark') return true;
  return systemPrefersDark();
}

let bridgeInstalled = false;

/**
 * Broadcast real OS scheme changes to every window so chrome set to "System"
 * follows the Mac live. Ignores the 'updated' events our own flip-probe and
 * website-appearance writes fire.
 */
export function initThemeBridge(): void {
  if (bridgeInstalled) return;
  bridgeInstalled = true;
  nativeTheme.on('updated', () => {
    if (probing) return;
    const scheme = systemPrefersDark() ? 'dark' : 'light';
    for (const win of BrowserWindow.getAllWindows()) {
      try { win.webContents.send('theme:system-changed', scheme); } catch { /* window closing */ }
    }
  });
}
