/**
 * Chrome (browser UI) theming — decoupled from the website color scheme.
 *
 * The Appearance setting themes ONLY Zio's own chrome by toggling the
 * `light-mode` class on <html>. It never touches Electron's
 * `nativeTheme.themeSource`, which is reserved for the separate
 * "Website appearance" setting (what pages see via prefers-color-scheme).
 *
 * "System" mode can't rely on this window's own matchMedia — Electron feeds
 * every renderer the (possibly overridden) nativeTheme signal — so the real
 * OS scheme is asked from the main process, with live updates pushed over
 * the 'theme:system-changed' channel.
 */

export type ThemeMode = 'system' | 'dark' | 'light';

let currentMode: ThemeMode = 'system';
let listenerInstalled = false;

function applyResolved(resolved: 'dark' | 'light'): void {
  document.documentElement.classList.toggle('light-mode', resolved === 'light');
}

/** Apply a chrome appearance mode. Returns the resolved look. */
export async function applyChromeTheme(mode: ThemeMode): Promise<'dark' | 'light'> {
  currentMode = mode;
  let resolved: 'dark' | 'light';
  if (mode === 'light' || mode === 'dark') {
    resolved = mode;
  } else {
    try {
      resolved = (await window.zio.theme.getSystem()) === 'light' ? 'light' : 'dark';
    } catch {
      resolved = 'dark'; // default chrome look
    }
  }
  applyResolved(resolved);
  return resolved;
}

/** Follow live OS scheme changes while the chrome mode is 'system'. Idempotent. */
export function installChromeThemeListener(): void {
  if (listenerInstalled) return;
  listenerInstalled = true;
  window.zio.on('theme:system-changed', (scheme) => {
    if (currentMode !== 'system') return;
    applyResolved(scheme === 'light' ? 'light' : 'dark');
  });
}
