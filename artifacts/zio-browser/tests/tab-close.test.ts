/**
 * Unit tests for closeTab's interaction with tab ordering in TabManager:
 * neighbor activation (Math.min(idx, len-1)), pinned-set cleanup,
 * recently-closed stack behavior (cap + skip of empty/new-tab pages), and
 * closeOtherTabs/closeTabsToRight never closing pinned tabs. Uses the real
 * TabManager with a mocked `electron` module (same pattern as
 * tab-reorder.test.ts).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('electron', async () => {
  const { EventEmitter } = await import('events');
  class FakeWebContents extends EventEmitter {
    url = '';
    destroyed = false;
    setWindowOpenHandler(): void {}
    loadURL = vi.fn(async (u: string) => { this.url = u; });
    getURL(): string { return this.url; }
    getTitle(): string { return 'title'; }
    isDestroyed(): boolean { return this.destroyed; }
    close(): void { this.destroyed = true; }
    focus(): void {}
    stopFindInPage(): void {}
    canGoBack(): boolean { return false; }
    canGoForward(): boolean { return false; }
    isLoading(): boolean { return false; }
    isCurrentlyAudible(): boolean { return false; }
    isAudioMuted(): boolean { return false; }
    getZoomFactor(): number { return 1; }
    setZoomFactor(): void {}
    setAudioMuted(): void {}
  }

  class FakeWebContentsView {
    webContents = new FakeWebContents();
    setBounds(): void {}
    setBackgroundColor(): void {}
  }

  const fakeSession = { fromPartition: () => ({}), defaultSession: {} };

  return {
    WebContentsView: FakeWebContentsView,
    BrowserWindow: class {},
    Menu: { buildFromTemplate: () => ({ popup: () => {} }) },
    clipboard: { writeText: () => {} },
    session: fakeSession,
  };
});

import { TabManager } from '../src/main/tab-manager';

function makeFakeWindow() {
  return {
    getContentSize: () => [1024, 768] as [number, number],
    contentView: {
      addChildView: vi.fn(),
      removeChildView: vi.fn(),
    },
  } as unknown as import('electron').BrowserWindow;
}

function makeManager() {
  return new TabManager(makeFakeWindow());
}

/** Create a tab in the background with a real-looking URL. */
function addTab(tm: TabManager, url: string, pinned = false): string {
  return tm.createTab(url, true, pinned);
}

