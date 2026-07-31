/**
 * Virtual keyboard — pure shared-logic tests: field classification,
 * auto-show gating, shift state, shortcut parsing/expansion, typing history,
 * suggestions ordering, strip position persistence, key-event mapping, and
 * the injected focus-reporter message protocol.
 */
import { describe, it, expect } from 'vitest';
import {
  VK_PREF_KEYS,
  VK_DOCK_HEIGHT,
  VK_DEFAULT_SETTINGS,
  classifyField,
  layerForFieldKind,
  shouldAutoShow,
  shouldAutoHide,
  parseVkStripMessage,
  stripLabelFor,
  buildVkStripHtml,
  VK_STRIP_LOG_PREFIX,
  suggestionsAllowedFor,
  nextShiftState,
  applyShift,
  normalizeShortcutTrigger,
  parseShortcuts,
  serializeShortcuts,
  matchShortcut,
  expandShortcutOnSpace,
  parseTypingHistory,
  serializeTypingHistory,
  extractWords,
  mergeHistory,
  VK_HISTORY_CAP,
  suggestFor,
  lastWordOf,
  bigramKey,
  parseBigramHistory,
  serializeBigramHistory,
  extractWordPairs,
  mergeBigrams,
  suggestNextWords,
  VK_BIGRAM_CAP,
  parseStripPos,
  clampStripPos,
  keyEventsFor,
  isVkSpecialKey,
  buildVkFocusReporterScript,
  parseVkFocusMessage,
  VK_FOCUS_LOG_PREFIX,
  VK_LETTER_ROWS,
  VK_SYMBOL_ROWS,
  VK_NUMERIC_ROWS,
} from '../src/shared/virtual-keyboard';
import { VK_COMMON_BIGRAMS, VK_DICTIONARY } from '../src/shared/vk-dictionary';
import { PREFERENCE_KEYS } from '../src/shared/db-schema';

describe('preference keys', () => {
  it('every VK pref key is registered in the shared schema', () => {
    const registered = new Set<string>(Object.values(PREFERENCE_KEYS));
    for (const key of Object.values(VK_PREF_KEYS)) {
      expect(registered.has(key), `missing ${key} in PREFERENCE_KEYS`).toBe(true);
    }
  });
});

describe('classifyField', () => {
  it('classifies input types', () => {
    expect(classifyField({ tag: 'input', type: 'text' })).toBe('text');
    expect(classifyField({ tag: 'input', type: null })).toBe('text');
    expect(classifyField({ tag: 'INPUT', type: 'search' })).toBe('text');
    expect(classifyField({ tag: 'input', type: 'password' })).toBe('password');
    expect(classifyField({ tag: 'input', type: 'number' })).toBe('number');
    expect(classifyField({ tag: 'input', type: 'tel' })).toBe('number');
    expect(classifyField({ tag: 'input', type: 'email' })).toBe('email');
    expect(classifyField({ tag: 'input', type: 'checkbox' })).toBe('none');
  });

  it('classifies textarea, contenteditable, and non-editable', () => {
    expect(classifyField({ tag: 'textarea' })).toBe('text');
    expect(classifyField({ tag: 'div', editable: true })).toBe('contenteditable');
    expect(classifyField({ tag: 'div' })).toBe('none');
  });
});

