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
 */
import { describe, it, expect } from 'vitest';
import React, { act, useState } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { useOmniboxUrlSync } from '../src/renderer/hooks/use-omnibox-url-sync';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

/**
 * Harness wired exactly like ChromeBar: same state trio
 * (omniboxValue / omniboxFocused / omniboxEdited), same hook, and the same
 * input rendering rule — the input shows the typed value while focused or
 * edited, otherwise the tab URL.
 */
function OmniboxHarness({ tabId, tabUrl }: { tabId: string | null; tabUrl: string }) {
  const [omniboxValue, setOmniboxValue] = useState('');
  const [omniboxFocused, setOmniboxFocused] = useState(false);
  const [omniboxEdited, setOmniboxEdited] = useState(false);

  useOmniboxUrlSync({
    activeTabId: tabId,
    activeTabUrl: tabUrl,
    omniboxFocused,
    omniboxEdited,
    setOmniboxValue,
    setOmniboxEdited,
  });

  return (
    <div>
      <input
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
    </div>
  );
}

interface Mounted {
  root: Root;
  el: HTMLElement;
  render: (tabId: string | null, tabUrl: string) => Promise<void>;
}

async function mount(tabId: string | null, tabUrl: string): Promise<Mounted> {
  const el = document.createElement('div');
  document.body.appendChild(el);
  const root = createRoot(el);
  const render = async (id: string | null, url: string) => {
    await act(async () => { root.render(<OmniboxHarness tabId={id} tabUrl={url} />); });
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