describe('TabManager closeTab', () => {
  let tm: TabManager;

  beforeEach(() => {
    tm = makeManager();
  });

  describe('neighbor activation', () => {
    it('activates the tab that slides into the closed slot when closing an active middle tab', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      const c = addTab(tm, 'https://c.test');
      tm.activateTab(b);
      tm.closeTab(b);
      expect(tm.getTabOrder()).toEqual([a, c]);
      expect(tm.getActiveTabId()).toBe(c);
    });

    it('activates the new last tab when closing the active last tab', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.activateTab(b);
      tm.closeTab(b);
      expect(tm.getActiveTabId()).toBe(a);
    });

    it('activates the new first tab when closing the active first tab', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.activateTab(a);
      tm.closeTab(a);
      expect(tm.getActiveTabId()).toBe(b);
    });

    it('keeps the active tab unchanged when closing an inactive tab', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.activateTab(a);
      tm.closeTab(b);
      expect(tm.getActiveTabId()).toBe(a);
    });

    it('sets the active tab to null when the last remaining tab closes', () => {
      const a = addTab(tm, 'https://a.test');
      tm.closeTab(a);
      expect(tm.getTabOrder()).toEqual([]);
      expect(tm.getActiveTabId()).toBeNull();
    });

    it('ignores unknown tab ids', () => {
      const a = addTab(tm, 'https://a.test');
      tm.closeTab('nope');
      expect(tm.getTabOrder()).toEqual([a]);
    });
  });

  describe('pinned-set cleanup', () => {
    it('removes a closed pinned tab from the pinned set and order', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const n1 = addTab(tm, 'https://a.test');
      tm.closeTab(p1);
      expect(tm.getTabOrder()).toEqual([p2, n1]);
      expect(tm.getPinnedUrls()).toEqual(['https://p2.test']);
    });

    it('shrinks the pinned section boundary used by moveTab after a pinned close', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const n1 = addTab(tm, 'https://a.test');
      const n2 = addTab(tm, 'https://b.test');
      tm.closeTab(p1);
      // Only one pinned tab left — a normal tab may now move to index 1.
      tm.moveTab(n2, 1);
      expect(tm.getTabOrder()).toEqual([p2, n2, n1]);
      // ...but still not into the pinned slot at index 0.
      tm.moveTab(n2, 0);
      expect(tm.getTabOrder()).toEqual([p2, n2, n1]);
    });

    it('fires onTabClosed for a closed pinned tab', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const onTabClosed = vi.fn();
      tm.setCallbacks({ onTabClosed });
      tm.closeTab(p1);
      expect(onTabClosed).toHaveBeenCalledWith(p1);
    });
  });

  describe('recently-closed stack', () => {
    it('records closed tabs most-recent-first', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.closeTab(a);
      tm.closeTab(b);
      expect(tm.getRecentlyClosed().map(e => e.url)).toEqual([
        'https://b.test',
        'https://a.test',
      ]);
    });

    it('skips empty/new-tab pages', () => {
      const blank = tm.createTab(undefined, true);
      tm.closeTab(blank);
      expect(tm.getRecentlyClosed()).toEqual([]);
    });

    it('caps the stack at 10 entries, dropping the oldest', () => {
      const ids: string[] = [];
      for (let i = 0; i < 12; i++) {
        ids.push(addTab(tm, `https://t${i}.test`));
      }
      for (const id of ids) tm.closeTab(id);
      const urls = tm.getRecentlyClosed().map(e => e.url);
      expect(urls).toHaveLength(10);
      expect(urls[0]).toBe('https://t11.test');
      expect(urls[9]).toBe('https://t2.test');
    });

    it('reopenClosedTab pops the most recent entry and recreates the tab', () => {
      const a = addTab(tm, 'https://a.test');
      tm.closeTab(a);
      const reopened = tm.reopenClosedTab();
      expect(reopened).not.toBeNull();
      expect(tm.getRecentlyClosed()).toEqual([]);
      expect(tm.getTabOrder()).toContain(reopened);
    });
  });

  describe('closeOtherTabs', () => {
    it('closes every other normal tab but never pinned tabs', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      const c = addTab(tm, 'https://c.test');
      tm.closeOtherTabs(b);
      expect(tm.getTabOrder()).toEqual([p1, p2, b]);
      expect(tm.getPinnedUrls()).toEqual(['https://p1.test', 'https://p2.test']);
      void a; void c;
    });

    it('keeps a pinned target and all other pinned tabs', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      addTab(tm, 'https://a.test');
      tm.closeOtherTabs(p1);
      expect(tm.getTabOrder()).toEqual([p1, p2]);
    });
  });

  describe('closeTabsToRight', () => {
    it('closes normal tabs to the right but skips pinned tabs', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      const c = addTab(tm, 'https://c.test');
      tm.closeTabsToRight(a);
      expect(tm.getTabOrder()).toEqual([p1, a]);
      void b; void c;
    });

    it('never closes a pinned tab even when it is to the right in tabOrder', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.pinTab(b, true); // order: [b, a] — b pinned at front
      const c = addTab(tm, 'https://c.test'); // order: [b, a, c]
      tm.closeTabsToRight(b);
      expect(tm.getTabOrder()).toEqual([b]);
      expect(tm.getPinnedUrls()).toEqual(['https://b.test']);
      void a; void c;
    });

    it('is a no-op for an unknown tab id', () => {
      const a = addTab(tm, 'https://a.test');
      tm.closeTabsToRight('nope');
      expect(tm.getTabOrder()).toEqual([a]);
    });
  });
});