describe('layer + auto-show + suggestion gating', () => {
  it('numeric fields open the numeric layer, others letters', () => {
    expect(layerForFieldKind('number')).toBe('numeric');
    expect(layerForFieldKind('text')).toBe('letters');
    expect(layerForFieldKind('password')).toBe('letters');
  });

  it('auto-show requires enabled + autoShow + a real field', () => {
    expect(shouldAutoShow({ enabled: true, autoShow: true }, 'text')).toBe(true);
    expect(shouldAutoShow({ enabled: true, autoShow: true }, 'password')).toBe(true);
    expect(shouldAutoShow({ enabled: true, autoShow: true }, 'none')).toBe(false);
    expect(shouldAutoShow({ enabled: true, autoShow: false }, 'text')).toBe(false);
    expect(shouldAutoShow({ enabled: false, autoShow: true }, 'text')).toBe(false);
  });

  it('shouldAutoHide fires only for kind none with autoShow enabled', () => {
    expect(shouldAutoHide({ enabled: true, autoShow: true }, 'none')).toBe(true);
    expect(shouldAutoHide({ enabled: true, autoShow: true }, 'text')).toBe(false);
    expect(shouldAutoHide({ enabled: true, autoShow: false }, 'none')).toBe(false);
    expect(shouldAutoHide({ enabled: false, autoShow: true }, 'none')).toBe(false);
  });

  it('suggestions are never allowed in password fields', () => {
    expect(suggestionsAllowedFor('password')).toBe(false);
    expect(suggestionsAllowedFor('none')).toBe(false);
    expect(suggestionsAllowedFor('text')).toBe(true);
    expect(suggestionsAllowedFor('email')).toBe(true);
  });

  it('keyboard is disabled by default', () => {
    expect(VK_DEFAULT_SETTINGS.enabled).toBe(false);
  });
});

describe('shift state machine', () => {
  it('cycles off → shift → caps → off on tap', () => {
    expect(nextShiftState('off', 'tapShift')).toBe('shift');
    expect(nextShiftState('shift', 'tapShift')).toBe('caps');
    expect(nextShiftState('caps', 'tapShift')).toBe('off');
  });

  it('one-shot shift drops after a letter; caps is sticky', () => {
    expect(nextShiftState('shift', 'typedLetter')).toBe('off');
    expect(nextShiftState('caps', 'typedLetter')).toBe('caps');
    expect(nextShiftState('off', 'typedLetter')).toBe('off');
  });

  it('applyShift uppercases only when shift/caps active', () => {
    expect(applyShift('a', 'off')).toBe('a');
    expect(applyShift('a', 'shift')).toBe('A');
    expect(applyShift('a', 'caps')).toBe('A');
  });
});

describe('text shortcuts', () => {
  const shortcuts = parseShortcuts(JSON.stringify([
    { trigger: 'addr', expansion: '1 Main St\nSpringfield' },
    { trigger: 'sig', expansion: 'Best,\nSam' },
  ]));

  it('normalizes triggers (lowercase, no whitespace)', () => {
    expect(normalizeShortcutTrigger('  My Addr ')).toBe('myaddr');
  });

  it('parses valid JSON, skips bad rows and duplicates, round-trips', () => {
    expect(shortcuts).toHaveLength(2);
    expect(parseShortcuts('not json')).toEqual([]);
    expect(parseShortcuts(null)).toEqual([]);
    const withDupes = parseShortcuts(JSON.stringify([
      { trigger: 'a', expansion: 'one' },
      { trigger: 'A ', expansion: 'two' },
      { trigger: '', expansion: 'x' },
      { expansion: 'no trigger' },
    ]));
    expect(withDupes).toEqual([{ trigger: 'a', expansion: 'one' }]);
    expect(parseShortcuts(serializeShortcuts(shortcuts))).toEqual(shortcuts);
  });

  it('matches case-insensitively', () => {
    expect(matchShortcut('ADDR', shortcuts)?.expansion).toContain('Main St');
    expect(matchShortcut('nope', shortcuts)).toBeNull();
  });

  it('expandShortcutOnSpace returns delete+insert instruction', () => {
    const r = expandShortcutOnSpace('addr', shortcuts, true);
    expect(r).toEqual({ backspaceCount: 4, insertText: '1 Main St\nSpringfield ' });
    expect(expandShortcutOnSpace('addr', shortcuts, false)).toBeNull();
    expect(expandShortcutOnSpace('other', shortcuts, true)).toBeNull();
  });
});

