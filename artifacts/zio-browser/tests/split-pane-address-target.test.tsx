// @vitest-environment jsdom
/**
 * Guards the "address bar always targets the pane you clicked" contract for
 * the Website + Website tab split:
 *
 * ChromeBar (real component, mocked window.zio + IPC-driven tab store):
 *   - the "Left/Right pane" omnibox badge renders ONLY in 'browser+browser'
 *     mode — hidden in 'browser', split-with-zio, and files modes
 *   - the badge label matches tab.focusedPane ('primary' → "Left pane",
 *     'second' → "Right pane"), defaulting to 'primary' when unset
 *   - mousedown on the badge calls window.zio.tabs.focusPane with the
 *     OPPOSITE pane (it's a toggle)
 *
 * PaneFocusFrames (real component App renders over the split):
 *   - only the focused pane's frame carries the "Address bar · Left/Right
 *     pane" tag, and the side matches focusedPane
 *   - clicking a frame calls focusPane with THAT pane
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act, useEffect } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ChromeBar } from '../src/renderer/components/ChromeBar';
import { PaneFocusFrames } from '../src/renderer/components/PaneFocusFrames';
import { useTabStore } from '../src/renderer/store/tab-store';
import { buildZioMock, resolved, type IpcHandlerMap } from './helpers/zio-mock';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

const TAB_ID = 'tab-1';
const CURRENT_URL = 'https://current.example/page';

let focusPaneSpy: ReturnType<typeof vi.fn>;
// The tab store wires its IPC listeners exactly ONCE per process (module-level
// guard), against whatever window.zio exists at first mount — so this handler
// map must persist across tests/mocks or later emits go nowhere.
const ipcHandlers: IpcHandlerMap = new Map();
let tabState: Record<string, unknown>;

function emit(channel: string, ...args: unknown[]): void {
  ipcHandlers.get(channel)?.forEach(fn => fn(...args));
}

function makeZio() {
  focusPaneSpy = vi.fn(() => Promise.resolve());
  return buildZioMock({
    ipcHandlers,
    overrides: {
      tabs: {
        getOrder: resolved([TAB_ID]),
        getActive: resolved(TAB_ID),
        getState: vi.fn(() => Promise.resolve(tabState)),
        focusPane: focusPaneSpy,
      },
    },
  }).zio;
}

/** Boots the singleton tab store (getOrder/getActive/getState) like App does. */
function TabStoreBoot() {
  const { initTabs } = useTabStore();
  useEffect(() => { void initTabs(); }, [initTabs]);
  return null;
}

function ChromeBarHarness() {
  return (
    <>
      <TabStoreBoot />
      <ChromeBar
        zioPanelOpen={false}
        onToggleZio={() => {}}
        onOpenAuth={() => {}}
        onOpenTabSearch={() => {}}
        readingListOpen={false}
        onToggleReadingList={() => {}}
      />
    </>
  );
}

let container: HTMLDivElement;
let root: Root;

async function flush(ms = 10): Promise<void> {
  await act(async () => { await new Promise(r => setTimeout(r, ms)); });
}

async function mount(node: React.ReactElement): Promise<void> {
  container = document.createElement('div');
  document.body.appendChild(container);
  await act(async () => {
    root = createRoot(container);
    root.render(node);
  });
  await flush();
}

afterEach(async () => {
  await act(async () => { root.unmount(); });
  container.remove();
});

/** The split-pane target badge inside the omnibox form (or null). */
function paneBadge(): HTMLButtonElement | null {
  return (Array.from(container.querySelectorAll('button')) as HTMLButtonElement[])
    .find(b => /^(Left|Right) pane$/.test((b.textContent ?? '').replace(/[◧◨]/g, '').trim())) ?? null;
}

/** Push a tab state patch through the same IPC channel main uses. */
async function patchTab(patch: Record<string, unknown>): Promise<void> {
  act(() => { emit('tab:state-changed', TAB_ID, patch); });
  await flush();
}

beforeEach(() => {
  vi.restoreAllMocks();
  tabState = {
    url: CURRENT_URL, title: 'Current Page', isLoading: false, pinned: false,
    mode: 'browser',
  };
  (window as unknown as Record<string, unknown>).zio = makeZio();
});

