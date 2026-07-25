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
// Each tab can independently show: a plain website, the Sayzio webapp,
// a Sayzio-app + website split, or an Ask Zio + website split.

export type TabMode = 'web' | 'sayzio' | 'sayzio-split' | 'zio-split';

export const TAB_MODES: TabMode[] = ['web', 'sayzio', 'sayzio-split', 'zio-split'];

export const TAB_MODE_LABELS: Record<TabMode, string> = {
  web: 'Website',
  sayzio: 'Sayzio Webapp',
  'sayzio-split': 'Sayzio + Website',
  'zio-split': 'Ask Zio + Website',
};

export const TAB_MODE_DESCRIPTIONS: Record<TabMode, string> = {
  web: 'Just the website in this tab',
  sayzio: 'The Sayzio webapp fills this tab',
  'sayzio-split': 'Sayzio webapp on the left, website on the right',
  'zio-split': 'Website on the left, Ask Zio on the right',
};

export const TAB_MODE_ICONS: Record<TabMode, string> = {
  web: '🌐',
  sayzio: '⊡',
  'sayzio-split': '⬛',
  'zio-split': '⚡',
};

/** Ratio of the tab area given to the Sayzio app view in sayzio-split mode. */
export const TAB_SPLIT_RATIO = 0.5;
export const TAB_SPLIT_DIVIDER_WIDTH = 4;

export const DEFAULT_SPLIT_RATIO = 0.35;
export const MIN_SPLIT_RATIO = 0.20;
export const MAX_SPLIT_RATIO = 0.60;
export const SPLIT_DIVIDER_WIDTH = 4;

export const SAYZIO_DASHBOARD_URL = 'https://1in.me/user/dashboard';
export const SAYZIO_BASE_HOST = '1in.me';

/** Zio panel width (px) in browser-mode docked presentation */
export const DEFAULT_ZIO_PANEL_WIDTH = 360;
export const MIN_ZIO_PANEL_WIDTH = 260;
export const MAX_ZIO_PANEL_WIDTH = 640;
export const ZIO_PANEL_DIVIDER_WIDTH = 4;
