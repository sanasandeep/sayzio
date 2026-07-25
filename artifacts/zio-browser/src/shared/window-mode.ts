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
// Each tab is built from four primitives ("panes"):
//   browser   — any website (the tab's own web view)
//   dashboard — the Sayzio dashboard app (1in.me/user/dashboard)
//   sayzio    — the Sayzio website (1in.me)
//   zio       — the Ask Zio AI panel (renderer-drawn)
// A tab shows ONE pane, or a split of any TWO panes → 10 possible modes.

export type TabPane = 'browser' | 'dashboard' | 'sayzio' | 'zio';

export const TAB_PANES: TabPane[] = ['browser', 'dashboard', 'sayzio', 'zio'];

export const TAB_PANE_LABELS: Record<TabPane, string> = {
  browser: 'Website',
  dashboard: 'Dashboard',
  sayzio: 'Sayzio',
  zio: 'Ask Zio',
};

export const TAB_PANE_ICONS: Record<TabPane, string> = {
  browser: '🌐',
  dashboard: '⊡',
  sayzio: '⬛',
  zio: '⚡',
};

/**
 * A tab mode is a single pane id, or `'left+right'` for a two-pane split.
 * The pane order in the string IS the layout order (left pane first).
 */
export type TabMode =
  | 'browser'
  | 'dashboard'
  | 'sayzio'
  | 'zio'
  | 'dashboard+browser'
  | 'sayzio+browser'
  | 'dashboard+sayzio'
  | 'browser+zio'
  | 'dashboard+zio'
  | 'sayzio+zio';

export const TAB_MODES: TabMode[] = [
  'browser',
  'dashboard',
  'sayzio',
  'zio',
  'dashboard+browser',
  'sayzio+browser',
  'dashboard+sayzio',
  'browser+zio',
  'dashboard+zio',
  'sayzio+zio',
];

export const TAB_MODE_LABELS: Record<TabMode, string> = {
  browser: 'Website',
  dashboard: 'Dashboard',
  sayzio: 'Sayzio',
  zio: 'Ask Zio',
  'dashboard+browser': 'Dashboard + Website',
  'sayzio+browser': 'Sayzio + Website',
  'dashboard+sayzio': 'Dashboard + Sayzio',
  'browser+zio': 'Website + Ask Zio',
  'dashboard+zio': 'Dashboard + Ask Zio',
  'sayzio+zio': 'Sayzio + Ask Zio',
};

export const TAB_MODE_DESCRIPTIONS: Record<TabMode, string> = {
  browser: 'Just the website in this tab',
  dashboard: 'Your Sayzio dashboard fills this tab',
  sayzio: 'The Sayzio website fills this tab',
  zio: 'The Ask Zio assistant fills this tab',
  'dashboard+browser': 'Dashboard on the left, website on the right',
  'sayzio+browser': 'Sayzio on the left, website on the right',
  'dashboard+sayzio': 'Dashboard on the left, Sayzio on the right',
  'browser+zio': 'Website on the left, Ask Zio on the right',
  'dashboard+zio': 'Dashboard on the left, Ask Zio on the right',
  'sayzio+zio': 'Sayzio on the left, Ask Zio on the right',
};

export const TAB_MODE_ICONS: Record<TabMode, string> = {
  browser: '🌐',
  dashboard: '⊡',
  sayzio: '⬛',
  zio: '⚡',
  'dashboard+browser': '⊡🌐',
  'sayzio+browser': '⬛🌐',
  'dashboard+sayzio': '⊡⬛',
  'browser+zio': '🌐⚡',
  'dashboard+zio': '⊡⚡',
  'sayzio+zio': '⬛⚡',
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
  // Canonical modes win first — 'sayzio' is BOTH a legacy v0.1.x value (the
  // webapp, now 'dashboard') and a canonical single-pane mode (Sayzio home).
  // Checking canonical first keeps the new mode selectable; old persisted
  // 'sayzio' tabs simply restore as the Sayzio home surface.
  if ((TAB_MODES as string[]).includes(raw)) return raw as TabMode;
  // Legacy v0.1.x tab modes
  const legacy: Record<string, TabMode> = {
    web: 'browser',
    'sayzio-split': 'dashboard+browser',
    'zio-split': 'browser+zio',
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
  const { left, right } = parseTabMode(mode);
  if (left === pane && right) return right;
  if (right === pane) return left;
  if (left === pane && !right) return 'browser';
  return mode;
}

/** Ratio of the tab area given to the Sayzio app view in sayzio-split mode. */
export const TAB_SPLIT_RATIO = 0.5;
export const TAB_SPLIT_DIVIDER_WIDTH = 4;

export const DEFAULT_SPLIT_RATIO = 0.35;
export const MIN_SPLIT_RATIO = 0.20;
export const MAX_SPLIT_RATIO = 0.60;
export const SPLIT_DIVIDER_WIDTH = 4;

export const SAYZIO_DASHBOARD_URL = 'https://1in.me/user/dashboard';
export const SAYZIO_HOME_URL = 'https://1in.me';
export const SAYZIO_BASE_HOST = '1in.me';

/** Zio panel width (px) in browser-mode docked presentation */
export const DEFAULT_ZIO_PANEL_WIDTH = 360;
export const MIN_ZIO_PANEL_WIDTH = 260;
export const MAX_ZIO_PANEL_WIDTH = 640;
export const ZIO_PANEL_DIVIDER_WIDTH = 4;