describe('typing history', () => {
  it('parses only sane entries and round-trips', () => {
    const h = parseTypingHistory(JSON.stringify({ hello: 3, x: 1, 'bad word': 2, ok: -1, world: 2.7 }));
    expect(h).toEqual({ hello: 3, world: 2 });
    expect(parseTypingHistory('nope')).toEqual({});
    expect(parseTypingHistory(null)).toEqual({});
    expect(parseTypingHistory(serializeTypingHistory(h))).toEqual(h);
  });

  it('extractWords keeps 3+ letter alphabetic words', () => {
    expect(extractWords("Hello, it's me! To a1b via www")).toEqual(['hello', "it's", 'via', 'www']);
  });

  it('mergeHistory increments counts and prunes at the cap', () => {
    const merged = mergeHistory({ hello: 1 }, ['hello', 'world', 'no', 'BAD']);
    expect(merged).toEqual({ hello: 2, world: 1 });

    const big: Record<string, number> = {};
    for (let i = 0; i < VK_HISTORY_CAP; i++) {
      const key = `word${String(i).replace(/[0-9]/g, (d) => 'abcdefghij'[Number(d)])}`;
      big[key] = i + 2;
    }
    const over = mergeHistory(big, ['zzz']);
    expect(Object.keys(over).length).toBeLessThanOrEqual(VK_HISTORY_CAP);
    // 'zzz' has count 1 — the least used — so it gets pruned first.
    expect(over['zzz']).toBeUndefined();
  });
});

