/**
 * Virtual keyboard — pure shared logic (no Electron imports) so the
 * suggestion engine, layouts, shortcut expansion, and key-event mapping are
 * unit-testable and usable from both the main process and the renderer.
 */

// ── Preference keys (stored in the SQLite `preferences` table) ──────────────
export const VK_PREF_KEYS = {
  ENABLED: 'vk_enabled',
  AUTO_SHOW: 'vk_auto_show',
  SUGGESTIONS: 'vk_suggestions',
  LEARN_HISTORY: 'vk_learn_history',
  SELECTION_MODE: 'vk_selection_mode',
  EXPAND_ON_SPACE: 'vk_expand_on_space',
  SHORTCUTS: 'vk_shortcuts',
  TYPING_HISTORY: 'vk_typing_history',
  BIGRAMS: 'vk_bigrams',
  STRIP_POS: 'vk_strip_pos',
} as const;

/** Renderer-local window event fired when any VK setting changes. */
export const VK_SETTINGS_CHANGED_EVENT = 'zio:vk-settings-changed';

/** Height in px the docked keyboard reserves at the bottom of the tab area. */
export const VK_DOCK_HEIGHT = 260;

/** Hover-dwell time (ms) before a suggestion is accepted in dwell mode. */
export const VK_DWELL_MS = 900;

// ── Field focus reporting ────────────────────────────────────────────────────

export type VkFieldKind = 'text' | 'number' | 'email' | 'password' | 'contenteditable' | 'none';

export interface VkFocusPayload {
  kind: VkFieldKind;
}

export interface VkSettings {
  enabled: boolean;
  autoShow: boolean;
  suggestions: boolean;
  learnHistory: boolean;
  selectionMode: 'click' | 'dwell';
  expandOnSpace: boolean;
}

export const VK_DEFAULT_SETTINGS: VkSettings = {
  enabled: false,
  autoShow: true,
  suggestions: true,
  learnHistory: true,
  selectionMode: 'click',
  expandOnSpace: true,
};

/** Classify a focused element description into a VkFieldKind. */
export function classifyField(el: { tag: string; type?: string | null; editable?: boolean }): VkFieldKind {
  const tag = (el.tag || '').toLowerCase();
  if (tag === 'input') {
    const t = (el.type || 'text').toLowerCase();
    if (t === 'password') return 'password';
    if (t === 'number' || t === 'tel') return 'number';
    if (t === 'email') return 'email';
    if (['text', 'search', 'url'].includes(t)) return 'text';
    return 'none';
  }
  if (tag === 'textarea') return 'text';
  if (el.editable) return 'contenteditable';
  return 'none';
}

/** Which layer the keyboard should open on for a given field kind. */
export type VkLayer = 'letters' | 'symbols' | 'numeric';

export function layerForFieldKind(kind: VkFieldKind): VkLayer {
  return kind === 'number' ? 'numeric' : 'letters';
}

/** Should the keyboard auto-open for this focus report? */
export function shouldAutoShow(settings: Pick<VkSettings, 'enabled' | 'autoShow'>, kind: VkFieldKind): boolean {
  return settings.enabled && settings.autoShow && kind !== 'none';
}

/**
 * Should the keyboard auto-hide for this focus report? Mirrors auto-show:
 * when the user blurs the field (or navigates, which resets focus to 'none'),
 * an auto-shown keyboard goes away again.
 */
export function shouldAutoHide(settings: Pick<VkSettings, 'enabled' | 'autoShow'>, kind: VkFieldKind): boolean {
  return settings.enabled && settings.autoShow && kind === 'none';
}

/** Suggestions (and history learning) are suppressed in password fields. */
export function suggestionsAllowedFor(kind: VkFieldKind): boolean {
  return kind !== 'password' && kind !== 'none';
}

// ── Layouts ──────────────────────────────────────────────────────────────────

