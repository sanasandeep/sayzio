// @vitest-environment jsdom
/**
 * Renderer-level coverage for the notes-count badge on the ChromeBar's
 * pinned Notes tool (data-testid="notes-count-badge"):
 *
 *  - hidden when the host has 0 notes
 *  - shows the count for the active tab's host
 *  - caps the display at "99+"
 *  - hidden (and never queried) in private windows and on about:newtab
 *  - refetches when the tab navigates to a different page
 *  - refetches when the notes panel closes (notesPanelOpen true→false in
 *    ChromeBar's count effect) — the path that keeps the badge accurate
 *    right after the user adds or deletes a note in the panel.
 *
 * The REAL ChromeBar is rendered against a full window.zio mock; the tab
 * store is seeded through its own initTabs() path and navigations are
 * driven through the same 'tab:navigated' IPC event main would emit.
 */
import { describe, it, expect, beforeEach, vi } from 'vitest';
import React, { act, useEffect } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ChromeBar } from '../src/renderer/components/ChromeBar';
import { useTabStore } from '../src/renderer/store/tab-store';
import { PINNED_TOOLS_PREF_KEY } from '../src/shared/toolbar-pins';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

// ── window.zio mock ──────────────────────────────────────────────────────────
// The tab store wires its IPC listeners exactly once per module load
// (wireIpc's ipcWired guard), so the handler registry must be shared across
// every per-test mock instance: `on` always writes into this one map.
type Handler = (...args: unknown[]) => void;
const ipcHandlers = new Map<string, Set<Handler>>();

function emit(event: string, ...args: unknown[]): void {
  for (const h of ipcHandlers.get(event) ?? []) h(...args);
}

let countForHost: ReturnType<typeof vi.fn>;
let tabState: { url: string; title: string };

function buildZioMock() {
  return {
    platform: 'linux',
    on: vi.fn((event: string, handler: Handler) => {
      if (!ipcHandlers.has(event)) ipcHandlers.set(event, new Set());
      ipcHandlers.get(event)!.add(handler);
    }),
    off: vi.fn((event: string, handler: Handler) => {
      ipcHandlers.get(event)?.delete(handler);
    }),
    window: { setChromeOverlay: vi.fn(() => Promise.resolve()) },
    tabs: {
      getOrder: vi.fn(() => Promise.resolve(['tab-1'])),
      getActive: vi.fn(() => Promise.resolve('tab-1')),
      getState: vi.fn(() => Promise.resolve({ ...tabState, isLoading: false, pinned: false })),
      recentlyClosed: vi.fn(() => Promise.resolve([])),
      navigate: vi.fn(() => Promise.resolve()),
    },
    audio: { getMuteAll: vi.fn(() => Promise.resolve(false)) },
    history: {
      search: vi.fn(() => Promise.resolve([])),
      record: vi.fn(() => Promise.resolve()),
    },
    bookmarks: {
      search: vi.fn(() => Promise.resolve([])),
      isBookmarked: vi.fn(() => Promise.resolve(false)),
    },
    readingList: {
      isSaved: vi.fn(() => Promise.resolve(false)),
      unreadCount: vi.fn(() => Promise.resolve(0)),
    },
    notes: { countForHost },
    sync: {
      pendingCount: vi.fn(() => Promise.resolve(0)),
      pendingByProfile: vi.fn(() => Promise.resolve([])),
    },
    tracker: {
      isEnabled: vi.fn(() => Promise.resolve(false)),
      getCount: vi.fn(() => Promise.resolve(0)),
    },
    adblock: { getState: vi.fn(() => Promise.resolve(null)) },
    prefs: {
      // Pin the Notes tool so the badge renders on the toolbar itself.
      get: vi.fn((key: string) =>
        Promise.resolve(key === PINNED_TOOLS_PREF_KEY ? JSON.stringify(['notes']) : null)),
      set: vi.fn(() => Promise.resolve()),
    },
    auth: {
      getToken: vi.fn(() => Promise.resolve(null)),
      getUser: vi.fn(() => Promise.resolve(null)),
    },
    profiles: {
      list: vi.fn(() => Promise.resolve([])),
      getActive: vi.fn(() => Promise.resolve('default')),
      switch: vi.fn(() => Promise.resolve()),
      warmSession: vi.fn(() => Promise.resolve()),
    },
  };
}

beforeEach(() => {
  // NOTE: ipcHandlers is intentionally NOT cleared here — the tab store's
  // wireIpc registers its listeners exactly once per module load (first
  // initTabs), and clearing the map would silently disconnect them for
  // every later test. ChromeBar's own listeners deregister via `off` on
  // unmount, so the map stays tidy.
  countForHost = vi.fn(() => Promise.resolve(0));
  tabState = { url: 'https://example.com/page', title: 'Example' };
  (window as unknown as { zio: unknown }).zio = buildZioMock();
});