describe('bigram (next-word) history', () => {
  it('vk_bigrams pref key exists and bigramKey joins with a space', () => {
    expect(VK_PREF_KEYS.BIGRAMS).toBe('vk_bigrams');
    expect(bigramKey('hello', 'world')).toBe('hello world');
  });

  it('parses only sane pair entries and round-trips', () => {
    const raw = JSON.stringify({
      'hello world': 3,
      'to be': 2,
      'a b': 1,            // single-letter words — rejected
      'three word key': 4, // not a pair — rejected
      "'' ''": 2,          // no letters — rejected
      'neg pair': -1,
      'frac pair': 2.9,
    });
    const b = parseBigramHistory(raw);
    expect(b).toEqual({ 'hello world': 3, 'to be': 2, 'frac pair': 2 });
    expect(parseBigramHistory('nope')).toEqual({});
    expect(parseBigramHistory(null)).toEqual({});
    expect(parseBigramHistory(serializeBigramHistory(b))).toEqual(b);
  });

  it('extractWordPairs yields consecutive lowercase pairs (2+ letters)', () => {
    expect(extractWordPairs('On my way home')).toEqual([
      ['on', 'my'],
      ['my', 'way'],
      ['way', 'home'],
    ]);
    expect(extractWordPairs('a b')).toEqual([]);
    expect(extractWordPairs('solo')).toEqual([]);
  });

  it('mergeBigrams increments counts, rejects junk, prunes at the cap', () => {
    const merged = mergeBigrams({ 'hello world': 1 }, [
      ['hello', 'world'],
      ['to', 'be'],
      ['x', 'be'],       // too short
      ['bad word!', 'ok'], // invalid chars
    ]);
    expect(merged).toEqual({ 'hello world': 2, 'to be': 1 });

    const big: Record<string, number> = {};
    for (let i = 0; i < VK_BIGRAM_CAP; i++) {
      const w = `word${String(i).replace(/[0-9]/g, (d) => 'abcdefghij'[Number(d)])}`;
      big[`${w} next`] = i + 2;
    }
    const over = mergeBigrams(big, [['zz', 'zz']]);
    expect(Object.keys(over).length).toBeLessThanOrEqual(VK_BIGRAM_CAP);
    expect(over['zz zz']).toBeUndefined(); // count 1 — pruned first
  });

  it('suggestNextWords predicts by frequency for the previous word only', () => {
    const bigrams = { 'on my': 5, 'on the': 9, 'on top': 2, 'on a': 1, 'in the': 7 };
    const out = suggestNextWords('On', bigrams);
    expect(out).toEqual([
      { word: 'the', source: 'prediction' },
      { word: 'my', source: 'prediction' },
      { word: 'top', source: 'prediction' },
    ]);
    expect(suggestNextWords('nowhere', bigrams)).toEqual([]);
    expect(suggestNextWords('', bigrams)).toEqual([]);
    expect(suggestNextWords('on', bigrams, 1)).toEqual([{ word: 'the', source: 'prediction' }]);
  });

  it('falls back to built-in common pairs only when learned bigrams have no match', () => {
    const bigrams = { 'on the': 9 };
    // No learned match → fallback fills in.
    expect(suggestNextWords('thank', bigrams, 3, VK_COMMON_BIGRAMS)).toEqual([
      { word: 'you', source: 'prediction' },
    ]);
    // Learned pairs outrank the fallback entirely.
    expect(suggestNextWords('on', bigrams, 3, VK_COMMON_BIGRAMS)).toEqual([
      { word: 'the', source: 'prediction' },
    ]);
    // Respects limit and case-insensitivity; unknown words still yield nothing.
    expect(suggestNextWords('Thank', bigrams, 3, VK_COMMON_BIGRAMS)).toEqual([
      { word: 'you', source: 'prediction' },
    ]);
    expect(suggestNextWords('of', bigrams, 2, VK_COMMON_BIGRAMS)).toHaveLength(2);
    expect(suggestNextWords('zzzunknown', bigrams, 3, VK_COMMON_BIGRAMS)).toEqual([]);
    // No fallback provided → old behavior.
    expect(suggestNextWords('thank', bigrams)).toEqual([]);
  });

  it('VK_COMMON_BIGRAMS entries are well-formed lowercase words', () => {
    for (const [prev, nexts] of Object.entries(VK_COMMON_BIGRAMS)) {
      expect(prev).toMatch(/^[a-z']{1,32}$/);
      expect(nexts.length).toBeGreaterThan(0);
      for (const w of nexts) expect(w).toMatch(/^[a-z']{1,32}$/);
    }
  });
});

describe('suggestFor', () => {
  const shortcuts = [{ trigger: 'omw', expansion: 'On my way!' }];
  const history = { become: 9, because: 5 };

  it('shortcut first, then history by usage, then dictionary, capped at 3', () => {
    const out = suggestFor('be', { shortcuts, history, dictionary: VK_DICTIONARY });
    expect(out).toHaveLength(3);
    expect(out[0]).toEqual({ word: 'become', source: 'history' });
    expect(out[1]).toEqual({ word: 'because', source: 'history' });
    expect(out[2].source).toBe('dictionary');
    expect(out[2].word.startsWith('be')).toBe(true);
  });

  it('a matching shortcut is always the top suggestion', () => {
    const out = suggestFor('om', { shortcuts, history, dictionary: VK_DICTIONARY });
    expect(out[0]).toEqual({ word: 'omw', source: 'shortcut', expansion: 'On my way!' });
  });

  it('deduplicates and skips the exact prefix itself', () => {
    const out = suggestFor('because', { shortcuts: [], history, dictionary: ['because', 'becauseof'] });
    expect(out.map(s => s.word)).toEqual(['becauseof']);
  });

  it('blends bigram predictions above history and dictionary, below shortcuts', () => {
    const bigrams = { 'on beaches': 3, 'on behalf': 8, 'on the': 9, 'in because': 4 };
    const out = suggestFor('be', { shortcuts, history, dictionary: VK_DICTIONARY, prevWord: 'on', bigrams });
    expect(out).toEqual([
      { word: 'behalf', source: 'prediction' },
      { word: 'beaches', source: 'prediction' },
      { word: 'become', source: 'history' },
    ]);

    const withShortcut = suggestFor('om', {
      shortcuts,
      history: {},
      dictionary: ['omelette'],
      prevWord: 'the',
      bigrams: { 'the omen': 2 },
    });
    expect(withShortcut[0].source).toBe('shortcut');
    expect(withShortcut[1]).toEqual({ word: 'omen', source: 'prediction' });
    expect(withShortcut[2]).toEqual({ word: 'omelette', source: 'dictionary' });
  });

  it('bigram blending dedupes against history and skips the exact prefix', () => {
    const bigrams = { 'on become': 5, 'on be': 7 };
    const out = suggestFor('be', { shortcuts: [], history, dictionary: [], prevWord: 'On ', bigrams });
    expect(out).toEqual([
      { word: 'become', source: 'prediction' },
      { word: 'because', source: 'history' },
    ]);
  });

  it('ignores bigrams for other previous words or when prevWord is absent', () => {
    const bigrams = { 'in because': 9 };
    expect(suggestFor('be', { shortcuts: [], history: {}, dictionary: [], prevWord: 'on', bigrams })).toEqual([]);
    const noPrev = suggestFor('be', { shortcuts: [], history, dictionary: [], bigrams });
    expect(noPrev.map(s => s.source)).toEqual(['history', 'history']);
  });

  it('returns [] for an empty prefix', () => {
    expect(suggestFor('  ', { shortcuts, history, dictionary: VK_DICTIONARY })).toEqual([]);
  });

  it('lastWordOf pulls the trailing word', () => {
    expect(lastWordOf('hello wor')).toBe('wor');
    expect(lastWordOf("don't sto")).toBe('sto');
    expect(lastWordOf('end ')).toBe('');
    expect(lastWordOf('')).toBe('');
  });
});

describe('strip position', () => {
  const box = { width: 800, height: VK_DOCK_HEIGHT - 44 };

  it('parses and clamps a persisted position', () => {
    expect(parseStripPos(JSON.stringify({ x: 40, y: 10 }), box)).toEqual({ x: 40, y: 10 });
    expect(parseStripPos(JSON.stringify({ x: -50, y: 9999 }), box)).toEqual({ x: 0, y: box.height });
    expect(parseStripPos('garbage', box)).toBeNull();
    expect(parseStripPos(null, box)).toBeNull();
    expect(parseStripPos(JSON.stringify({ x: 'a', y: 1 }), box)).toBeNull();
  });

  it('clampStripPos keeps points inside the box', () => {
    expect(clampStripPos({ x: -1, y: -1 }, box)).toEqual({ x: 0, y: 0 });
    expect(clampStripPos({ x: 900, y: 500 }, box)).toEqual({ x: 800, y: box.height });
  });
});

describe('floating strip protocol', () => {
  it('parseVkStripMessage accepts only prefixed integer-index payloads', () => {
    expect(parseVkStripMessage(`${VK_STRIP_LOG_PREFIX}{"index":2}`)).toEqual({ index: 2 });
    expect(parseVkStripMessage(`${VK_STRIP_LOG_PREFIX}{"index":0}`)).toEqual({ index: 0 });
    expect(parseVkStripMessage('{"index":1}')).toBeNull();
    expect(parseVkStripMessage(`${VK_STRIP_LOG_PREFIX}{"index":-1}`)).toBeNull();
    expect(parseVkStripMessage(`${VK_STRIP_LOG_PREFIX}{"index":1.5}`)).toBeNull();
    expect(parseVkStripMessage(`${VK_STRIP_LOG_PREFIX}{"index":"1"}`)).toBeNull();
    expect(parseVkStripMessage(`${VK_STRIP_LOG_PREFIX}not-json`)).toBeNull();
  });

  it('stripLabelFor renders words plainly and shortcuts with a preview', () => {
    expect(stripLabelFor({ word: 'hello', source: 'dictionary' })).toBe('hello');
    expect(stripLabelFor({ word: 'addr', source: 'shortcut', expansion: '1 Main St\nTown' })).toBe('addr → 1 Main St');
    const long = 'x'.repeat(40);
    expect(stripLabelFor({ word: 'sig', source: 'shortcut', expansion: long })).toBe(`sig → ${'x'.repeat(24)}…`);
  });

  it('buildVkStripHtml exposes the update hook and reports via the log prefix', () => {
    const html = buildVkStripHtml();
    expect(html).toContain('__zioVkStripUpdate');
    expect(html).toContain(VK_STRIP_LOG_PREFIX);
    expect(html).toContain('-webkit-app-region: drag');
    expect(html).toContain('-webkit-app-region: no-drag');
  });
});

describe('key events', () => {
  it('Enter and Tab include a char event; arrows/backspace do not', () => {
    expect(keyEventsFor('Enter').map(e => e.type)).toEqual(['keyDown', 'char', 'keyUp']);
    expect(keyEventsFor('Tab').map(e => e.type)).toEqual(['keyDown', 'char', 'keyUp']);
    expect(keyEventsFor('Backspace')).toEqual([
      { type: 'keyDown', keyCode: 'Backspace' },
      { type: 'keyUp', keyCode: 'Backspace' },
    ]);
    expect(keyEventsFor('ArrowLeft').map(e => e.type)).toEqual(['keyDown', 'keyUp']);
  });

  it('arrows map to Electron accelerator codes, not DOM key names', () => {
    expect(keyEventsFor('ArrowLeft')).toEqual([
      { type: 'keyDown', keyCode: 'Left' },
      { type: 'keyUp', keyCode: 'Left' },
    ]);
    expect(keyEventsFor('ArrowRight').map(e => e.keyCode)).toEqual(['Right', 'Right']);
    expect(keyEventsFor('ArrowUp').map(e => e.keyCode)).toEqual(['Up', 'Up']);
    expect(keyEventsFor('ArrowDown').map(e => e.keyCode)).toEqual(['Down', 'Down']);
    expect(keyEventsFor('Enter')[0].keyCode).toBe('Return');
  });

  it('isVkSpecialKey validates the allowlist', () => {
    expect(isVkSpecialKey('Backspace')).toBe(true);
    expect(isVkSpecialKey('ArrowUp')).toBe(true);
    expect(isVkSpecialKey('Delete')).toBe(false);
    expect(isVkSpecialKey(5)).toBe(false);
  });
});

describe('focus reporter protocol', () => {
  it('reporter script is idempotent and reports via the magic prefix', () => {
    const script = buildVkFocusReporterScript();
    expect(script).toContain('__zioVkReporterInstalled');
    expect(script).toContain(VK_FOCUS_LOG_PREFIX);
    expect(script).toContain('focusin');
  });

  it('parseVkFocusMessage accepts only well-formed reports', () => {
    expect(parseVkFocusMessage(`${VK_FOCUS_LOG_PREFIX}{"kind":"text"}`)).toEqual({ kind: 'text' });
    expect(parseVkFocusMessage(`${VK_FOCUS_LOG_PREFIX}{"kind":"password"}`)).toEqual({ kind: 'password' });
    expect(parseVkFocusMessage(`${VK_FOCUS_LOG_PREFIX}{"kind":"bogus"}`)).toBeNull();
    expect(parseVkFocusMessage(`${VK_FOCUS_LOG_PREFIX}not json`)).toBeNull();
    expect(parseVkFocusMessage('unrelated console noise')).toBeNull();
  });
});

describe('layouts + dictionary sanity', () => {
  it('QWERTY rows are complete', () => {
    expect(VK_LETTER_ROWS[0].join('')).toBe('qwertyuiop');
    expect(VK_LETTER_ROWS[1].join('')).toBe('asdfghjkl');
    expect(VK_LETTER_ROWS[2].join('')).toBe('zxcvbnm');
    expect(VK_SYMBOL_ROWS.flat().length).toBeGreaterThan(30);
    expect(VK_NUMERIC_ROWS.flat()).toContain('0');
  });

  it('dictionary is lowercase, sorted-friendly, and non-trivial', () => {
    expect(VK_DICTIONARY.length).toBeGreaterThan(200);
    for (const w of VK_DICTIONARY) {
      expect(w).toMatch(/^[a-z']+$/);
    }
  });
});