export const VK_LETTER_ROWS: string[][] = [
  ['q', 'w', 'e', 'r', 't', 'y', 'u', 'i', 'o', 'p'],
  ['a', 's', 'd', 'f', 'g', 'h', 'j', 'k', 'l'],
  ['z', 'x', 'c', 'v', 'b', 'n', 'm'],
];

export const VK_SYMBOL_ROWS: string[][] = [
  ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
  ['!', '@', '#', '$', '%', '^', '&', '*', '(', ')'],
  ['-', '_', '=', '+', '/', '\\', ':', ';', '"', "'"],
  ['?', ',', '.', '<', '>', '[', ']', '{', '}', '~'],
];

export const VK_NUMERIC_ROWS: string[][] = [
  ['1', '2', '3'],
  ['4', '5', '6'],
  ['7', '8', '9'],
  ['.', '0', '-'],
];

// ── Shift / caps state ───────────────────────────────────────────────────────

export type VkShiftState = 'off' | 'shift' | 'caps';

/**
 * Shift button cycles off → shift → caps → off. Typing a letter while in
 * one-shot 'shift' drops back to 'off'; 'caps' is sticky.
 */
export function nextShiftState(state: VkShiftState, action: 'tapShift' | 'typedLetter'): VkShiftState {
  if (action === 'tapShift') {
    return state === 'off' ? 'shift' : state === 'shift' ? 'caps' : 'off';
  }
  // typedLetter
  return state === 'shift' ? 'off' : state;
}

export function applyShift(char: string, state: VkShiftState): string {
  return state === 'off' ? char : char.toUpperCase();
}

// ── Text shortcuts ───────────────────────────────────────────────────────────

export interface VkShortcut {
  /** What the user types (single word, matched case-insensitively). */
  trigger: string;
  /** Expansion text — may contain multiple lines. */
  expansion: string;
}

export function normalizeShortcutTrigger(raw: string): string {
  return raw.trim().toLowerCase().replace(/\s+/g, '');
}

export function parseShortcuts(raw: string | null | undefined): VkShortcut[] {
  if (!raw) return [];
  try {
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    const out: VkShortcut[] = [];
    const seen = new Set<string>();
    for (const item of parsed) {
      if (!item || typeof item !== 'object') continue;
      const t = normalizeShortcutTrigger(String((item as { trigger?: unknown }).trigger ?? ''));
      const e = String((item as { expansion?: unknown }).expansion ?? '');
      if (!t || !e || seen.has(t)) continue;
      seen.add(t);
      out.push({ trigger: t, expansion: e });
    }
    return out;
  } catch {
    return [];
  }
}

export function serializeShortcuts(shortcuts: VkShortcut[]): string {
  return JSON.stringify(shortcuts);
}

export function matchShortcut(word: string, shortcuts: VkShortcut[]): VkShortcut | null {
  const w = word.trim().toLowerCase();
  if (!w) return null;
  return shortcuts.find(s => s.trigger === w) ?? null;
}

/**
 * When "expand on space" is enabled and the word just completed matches a
 * shortcut trigger, return the replacement instruction: delete the trigger
 * (backspaceCount chars) and insert the expansion followed by a space.
 */
export function expandShortcutOnSpace(
  lastWord: string,
  shortcuts: VkShortcut[],
  enabled: boolean,
): { backspaceCount: number; insertText: string } | null {
  if (!enabled) return null;
  const hit = matchShortcut(lastWord, shortcuts);
  if (!hit) return null;
  return { backspaceCount: lastWord.length, insertText: hit.expansion + ' ' };
}

// ── Typing history ───────────────────────────────────────────────────────────

/** word → use count. */
export type VkTypingHistory = Record<string, number>;

export const VK_HISTORY_CAP = 500;

export function parseTypingHistory(raw: string | null | undefined): VkTypingHistory {
  if (!raw) return {};
  try {
    const parsed: unknown = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};
    const out: VkTypingHistory = {};
    for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
      if (typeof v === 'number' && v > 0 && /^[a-z']{3,32}$/.test(k)) out[k] = Math.floor(v);
    }
    return out;
  } catch {
    return {};
  }
}

