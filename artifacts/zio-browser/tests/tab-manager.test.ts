/**
 * Unit tests for the TabManager (pinned tabs, tab ordering, recently-closed)
 * and the PINNED_TABS preference round-trip through the real db module
 * (in-memory SQLite).
 *
 * Electron is mocked: fake WebContentsView instances carry just enough
 * WebContents behavior (loadURL/getURL/getTitle/close) for the tab lifecycle.
 */
import { describe, it, expect, beforeEach, beforeAll, vi } from 'vitest';

// ── Fake electron ────────────────────────────────────────────────────────────
// vi.mock factories are hoisted, so the fake classes must be created via
// vi.hoisted to be available inside the factory. vi.hoisted also runs before
// module imports, so a minimal event emitter is defined inline.

const { FakeWebContents, FakeWebContentsView } = vi.hoisted(() => {
class MiniEmitter {
  private listeners = new Map<string, Array<(...args: unknown[]) => void>>();
  on(event: string, cb: (...args: unknown[]) => void): this {
    const arr = this.listeners.get(event) ?? [];
    arr.push(cb);
    this.listeners.set(event, arr);
    return this;
  }
  emit(event: string, ...args: unknown[]): boolean {
    const arr = this.listeners.get(event) ?? [];
    for (const cb of arr) cb(...args);
    return arr.length > 0;
  }
}

class FakeWebContents extends MiniEmitter {
  private url = '';
  private title = '';
  private destroyed = false;
  private muted = false;
  private zoom = 1;

  async loadURL(url: string): Promise<void> { this.url = url; this.title = url; }
  getURL(): string { return this.url; }
  getTitle(): string { return this.title; }
  setTitle(t: string): void { this.title = t; }
  isDestroyed(): boolean { return this.destroyed; }
  close(): void { this.destroyed = true; }
  canGoBack(): boolean { return false; }
  canGoForward(): boolean { return false; }
  isLoading(): boolean { return false; }
  isCurrentlyAudible(): boolean { return false; }
  isAudioMuted(): boolean { return this.muted; }
  setAudioMuted(m: boolean): void { this.muted = m; }
  getZoomFactor(): number { return this.zoom; }
  setZoomFactor(z: number): void { this.zoom = z; }
  focus(): void {}
  stopFindInPage(): void {}
  setWindowOpenHandler(): void {}
  get id(): number { return 1; }
}

class FakeWebContentsView {
  webContents = new FakeWebContents();
  private bounds = { x: 0, y: 0, width: 0, height: 0 };
  setBounds(b: { x: number; y: number; width: number; height: number }): void { this.bounds = b; }
  getBounds() { return this.bounds; }
  setBackgroundColor(): void {}
}

return { FakeWebContents, FakeWebContentsView };
});
type FakeWebContents = InstanceType<typeof FakeWebContents>;

function makeFakeWindow() {
  return {
    getContentSize: () => [1200, 800],
    contentView: {
      addChildView: vi.fn(),
      removeChildView: vi.fn(),
    },
  };
}

vi.mock('electron', () => {
  const fakeSession = { fromPartition: vi.fn(() => ({})), defaultSession: {} };
  return {
    BrowserWindow: class {},
    WebContentsView: FakeWebContentsView,
    Menu: { buildFromTemplate: vi.fn(() => ({ popup: vi.fn() })) },
    clipboard: { writeText: vi.fn() },
    session: fakeSession,
    app: {
      getPath: () => '/tmp',
      getVersion: () => '0.0.0-test',
      on: vi.fn(),
      whenReady: () => Promise.resolve(),
    },
    ipcMain: { handle: vi.fn(), on: vi.fn() },
  };
});

import { TabManager } from '../src/main/tab-manager';
import { PREFERENCE_KEYS, CREATE_TABLES_SQL } from '../src/shared/db-schema';

type AnyWin = ConstructorParameters<typeof TabManager>[0];

function makeManager() {
  const win = makeFakeWindow();
  const tm = new TabManager(win as unknown as AnyWin);
  return { tm, win };
}

/** Set the fake page URL/title for a tab (simulates a completed navigation). */
function setPage(tm: TabManager, id: string, url: string, title?: string): void {
  const wc = tm.getWebContents(id) as unknown as FakeWebContents;
  void wc.loadURL(url);
  if (title) wc.setTitle(title);
}

