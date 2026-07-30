// @vitest-environment jsdom
/**
 * Guards the omnibox suggestion-acceptance ordering contract:
 *
 * The suggestions dropdown accepts clicks via `onMouseDown` (with
 * preventDefault) specifically so the click lands BEFORE the input's blur
 * closes the dropdown. If a refactor swapped it to `onClick`, blur would
 * unmount the row first and clicking a suggestion would silently do nothing.
 *
 * These tests render the REAL ChromeBar with a mocked window.zio and an
 * IPC/initTabs-driven tab store, type a query, wait for the mocked
 * history/bookmark suggestions, then:
 *   - dispatch mousedown (never click) on a suggestion row and assert
 *     navigate() is called with the suggestion URL and the dropdown closes
 *     even when the input's blur fires right after (real event order)
 *   - drive ArrowDown/ArrowUp + Enter and assert the highlighted suggestion
 *     is accepted
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act, useEffect } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ChromeBar } from '../src/renderer/components/ChromeBar';
import { useTabStore } from '../src/renderer/store/tab-store';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

const TAB_ID = 'tab-1';
const CURRENT_URL = 'https://current.example/page';
const BOOKMARK_URL = 'https://bookmark.example/saved';
const HISTORY_URL = 'https://history.example/visited';

let navigateSpy: ReturnType<typeof vi.fn>;
let ipcHandlers: Map<string, Set<(...args: unknown[]) => void>>;

function buildZioMock() {
  navigateSpy = vi.fn(() => Promise.resolve());
  ipcHandlers = new Map();
  const resolved = <T,>(v: T) => vi.fn(() => Promise.resolve(v));
  return {
    platform: 'linux',
    on: (channel: string, fn: (...args: unknown[]) => void) => {
      if (!ipcHandlers.has(channel)) ipcHandlers.set(channel, new Set());
      ipcHandlers.get(channel)!.add(fn);
    },
    off: (channel: string, fn: (...args: unknown[]) => void) => {
      ipcHandlers.get(channel)?.delete(fn);
    },
    tabs: {
      getOrder: resolved([TAB_ID]),
      getActive: resolved(TAB_ID),
      getState: resolved({
        url: CURRENT_URL, title: 'Current Page', isLoading: false, pinned: false,
      }),
      recentlyClosed: resolved([]),
      navigate: navigateSpy,
      create: resolved(null),
      close: resolved(undefined),
      activate: resolved(undefined),
      back: resolved(undefined),
      forward: resolved(undefined),
      reload: resolved(undefined),
      stop: resolved(undefined),
      pin: resolved(undefined),
      mute: resolved(undefined),
      muteAll: resolved(undefined),
      move: resolved(undefined),
      duplicate: resolved(null),
      closeOthers: resolved(undefined),
      closeToRight: resolved(undefined),
      setMode: resolved(undefined),
      reopenClosed: resolved(null),
      reopenFromRecent: resolved(null),
    },
    history: {
      search: vi.fn(() => Promise.resolve([
        { url: HISTORY_URL, title: 'History Result' },
      ])),
      record: resolved(undefined),
    },
    bookmarks: {
      search: vi.fn(() => Promise.resolve([
        { url: BOOKMARK_URL, title: 'Bookmark Result' },
      ])),
      isBookmarked: resolved(false),
      add: resolved(undefined),
      remove: resolved(undefined),
    },
    readingList: {
      isSaved: resolved(false),
      unreadCount: resolved(0),
      add: resolved(undefined),
    },
    sync: {
      pendingCount: resolved(0),
      pendingByProfile: resolved([]),
    },
    tracker: {
      isEnabled: resolved(false),
      getCount: resolved(0),
    },
    audio: {
      getMuteAll: resolved(false),
    },
    window: {
      setChromeOverlay: resolved(undefined),
      openPrivate: resolved(undefined),
    },
    prefs: {
      get: resolved(null),
      set: resolved(undefined),
      all: resolved({}),
    },
    profiles: {
      list: resolved([]),
      getActive: resolved(null),
      switch: resolved(undefined),
      warmSession: resolved(undefined),
    },
    auth: {
      getToken: resolved(null),
      getUser: resolved(null),
    },
  };
}

/** Boots the singleton tab store (getOrder/getActive/getState) like App does. */
function TabStoreBoot() {
  const { initTabs } = useTabStore();
  useEffect(() => { void initTabs(); }, [initTabs]);
  return null;
}

