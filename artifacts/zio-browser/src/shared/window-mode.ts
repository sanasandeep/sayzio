/**
 * Window mode type definitions shared between main and renderer.
 */

export type WindowMode = 'dashboard' | 'split' | 'browser';

export const WINDOW_MODES: WindowMode[] = ['dashboard', 'split', 'browser'];

export const WINDOW_MODE_LABELS: Record<WindowMode, string> = {
  dashboard: 'Dashboard',
  split: 'Split',
  browser: 'Browser',
};

export const WINDOW_MODE_DESCRIPTIONS: Record<WindowMode, string> = {
  dashboard: 'Open the Sayzio dashboard as an app',
  split: 'Sayzio + Zio tools on the left, browser on the right',
  browser: 'Full browser with Zio panel on demand',
};

export const WINDOW_MODE_ICONS: Record<WindowMode, string> = {
  dashboard: '⊡',
  split: '⬛',
  browser: '🌐',
};

// ── Per-tab view modes ────────────────────────────────────────────────────────
// Each tab is built from three primitives ("panes"):
//   browser   — any website (the tab's own web view)
//   dashboard — the Sayzio dashboard app (1in.me/user/dashboard)
//   zio       — the Ask Zio AI panel (renderer-drawn)
// A tab shows ONE pane, or a split of TWO panes → 7 possible modes.
// (The standalone 'sayzio' website pane was removed — the Sayzio site is
// reachable as a regular website tab; see normalizeTabMode for legacy mapping.)

export type TabPane = 'browser' | 'dashboard' | 'zio' | 'files';

export const TAB_PANES: TabPane[] = ['browser', 'dashboard', 'zio', 'files'];

export const TAB_PANE_LABELS: Record<TabPane, string> = {
  browser: 'Website',
  dashboard: 'Dashboard',
  zio: 'Ask Zio',
  files: 'My Files',
};

export const TAB_PANE_ICONS: Record<TabPane, string> = {
  browser: '🌐',
  dashboard: '⊡',
  zio: '⚡',
  files: '📁',
};

/**
 * A tab mode is a single pane id, or `'left+right'` for a two-pane split.
 * The pane order in the string IS the layout order (left pane first).
 */
export type TabMode =
  | 'browser'
  | 'dashboard'
  | 'zio'
  | 'files'
  | 'dashboard+browser'
  | 'browser+browser'
  | 'browser+zio'
  | 'dashboard+zio'
  | 'dashboard+files'
  | 'files+zio'
  | 'browser+files';

export const TAB_MODES: TabMode[] = [
  'browser',
  'dashboard',
  'zio',
  'files',
  'dashboard+browser',
  'browser+browser',
  'browser+zio',
  'dashboard+zio',
  'dashboard+files',
  'files+zio',
  // 'browser+files' (Website + My Files) is intentionally NOT offered in the
  // picker (removed by owner request — redundant with the full My Files tab).
  // The mode itself stays in the TabMode type + layout handling so any tab
  // persisted in that mode before the removal still renders and can be
  // switched away from.
];

export const TAB_MODE_LABELS: Record<TabMode, string> = {
  browser: 'Website',
  dashboard: 'Dashboard',
  zio: 'Ask Zio',
  files: 'My Files',
  'dashboard+browser': 'Dashboard + Website',
  'browser+browser': 'Website + Website',
  'browser+zio': 'Website + Ask Zio',
  'dashboard+zio': 'Dashboard + Ask Zio',
  'dashboard+files': 'Dashboard + My Files',
  'files+zio': 'My Files + Ask Zio',
  'browser+files': 'Website + My Files',
};

export const TAB_MODE_DESCRIPTIONS: Record<TabMode, string> = {
  browser: 'Just the website in this tab',
  dashboard: 'Your Sayzio dashboard fills this tab',
  zio: 'Ask Zio fills this tab',
  files: 'Your Sayzio Files fill this tab — drop files to upload',
  'dashboard+browser': 'Dashboard on the left, website on the right',
  'browser+browser': 'Two independent websites side by side',
  'browser+zio': 'Website on the left, Ask Zio on the right',
  'dashboard+zio': 'Dashboard on the left, Ask Zio on the right',
  'dashboard+files': 'Dashboard on the left, your files on the right',
  'files+zio': 'Your files on the left, Ask Zio on the right',
  'browser+files': 'Website on the left, your files on the right',
};

export const TAB_MODE_ICONS: Record<TabMode, string> = {
  browser: '🌐',
  dashboard: '⊡',
  zio: '⚡',
  files: '📁',
  'dashboard+browser': '⊡🌐',
  'browser+browser': '🌐🌐',
  'browser+zio': '🌐⚡',
  'dashboard+zio': '⊡⚡',
  'dashboard+files': '⊡📁',
  'files+zio': '📁⚡',
  'browser+files': '🌐📁',
};