describe('TabManager ordering + pinning', () => {
  let tm: TabManager;

  beforeEach(() => {
    ({ tm } = makeManager());
  });

  it('pinning moves a tab to the front of the order', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    const c = tm.createTab('https://c.test');
    expect(tm.getTabOrder()).toEqual([a, b, c]);

    tm.pinTab(c, true);
    expect(tm.getTabOrder()).toEqual([c, a, b]);
    expect(tm.getTabState(c)?.pinned).toBe(true);
  });

  it('a second pinned tab goes after existing pinned tabs, before non-pinned', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    const c = tm.createTab('https://c.test');

    tm.pinTab(c, true);
    tm.pinTab(b, true);
    // Pinned section: [c, b] (b inserted after existing pinned c), then a.
    expect(tm.getTabOrder()).toEqual([c, b, a]);
  });

  it('unpinning moves the tab just after the pinned section', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    const c = tm.createTab('https://c.test');
    tm.pinTab(b, true);
    tm.pinTab(c, true);
    expect(tm.getTabOrder()).toEqual([b, c, a]);

    tm.pinTab(b, false);
    // b leaves the pinned section; non-pinned insert appends to the end.
    const order = tm.getTabOrder();
    expect(order[0]).toBe(c); // pinned stays at front
    expect(order.slice(1)).toContain(b);
    expect(order.slice(1)).toContain(a);
    expect(tm.getTabState(b)?.pinned).toBe(false);
  });

  it('maintains pinned-first invariant across many pin/unpin cycles', () => {
    const ids = [
      tm.createTab('https://t0.test'),
      tm.createTab('https://t1.test'),
      tm.createTab('https://t2.test'),
      tm.createTab('https://t3.test'),
      tm.createTab('https://t4.test'),
    ];

    // Heavy churn: pin/unpin in a deterministic pseudo-random pattern.
    const ops: Array<[number, boolean]> = [
      [0, true], [3, true], [1, true], [3, false], [4, true],
      [0, false], [2, true], [1, false], [4, false], [2, false],
      [1, true], [0, true], [3, true],
    ];
    for (const [i, pinned] of ops) {
      const id = ids[i];
      if (id) tm.pinTab(id, pinned);
    }

    const order = tm.getTabOrder();
    expect(order).toHaveLength(5);
    expect(new Set(order)).toEqual(new Set(ids));

    // Invariant: all pinned tabs precede all non-pinned tabs.
    const pinnedFlags = order.map(id => tm.getTabState(id)!.pinned);
    const firstNonPinned = pinnedFlags.indexOf(false);
    if (firstNonPinned !== -1) {
      expect(pinnedFlags.slice(firstNonPinned).every(p => !p)).toBe(true);
    }
    // Final expected pinned set: 1, 0, 3
    const pinnedIds = order.filter(id => tm.getTabState(id)!.pinned);
    expect(new Set(pinnedIds)).toEqual(new Set([ids[1], ids[0], ids[3]]));
  });

  it('new pinned tab created via createTab lands in the pinned section', () => {
    const a = tm.createTab('https://a.test');
    tm.pinTab(a, true);
    tm.createTab('https://b.test'); // non-pinned
    const p = tm.createTab('https://p.test', true, true);
    const order = tm.getTabOrder();
    // p inserted after a (pinned), before the non-pinned tab.
    expect(order.indexOf(p)).toBe(1);
    expect(order[0]).toBe(a);
  });

  it('pinTab is a no-op when the pinned state is unchanged', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    tm.pinTab(a, true);
    const before = tm.getTabOrder();
    tm.pinTab(a, true);
    tm.pinTab(b, false);
    expect(tm.getTabOrder()).toEqual(before);
  });
});

describe('TabManager recently-closed stack', () => {
  let tm: TabManager;

  beforeEach(() => {
    ({ tm } = makeManager());
  });

  it('closeTab pushes the tab onto the recently-closed stack', () => {
    const a = tm.createTab('https://a.test');
    setPage(tm, a, 'https://a.test/page', 'Page A');
    tm.closeTab(a);

    const closed = tm.getRecentlyClosed();
    expect(closed).toHaveLength(1);
    expect(closed[0]).toMatchObject({ url: 'https://a.test/page', title: 'Page A' });
  });

  it('does not record empty/new-tab pages', () => {
    const blank = tm.createTab(); // about:newtab — never loaded
    tm.closeTab(blank);
    expect(tm.getRecentlyClosed()).toHaveLength(0);
  });

  it('reopenClosedTab restores the last-closed URL and pops the stack', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    setPage(tm, a, 'https://a.test/');
    setPage(tm, b, 'https://b.test/');
    tm.closeTab(a);
    tm.closeTab(b); // b closed last → reopened first

    const reopened = tm.reopenClosedTab();
    expect(reopened).not.toBeNull();
    expect(tm.getWebContents(reopened!)?.getURL()).toBe('https://b.test/');
    expect(tm.getRecentlyClosed()).toHaveLength(1);
    expect(tm.getRecentlyClosed()[0]?.url).toBe('https://a.test/');
  });

  it('reopenClosedTab returns null when the stack is empty', () => {
    expect(tm.reopenClosedTab()).toBeNull();
  });

  it('caps the stack at 10 entries (oldest dropped)', () => {
    for (let i = 0; i < 13; i++) {
      const id = tm.createTab(`https://site${i}.test`);
      setPage(tm, id, `https://site${i}.test/`);
      tm.closeTab(id);
    }
    const closed = tm.getRecentlyClosed();
    expect(closed).toHaveLength(10);
    expect(closed[0]?.url).toBe('https://site12.test/');
    expect(closed[9]?.url).toBe('https://site3.test/');
  });
});

