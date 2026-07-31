// @vitest-environment jsdom
/**
 * Renderer-level coverage for the ChromeBar address-bar URL-sync invariants
 * (useOmniboxUrlSync — the exact hook ChromeBar consumes):
 *
 *  1. Uncommitted typed text + UNFOCUSED bar + tab navigation (redirect /
 *     link click) → the bar discards the stale text and shows the new URL,
 *     with the edited flag cleared — the bar stays truthful.
 *  2. Typing while FOCUSED is never interrupted by background navigations.
 *  3. No edits + unfocused → the bar simply mirrors the tab URL.
 *  4. Switching tabs always resets the bar to the new tab's URL.
 *  5. COMMIT path: pressing Enter navigates to exactly the typed text, blurs
 *     the bar, and clears the edited flag; the subsequent tab URL update
 *     (the navigation landing) syncs the bar cleanly.
 *  6. RECOVERY path: text discarded by an automatic navigation is stashed in
 *     discardedTypedTextRef and Ctrl/Cmd+Z restores it (edited flag set);
 *     the stash is kept PER TAB across tab switches (returning to a tab
 *     restores its stash, and an uncommitted draft interrupted by a tab
 *     switch is stashed too) and clears on user-committed navigations
 *     (Enter submit / suggestion accept).
 *  7. ESCAPE path: Escape resets the bar to the tab URL and clears the edited
 *     flag, but — like Chrome — the cleared text is stashed first so Ctrl/Cmd+Z
 *     restores exactly what Escape wiped (never older stale text); whitespace
 *     or empty values are never stashed.
 *  8. TWO-PRESS ESCAPE: with the suggestions dropdown OPEN, the first Escape
 *     only closes the dropdown — typed text and the edited flag are untouched;
 *     the second Escape (suggestions now closed) resets the bar AND stashes
 *     the text so Ctrl/Cmd+Z still recovers it.
 */
