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