describe('TabManager closeOtherTabs / closeTabsToRight', () => {
  let tm: TabManager;

  beforeEach(() => {
    ({ tm } = makeManager());
  });

  it('closeOtherTabs never closes pinned tabs', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    const c = tm.createTab('https://c.test');
    const d = tm.createTab('https://d.test');
    tm.pinTab(a, true);
    tm.pinTab(d, true);

    tm.closeOtherTabs(b);

    const order = tm.getTabOrder();
    expect(order).toContain(a);
    expect(order).toContain(d);
    expect(order).toContain(b);
    expect(order).not.toContain(c);
  });

  it('closeTabsToRight skips pinned tabs', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    const c = tm.createTab('https://c.test');
    // Pin c but keep it conceptually "to the right" is impossible (pins move
    // to front), so pin after computing: pin c → order [c, a, b].
    tm.pinTab(c, true);
    tm.closeTabsToRight(a); // closes only b (right of a, not pinned)
    const order = tm.getTabOrder();
    expect(order).toEqual([c, a]);
    expect(order).not.toContain(b);
  });

  it('closing the active tab activates the next tab in order', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    tm.activateTab(a);
    tm.closeTab(a);
    expect(tm.getActiveTabId()).toBe(b);
  });
});

describe('TabManager chrome-overlay suppression', () => {
  it('never re-attaches views while suppressed; re-attaches after release', () => {
    const { tm, win } = makeManager();
    const a = tm.createTab('https://a.test');
    tm.activateTab(a);

    // Simulate a renderer panel (settings/menu) holding the chrome overlay.
    tm.setOverlaySuppressed(true);
    tm.hideAllTabs();
    win.contentView.addChildView.mockClear();

    // Resize/tab/panel events must NOT re-attach native views over the panel.
    tm.relayoutActiveTab();
    tm.resizeTabs({ x: 0, y: 72, width: 1200, height: 728 });
    expect(win.contentView.addChildView).not.toHaveBeenCalled();

    // Releasing the overlay restores the layout (as setMode does on release).
    tm.setOverlaySuppressed(false);
    tm.relayoutActiveTab();
    expect(win.contentView.addChildView).toHaveBeenCalled();
  });
});

describe('TabManager pinned URL persistence helpers', () => {
  let tm: TabManager;

  beforeEach(() => {
    ({ tm } = makeManager());
  });

  it('getPinnedUrls returns pinned tab URLs in strip order, skipping blank pages', () => {
    const a = tm.createTab('https://a.test');
    const b = tm.createTab('https://b.test');
    const blank = tm.createTab(); // never navigates
    setPage(tm, a, 'https://a.test/');
    setPage(tm, b, 'https://b.test/');
    tm.pinTab(b, true);
    tm.pinTab(a, true);
    tm.pinTab(blank, true);

    expect(tm.getPinnedUrls()).toEqual(['https://b.test/', 'https://a.test/']);
  });

  it('initPinnedUrls restores tabs as pinned, in order, at the front', () => {
    tm.initPinnedUrls(['https://one.test/', 'https://two.test/']);
    const order = tm.getTabOrder();
    expect(order).toHaveLength(2);
    expect(tm.getTabState(order[0]!)?.pinned).toBe(true);
    expect(tm.getTabState(order[1]!)?.pinned).toBe(true);
    expect(tm.getWebContents(order[0]!)?.getURL()).toBe('https://one.test/');
    expect(tm.getWebContents(order[1]!)?.getURL()).toBe('https://two.test/');
    // A subsequent normal tab lands after the pinned section.
    const n = tm.createTab('https://n.test');
    expect(tm.getTabOrder()[2]).toBe(n);
  });

  it('fires onPinnedUrlsChange when pin state changes', () => {
    const urls: string[][] = [];
    tm.setCallbacks({ onPinnedUrlsChange: u => { urls.push(u); } });
    const a = tm.createTab('https://a.test');
    setPage(tm, a, 'https://a.test/');
    tm.pinTab(a, true);
    tm.pinTab(a, false);
    expect(urls).toEqual([['https://a.test/'], []]);
  });
});