import { describe, it, expect, vi } from 'vitest';
import React, { act, useRef, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { useOmniboxUrlSync } from '../src/renderer/hooks/use-omnibox-url-sync';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

/**
 * Harness wired exactly like ChromeBar: same state trio
 * (omniboxValue / omniboxFocused / omniboxEdited), same hook, and the same
 * input rendering rule — the input shows the typed value while focused or
 * edited, otherwise the tab URL.
 */
function OmniboxHarness({
  tabId,
  tabUrl,
  navigate,
  stashProbe,
}: {
  tabId: string | null;
  tabUrl: string;
  navigate?: (tabId: string, url: string) => Promise<void>;
  /** Lets tests read the hook's discarded-text stash directly. */
  stashProbe?: { current: { current: string | null } | null };
}) {
  const [omniboxValue, setOmniboxValue] = useState('');
  const [omniboxFocused, setOmniboxFocused] = useState(false);
  const [omniboxEdited, setOmniboxEdited] = useState(false);
  // Mirrors ChromeBar's suggestions dropdown state (suggestionsOpen is
  // derived from a non-empty suggestions list there).
  const [suggestions, setSuggestions] = useState<string[]>([]);
  const [suggestionIndex, setSuggestionIndex] = useState(-1);
  const suggestionsOpen = suggestions.length > 0;
  const omniboxRef = useRef<HTMLInputElement>(null);

  const { discardedTypedTextRef } = useOmniboxUrlSync({
    activeTabId: tabId,
    activeTabUrl: tabUrl,
    omniboxFocused,
    omniboxEdited,
    omniboxValue,
    setOmniboxValue,
    setOmniboxEdited,
  });
  if (stashProbe) stashProbe.current = discardedTypedTextRef;

  // Mirrors ChromeBar's handleOmniboxKeyDown Ctrl/Cmd+Z branch: restore the
  // stashed discarded text (edited flag set), but only when there are no
  // fresh uncommitted edits.
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'z'
        && !omniboxEdited && discardedTypedTextRef.current !== null) {
      e.preventDefault();
      setOmniboxValue(discardedTypedTextRef.current);
      setOmniboxEdited(true);
      discardedTypedTextRef.current = null;
      return;
    }
    // Mirrors ChromeBar's TWO Escape branches:
    //  - suggestions CLOSED: reset to the tab URL, but stash the cleared text
    //    first so Ctrl/Cmd+Z can recover it.
    //  - suggestions OPEN: only close the dropdown — typed text and the
    //    edited flag are untouched.
    if (!suggestionsOpen) {
      if (e.key === 'Escape') {
        if (omniboxEdited && omniboxValue.trim() !== '') {
          discardedTypedTextRef.current = omniboxValue;
        }
        setOmniboxEdited(false);
        setOmniboxValue(tabUrl);
        omniboxRef.current?.blur();
      }
      return;
    }
    if (e.key === 'Escape') {
      setSuggestions([]);
      setSuggestionIndex(-1);
    }
  };

  // Mirrors ChromeBar's acceptSuggestion (user-committed navigation via a
  // suggestion): navigate, clear the edited flag AND the recovery stash.
  const handleAcceptSuggestion = (url: string) => {
    if (!tabId) return;
    void navigate?.(tabId, url);
    setOmniboxEdited(false);
    discardedTypedTextRef.current = null;
    omniboxRef.current?.blur();
  };

  // Mirrors ChromeBar's handleOmniboxSubmit (the Enter-to-navigate commit
  // path): navigate with the trimmed typed text, clear the edited flag, and
  // blur the input.
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!tabId || !omniboxValue.trim()) return;
    void navigate?.(tabId, omniboxValue.trim());
    setOmniboxEdited(false);
    discardedTypedTextRef.current = null;
    omniboxRef.current?.blur();
  };

  return (
    <form onSubmit={handleSubmit}>
      <input
        ref={omniboxRef}
        data-testid="omnibox"
        value={omniboxFocused || omniboxEdited ? omniboxValue : tabUrl}
        onFocus={() => setOmniboxFocused(true)}
        onBlur={() => setOmniboxFocused(false)}
        onKeyDown={handleKeyDown}
        onChange={e => {
          setOmniboxValue(e.target.value);
          setOmniboxEdited(true);
        }}
      />
      <div data-testid="value">{omniboxValue}</div>
      <div data-testid="edited">{omniboxEdited ? 'edited' : 'clean'}</div>
      <div data-testid="focused">{omniboxFocused ? 'focused' : 'blurred'}</div>
      <div data-testid="suggestions">{suggestionsOpen ? 'open' : 'closed'}</div>
      <div data-testid="suggestion-index">{suggestionIndex}</div>
      <button
        type="button"
        data-testid="open-suggestions"
        onClick={() => {
          setSuggestions(['https://one.suggested.example/', 'https://two.suggested.example/']);
          setSuggestionIndex(0);
        }}
      >
        open suggestions
      </button>
      <button
        type="button"
        data-testid="suggestion"
        onClick={() => handleAcceptSuggestion('https://suggested.example/')}
      >
        suggestion
      </button>
    </form>
  );
}

interface Mounted {
  root: Root;
  el: HTMLElement;
  render: (tabId: string | null, tabUrl: string) => Promise<void>;
}

async function mount(
  tabId: string | null,
  tabUrl: string,
  navigate?: (tabId: string, url: string) => Promise<void>,
  stashProbe?: { current: { current: string | null } | null },
): Promise<Mounted> {
  const el = document.createElement('div');
  document.body.appendChild(el);
  const root = createRoot(el);
  const render = async (id: string | null, url: string) => {
    await act(async () => {
      root.render(
        <OmniboxHarness tabId={id} tabUrl={url} navigate={navigate} stashProbe={stashProbe} />,
      );
    });
  };
  await render(tabId, tabUrl);
  return { root, el, render };
}

const input = (el: HTMLElement) => el.querySelector('[data-testid="omnibox"]') as HTMLInputElement;
const text = (el: HTMLElement, id: string) => el.querySelector(`[data-testid="${id}"]`)!.textContent;

