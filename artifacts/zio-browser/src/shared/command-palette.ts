/**
 * Command palette types, fuzzy matching, and command registry for the Zio Browser.
 * Pure functions — no Electron or browser dependencies.
 */

export type PaletteResultKind = 'tab' | 'bookmark' | 'history' | 'sayzio-link' | 'command';

export interface PaletteItem {
  id: string;
  kind: PaletteResultKind;
  title: string;
  subtitle?: string;
  icon?: string;
  /** For tab items: the tab id to activate */
  tabId?: string;
  /** For URL items: the URL to navigate to */
  url?: string;
  /** For command items: the action name to execute */
  action?: string;
  /** Score — assigned during search, higher = better match */
  score: number;
}

/** A browser command entry with availability context */
export interface CommandEntry {
  id: string;
  title: string;
  subtitle?: string;
  icon: string;
  action: string;
  /** Keywords for fuzzy matching (in addition to title/subtitle) */
  keywords?: string[];
}

/**
 * All browser action commands. Availability filtering is done at render time
 * based on current context (activeTab, user, mode, isPrivate).
 */
export const COMMAND_REGISTRY: CommandEntry[] = [
  {
    id: 'new-tab',
    title: 'New Tab',
    icon: '＋',
    action: 'new-tab',
    keywords: ['open', 'create'],
  },
  {
    id: 'close-tab',
    title: 'Close Tab',
    icon: '✕',
    action: 'close-tab',
    keywords: ['remove'],
  },
  {
    id: 'shorten-page',
    title: 'Shorten this page',
    subtitle: 'Create a Sayzio short link for the current URL',
    icon: '🔗',
    action: 'shorten-page',
    keywords: ['link', 'short', 'url', 'sayzio', 'copy'],
  },
  {
    id: 'qr-page',
    title: 'Generate QR code for this page',
    subtitle: 'Open the QR / shorten popover',
    icon: '⬛',
    action: 'qr-page',
    keywords: ['qr', 'code', 'scan', 'sayzio'],
  },
  {
    id: 'add-to-biolink',
    title: 'Add this page to my biolink',
    subtitle: 'Append the current URL as a block',
    icon: '📎',
    action: 'add-to-biolink',
    keywords: ['biolink', 'block', 'add'],
  },
  {
    id: 'new-window',
    title: 'New Window',
    subtitle: 'Open another browser window',
    icon: '🪟',
    action: 'new-window',
    keywords: ['window', 'open', 'new'],
  },
  {
    id: 'new-private-window',
    title: 'New Private Window',
    subtitle: 'Open an incognito window',
    icon: '🔒',
    action: 'new-private-window',
    keywords: ['incognito', 'private', 'secret'],
  },
  {
    id: 'focus-address-bar',
    title: 'Go to URL / Search',
    subtitle: 'Focus the address bar',
    icon: '🔍',
    action: 'focus-address-bar',
    keywords: ['omnibox', 'navigate', 'address', 'url', 'search'],
  },
  {
    id: 'mode-browser',
    title: 'Switch to Browser mode',
    icon: '🌐',
    action: 'mode-browser',
    keywords: ['window', 'layout', 'browser'],
  },
  {
    id: 'mode-split',
    title: 'Switch to Split mode',
    subtitle: 'Side-by-side browser + Sayzio dashboard',
    icon: '⬛',
    action: 'mode-split',
    keywords: ['window', 'layout', 'split', 'side'],
  },
  {
    id: 'mode-dashboard',
    title: 'Switch to Dashboard mode',
    subtitle: 'Show Sayzio dashboard fullscreen',
    icon: '📊',
    action: 'mode-dashboard',
    keywords: ['window', 'layout', 'dashboard', 'sayzio'],
  },
  {
    id: 'reload-tab',
    title: 'Reload this page',
    icon: '↻',
    action: 'reload-tab',
    keywords: ['refresh', 'reload'],
  },
  {
    id: 'find-on-page',
    title: 'Find on page',
    subtitle: 'Ctrl/Cmd+F',
    icon: '🔍',
    action: 'find-on-page',
    keywords: ['search', 'find', 'text'],
  },
  {
    id: 'restore-session',
    title: 'Restore previous session',
    subtitle: 'Reopen the tabs from your last browsing session',
    icon: '🕐',
    action: 'restore-session',
    keywords: ['session', 'restore', 'reopen', 'tabs', 'previous', 'last'],
  },
  {
    id: 'shortcuts',
    title: 'Keyboard Shortcuts',
    subtitle: 'View all keyboard shortcuts',
    icon: '⌨️',
    action: 'shortcuts',
    keywords: ['hotkeys', 'keybinds', 'help', 'cheatsheet'],
  },
];