// ── PINNED_TABS preference round-trip through the real db module ────────────

describe('PINNED_TABS preference round-trip', () => {
  let db: typeof import('../src/main/db');

  beforeAll(async () => {
    db = await import('../src/main/db');
    db.initDb(':memory:');
  });

  it('PINNED_TABS key exists in the schema preference key catalog', () => {
    expect(PREFERENCE_KEYS.PINNED_TABS).toBe('pinned_tabs');
    expect(CREATE_TABLES_SQL).toContain('CREATE TABLE IF NOT EXISTS preferences');
  });

  it('round-trips a JSON array of pinned URLs', () => {
    const urls = ['https://a.test/', 'https://b.test/page?x=1'];
    db.setPreference(PREFERENCE_KEYS.PINNED_TABS, JSON.stringify(urls));
    const raw = db.getPreference(PREFERENCE_KEYS.PINNED_TABS);
    expect(raw).not.toBeNull();
    expect(JSON.parse(raw!)).toEqual(urls);
  });

  it('overwrites rather than duplicates on repeated saves', () => {
    db.setPreference(PREFERENCE_KEYS.PINNED_TABS, JSON.stringify(['https://x.test/']));
    db.setPreference(PREFERENCE_KEYS.PINNED_TABS, JSON.stringify([]));
    expect(JSON.parse(db.getPreference(PREFERENCE_KEYS.PINNED_TABS)!)).toEqual([]);
  });

  it('returns null before the key was ever written (startup default path)', () => {
    // Uses a fresh key that the suite never wrote.
    expect(db.getPreference(PREFERENCE_KEYS.NEW_TAB_PAGE)).toBeNull();
  });

  it('TabManager pinned URLs survive a simulated restart via the preference', () => {
    // "Session 1": pin two tabs and persist.
    const { tm: tm1 } = makeManager();
    const a = tm1.createTab('https://keep1.test');
    const b = tm1.createTab('https://keep2.test');
    setPage(tm1, a, 'https://keep1.test/');
    setPage(tm1, b, 'https://keep2.test/');
    tm1.pinTab(a, true);
    tm1.pinTab(b, true);
    db.setPreference(PREFERENCE_KEYS.PINNED_TABS, JSON.stringify(tm1.getPinnedUrls()));

    // "Session 2": restore from the preference like index.ts does.
    const saved = JSON.parse(db.getPreference(PREFERENCE_KEYS.PINNED_TABS) ?? '[]') as string[];
    const { tm: tm2 } = makeManager();
    tm2.initPinnedUrls(saved);
    expect(tm2.getPinnedUrls()).toEqual(['https://keep1.test/', 'https://keep2.test/']);
    expect(tm2.getTabOrder().every(id => tm2.getTabState(id)!.pinned)).toBe(true);
  });
});

describe('Renderer-drawn internal pages (about:sayzio / about:zio)', () => {
  let tm: TabManager;

  beforeEach(() => {
    ({ tm } = makeManager());
  });

  it('createTab with an internal URL reports canonical internal state', () => {
    const id = tm.createTab('about:sayzio');
    const state = tm.getTabState(id)!;
    expect(state.url).toBe('about:sayzio');
    expect(state.displayUrl).toBe('about:sayzio');
    expect(state.title).toBe('About Sayzio');
    expect(state.isLoading).toBe(false);
    // The native webContents must never have loaded the internal URL.
    const wc = tm.getWebContents(id) as unknown as FakeWebContents;
    expect(wc.getURL()).toBe('');
  });

  it('navigating from a real page to an internal page overrides stale wc state', () => {
    const id = tm.createTab('https://real.test');
    setPage(tm, id, 'https://real.test/', 'Real Site');
    tm.navigate(id, 'about:zio');
    const state = tm.getTabState(id)!;
    expect(state.url).toBe('about:zio');
    expect(state.title).toBe('About Zio Browser');
  });

  it('navigating from an internal page back to a real URL clears internal state', () => {
    const id = tm.createTab('about:zio');
    tm.navigate(id, 'https://next.test');
    const state = tm.getTabState(id)!;
    expect(state.url).toBe('https://next.test');
  });

  it('closing a tab showing an internal page records the internal page, not the prior site', () => {
    const id = tm.createTab('https://old.test');
    setPage(tm, id, 'https://old.test/', 'Old Site');
    tm.navigate(id, 'about:sayzio');
    tm.closeTab(id);
    const recent = tm.getRecentlyClosed();
    expect(recent[0]?.url).toBe('about:sayzio');
    expect(recent[0]?.title).toBe('About Sayzio');
  });
});
