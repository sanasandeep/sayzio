/**
 * Docked on-screen virtual keyboard. Rendered below the tab area (the main
 * process shrinks native views by VK_DOCK_HEIGHT while visible) so it never
 * fights the WebContentsViews for space. Keys are injected into the focused
 * tab page via the vk IPC namespace.
 *
 * The draggable, semi-transparent suggestion strip floats anywhere over the
 * page. Native views cover the renderer DOM, so the strip is a frameless
 * child BrowserWindow owned by the main process; this component drives it via
 * the vk.strip* IPC calls and receives selections on 'vk:strip-select'.
 */
import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import {
  VK_LETTER_ROWS,
  VK_SYMBOL_ROWS,
  VK_NUMERIC_ROWS,
  VK_DOCK_HEIGHT,
  VK_DWELL_MS,
  VK_PREF_KEYS,
  applyShift,
  nextShiftState,
  layerForFieldKind,
  suggestionsAllowedFor,
  suggestFor,
  suggestNextWords,
  lastWordOf,
  expandShortcutOnSpace,
  extractWords,
  extractWordPairs,
  parseShortcuts,
  parseTypingHistory,
  parseBigramHistory,
  stripLabelFor,
  type VkLayer,
  type VkShiftState,
  type VkFieldKind,
  type VkSettings,
  type VkShortcut,
  type VkTypingHistory,
  type VkBigramHistory,
  type VkSuggestion,
  type VkStripUpdatePayload,
  type VkSpecialKey,
} from '../../shared/virtual-keyboard';
import { VK_COMMON_BIGRAMS, VK_DICTIONARY } from '../../shared/vk-dictionary';

interface Props {
  settings: VkSettings;
  /** Kind of page field currently focused (drives layer + suggestion gating). */
  fieldKind: VkFieldKind;
  onClose: () => void;
}

const keyStyle: React.CSSProperties = {
  flex: 1,
  minWidth: 0,
  height: 38,
  borderRadius: 8,
  border: '1px solid var(--color-border)',
  background: 'var(--color-bg-elevated)',
  color: 'var(--color-text)',
  fontSize: 15,
  cursor: 'pointer',
  userSelect: 'none',
  padding: 0,
};

const specialKeyStyle: React.CSSProperties = {
  ...keyStyle,
  background: 'var(--color-bg)',
  color: 'var(--color-text-muted)',
  fontSize: 13,
  fontWeight: 600,
};

