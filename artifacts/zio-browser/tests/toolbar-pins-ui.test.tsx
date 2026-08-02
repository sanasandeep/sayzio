// @vitest-environment jsdom
/**
 * Renderer-level coverage for the 2-pin toolbar cap and cross-surface sync.
 *
 * Both surfaces that manage pins — the Settings → General "Toolbar" block
 * (ToolbarBlock) and the ChromeBar "⋯" overflow menu — share the
 * usePinnedTools hook, which loads the `pinned_toolbar_tools` preference,
 * enforces MAX_PINNED_TOOLS, and syncs via the `zio:pinned-tools-changed`
 * window event. These tests render the real ToolbarBlock plus a harness
 * wired exactly like ChromeBar (same hook) and verify:
 *   - pinning a 3rd tool is blocked in the Settings block
 *   - disabled checkboxes carry the "Toolbar is full" hint
 *   - dispatching zio:pinned-tools-changed updates both surfaces' state
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ToolbarBlock } from '../src/renderer/components/SettingsPanel';
import { usePinnedTools } from '../src/renderer/hooks/use-pinned-tools';
import {
  MAX_PINNED_TOOLS,
  PINNED_TOOLS_PREF_KEY,
  PINNED_TOOLS_CHANGED_EVENT,
} from '../src/shared/toolbar-pins';
import type { PinnableTool } from '../src/shared/toolbar-pins';
import { buildZioMock } from './helpers/zio-mock';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

// ── window.zio mock (shared helper) with prefs backed by an in-memory store ─
let prefStore: Map<string, string>;
let prefsSet: ReturnType<typeof vi.fn>;

beforeEach(() => {
  prefStore = new Map();
  prefsSet = vi.fn((key: string, value: string) => {
    prefStore.set(key, value);
    return Promise.resolve();
  });
  const { zio } = buildZioMock({
    overrides: {
      prefs: {
        get: vi.fn((key: string) => Promise.resolve(prefStore.get(key) ?? null)),
        set: prefsSet,
      },
    },
  });
  (window as unknown as Record<string, unknown>).zio = zio;
});

/**
 * Harness mirroring how ChromeBar consumes the shared hook: it renders the
 * pinned list plus one toggle button per tool (the overflow-menu rows).
 */
function ChromeBarPinHarness() {
  const { pinned, capReached, togglePin } = usePinnedTools();
  return (
    <div>
      <div data-testid="cb-pinned">{pinned.join(',')}</div>
      <div data-testid="cb-cap">{capReached ? 'full' : 'open'}</div>
      <button data-testid="cb-pin-device_lab" onClick={() => togglePin('device_lab')}>pin</button>
    </div>
  );
}

async function mount(node: React.ReactElement): Promise<{ root: Root; el: HTMLElement }> {
  const el = document.createElement('div');
  document.body.appendChild(el);
  const root = createRoot(el);
  await act(async () => { root.render(node); });
  return { root, el };
}

async function flush(): Promise<void> {
  // Drain the pref-load promise and the setTimeout(0) event dispatch.
  await act(async () => { await new Promise(r => setTimeout(r, 5)); });
}

function checkboxFor(el: HTMLElement, label: string): HTMLInputElement {
  const row = Array.from(el.querySelectorAll('label')).find(l => l.textContent?.includes(label));
  expect(row, `row for ${label}`).toBeTruthy();
  return row!.querySelector('input[type="checkbox"]') as HTMLInputElement;
}

