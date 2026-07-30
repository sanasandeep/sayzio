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
}: {
  tabId: string | null;
  tabUrl: string;
  navigate?: (tabId: string, url: string) => Promise<void>;
}) {
  const [omniboxValue, setOmniboxValue] = useState('');
  const [omniboxFocused, setOmniboxFocused] = useState(false);
  const [omniboxEdited, setOmniboxEdited] = useState(false);
  const omniboxRef = useRef<HTMLInputElement>(null);

  useOmniboxUrlSync({
    activeTabId: tabId,
    activeTabUrl: tabUrl,
    omniboxFocused,
    omniboxEdited,
    setOmniboxValue,
    setOmniboxEdited,
  });

  // Mirrors ChromeBar's handleOmniboxSubmit (the Enter-to-navigate commit
  // path): navigate with the trimmed typed text, clear the edited flag, and
  // blur the input.
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!tabId || !omniboxValue.trim()) return;
    void navigate?.(tabId, omniboxValue.trim());
    setOmniboxEdited(false);
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
        onChange={e => {
          setOmniboxValue(e.target.value);
          setOmniboxEdited(true);
        }}
      />
      <div data-testid="value">{omniboxValue}</div>
      <div data-testid="edited">{omniboxEdited ? 'edited' : 'clean'}</div>
      <div data-testid="focused">{omniboxFocused ? 'focused' : 'blurred'}</div>
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
): Promise<Mounted> {
  const el = document.createElement('div');
  document.body.appendChild(el);
  const root = createRoot(el);
  const render = async (id: string | null, url: string) => {
    await act(async () => { root.render(<OmniboxHarness tabId={id} tabUrl={url} navigate={navigate} />); });
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