function setNativeValue(inp: HTMLInputElement, value: string) {
  // React overrides the value setter on the element instance; go through the
  // prototype setter so the synthetic change event carries the new value.
  const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
  setter.call(inp, value);
  inp.dispatchEvent(new Event('input', { bubbles: true }));
}

async function typeInto(el: HTMLElement, value: string) {
  await act(async () => { setNativeValue(input(el), value); });
}

// React binds onFocus/onBlur to the bubbling focusin/focusout events; use the
// real element focus()/blur() so jsdom fires the proper event sequence.
async function focus(el: HTMLElement) {
  await act(async () => { input(el).focus(); });
}

async function blur(el: HTMLElement) {
  await act(async () => { input(el).blur(); });
}

// Pressing Enter in a single-input form triggers a submit; drive the same
// path directly via requestSubmit so React's onSubmit handler runs.
async function pressEnter(el: HTMLElement) {
  await act(async () => {
    input(el).form!.requestSubmit();
  });
}

describe('useOmniboxUrlSync — address bar stays truthful during redirects', () => {
  it('discards uncommitted text when the tab navigates while the bar is unfocused', async () => {
    const m = await mount('tab-1', 'https://start.example/');

    // User focuses, types, then clicks away without committing (Enter).
    await focus(m.el);
    await typeInto(m.el, 'half-typed query');
    expect(text(m.el, 'edited')).toBe('edited');
    await blur(m.el);
    // Uncommitted edit survives the blur itself (no navigation yet).
    expect(input(m.el).value).toBe('half-typed query');

    // The tab redirects on its own.
    await m.render('tab-1', 'https://redirected.example/final');

    // Bar shows the real URL and the edited state is cleared.
    expect(input(m.el).value).toBe('https://redirected.example/final');
    expect(text(m.el, 'value')).toBe('https://redirected.example/final');
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('never interrupts typing: focused text survives a background navigation', async () => {
    const m = await mount('tab-1', 'https://start.example/');

    await focus(m.el);
    await typeInto(m.el, 'sayzio.app/pri');

    // Background navigation (e.g. a slow redirect landing) while still typing.
    await m.render('tab-1', 'https://redirected.example/');
    expect(input(m.el).value).toBe('sayzio.app/pri');
    expect(text(m.el, 'edited')).toBe('edited');

    // Keep typing across yet another navigation — still untouched.
    await typeInto(m.el, 'sayzio.app/pricing');
    await m.render('tab-1', 'https://redirected.example/two');
    expect(input(m.el).value).toBe('sayzio.app/pricing');
    expect(text(m.el, 'edited')).toBe('edited');
  });

  it('mirrors the tab URL when unfocused with no edits', async () => {
    const m = await mount('tab-1', 'https://a.example/');
    expect(input(m.el).value).toBe('https://a.example/');

    await m.render('tab-1', 'https://b.example/');
    expect(input(m.el).value).toBe('https://b.example/');
    expect(text(m.el, 'value')).toBe('https://b.example/');
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('keeps uncommitted text on unrelated re-renders (no URL change, unfocused)', async () => {
    const m = await mount('tab-1', 'https://start.example/');
    await focus(m.el);
    await typeInto(m.el, 'draft text');
    await blur(m.el);

    // Re-render with the SAME url — e.g. some other piece of state changed.
    await m.render('tab-1', 'https://start.example/');
    expect(input(m.el).value).toBe('draft text');
    expect(text(m.el, 'edited')).toBe('edited');
  });

  it('switching tabs resets the bar to the new tab URL and clears edits', async () => {
    const m = await mount('tab-1', 'https://one.example/');
    await focus(m.el);
    await typeInto(m.el, 'typing on tab one');
    await blur(m.el);

    await m.render('tab-2', 'https://two.example/');
    expect(input(m.el).value).toBe('https://two.example/');
    expect(text(m.el, 'value')).toBe('https://two.example/');
    expect(text(m.el, 'edited')).toBe('clean');
  });
});

describe('useOmniboxUrlSync — Enter commits the typed text (navigate path)', () => {
  it('pressing Enter navigates to exactly the typed text, blurs the bar, and clears the edited flag', async () => {
    const navigate = vi.fn(() => Promise.resolve());
    const m = await mount('tab-1', 'https://start.example/', navigate);

    await focus(m.el);
    await typeInto(m.el, '  https://typed.example/dest  ');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(text(m.el, 'focused')).toBe('focused');

    await pressEnter(m.el);

    // navigate() is called once with the ACTIVE tab id and the trimmed typed
    // text — not a stale value or the current tab URL.
    expect(navigate).toHaveBeenCalledTimes(1);
    expect(navigate).toHaveBeenCalledWith('tab-1', 'https://typed.example/dest');

    // The commit blurs the bar and clears the edited state.
    expect(text(m.el, 'focused')).toBe('blurred');
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('the navigation landing after a commit syncs the bar without re-discarding anything', async () => {
    const navigate = vi.fn(() => Promise.resolve());
    const m = await mount('tab-1', 'https://start.example/', navigate);

    await focus(m.el);
    await typeInto(m.el, 'https://typed.example/dest');
    await pressEnter(m.el);
    expect(navigate).toHaveBeenCalledWith('tab-1', 'https://typed.example/dest');
    expect(text(m.el, 'edited')).toBe('clean');

    // The tab reports the navigation landing (possibly normalized/redirected).
    await m.render('tab-1', 'https://typed.example/dest/landing');

    // The bar mirrors the landed URL; state stays clean and no extra
    // navigations are triggered.
    expect(input(m.el).value).toBe('https://typed.example/dest/landing');
    expect(text(m.el, 'value')).toBe('https://typed.example/dest/landing');
    expect(text(m.el, 'edited')).toBe('clean');
    expect(text(m.el, 'focused')).toBe('blurred');
    expect(navigate).toHaveBeenCalledTimes(1);
  });

  it('Enter with only whitespace or no active tab does not navigate', async () => {
    const navigate = vi.fn(() => Promise.resolve());
    const m = await mount('tab-1', 'https://start.example/', navigate);

    await focus(m.el);
    await typeInto(m.el, '   ');
    await pressEnter(m.el);
    expect(navigate).not.toHaveBeenCalled();

    // No active tab → submit is a no-op too.
    const m2 = await mount(null, '', navigate);
    await focus(m2.el);
    await typeInto(m2.el, 'https://somewhere.example/');
    await pressEnter(m2.el);
    expect(navigate).not.toHaveBeenCalled();
  });
});

describe('useOmniboxUrlSync — shortcut navigations (back/forward/home/reload) vs uncommitted text', () => {
  // Alt+Left/Right, Alt+Home, toolbar back/forward/home all surface to the
  // renderer the same way: the active tab's URL changes without the user
  // committing anything through the bar. These must obey the same
  // discard/preserve invariants as redirects.

  it('UNFOCUSED + uncommitted text: a back/forward-style URL change discards the stale text', async () => {
    const m = await mount('tab-1', 'https://current.example/page-b');

    await focus(m.el);
    await typeInto(m.el, 'unfinished search terms');
    await blur(m.el);
    expect(input(m.el).value).toBe('unfinished search terms');
    expect(text(m.el, 'edited')).toBe('edited');

    // User hits Alt+Left — the tab goes back to the previous history entry.
    await m.render('tab-1', 'https://current.example/page-a');

    expect(input(m.el).value).toBe('https://current.example/page-a');
    expect(text(m.el, 'value')).toBe('https://current.example/page-a');
    expect(text(m.el, 'edited')).toBe('clean');

    // Alt+Right forward again — bar keeps mirroring, no stale text returns.
    await m.render('tab-1', 'https://current.example/page-b');
    expect(input(m.el).value).toBe('https://current.example/page-b');
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('FOCUSED + uncommitted text: a back/forward-style URL change never touches the typed text', async () => {
    const m = await mount('tab-1', 'https://current.example/page-b');

    await focus(m.el);
    await typeInto(m.el, 'still typing this out');

    // Alt+Home style jump while the bar stays focused (shortcut handled
    // globally, focus never left the input).
    await m.render('tab-1', 'https://home.example/');
    expect(input(m.el).value).toBe('still typing this out');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(text(m.el, 'focused')).toBe('focused');

    // Back again — still untouched.
    await m.render('tab-1', 'https://current.example/page-b');
    expect(input(m.el).value).toBe('still typing this out');
    expect(text(m.el, 'edited')).toBe('edited');

    // Only once the user blurs AND another navigation lands does the bar
    // discard the stale text.
    await blur(m.el);
    expect(input(m.el).value).toBe('still typing this out');
    await m.render('tab-1', 'https://current.example/page-c');
    expect(input(m.el).value).toBe('https://current.example/page-c');
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('reload (same URL, no change) preserves unfocused uncommitted text', async () => {
    const m = await mount('tab-1', 'https://current.example/page');

    await focus(m.el);
    await typeInto(m.el, 'draft not yet committed');
    await blur(m.el);

    // Ctrl+R / toolbar reload: the tab re-navigates to the SAME URL, so the
    // renderer sees a re-render with an unchanged activeTabUrl. The draft
    // must survive — nothing actually moved.
    await m.render('tab-1', 'https://current.example/page');
    expect(input(m.el).value).toBe('draft not yet committed');
    expect(text(m.el, 'edited')).toBe('edited');
  });

  it('back/forward with no edits: the bar simply follows history, staying clean', async () => {
    const m = await mount('tab-1', 'https://h.example/three');
    expect(input(m.el).value).toBe('https://h.example/three');

    await m.render('tab-1', 'https://h.example/two');
    expect(input(m.el).value).toBe('https://h.example/two');
    await m.render('tab-1', 'https://h.example/one');
    expect(input(m.el).value).toBe('https://h.example/one');
    await m.render('tab-1', 'https://h.example/two');
    expect(input(m.el).value).toBe('https://h.example/two');
    expect(text(m.el, 'edited')).toBe('clean');
    expect(text(m.el, 'focused')).toBe('blurred');
  });
});

describe('useOmniboxUrlSync — Ctrl/Cmd+Z recovers text discarded by a surprise navigation', () => {
  const keyZ = (opts: { ctrlKey?: boolean; metaKey?: boolean; shiftKey?: boolean; altKey?: boolean }) =>
    new KeyboardEvent('keydown', { key: 'z', bubbles: true, cancelable: true, ...opts });

  async function pressUndo(el: HTMLElement, opts: Parameters<typeof keyZ>[0] = { ctrlKey: true }) {
    await act(async () => { input(el).dispatchEvent(keyZ(opts)); });
  }

  it('stashes discarded text in discardedTypedTextRef after an automatic navigation', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'important half-typed note');
    await blur(m.el);
    expect(stash.current!.current).toBeNull();

    // The tab redirects on its own — the typed text is discarded but stashed.
    await m.render('tab-1', 'https://redirected.example/final');
    expect(input(m.el).value).toBe('https://redirected.example/final');
    expect(text(m.el, 'edited')).toBe('clean');
    expect(stash.current!.current).toBe('important half-typed note');
  });

  it('Ctrl+Z restores the discarded text into the bar with the edited flag set', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'recover me please');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('recover me please');

    await pressUndo(m.el, { ctrlKey: true });

    expect(text(m.el, 'value')).toBe('recover me please');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(input(m.el).value).toBe('recover me please');
    // The stash is consumed — a second Ctrl+Z has nothing to restore.
    expect(stash.current!.current).toBeNull();
  });

  it('Cmd+Z (macOS) restores too; Shift/Alt-modified combos do not', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'mac draft');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');

    // Ctrl+Shift+Z (redo) and Alt+Z must NOT trigger the restore.
    await pressUndo(m.el, { ctrlKey: true, shiftKey: true });
    expect(text(m.el, 'edited')).toBe('clean');
    expect(stash.current!.current).toBe('mac draft');
    await pressUndo(m.el, { ctrlKey: true, altKey: true });
    expect(text(m.el, 'edited')).toBe('clean');
    expect(stash.current!.current).toBe('mac draft');

    await pressUndo(m.el, { metaKey: true });
    expect(text(m.el, 'value')).toBe('mac draft');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(stash.current!.current).toBeNull();
  });

  it('Ctrl+Z defers to native undo while there are fresh uncommitted edits', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'first draft');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('first draft');

    // User starts typing something NEW — omniboxEdited is true again, so
    // Ctrl+Z must be left to the input's native undo, not the stash restore.
    await focus(m.el);
    await typeInto(m.el, 'second draft');
    expect(text(m.el, 'edited')).toBe('edited');
    await pressUndo(m.el, { ctrlKey: true });
    expect(input(m.el).value).toBe('second draft');
    expect(stash.current!.current).toBe('first draft');
  });

  it('tab switch keeps the stash per tab — the new tab has nothing, the old tab restores', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://one.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'tab one draft');
    await blur(m.el);
    await m.render('tab-1', 'https://one.example/redirected');
    expect(stash.current!.current).toBe('tab one draft');

    // On tab 2, there is no stash — Ctrl+Z restores nothing.
    await m.render('tab-2', 'https://two.example/');
    expect(stash.current!.current).toBeNull();
    await pressUndo(m.el, { ctrlKey: true });
    expect(input(m.el).value).toBe('https://two.example/');
    expect(text(m.el, 'edited')).toBe('clean');

    // Back on tab 1, the stash is restored and Ctrl+Z recovers the draft.
    await m.render('tab-1', 'https://one.example/redirected');
    expect(stash.current!.current).toBe('tab one draft');
    await pressUndo(m.el, { ctrlKey: true });
    expect(input(m.el).value).toBe('tab one draft');
    expect(text(m.el, 'value')).toBe('tab one draft');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(stash.current!.current).toBeNull();
  });

  it('an uncommitted draft interrupted by a tab switch is recoverable when returning', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://one.example/', undefined, stash);

    // User is mid-typing (no discard happened yet) and clicks another tab.
    await focus(m.el);
    await typeInto(m.el, 'half-typed on tab one');
    await blur(m.el);
    expect(stash.current!.current).toBeNull();

    await m.render('tab-2', 'https://two.example/');
    expect(input(m.el).value).toBe('https://two.example/');
    expect(text(m.el, 'edited')).toBe('clean');
    expect(stash.current!.current).toBeNull();

    // Returning to tab 1: bar shows the tab URL, but Ctrl+Z restores the draft.
    await m.render('tab-1', 'https://one.example/');
    expect(input(m.el).value).toBe('https://one.example/');
    expect(stash.current!.current).toBe('half-typed on tab one');
    await pressUndo(m.el, { ctrlKey: true });
    expect(input(m.el).value).toBe('half-typed on tab one');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(stash.current!.current).toBeNull();
  });

  it('a fresh uncommitted draft wins over an older discarded stash on tab switch', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://one.example/', undefined, stash);

    // An automatic navigation stashes an older draft...
    await focus(m.el);
    await typeInto(m.el, 'older draft');
    await blur(m.el);
    await m.render('tab-1', 'https://one.example/redirected');
    expect(stash.current!.current).toBe('older draft');

    // ...then the user types NEW text and switches tabs without committing.
    await focus(m.el);
    await typeInto(m.el, 'newest draft');
    await blur(m.el);
    await m.render('tab-2', 'https://two.example/');

    // Returning restores the newest draft, not the stale one.
    await m.render('tab-1', 'https://one.example/redirected');
    expect(stash.current!.current).toBe('newest draft');
    await pressUndo(m.el, { ctrlKey: true });
    expect(input(m.el).value).toBe('newest draft');
  });

  it('a committed navigation clears the per-tab stash across tab switches too', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const navigate = vi.fn(() => Promise.resolve());
    const m = await mount('tab-1', 'https://one.example/', navigate, stash);

    await focus(m.el);
    await typeInto(m.el, 'doomed draft');
    await blur(m.el);
    await m.render('tab-1', 'https://one.example/redirected');
    expect(stash.current!.current).toBe('doomed draft');

    // The user commits a navigation — the stash is consumed for good.
    await focus(m.el);
    await typeInto(m.el, 'https://typed.example/next');
    await pressEnter(m.el);
    expect(stash.current!.current).toBeNull();

    // Switching away and back must NOT resurrect the pre-commit draft.
    await m.render('tab-2', 'https://two.example/');
    await m.render('tab-1', 'https://one.example/redirected');
    expect(stash.current!.current).toBeNull();
    await pressUndo(m.el, { ctrlKey: true });
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('a user-committed navigation (Enter) clears the stash', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const navigate = vi.fn(() => Promise.resolve());
    const m = await mount('tab-1', 'https://start.example/', navigate, stash);

    await focus(m.el);
    await typeInto(m.el, 'lost draft');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('lost draft');

    // Instead of recovering, the user types a fresh destination and commits.
    await focus(m.el);
    await typeInto(m.el, 'https://typed.example/next');
    await pressEnter(m.el);
    expect(navigate).toHaveBeenCalledWith('tab-1', 'https://typed.example/next');
    expect(stash.current!.current).toBeNull();

    await pressUndo(m.el, { ctrlKey: true });
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('accepting a suggestion (user commit) clears the stash', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const navigate = vi.fn(() => Promise.resolve());
    const m = await mount('tab-1', 'https://start.example/', navigate, stash);

    await focus(m.el);
    await typeInto(m.el, 'abandoned words');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('abandoned words');

    await act(async () => {
      (m.el.querySelector('[data-testid="suggestion"]') as HTMLButtonElement).click();
    });
    expect(navigate).toHaveBeenCalledWith('tab-1', 'https://suggested.example/');
    expect(stash.current!.current).toBeNull();

    await pressUndo(m.el, { ctrlKey: true });
    expect(text(m.el, 'edited')).toBe('clean');
  });

  it('whitespace-only or empty typed values are never stashed', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, '   ');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBeNull();
    await pressUndo(m.el, { ctrlKey: true });
    expect(text(m.el, 'edited')).toBe('clean');
  });
});