function Harness() {
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

function omnibox(): HTMLInputElement {
  const input = container.querySelector(
    'input[placeholder="Search or enter URL"]',
  ) as HTMLInputElement | null;
  expect(input, 'omnibox input').toBeTruthy();
  return input!;
}

function suggestionRows(): HTMLElement[] {
  // Suggestion rows are the only mousedown-handling divs whose text carries
  // our mocked result titles; match on content to stay resilient to styling.
  return Array.from(container.querySelectorAll('div')).filter(d =>
    d.childElementCount > 0 &&
    (d.textContent === null ? false : /Result|Search for/.test(d.textContent)) &&
    d.style.cursor === 'pointer',
  ) as HTMLElement[];
}

function rowFor(title: string): HTMLElement {
  const row = suggestionRows().find(r => r.textContent?.includes(title));
  expect(row, `suggestion row "${title}"`).toBeTruthy();
  return row!;
}

/** Native value set + input event so React's onChange fires. */
function typeInOmnibox(value: string): void {
  const input = omnibox();
  const setter = Object.getOwnPropertyDescriptor(
    window.HTMLInputElement.prototype, 'value',
  )!.set!;
  act(() => {
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
}

async function openSuggestions(query: string): Promise<void> {
  act(() => { omnibox().focus(); });
  typeInOmnibox(query);
  // Past the 120ms debounce + the search promises.
  await flush(180);
  expect(rowFor('Bookmark Result')).toBeTruthy();
  expect(rowFor('History Result')).toBeTruthy();
}

function keydown(key: string): void {
  act(() => {
    omnibox().dispatchEvent(new KeyboardEvent('keydown', {
      key, bubbles: true, cancelable: true,
    }));
  });
}

beforeEach(async () => {
  vi.restoreAllMocks();
  (window as unknown as Record<string, unknown>).zio = buildZioMock();
  container = document.createElement('div');
  document.body.appendChild(container);
  await act(async () => {
    root = createRoot(container);
    root.render(<Harness />);
  });
  await flush();
  // Sanity: the store booted with the active tab.
  expect(omnibox().value).toBe(CURRENT_URL);
});

afterEach(() => {
  act(() => root.unmount());
  container.remove();
});

describe('omnibox suggestion click (mousedown-before-blur contract)', () => {
  it('mousedown on a suggestion navigates and closes the dropdown', async () => {
    await openSuggestions('bookm');

    const row = rowFor('Bookmark Result');
    await act(async () => {
      row.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
    });
    await flush();

    expect(navigateSpy).toHaveBeenCalledTimes(1);
    expect(navigateSpy).toHaveBeenCalledWith(TAB_ID, BOOKMARK_URL);
    expect(suggestionRows()).toHaveLength(0);
    // acceptSuggestion blurs the input, releasing omnibox focus.
    expect(document.activeElement).not.toBe(omnibox());
  });

  it('click still lands when blur fires right after mousedown (real event order)', async () => {
    await openSuggestions('hist');

    const row = rowFor('History Result');
    await act(async () => {
      // Browsers fire mousedown on the row, THEN blur on the input. The row
      // handler must have already accepted the suggestion by blur time.
      row.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true }));
      omnibox().blur();
    });
    await flush();

    expect(navigateSpy).toHaveBeenCalledTimes(1);
    expect(navigateSpy).toHaveBeenCalledWith(TAB_ID, HISTORY_URL);
    expect(suggestionRows()).toHaveLength(0);
  });

  it('the row handler is mousedown-based: plain blur alone closes the dropdown without navigating', async () => {
    await openSuggestions('hist');

    await act(async () => {
      omnibox().blur();
    });
    await flush();

    // Dropdown gone, nothing navigated — proving acceptance can ONLY come
    // from the pre-blur mousedown path.
    expect(suggestionRows()).toHaveLength(0);
    expect(navigateSpy).not.toHaveBeenCalled();
  });
});

describe('omnibox arrow-key selection + Enter', () => {
  it('ArrowDown twice + Enter accepts the second suggestion (history row)', async () => {
    await openSuggestions('res');

    // Row order with no Sayzio token: bookmark, history, search fallback.
    keydown('ArrowDown'); // -> index 0 (bookmark)
    keydown('ArrowDown'); // -> index 1 (history)
    keydown('Enter');
    await flush();

    expect(navigateSpy).toHaveBeenCalledTimes(1);
    expect(navigateSpy).toHaveBeenCalledWith(TAB_ID, HISTORY_URL);
    expect(suggestionRows()).toHaveLength(0);
  });

  it('ArrowUp wraps to the last row (search fallback) and Enter accepts it', async () => {
    await openSuggestions('res');

    keydown('ArrowUp'); // wraps from -1/0 to the last row: Search for "res"
    keydown('Enter');
    await flush();

    expect(navigateSpy).toHaveBeenCalledTimes(1);
    // The search fallback row navigates with the raw query.
    expect(navigateSpy).toHaveBeenCalledWith(TAB_ID, 'res');
    expect(suggestionRows()).toHaveLength(0);
  });

  it('Enter with no highlighted suggestion does not accept a row', async () => {
    await openSuggestions('res');

    keydown('Enter'); // suggestionIndex is -1 → falls through to form submit
    await flush();

    // No suggestion was accepted (the form submit path uses the typed value,
    // and jsdom does not run requestSubmit form handlers here).
    expect(navigateSpy).not.toHaveBeenCalledWith(TAB_ID, BOOKMARK_URL);
    expect(navigateSpy).not.toHaveBeenCalledWith(TAB_ID, HISTORY_URL);
  });
});