describe('Settings ToolbarBlock — 2-pin cap', () => {
  it('loads existing pins from the preference', async () => {
    prefStore.set(PINNED_TOOLS_PREF_KEY, JSON.stringify(['dialer', 'screenshot']));
    const { root, el } = await mount(<ToolbarBlock />);
    await flush();
    expect(checkboxFor(el, 'Dialer').checked).toBe(true);
    expect(checkboxFor(el, 'Screenshot').checked).toBe(true);
    expect(checkboxFor(el, 'Reading list').checked).toBe(false);
    act(() => root.unmount());
  });

  it('blocks pinning a 3rd tool: checkbox disabled, hint shown, pref untouched', async () => {
    prefStore.set(PINNED_TOOLS_PREF_KEY, JSON.stringify(['dialer', 'screenshot']));
    const { root, el } = await mount(<ToolbarBlock />);
    await flush();

    const third = checkboxFor(el, 'Device Lab');
    expect(third.disabled).toBe(true);
    // Disabled rows carry the "toolbar is full" hint tooltip (on the row
    // container that wraps the label + reorder arrows).
    const row = third.closest('[title]') as HTMLElement;
    expect(row.title).toContain('Toolbar is full');
    expect(row.title).toContain(`max ${MAX_PINNED_TOOLS}`);
    // The block-level hint is visible too.
    expect(el.textContent).toContain('Toolbar is full — unpin a tool to pin a different one.');

    // A click on a disabled checkbox fires no change; even a forced change
    // event must not grow the list past the cap.
    await act(async () => {
      third.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    await flush();
    expect(checkboxFor(el, 'Device Lab').checked).toBe(false);
    expect(prefsSet).not.toHaveBeenCalled();
    act(() => root.unmount());
  });

  it('still allows unpinning at the cap, then re-enables the other tools', async () => {
    prefStore.set(PINNED_TOOLS_PREF_KEY, JSON.stringify(['dialer', 'screenshot']));
    const { root, el } = await mount(<ToolbarBlock />);
    await flush();

    await act(async () => {
      checkboxFor(el, 'Dialer').click();
    });
    await flush();

    expect(checkboxFor(el, 'Dialer').checked).toBe(false);
    expect(checkboxFor(el, 'Device Lab').disabled).toBe(false);
    expect(prefsSet).toHaveBeenCalledWith(PINNED_TOOLS_PREF_KEY, JSON.stringify(['screenshot']));
    act(() => root.unmount());
  });
});

describe('cross-surface sync via zio:pinned-tools-changed', () => {
  it('dispatching the event updates both surfaces', async () => {
    const settings = await mount(<ToolbarBlock />);
    const chromeBar = await mount(<ChromeBarPinHarness />);
    await flush();

    await act(async () => {
      window.dispatchEvent(new CustomEvent(PINNED_TOOLS_CHANGED_EVENT, {
        detail: ['reading_list', 'dialer'] satisfies PinnableTool[],
      }));
    });

    expect(checkboxFor(settings.el, 'Reading list').checked).toBe(true);
    expect(checkboxFor(settings.el, 'Dialer').checked).toBe(true);
    expect(chromeBar.el.querySelector('[data-testid="cb-pinned"]')!.textContent)
      .toBe('reading_list,dialer');
    expect(chromeBar.el.querySelector('[data-testid="cb-cap"]')!.textContent).toBe('full');

    act(() => settings.root.unmount());
    act(() => chromeBar.root.unmount());
  });

  it('event payloads are sanitized: unknown ids dropped, list capped', async () => {
    const chromeBar = await mount(<ChromeBarPinHarness />);
    await flush();

    await act(async () => {
      window.dispatchEvent(new CustomEvent(PINNED_TOOLS_CHANGED_EVENT, {
        detail: ['bogus', 'dialer', 'screenshot', 'device_lab'],
      }));
    });

    // Unknown id dropped, then capped at MAX_PINNED_TOOLS.
    expect(chromeBar.el.querySelector('[data-testid="cb-pinned"]')!.textContent)
      .toBe('dialer,screenshot');
    act(() => chromeBar.root.unmount());
  });

  it('toggling in Settings broadcasts to the other surface (and persists)', async () => {
    const settings = await mount(<ToolbarBlock />);
    const chromeBar = await mount(<ChromeBarPinHarness />);
    await flush();

    await act(async () => {
      checkboxFor(settings.el, 'Screenshot').click();
    });
    await flush(); // let the setTimeout(0) event dispatch land

    expect(chromeBar.el.querySelector('[data-testid="cb-pinned"]')!.textContent).toBe('screenshot');
    expect(prefsSet).toHaveBeenCalledWith(PINNED_TOOLS_PREF_KEY, JSON.stringify(['screenshot']));
    act(() => settings.root.unmount());
    act(() => chromeBar.root.unmount());
  });

  it('the other surface cannot push past the cap either', async () => {
    prefStore.set(PINNED_TOOLS_PREF_KEY, JSON.stringify(['dialer', 'screenshot']));
    const settings = await mount(<ToolbarBlock />);
    const chromeBar = await mount(<ChromeBarPinHarness />);
    await flush();

    prefsSet.mockClear();
    await act(async () => {
      (chromeBar.el.querySelector('[data-testid="cb-pin-device_lab"]') as HTMLButtonElement).click();
    });
    await flush();

    expect(chromeBar.el.querySelector('[data-testid="cb-pinned"]')!.textContent)
      .toBe('dialer,screenshot');
    expect(checkboxFor(settings.el, 'Device Lab').checked).toBe(false);
    expect(prefsSet).not.toHaveBeenCalled();
    act(() => settings.root.unmount());
    act(() => chromeBar.root.unmount());
  });
});