export function VirtualKeyboard({ settings, fieldKind, onClose }: Props) {
  const [layer, setLayer] = useState<VkLayer>(() => layerForFieldKind(fieldKind));
  const [shift, setShift] = useState<VkShiftState>('off');
  // Local echo of what's been typed since focus — feeds suggestions/shortcuts.
  const [buffer, setBuffer] = useState('');
  const [shortcuts, setShortcuts] = useState<VkShortcut[]>([]);
  const [history, setHistory] = useState<VkTypingHistory>({});
  const [bigrams, setBigrams] = useState<VkBigramHistory>({});
  // Last word completed with space/suggestion — drives next-word predictions.
  const [prevWord, setPrevWord] = useState('');
  const pendingWordsRef = useRef<string[]>([]);
  const pendingPairsRef = useRef<Array<[string, string]>>([]);
  // Bridges word pairs across separate learnFrom calls (word … space … word).
  const lastLearnedWordRef = useRef('');

  // Follow the focused field: numeric fields open the numeric layer, and any
  // focus change resets the local typing echo (the page's real content is
  // unknown to us).
  useEffect(() => {
    setLayer(layerForFieldKind(fieldKind));
    setBuffer('');
    setPrevWord('');
    lastLearnedWordRef.current = '';
  }, [fieldKind]);

  // Load shortcuts + history once per open.
  useEffect(() => {
    let cancelled = false;
    void (async () => {
      try {
        const [rawShortcuts, rawHistory, rawBigrams] = await Promise.all([
          window.zio.prefs.get(VK_PREF_KEYS.SHORTCUTS) as Promise<string | null>,
          window.zio.prefs.get(VK_PREF_KEYS.TYPING_HISTORY) as Promise<string | null>,
          window.zio.prefs.get(VK_PREF_KEYS.BIGRAMS) as Promise<string | null>,
        ]);
        if (cancelled) return;
        setShortcuts(parseShortcuts(rawShortcuts));
        setHistory(parseTypingHistory(rawHistory));
        setBigrams(parseBigramHistory(rawBigrams));
      } catch { /* defaults are fine */ }
    })();
    return () => { cancelled = true; };
  }, []);

  // Flush learned words when the keyboard closes or on unload.
  const flushLearned = useCallback(() => {
    const words = pendingWordsRef.current;
    const pairs = pendingPairsRef.current;
    if (words.length === 0 && pairs.length === 0) return;
    pendingWordsRef.current = [];
    pendingPairsRef.current = [];
    if (settings.learnHistory) void window.zio.vk.recordWords(words, pairs);
  }, [settings.learnHistory]);
  useEffect(() => () => { flushLearned(); }, [flushLearned]);

  const allowSuggest = settings.suggestions && suggestionsAllowedFor(fieldKind);
  const allowLearn = settings.learnHistory && suggestionsAllowedFor(fieldKind) && fieldKind !== 'number';

  const prefix = lastWordOf(buffer);
  const suggestions = useMemo<VkSuggestion[]>(() => {
    if (!allowSuggest) return [];
    if (prefix) return suggestFor(prefix, { shortcuts, history, dictionary: VK_DICTIONARY, prevWord, bigrams });
    // After a space: predict whole next words from learned bigrams.
    if (prevWord && buffer.endsWith(' ')) return suggestNextWords(prevWord, bigrams, 3, VK_COMMON_BIGRAMS);
    return [];
  }, [allowSuggest, prefix, shortcuts, history, prevWord, buffer, bigrams]);

  // ── Input actions ──────────────────────────────────────────────────────────

  const insert = useCallback((text: string) => {
    void window.zio.vk.insertText(text);
  }, []);

  const learnFrom = useCallback((text: string) => {
    if (!allowLearn) return;
    const words = extractWords(text);
    if (words.length > 0) pendingWordsRef.current.push(...words);
    // Learn word pairs for next-word prediction, bridging across the previous
    // learned word (learnFrom usually receives one word at a time).
    const pairText = lastLearnedWordRef.current ? `${lastLearnedWordRef.current} ${text}` : text;
    const pairs = extractWordPairs(pairText);
    if (pairs.length > 0) pendingPairsRef.current.push(...pairs);
    const seq = (text.toLowerCase().match(/[a-z']{2,32}/g) ?? []).filter(w => /[a-z]/.test(w));
    if (seq.length > 0) lastLearnedWordRef.current = seq[seq.length - 1];
  }, [allowLearn]);

  const handleChar = useCallback((ch: string) => {
    const out = applyShift(ch, shift);
    insert(out);
    setBuffer(prev => prev + out);
    if (/[a-z]/i.test(ch)) setShift(prev => nextShiftState(prev, 'typedLetter'));
  }, [shift, insert]);

  const handleSpace = useCallback(() => {
    const word = lastWordOf(buffer);
    const expansion = expandShortcutOnSpace(word, shortcuts, settings.expandOnSpace);
    if (expansion) {
      void (async () => {
        for (let i = 0; i < expansion.backspaceCount; i++) {
          await window.zio.vk.sendKey('Backspace');
        }
        await window.zio.vk.insertText(expansion.insertText);
      })();
      learnFrom(expansion.insertText);
      const expWord = lastWordOf(expansion.insertText.trimEnd());
      setPrevWord(expWord.toLowerCase());
      setBuffer(' ');
      return;
    }
    insert(' ');
    if (word) learnFrom(word);
    setPrevWord(word.toLowerCase());
    // Keep a trailing-space echo so next-word predictions know a word just
    // ended (the prefix is empty right after a space).
    setBuffer(word ? ' ' : '');
  }, [buffer, shortcuts, settings.expandOnSpace, insert, learnFrom]);

  const handleSpecial = useCallback((key: VkSpecialKey) => {
    void window.zio.vk.sendKey(key);
    if (key === 'Backspace') {
      setBuffer(prev => prev.slice(0, -1));
      setPrevWord('');
    } else if (key === 'Enter') {
      const word = lastWordOf(buffer);
      if (word) learnFrom(word);
      setBuffer('');
      setPrevWord('');
      lastLearnedWordRef.current = '';
    } else {
      // Arrows / Tab move the caret somewhere we can't track — reset the echo.
      setBuffer('');
      setPrevWord('');
      lastLearnedWordRef.current = '';
    }
  }, [buffer, learnFrom]);

  const acceptSuggestion = useCallback((s: VkSuggestion) => {
    const p = lastWordOf(buffer);
    void (async () => {
      for (let i = 0; i < p.length; i++) {
        await window.zio.vk.sendKey('Backspace');
      }
      const text = (s.source === 'shortcut' && s.expansion ? s.expansion : s.word) + ' ';
      await window.zio.vk.insertText(text);
      learnFrom(text);
    })();
    const accepted = lastWordOf((s.source === 'shortcut' && s.expansion ? s.expansion : s.word).trimEnd());
    setPrevWord(accepted.toLowerCase());
    setBuffer(' ');
  }, [buffer, learnFrom]);

  // ── Floating suggestion strip (frameless child window, main-owned) ─────────

  // Show/hide the strip window with the keyboard; it floats over the page.
  useEffect(() => {
    if (allowSuggest) void window.zio.vk.stripShow();
    else void window.zio.vk.stripHide();
    return () => { void window.zio.vk.stripHide(); };
  }, [allowSuggest]);

  // Re-render the strip whenever suggestions or the selection mode change.
  // Dwell timing runs inside the strip page itself (hover never leaves it).
  useEffect(() => {
    if (!allowSuggest) return;
    const payload: VkStripUpdatePayload = {
      suggestions: suggestions.map(s => ({
        label: stripLabelFor(s),
        title: s.source === 'shortcut' ? `Shortcut → ${s.expansion ?? ''}` : s.word,
        source: s.source,
      })),
      selectionMode: settings.selectionMode,
      dwellMs: VK_DWELL_MS,
      light: document.documentElement.classList.contains('light-mode'),
      placeholder: prefix ? 'No suggestions' : 'Suggestions appear as you type',
    };
    void window.zio.vk.stripUpdate(payload);
  }, [allowSuggest, suggestions, settings.selectionMode, prefix]);

  // Selections arrive from the strip window by index.
  const suggestionsRef = useRef<VkSuggestion[]>(suggestions);
  suggestionsRef.current = suggestions;
  const acceptRef = useRef(acceptSuggestion);
  acceptRef.current = acceptSuggestion;
  useEffect(() => {
    const onSelect = (...args: unknown[]) => {
      const index = args[0];
      if (typeof index !== 'number') return;
      const s = suggestionsRef.current[index];
      if (s) acceptRef.current(s);
    };
    window.zio.on('vk:strip-select', onSelect);
    return () => { window.zio.off('vk:strip-select', onSelect); };
  }, []);

  // ── Layout rows for the active layer ───────────────────────────────────────

  const rows = layer === 'letters' ? VK_LETTER_ROWS : layer === 'symbols' ? VK_SYMBOL_ROWS : VK_NUMERIC_ROWS;

  return (
    <div
      data-testid="vk-dock"
      style={{
        height: VK_DOCK_HEIGHT,
        flexShrink: 0,
        position: 'relative',
        background: 'var(--color-bg)',
        borderTop: '1px solid var(--color-border)',
        padding: '44px 8px 8px',
        display: 'flex',
        flexDirection: 'column',
        gap: 5,
        zIndex: 50,
      }}
    >
      {/* Close button */}
      <button
        onClick={onClose}
        data-testid="vk-close"
        title="Hide keyboard"
        style={{
          position: 'absolute',
          top: 6,
          right: 8,
          padding: '3px 10px',
          borderRadius: 8,
          border: '1px solid var(--color-border)',
          background: 'var(--color-bg-elevated)',
          color: 'var(--color-text-muted)',
          fontSize: 12,
          cursor: 'pointer',
          zIndex: 61,
        }}
      >
        ⌄ Hide
      </button>

      {/* Key rows */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 5, maxWidth: layer === 'numeric' ? 320 : 860, width: '100%', margin: '0 auto' }}>
        {rows.map((row, ri) => (
          <div key={ri} style={{ display: 'flex', gap: 5, flex: 1 }}>
            {layer === 'letters' && ri === 2 && (
              <button
                onClick={() => setShift(prev => nextShiftState(prev, 'tapShift'))}
                data-testid="vk-shift"
                style={{
                  ...specialKeyStyle,
                  flex: 1.4,
                  background: shift !== 'off' ? 'var(--color-primary)' : specialKeyStyle.background,
                  color: shift !== 'off' ? '#fff' : specialKeyStyle.color,
                }}
                title={shift === 'caps' ? 'Caps lock on' : shift === 'shift' ? 'Shift on' : 'Shift'}
              >
                {shift === 'caps' ? '⇪' : '⇧'}
              </button>
            )}
            {row.map((k) => (
              <button
                key={k}
                data-testid={`vk-key-${k}`}
                onClick={() => handleChar(k)}
                style={keyStyle}
              >
                {layer === 'letters' ? applyShift(k, shift) : k}
              </button>
            ))}
            {layer === 'letters' && ri === 2 && (
              <button onClick={() => handleSpecial('Backspace')} data-testid="vk-backspace" style={{ ...specialKeyStyle, flex: 1.4 }} title="Backspace">⌫</button>
            )}
          </div>
        ))}

        {/* Bottom row: layer switches, space, enter, arrows */}
        <div style={{ display: 'flex', gap: 5, flex: 1 }}>
          {layer !== 'numeric' && (
            <button
              onClick={() => setLayer(layer === 'letters' ? 'symbols' : 'letters')}
              data-testid="vk-layer-toggle"
              style={{ ...specialKeyStyle, flex: 1.2 }}
            >
              {layer === 'letters' ? '?123' : 'ABC'}
            </button>
          )}
          {layer === 'numeric' && (
            <button onClick={() => setLayer('letters')} data-testid="vk-layer-toggle" style={{ ...specialKeyStyle, flex: 1.2 }}>ABC</button>
          )}
          {layer !== 'numeric' && (
            <button onClick={() => setLayer('numeric')} data-testid="vk-layer-numeric" style={{ ...specialKeyStyle, flex: 1 }}>123</button>
          )}
          {layer !== 'numeric' ? (
            <button onClick={handleSpace} data-testid="vk-space" style={{ ...keyStyle, flex: 5 }} title="Space"> </button>
          ) : (
            <button onClick={() => handleSpecial('Backspace')} data-testid="vk-backspace-num" style={{ ...specialKeyStyle, flex: 1 }} title="Backspace">⌫</button>
          )}
          <button onClick={() => handleSpecial('Enter')} data-testid="vk-enter" style={{ ...specialKeyStyle, flex: 1.4 }} title="Enter">⏎</button>
          <button onClick={() => handleSpecial('ArrowLeft')} data-testid="vk-arrow-left" style={{ ...specialKeyStyle, flex: 0.8 }} title="Left">←</button>
          <button onClick={() => handleSpecial('ArrowUp')} data-testid="vk-arrow-up" style={{ ...specialKeyStyle, flex: 0.8 }} title="Up">↑</button>
          <button onClick={() => handleSpecial('ArrowDown')} data-testid="vk-arrow-down" style={{ ...specialKeyStyle, flex: 0.8 }} title="Down">↓</button>
          <button onClick={() => handleSpecial('ArrowRight')} data-testid="vk-arrow-right" style={{ ...specialKeyStyle, flex: 0.8 }} title="Right">→</button>
        </div>
      </div>
    </div>
  );
}