describe('ChromeBar — split-pane omnibox badge', () => {
  it('is hidden outside browser+browser (browser, browser+zio, files, files+zio)', async () => {
    await mount(<ChromeBarHarness />);
    expect(paneBadge()).toBeNull();

    for (const mode of ['browser+zio', 'files', 'files+zio', 'zio']) {
      await patchTab({ mode });
      expect(paneBadge(), `mode ${mode}`).toBeNull();
    }
  });

  it('appears in browser+browser and defaults to "Left pane" when focusedPane is unset', async () => {
    await mount(<ChromeBarHarness />);
    await patchTab({ mode: 'browser+browser' });
    const badge = paneBadge();
    expect(badge).toBeTruthy();
    expect(badge!.textContent).toContain('Left pane');
    expect(badge!.title).toContain('controls the left pane');
  });

  it('label tracks tab.focusedPane', async () => {
    await mount(<ChromeBarHarness />);
    await patchTab({ mode: 'browser+browser', focusedPane: 'second' });
    expect(paneBadge()!.textContent).toContain('Right pane');
    expect(paneBadge()!.title).toContain('controls the right pane');

    await patchTab({ focusedPane: 'primary' });
    expect(paneBadge()!.textContent).toContain('Left pane');
  });

  it('mousedown toggles: primary → focusPane(tab, "second") and back', async () => {
    await mount(<ChromeBarHarness />);
    await patchTab({ mode: 'browser+browser', focusedPane: 'primary' });

    act(() => {
      paneBadge()!.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    });
    expect(focusPaneSpy).toHaveBeenCalledTimes(1);
    expect(focusPaneSpy).toHaveBeenCalledWith(TAB_ID, 'second');

    // Main confirms the switch; the badge flips and now toggles back.
    await patchTab({ focusedPane: 'second' });
    act(() => {
      paneBadge()!.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    });
    expect(focusPaneSpy).toHaveBeenCalledTimes(2);
    expect(focusPaneSpy).toHaveBeenLastCalledWith(TAB_ID, 'primary');
  });

  it('disappears again when the tab leaves browser+browser', async () => {
    await mount(<ChromeBarHarness />);
    await patchTab({ mode: 'browser+browser' });
    expect(paneBadge()).toBeTruthy();
    await patchTab({ mode: 'browser' });
    expect(paneBadge()).toBeNull();
  });
});

describe('PaneFocusFrames — focused frame tag', () => {
  function frames(): HTMLElement[] {
    return Array.from(container.querySelectorAll('div[title]')) as HTMLElement[];
  }

  it('tags only the focused pane, with the matching side label', async () => {
    await mount(<PaneFocusFrames tabId={TAB_ID} focusedPane="primary" splitRatio={0.5} />);
    const [left, right] = frames();
    expect(frames()).toHaveLength(2);
    expect(left.title).toBe('Address bar controls this pane');
    expect(right.title).toBe('Click to control this pane from the address bar');
    expect(left.textContent).toContain('Address bar · Left pane');
    expect(right.textContent).not.toContain('Address bar');
    expect(container.textContent).not.toContain('Right pane');
  });

  it('moves the tag to the right pane when focusedPane is "second"', async () => {
    await mount(<PaneFocusFrames tabId={TAB_ID} focusedPane="second" splitRatio={0.5} />);
    const [left, right] = frames();
    expect(right.title).toBe('Address bar controls this pane');
    expect(right.textContent).toContain('Address bar · Right pane');
    expect(left.textContent).not.toContain('Address bar');
  });

  it('clicking a frame focuses THAT pane', async () => {
    await mount(<PaneFocusFrames tabId={TAB_ID} focusedPane="primary" splitRatio={0.5} />);
    const [left, right] = frames();
    act(() => { right.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); });
    expect(focusPaneSpy).toHaveBeenLastCalledWith(TAB_ID, 'second');
    act(() => { left.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); });
    expect(focusPaneSpy).toHaveBeenLastCalledWith(TAB_ID, 'primary');
  });
});