/** Keyboard shortcut entries for the cheat-sheet. */
export interface ShortcutEntry {
  category: string;
  label: string;
  keys: string[];
}

export const KEYBOARD_SHORTCUTS: ShortcutEntry[] = [
  // Palette
  { category: 'General', label: 'Open Command Palette', keys: ['Ctrl/Cmd', 'K'] },
  { category: 'General', label: 'Find on page', keys: ['Ctrl/Cmd', 'F'] },
  { category: 'General', label: 'Focus address bar', keys: ['Ctrl/Cmd', 'L'] },
  // Tabs
  { category: 'Tabs', label: 'New Tab', keys: ['Ctrl/Cmd', 'T'] },
  { category: 'Tabs', label: 'Close Tab', keys: ['Ctrl/Cmd', 'W'] },
  // Navigation
  { category: 'Navigation', label: 'Back', keys: ['Alt', '←'] },
  { category: 'Navigation', label: 'Forward', keys: ['Alt', '→'] },
  { category: 'Navigation', label: 'Reload', keys: ['Ctrl/Cmd', 'R'] },
  { category: 'Navigation', label: 'Force Reload', keys: ['Ctrl/Cmd', 'Shift', 'R'] },
  // View
  { category: 'View', label: 'Zoom In', keys: ['Ctrl/Cmd', '='] },
  { category: 'View', label: 'Zoom Out', keys: ['Ctrl/Cmd', '−'] },
  { category: 'View', label: 'Reset Zoom', keys: ['Ctrl/Cmd', '0'] },
  // Window modes
  { category: 'Modes', label: 'Dashboard mode', keys: ['Ctrl/Cmd', 'Shift', '1'] },
  { category: 'Modes', label: 'Split mode', keys: ['Ctrl/Cmd', 'Shift', '2'] },
  { category: 'Modes', label: 'Browser mode', keys: ['Ctrl/Cmd', 'Shift', '3'] },
  { category: 'Modes', label: 'New Private Window', keys: ['Ctrl/Cmd', 'Shift', 'N'] },
  // Developer
  { category: 'Developer', label: 'Developer Tools', keys: ['F12'] },
];

/**
 * Fuzzy-score `target` against `query`.
 * Returns >= 0 on match (higher = better), -1 on no match.
 */
export function fuzzyScore(target: string, query: string): number {
  if (!query) return 50;
  const t = target.toLowerCase();
  const q = query.toLowerCase();

  if (t === q) return 500;
  if (t.startsWith(q)) return 400 - t.length;
  if (t.includes(q)) {
    const idx = t.indexOf(q);
    const isWordStart = idx === 0 || /[\s\-_./(]/.test(t[idx - 1] ?? '');
    return isWordStart ? 350 - t.length : 300 - t.length;
  }

  // Fuzzy subsequence
  let qi = 0;
  let bonusScore = 0;
  let consecutive = 0;
  for (let ti = 0; ti < t.length && qi < q.length; ti++) {
    if (t[ti] === q[qi]) {
      qi++;
      consecutive++;
      bonusScore += consecutive * 5;
      if (ti === 0 || /[\s\-_./(]/.test(t[ti - 1] ?? '')) bonusScore += 10;
    } else {
      consecutive = 0;
    }
  }
  if (qi < q.length) return -1;
  return Math.max(0, bonusScore - t.length);
}

/**
 * Score an item against a query, checking title, subtitle, and keywords.
 * Returns the best score, or -1 if no match.
 */
export function scoreItem(item: { title: string; subtitle?: string; keywords?: string[] }, query: string): number {
  if (!query.trim()) return 50;

  const titleScore = fuzzyScore(item.title, query);
  const subtitleScore = item.subtitle ? fuzzyScore(item.subtitle, query) * 0.6 : -1;
  const keywordScore = item.keywords
    ? Math.max(-1, ...item.keywords.map(k => fuzzyScore(k, query) * 0.8))
    : -1;

  return Math.max(titleScore, subtitleScore, keywordScore);
}

/**
 * Filter and rank a list of palette items by query.
 * Returns only matching items sorted by score desc, capped at `limit`.
 */
export function searchItems(items: PaletteItem[], query: string, limit = 8): PaletteItem[] {
  if (!query.trim()) {
    return items.slice(0, limit).map(i => ({ ...i, score: 50 }));
  }

  const scored = items.flatMap(item => {
    const s = scoreItem(item, query);
    if (s < 0) return [];
    return [{ ...item, score: s }];
  });

  return scored
    .sort((a, b) => b.score - a.score)
    .slice(0, limit);
}