export function serializeTypingHistory(history: VkTypingHistory): string {
  return JSON.stringify(history);
}

/** Extract learnable words from typed text: 3+ letters, alphabetic. */
export function extractWords(text: string): string[] {
  const matches = text.toLowerCase().match(/[a-z']{3,32}/g);
  return matches ? matches.filter(w => /[a-z]/.test(w)) : [];
}

/**
 * Merge newly typed words into the history, incrementing counts and pruning
 * the least-used entries when over the cap.
 */
export function mergeHistory(history: VkTypingHistory, words: string[], cap = VK_HISTORY_CAP): VkTypingHistory {
  const next: VkTypingHistory = { ...history };
  for (const w of words) {
    if (!/^[a-z']{3,32}$/.test(w)) continue;
    next[w] = (next[w] ?? 0) + 1;
  }
  const entries = Object.entries(next);
  if (entries.length > cap) {
    entries.sort((a, b) => b[1] - a[1]);
    return Object.fromEntries(entries.slice(0, cap));
  }
  return next;
}

// ── Bigram (next-word) history ───────────────────────────────────────────────
//
// Learned word pairs power "next word" predictions after a space, like phone
// keyboards. Stored as a flat map of "prev next" → use count under the
// vk_bigrams preference. Same privacy rules as word history: learning is
// gated by the same handler (never passwords, never private windows) and the
// "Clear learned words" action wipes bigrams too.

/** "prev next" → use count. */
export type VkBigramHistory = Record<string, number>;

export const VK_BIGRAM_CAP = 800;

const VK_WORD_RE = /^[a-z']{2,32}$/;

export function bigramKey(prev: string, next: string): string {
  return `${prev} ${next}`;
}

export function parseBigramHistory(raw: string | null | undefined): VkBigramHistory {
  if (!raw) return {};
  try {
    const parsed: unknown = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};
    const out: VkBigramHistory = {};
    for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
      if (typeof v !== 'number' || v <= 0) continue;
      const parts = k.split(' ');
      if (parts.length !== 2) continue;
      if (!VK_WORD_RE.test(parts[0]) || !VK_WORD_RE.test(parts[1])) continue;
      if (!/[a-z]/.test(parts[0]) || !/[a-z]/.test(parts[1])) continue;
      out[k] = Math.floor(v);
    }
    return out;
  } catch {
    return {};
  }
}

export function serializeBigramHistory(bigrams: VkBigramHistory): string {
  return JSON.stringify(bigrams);
}

/**
 * Extract learnable consecutive word pairs from typed text. Words here are
 * 2+ letters (shorter than single-word learning's 3+ so common connectors
 * like "to"/"of" can be predicted), lowercase, letters/apostrophes only.
 */
export function extractWordPairs(text: string): Array<[string, string]> {
  const words = (text.toLowerCase().match(/[a-z']{2,32}/g) ?? []).filter(w => /[a-z]/.test(w));
  const pairs: Array<[string, string]> = [];
  for (let i = 0; i + 1 < words.length; i++) pairs.push([words[i], words[i + 1]]);
  return pairs;
}

/**
 * Merge newly typed word pairs into the bigram history, incrementing counts
 * and pruning the least-used entries when over the cap.
 */
export function mergeBigrams(
  bigrams: VkBigramHistory,
  pairs: Array<[string, string]>,
  cap = VK_BIGRAM_CAP,
): VkBigramHistory {
  const next: VkBigramHistory = { ...bigrams };
  for (const pair of pairs) {
    if (!Array.isArray(pair) || pair.length !== 2) continue;
    const [a, b] = pair;
    if (typeof a !== 'string' || typeof b !== 'string') continue;
    if (!VK_WORD_RE.test(a) || !VK_WORD_RE.test(b)) continue;
    if (!/[a-z]/.test(a) || !/[a-z]/.test(b)) continue;
    const k = bigramKey(a, b);
    next[k] = (next[k] ?? 0) + 1;
  }
  const entries = Object.entries(next);
  if (entries.length > cap) {
    entries.sort((a, b) => b[1] - a[1]);
    return Object.fromEntries(entries.slice(0, cap));
  }
  return next;
}

// ── Suggestions ──────────────────────────────────────────────────────────────

export interface VkSuggestion {
  word: string;
  source: 'shortcut' | 'history' | 'dictionary' | 'prediction';
  /** Present for shortcut suggestions — the text to insert instead. */
  expansion?: string;
}

/**
 * Compute word suggestions for the current prefix. A matching text shortcut
 * is always the top suggestion; when `prevWord` + `bigrams` are provided,
 * learned next-word predictions that also start with the prefix come next
 * (phone-keyboard style blending); then learned history words (most-used
 * first), then dictionary words. Deduplicated, capped at `limit`.
 */
export function suggestFor(
  prefix: string,
  opts: {
    shortcuts: VkShortcut[];
    history: VkTypingHistory;
    dictionary: readonly string[];
    limit?: number;
    /** Previous completed word — enables mid-word bigram blending. */
    prevWord?: string;
    /** Learned bigram history, used with `prevWord`. */
    bigrams?: VkBigramHistory;
  },
): VkSuggestion[] {
  const p = prefix.trim().toLowerCase();
  const limit = opts.limit ?? 3;
  if (!p || limit <= 0) return [];
  const out: VkSuggestion[] = [];
  const seen = new Set<string>();

  const shortcut = matchShortcut(p, opts.shortcuts) ?? opts.shortcuts.find(s => s.trigger.startsWith(p)) ?? null;
  if (shortcut) {
    out.push({ word: shortcut.trigger, source: 'shortcut', expansion: shortcut.expansion });
    seen.add(shortcut.trigger);
  }

  const prev = opts.prevWord?.trim().toLowerCase();
  if (prev && opts.bigrams) {
    const bigramPrefix = `${prev} `;
    const predicted = Object.entries(opts.bigrams)
      .filter(([k]) => k.startsWith(bigramPrefix))
      .map(([k, n]) => [k.slice(bigramPrefix.length), n] as const)
      .filter(([w]) => w.startsWith(p) && w !== p)
      .sort((a, b) => b[1] - a[1]);
    for (const [w] of predicted) {
      if (out.length >= limit) return out;
      if (seen.has(w)) continue;
      seen.add(w);
      out.push({ word: w, source: 'prediction' });
    }
  }

  const historyMatches = Object.entries(opts.history)
    .filter(([w]) => w.startsWith(p) && w !== p)
    .sort((a, b) => b[1] - a[1]);
  for (const [w] of historyMatches) {
    if (out.length >= limit) return out;
    if (seen.has(w)) continue;
    seen.add(w);
    out.push({ word: w, source: 'history' });
  }

  for (const w of opts.dictionary) {
    if (out.length >= limit) return out;
    if (!w.startsWith(p) || w === p || seen.has(w)) continue;
    seen.add(w);
    out.push({ word: w, source: 'dictionary' });
  }
  return out;
}

/**
 * Predict likely next words after `prevWord` from learned bigram frequency
 * (most-used first). Used when the strip has no in-progress prefix — i.e.
 * right after a space — to suggest whole next words like phone keyboards.
 *
 * When the learned bigrams have no match for `prevWord`, an optional bundled
 * common-pairs table (`fallback`) fills in so predictions work from day one.
 * Learned pairs always outrank the fallback; nothing new is stored or learned.
 */
export function suggestNextWords(
  prevWord: string,
  bigrams: VkBigramHistory,
  limit = 3,
  fallback?: Readonly<Record<string, readonly string[]>>,
): VkSuggestion[] {
  const prev = prevWord.trim().toLowerCase();
  if (!prev || limit <= 0) return [];
  const prefix = `${prev} `;
  const learned = Object.entries(bigrams)
    .filter(([k]) => k.startsWith(prefix))
    .sort((a, b) => b[1] - a[1])
    .slice(0, limit)
    .map(([k]) => ({ word: k.slice(prefix.length), source: 'prediction' as const }));
  if (learned.length > 0 || !fallback) return learned;
  const common = fallback[prev];
  if (!common) return [];
  return common.slice(0, limit).map(word => ({ word, source: 'prediction' as const }));
}

/** The trailing word of a typed buffer (letters/apostrophes), or ''. */
export function lastWordOf(buffer: string): string {
  const m = buffer.match(/[A-Za-z']+$/);
  return m ? m[0] : '';
}

// ── Suggestion strip position ────────────────────────────────────────────────

export interface VkStripPos {
  x: number;
  y: number;
}

/** Parse a persisted strip position, clamping into the given box. */
export function parseStripPos(
  raw: string | null | undefined,
  box: { width: number; height: number },
): VkStripPos | null {
  if (!raw) return null;
  try {
    const p: unknown = JSON.parse(raw);
    if (!p || typeof p !== 'object') return null;
    const x = Number((p as { x?: unknown }).x);
    const y = Number((p as { y?: unknown }).y);
    if (!Number.isFinite(x) || !Number.isFinite(y)) return null;
    return clampStripPos({ x, y }, box);
  } catch {
    return null;
  }
}

export function clampStripPos(pos: VkStripPos, box: { width: number; height: number }): VkStripPos {
  return {
    x: Math.max(0, Math.min(box.width, pos.x)),
    y: Math.max(0, Math.min(box.height, pos.y)),
  };
}

// ── Key injection (main process feeds these to wc.sendInputEvent) ────────────

export type VkSpecialKey = 'Backspace' | 'Enter' | 'Tab' | 'ArrowLeft' | 'ArrowRight' | 'ArrowUp' | 'ArrowDown';

export interface VkInputEvent {
  type: 'keyDown' | 'char' | 'keyUp';
  keyCode: string;
}

/** The sendInputEvent sequence for a special (non-text) key. */
export function keyEventsFor(key: VkSpecialKey): VkInputEvent[] {
  if (key === 'Enter') {
    return [
      { type: 'keyDown', keyCode: 'Return' },
      { type: 'char', keyCode: '\u000d' },
      { type: 'keyUp', keyCode: 'Return' },
    ];
  }
  if (key === 'Tab') {
    return [
      { type: 'keyDown', keyCode: 'Tab' },
      { type: 'char', keyCode: '\u0009' },
      { type: 'keyUp', keyCode: 'Tab' },
    ];
  }
  // Electron's sendInputEvent expects accelerator-style key codes, not DOM
  // KeyboardEvent.key names — arrows must be Left/Right/Up/Down.
  const code = key === 'ArrowLeft' ? 'Left'
    : key === 'ArrowRight' ? 'Right'
    : key === 'ArrowUp' ? 'Up'
    : key === 'ArrowDown' ? 'Down'
    : key;
  return [
    { type: 'keyDown', keyCode: code },
    { type: 'keyUp', keyCode: code },
  ];
}

export function isVkSpecialKey(v: unknown): v is VkSpecialKey {
  return typeof v === 'string' &&
    ['Backspace', 'Enter', 'Tab', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(v);
}

// ── Focus reporter (injected into tab pages) ────────────────────────────────

/** Console-message prefix the injected reporter uses to signal main. */
export const VK_FOCUS_LOG_PREFIX = '__zio_vk_focus__:';

/**
 * Page script that reports which kind of editable field is focused. Signals
 * back to the main process via console.debug with a magic prefix (the same
 * transport pattern is filtered out of user-visible devtools noise by the
 * prefix). Idempotent via a window guard.
 */
export function buildVkFocusReporterScript(): string {
  return `
    (function() {
      if (window.__zioVkReporterInstalled) return;
      window.__zioVkReporterInstalled = true;
      var PREFIX = ${JSON.stringify(VK_FOCUS_LOG_PREFIX)};
      function classify(el) {
        if (!el) return 'none';
        var tag = (el.tagName || '').toLowerCase();
        if (tag === 'input') {
          var t = (el.getAttribute('type') || 'text').toLowerCase();
          if (t === 'password') return 'password';
          if (t === 'number' || t === 'tel') return 'number';
          if (t === 'email') return 'email';
          if (t === 'text' || t === 'search' || t === 'url') return 'text';
          return 'none';
        }
        if (tag === 'textarea') return 'text';
        if (el.isContentEditable) return 'contenteditable';
        return 'none';
      }
      var last = null;
      function report(kind) {
        if (kind === last) return;
        last = kind;
        try { console.debug(PREFIX + JSON.stringify({ kind: kind })); } catch (e) {}
      }
      document.addEventListener('focusin', function(e) { report(classify(e.target)); }, true);
      document.addEventListener('focusout', function() {
        setTimeout(function() {
          var kind = classify(document.activeElement);
          report(kind);
        }, 0);
      }, true);
      report(classify(document.activeElement));
    })();
  `;
}

// ── Floating suggestion strip window ────────────────────────────────────────
//
// Native WebContentsViews sit above the renderer DOM, so a strip that floats
// over the page cannot be a DOM element — it is a small frameless, transparent
// child BrowserWindow owned by the main process. The strip page signals
// selections back via console messages with a magic prefix (same transport as
// the focus reporter), and is re-rendered via an injected update function.

export const VK_STRIP_LOG_PREFIX = '__zio_vk_strip__:';
export const VK_STRIP_WIDTH = 480;
export const VK_STRIP_HEIGHT = 46;

/** Display item for one strip chip (renderer resolves labels via stripLabelFor). */
export interface VkStripChip {
  label: string;
  title: string;
  source: VkSuggestion['source'];
}

export interface VkStripUpdatePayload {
  suggestions: VkStripChip[];
  selectionMode: 'click' | 'dwell';
  dwellMs: number;
  /** Light-mode theming flag (strip window has no CSS vars of its own). */
  light: boolean;
  /** Placeholder text when there are no suggestions. */
  placeholder: string;
}

export interface VkStripSelectMessage {
  index: number;
}

/** Parse a console-message line from the strip window, or null. */
export function parseVkStripMessage(message: string): VkStripSelectMessage | null {
  if (!message.startsWith(VK_STRIP_LOG_PREFIX)) return null;
  try {
    const p: unknown = JSON.parse(message.slice(VK_STRIP_LOG_PREFIX.length));
    const index = (p as { index?: unknown })?.index;
    if (typeof index === 'number' && Number.isInteger(index) && index >= 0) return { index };
    return null;
  } catch {
    return null;
  }
}

/** Short display label for a suggestion chip. */
export function stripLabelFor(s: VkSuggestion): string {
  if (s.source !== 'shortcut') return s.word;
  const first = (s.expansion ?? '').split('\n')[0];
  const preview = first.length > 24 ? `${first.slice(0, 24)}…` : first;
  return `${s.word} → ${preview}`;
}

/**
 * The HTML document loaded into the floating strip window. Exposes
 * `window.__zioVkStripUpdate(payload)` for re-rendering; the ⠿ grip is an
 * app-region drag handle so the OS moves the window natively. Selections are
 * reported via `console.log(VK_STRIP_LOG_PREFIX + JSON.stringify({index}))`.
 */
export function buildVkStripHtml(): string {
  return `<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
  html, body { margin: 0; height: 100%; background: transparent; overflow: hidden;
    font-family: system-ui, -apple-system, sans-serif; user-select: none; }
  #strip { display: flex; align-items: center; gap: 6px; height: 100%; box-sizing: border-box;
    padding: 4px 10px; border-radius: 12px; border: 1px solid rgba(128,128,128,0.45);
    background: rgba(24,26,32,0.82); color: #eee; }
  body.light #strip { background: rgba(250,250,252,0.85); color: #1a1a22; }
  #grip { -webkit-app-region: drag; cursor: grab; font-size: 13px; opacity: 0.6; padding: 6px 4px; }
  #chips { display: flex; align-items: center; gap: 6px; flex: 1; min-width: 0; overflow: hidden; }
  .chip { -webkit-app-region: no-drag; border-radius: 8px; padding: 4px 12px; font-size: 13px;
    cursor: pointer; white-space: nowrap; max-width: 220px; overflow: hidden; text-overflow: ellipsis;
    border: 1px solid rgba(128,128,128,0.45); background: rgba(255,255,255,0.08); color: inherit; position: relative; }
  body.light .chip { background: rgba(0,0,0,0.05); }
  .chip.shortcut { border-color: #6ea8ff; background: rgba(110,168,255,0.18); }
  .chip .dwell-fill { position: absolute; left: 0; top: 0; bottom: 0; width: 0; background: rgba(110,168,255,0.35);
    pointer-events: none; }
  .chip.dwelling .dwell-fill { width: 100%; transition: width var(--dwell, 900ms) linear; }
  #empty { font-size: 12px; opacity: 0.55; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style></head><body>
<div id="strip"><span id="grip" title="Drag to move">⠿</span><div id="chips"></div></div>
<script>
  var PREFIX = ${JSON.stringify(VK_STRIP_LOG_PREFIX)};
  var state = { suggestions: [], selectionMode: 'click', dwellMs: 900, light: false, placeholder: '' };
  var dwellTimer = null;
  function select(i) { try { console.log(PREFIX + JSON.stringify({ index: i })); } catch (e) {} }
  function cancelDwell(chip) {
    if (dwellTimer) { clearTimeout(dwellTimer); dwellTimer = null; }
    if (chip) chip.classList.remove('dwelling');
  }
  function render() {
    document.body.classList.toggle('light', !!state.light);
    var chips = document.getElementById('chips');
    chips.innerHTML = '';
    if (!state.suggestions.length) {
      var empty = document.createElement('span');
      empty.id = 'empty';
      empty.textContent = state.placeholder || '';
      chips.appendChild(empty);
      return;
    }
    state.suggestions.forEach(function (s, i) {
      var b = document.createElement('button');
      b.className = 'chip' + (s.source === 'shortcut' ? ' shortcut' : '');
      b.textContent = s.label;
      b.title = s.title || s.label;
      var fill = document.createElement('span');
      fill.className = 'dwell-fill';
      b.appendChild(fill);
      b.style.setProperty('--dwell', state.dwellMs + 'ms');
      b.addEventListener('click', function () { if (state.selectionMode === 'click') select(i); });
      b.addEventListener('mouseenter', function () {
        if (state.selectionMode !== 'dwell') return;
        cancelDwell(null);
        b.classList.add('dwelling');
        dwellTimer = setTimeout(function () { b.classList.remove('dwelling'); select(i); }, state.dwellMs);
      });
      b.addEventListener('mouseleave', function () { cancelDwell(b); });
      chips.appendChild(b);
    });
  }
  window.__zioVkStripUpdate = function (payload) {
    cancelDwell(null);
    state = payload || state;
    render();
  };
  render();
</script>
</body></html>`;
}

/** Parse a console-message line from the injected reporter, or null. */
export function parseVkFocusMessage(message: string): VkFocusPayload | null {
  if (!message.startsWith(VK_FOCUS_LOG_PREFIX)) return null;
  try {
    const p: unknown = JSON.parse(message.slice(VK_FOCUS_LOG_PREFIX.length));
    const kind = (p as { kind?: unknown })?.kind;
    if (kind === 'text' || kind === 'number' || kind === 'email' || kind === 'password' || kind === 'contenteditable' || kind === 'none') {
      return { kind };
    }
    return null;
  } catch {
    return null;
  }
}