describe('Escape resets the omnibox without stranding recoverable text', () => {
  const keyEvent = (key: string, opts: KeyboardEventInit = {}) =>
    new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...opts });

  async function press(el: HTMLElement, key: string, opts: KeyboardEventInit = {}) {
    await act(async () => { input(el).dispatchEvent(keyEvent(key, opts)); });
  }

  it('Escape resets to the tab URL, clears the edited flag, and stashes the cleared text', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'escape me draft');
    expect(text(m.el, 'edited')).toBe('edited');

    await press(m.el, 'Escape');

    expect(text(m.el, 'value')).toBe('https://start.example/');
    expect(text(m.el, 'edited')).toBe('clean');
    expect(stash.current!.current).toBe('escape me draft');
  });

  it('Ctrl+Z after Escape restores exactly the text Escape cleared', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'wiped by escape');
    await press(m.el, 'Escape');
    expect(text(m.el, 'edited')).toBe('clean');

    await press(m.el, 'z', { ctrlKey: true });

    expect(text(m.el, 'value')).toBe('wiped by escape');
    expect(input(m.el).value).toBe('wiped by escape');
    expect(text(m.el, 'edited')).toBe('edited');
    // The stash is consumed.
    expect(stash.current!.current).toBeNull();
  });

  it('Escape overwrites an older stash so Ctrl+Z never restores stale text', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    // An automatic navigation stashes an older draft.
    await focus(m.el);
    await typeInto(m.el, 'older stale draft');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('older stale draft');

    // The user types fresh text and hits Escape.
    await focus(m.el);
    await typeInto(m.el, 'newest draft');
    await press(m.el, 'Escape');
    expect(stash.current!.current).toBe('newest draft');

    await press(m.el, 'z', { ctrlKey: true });
    expect(text(m.el, 'value')).toBe('newest draft');
    expect(text(m.el, 'edited')).toBe('edited');
  });

  it('Escape with no edits or whitespace-only text does not touch the stash', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    // No edits at all: Escape leaves the (empty) stash alone.
    await focus(m.el);
    await press(m.el, 'Escape');
    expect(stash.current!.current).toBeNull();

    // Whitespace-only edits are never stashed.
    await focus(m.el);
    await typeInto(m.el, '   ');
    await press(m.el, 'Escape');
    expect(stash.current!.current).toBeNull();
    expect(text(m.el, 'edited')).toBe('clean');

    // And Escape with no edits must not clobber an EXISTING stash.
    await focus(m.el);
    await typeInto(m.el, 'keep me safe');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('keep me safe');
    await focus(m.el);
    await press(m.el, 'Escape');
    expect(stash.current!.current).toBe('keep me safe');
  });

  async function openSuggestions(el: HTMLElement) {
    await act(async () => {
      (el.querySelector('[data-testid="open-suggestions"]') as HTMLButtonElement).click();
    });
  }

  it('with suggestions open, the first Escape only closes the dropdown — typed text and edited flag survive', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'two-press draft');
    await openSuggestions(m.el);
    expect(text(m.el, 'suggestions')).toBe('open');

    await press(m.el, 'Escape');

    // Dropdown closed, highlight reset — but the text is untouched, the
    // edited flag stays set, the bar stays focused, and nothing was stashed.
    expect(text(m.el, 'suggestions')).toBe('closed');
    expect(text(m.el, 'suggestion-index')).toBe('-1');
    expect(input(m.el).value).toBe('two-press draft');
    expect(text(m.el, 'value')).toBe('two-press draft');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(text(m.el, 'focused')).toBe('focused');
    expect(stash.current!.current).toBeNull();
  });

  it('the second Escape (suggestions now closed) resets the bar, stashes the text, and Ctrl+Z restores it', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    await focus(m.el);
    await typeInto(m.el, 'recover after two escapes');
    await openSuggestions(m.el);

    // Escape #1: closes suggestions only.
    await press(m.el, 'Escape');
    expect(text(m.el, 'suggestions')).toBe('closed');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(stash.current!.current).toBeNull();

    // Escape #2: resets to the tab URL and stashes the typed text.
    await press(m.el, 'Escape');
    expect(text(m.el, 'value')).toBe('https://start.example/');
    expect(text(m.el, 'edited')).toBe('clean');
    expect(stash.current!.current).toBe('recover after two escapes');

    // Ctrl+Z brings the text back exactly.
    await press(m.el, 'z', { ctrlKey: true });
    expect(text(m.el, 'value')).toBe('recover after two escapes');
    expect(input(m.el).value).toBe('recover after two escapes');
    expect(text(m.el, 'edited')).toBe('edited');
    expect(stash.current!.current).toBeNull();
  });

  it('while suggestions are open, Escape never clobbers an existing stash', async () => {
    const stash: { current: { current: string | null } | null } = { current: null };
    const m = await mount('tab-1', 'https://start.example/', undefined, stash);

    // An automatic navigation stashes an earlier draft.
    await focus(m.el);
    await typeInto(m.el, 'earlier stashed draft');
    await blur(m.el);
    await m.render('tab-1', 'https://surprise.example/');
    expect(stash.current!.current).toBe('earlier stashed draft');

    // Fresh typing with the dropdown open; Escape #1 must leave the old
    // stash alone (the fresh text is still live in the bar).
    await focus(m.el);
    await typeInto(m.el, 'fresh dropdown draft');
    await openSuggestions(m.el);
    await press(m.el, 'Escape');
    expect(stash.current!.current).toBe('earlier stashed draft');
    expect(input(m.el).value).toBe('fresh dropdown draft');

    // Escape #2 replaces the stash with the freshly cleared text.
    await press(m.el, 'Escape');
    expect(stash.current!.current).toBe('fresh dropdown draft');
  });
});