/** Renders the real ChromeBar after seeding the tab store via initTabs(). */
function Harness({ notesPanelOpen, isPrivate }: { notesPanelOpen: boolean; isPrivate?: boolean }) {
  const { initTabs } = useTabStore();
  useEffect(() => { void initTabs(); }, [initTabs]);
  return (
    <ChromeBar
      zioPanelOpen={false}
      onToggleZio={() => {}}
      onOpenAuth={() => {}}
      onOpenTabSearch={() => {}}
      readingListOpen={false}
      onToggleReadingList={() => {}}
      isPrivate={isPrivate}
      notesPanelOpen={notesPanelOpen}
      onToggleNotes={() => {}}
    />
  );
}

interface Mounted {
  root: Root;
  el: HTMLElement;
  render: (notesPanelOpen: boolean, isPrivate?: boolean) => Promise<void>;
}

async function mount(notesPanelOpen: boolean, isPrivate?: boolean): Promise<Mounted> {
  // The tab store is module-level state that persists across tests; sync it
  // to this test's tab BEFORE the first render so ChromeBar's mount effects
  // never see a stale URL from a previous test. (No-op on the very first
  // test — initTabs seeds it there.)
  emit('tab:navigated', 'tab-1', tabState.url, tabState.title);
  const el = document.createElement('div');
  document.body.appendChild(el);
  const root = createRoot(el);
  const render = async (open: boolean, priv?: boolean) => {
    await act(async () => { root.render(<Harness notesPanelOpen={open} isPrivate={priv} />); });
    await flush();
  };
  await render(notesPanelOpen, isPrivate);
  return { root, el, render };
}

async function flush(): Promise<void> {
  await act(async () => { await new Promise(r => setTimeout(r, 5)); });
}

async function unmount(m: Mounted): Promise<void> {
  await act(async () => { m.root.unmount(); });
  m.el.remove();
}

const badge = (el: HTMLElement) =>
  el.querySelector('[data-testid="notes-count-badge"]') as HTMLElement | null;

describe('notes-count badge — browse states', () => {
  it('is hidden when the host has 0 notes', async () => {
    const m = await mount(false);
    expect(countForHost).toHaveBeenCalledWith('example.com');
    expect(badge(m.el)).toBeNull();
    await unmount(m);
  });

  it('shows the count for the active tab host', async () => {
    countForHost.mockImplementation(() => Promise.resolve(3));
    const m = await mount(false);
    expect(badge(m.el)?.textContent).toBe('3');
    await unmount(m);
  });

  it('caps the display at 99+', async () => {
    countForHost.mockImplementation(() => Promise.resolve(250));
    const m = await mount(false);
    expect(badge(m.el)?.textContent).toBe('99+');
    await unmount(m);
  });

  it('never queries and shows no badge in a private window', async () => {
    countForHost.mockImplementation(() => Promise.resolve(7));
    const m = await mount(false, true);
    expect(countForHost).not.toHaveBeenCalled();
    expect(badge(m.el)).toBeNull();
    await unmount(m);
  });

  it('never queries and shows no badge on about:newtab', async () => {
    tabState = { url: 'about:newtab', title: 'New Tab' };
    countForHost.mockImplementation(() => Promise.resolve(7));
    const m = await mount(false);
    expect(countForHost).not.toHaveBeenCalled();
    expect(badge(m.el)).toBeNull();
    await unmount(m);
  });

  it('refetches for the new host when the tab navigates', async () => {
    countForHost.mockImplementation((host: string) =>
      Promise.resolve(host === 'example.com' ? 2 : 8));
    const m = await mount(false);
    expect(badge(m.el)?.textContent).toBe('2');

    await act(async () => {
      emit('tab:navigated', 'tab-1', 'https://other.example.org/x', 'Other');
    });
    await flush();

    expect(countForHost).toHaveBeenCalledWith('other.example.org');
    expect(badge(m.el)?.textContent).toBe('8');
    await unmount(m);
  });
});

describe('notes-count badge — refresh when the notes panel closes', () => {
  it('re-queries countForHost on notesPanelOpen true→false and updates the badge', async () => {
    // The host starts with 2 notes; while the panel is open the user adds
    // notes, so the store's answer becomes 5.
    let currentCount = 2;
    countForHost.mockImplementation(() => Promise.resolve(currentCount));

    const m = await mount(false);
    expect(badge(m.el)?.textContent).toBe('2');
    const callsAfterMount = countForHost.mock.calls.length;

    // Open the notes panel (the effect keys on notesPanelOpen, so this may
    // refetch too), simulate the user adding notes while it's open, then
    // close it — the close is the edit-refresh path.
    await m.render(true);
    currentCount = 5;
    await m.render(false);

    // The close must have triggered a fresh countForHost read for the host…
    expect(countForHost.mock.calls.length).toBeGreaterThan(callsAfterMount);
    expect(countForHost).toHaveBeenLastCalledWith('example.com');
    // …and the badge now shows the post-edit count.
    expect(badge(m.el)?.textContent).toBe('5');
    await unmount(m);
  });

  it('a delete down to 0 hides the badge after the panel closes', async () => {
    let currentCount = 1;
    countForHost.mockImplementation(() => Promise.resolve(currentCount));

    const m = await mount(false);
    expect(badge(m.el)?.textContent).toBe('1');

    await m.render(true);
    currentCount = 0;
    await m.render(false);

    expect(badge(m.el)).toBeNull();
    await unmount(m);
  });
});