/** Split a TabMode into its left/right panes. */
export function parseTabMode(mode: TabMode): { left: TabPane; right: TabPane | null } {
  const idx = mode.indexOf('+');
  if (idx === -1) return { left: mode as TabPane, right: null };
  return { left: mode.slice(0, idx) as TabPane, right: mode.slice(idx + 1) as TabPane };
}

/** True when the mode contains the given pane (alone or in a split). */
export function tabModeIncludes(mode: TabMode, pane: TabPane): boolean {
  const { left, right } = parseTabMode(mode);
  return left === pane || right === pane;
}

/**
 * Normalize any incoming mode string — legacy v0.1.x values, raw pane pairs
 * in either order, or already-canonical modes — into a canonical TabMode.
 * Returns null when the string can't be interpreted.
 */
export function normalizeTabMode(raw: string | null | undefined): TabMode | null {
  if (!raw) return null;
  if ((TAB_MODES as string[]).includes(raw)) return raw as TabMode;
  // Legacy modes: v0.1.x values plus the removed 'sayzio' website pane.
  // Persisted sayzio modes restore to the nearest surviving mode; the
  // standalone 'sayzio' tab restores as a website tab (the caller points it
  // at the Sayzio home URL — see TabManager.setTabMode).
  const legacy: Record<string, TabMode> = {
    web: 'browser',
    'sayzio-split': 'dashboard+browser',
    'zio-split': 'browser+zio',
    sayzio: 'browser',
    'sayzio+browser': 'browser',
    'browser+sayzio': 'browser',
    'dashboard+sayzio': 'dashboard',
    'sayzio+dashboard': 'dashboard',
    'sayzio+zio': 'zio',
    'zio+sayzio': 'zio',
  };
  if (raw in legacy) return legacy[raw] as TabMode;
  // Non-canonical pane pair (e.g. 'browser+dashboard') → canonical order
  const idx = raw.indexOf('+');
  if (idx !== -1) {
    const a = raw.slice(0, idx);
    const b = raw.slice(idx + 1);
    if ((TAB_PANES as string[]).includes(a) && (TAB_PANES as string[]).includes(b) && a !== b) {
      const flipped = `${b}+${a}`;
      if ((TAB_MODES as string[]).includes(flipped)) return flipped as TabMode;
    }
  }
  return null;
}

/** The mode left after removing one pane from a split (or 'browser' fallback). */
export function tabModeWithout(mode: TabMode, pane: TabPane): TabMode {
  // A lone pane is only a valid TabMode if it exists as a standalone mode —
  // otherwise fall back to 'browser'. (All three panes are standalone today;
  // the guard keeps this safe if a split-only pane is ever added.)
  const asMode = (p: TabPane): TabMode =>
    (TAB_MODES as readonly string[]).includes(p) ? (p as TabMode) : 'browser';
  const { left, right } = parseTabMode(mode);
  if (left === pane && right) return asMode(right);
  if (right === pane) return asMode(left);
  if (left === pane && !right) return 'browser';
  return mode;
}

/**
 * The mode a tab should switch to so a dashboard pane becomes visible while
 * keeping the user's current content on screen (Sayzio rail navigation).
 * Already-dashboard modes are returned unchanged; otherwise the tab's
 * PRIMARY (left) pane is kept and paired with the dashboard.
 */
export function dashboardModeFor(mode: TabMode): TabMode {
  if (tabModeIncludes(mode, 'dashboard')) return mode;
  const { left } = parseTabMode(mode);
  switch (left) {
    case 'zio': return 'dashboard+zio';
    case 'files': return 'dashboard+files';
    default: return 'dashboard+browser';
  }
}

/** Default ratio of the tab area given to the LEFT pane in a two-pane split. */
export const TAB_SPLIT_RATIO = 0.5;
export const MIN_TAB_SPLIT_RATIO = 0.2;
export const MAX_TAB_SPLIT_RATIO = 0.8;
export const TAB_SPLIT_DIVIDER_WIDTH = 4;
/**
 * Inset (px) applied to each native pane of a Website + Website split so the
 * renderer can draw a clickable focus frame around the focused pane.
 */
export const TAB_SPLIT_FOCUS_FRAME = 3;

export const DEFAULT_SPLIT_RATIO = 0.35;
export const MIN_SPLIT_RATIO = 0.20;
export const MAX_SPLIT_RATIO = 0.60;
export const SPLIT_DIVIDER_WIDTH = 4;

export const SAYZIO_DASHBOARD_URL = 'https://sayzio.app/user/dashboard';
export const SAYZIO_HOME_URL = 'https://sayzio.app';
export const SAYZIO_BASE_HOST = 'sayzio.app';

/** Zio panel width (px) in browser-mode docked presentation */
export const DEFAULT_ZIO_PANEL_WIDTH = 360;
export const MIN_ZIO_PANEL_WIDTH = 260;
export const MAX_ZIO_PANEL_WIDTH = 640;
export const ZIO_PANEL_DIVIDER_WIDTH = 4;
