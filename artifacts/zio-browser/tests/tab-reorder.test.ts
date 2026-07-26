/**
 * Unit tests for tab ordering in TabManager: moveTab clamping to the
 * pinned/normal section boundaries, insertInOrder placement, and the
 * pinTab interplay. Uses the real TabManager with a mocked `electron`
 * module (fake BrowserWindow/WebContentsView/session).
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { EventEmitter } from 'events';

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
    setBackgroundColor(): void {}
    webContents = new FakeWebContents();
    setBounds(): void {}
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

/** Create a tab in the background with a real-looking URL so pinned-url persistence works. */
function addTab(tm: TabManager, url: string, pinned = false): string {
  const id = tm.createTab(url, true, pinned);
  return id;
}

describe('TabManager tab ordering', () => {
  let tm: TabManager;

  beforeEach(() => {
    tm = makeManager();
  });

  describe('insertInOrder via createTab', () => {
    it('appends normal tabs at the end and pinned tabs at the end of the pinned section', () => {
      const n1 = addTab(tm, 'https://a.test');
      const n2 = addTab(tm, 'https://b.test');
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const n3 = addTab(tm, 'https://c.test');
      expect(tm.getTabOrder()).toEqual([p1, p2, n1, n2, n3]);
    });

    it('pushes a pinned tab at the end when all tabs are pinned', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      expect(tm.getTabOrder()).toEqual([p1, p2]);
    });
  });

  describe('moveTab — normal tabs', () => {
    it('reorders normal tabs within the normal section', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      const c = addTab(tm, 'https://c.test');
      tm.moveTab(a, 2);
      expect(tm.getTabOrder()).toEqual([b, c, a]);
      tm.moveTab(c, 0);
      expect(tm.getTabOrder()).toEqual([c, b, a]);
    });

    it('clamps a normal tab so it cannot move before the pinned section', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.moveTab(b, 0); // wants front — must clamp to index 2 (after pinned)
      expect(tm.getTabOrder()).toEqual([p1, p2, b, a]);
    });

    it('clamps a normal tab moved past the end to the last index', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.moveTab(a, 99);
      expect(tm.getTabOrder()).toEqual([b, a]);
    });

    it('fires onTabOrderChange but not onPinnedUrlsChange for normal moves', () => {
      const a = addTab(tm, 'https://a.test');
      addTab(tm, 'https://b.test');
      const onTabOrderChange = vi.fn();
      const onPinnedUrlsChange = vi.fn();
      tm.setCallbacks({ onTabOrderChange, onPinnedUrlsChange });
      tm.moveTab(a, 1);
      expect(onTabOrderChange).toHaveBeenCalledTimes(1);
      expect(onPinnedUrlsChange).not.toHaveBeenCalled();
    });
  });

  describe('moveTab — pinned tabs', () => {
    it('reorders pinned tabs within the pinned section', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const p3 = addTab(tm, 'https://p3.test', true);
      const n1 = addTab(tm, 'https://a.test');
      tm.moveTab(p1, 2);
      expect(tm.getTabOrder()).toEqual([p2, p3, p1, n1]);
    });

    it('clamps a pinned tab so it cannot move past the pinned section', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const n1 = addTab(tm, 'https://a.test');
      const n2 = addTab(tm, 'https://b.test');
      tm.moveTab(p1, 3); // wants into normal section — clamps to index 1
      expect(tm.getTabOrder()).toEqual([p2, p1, n1, n2]);
      // pinned set integrity: pinned tabs still occupy the front
      tm.moveTab(p1, 99);
      expect(tm.getTabOrder()).toEqual([p2, p1, n1, n2]);
    });

    it('clamps a pinned tab moved to a negative index to the front', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      tm.moveTab(p2, -5);
      expect(tm.getTabOrder()).toEqual([p2, p1]);
    });

    it('fires onPinnedUrlsChange when a pinned tab moves', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      addTab(tm, 'https://p2.test', true);
      const onPinnedUrlsChange = vi.fn();
      tm.setCallbacks({ onPinnedUrlsChange });
      tm.moveTab(p1, 1);
      expect(onPinnedUrlsChange).toHaveBeenCalledTimes(1);
      expect(onPinnedUrlsChange).toHaveBeenCalledWith(['https://p2.test', 'https://p1.test']);
    });
  });

  describe('moveTab — no-op and invalid input', () => {
    it('ignores unknown tab ids', () => {
      const a = addTab(tm, 'https://a.test');
      tm.moveTab('nope', 0);
      expect(tm.getTabOrder()).toEqual([a]);
    });

    it('does not fire callbacks when the clamped index equals the current index', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      const onTabOrderChange = vi.fn();
      tm.setCallbacks({ onTabOrderChange });
      tm.moveTab(b, 1); // already at 1
      tm.moveTab(b, 99); // clamps back to 1
      expect(onTabOrderChange).not.toHaveBeenCalled();
      expect(tm.getTabOrder()).toEqual([a, b]);
    });

    it('truncates fractional indices', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.moveTab(a, 1.9); // trunc → 1
      expect(tm.getTabOrder()).toEqual([b, a]);
    });
  });

  describe('pinTab interplay', () => {
    it('moves a newly pinned tab to the end of the pinned section', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.pinTab(b, true);
      expect(tm.getTabOrder()).toEqual([p1, b, a]);
    });

    it('moves an unpinned tab to the end of the strip', () => {
      const p1 = addTab(tm, 'https://p1.test', true);
      const p2 = addTab(tm, 'https://p2.test', true);
      const a = addTab(tm, 'https://a.test');
      tm.pinTab(p1, false);
      expect(tm.getTabOrder()).toEqual([p2, a, p1]);
    });

    it('respects the new section boundary in moveTab after pin/unpin', () => {
      const a = addTab(tm, 'https://a.test');
      const b = addTab(tm, 'https://b.test');
      tm.pinTab(a, true); // order: [a, b]
      tm.moveTab(b, 0); // b is normal, cannot enter pinned section
      expect(tm.getTabOrder()).toEqual([a, b]);
      tm.pinTab(a, false); // order: [b, a]
      tm.moveTab(a, 0); // no pinned tabs left — free move
      expect(tm.getTabOrder()).toEqual([a, b]);
    });
  });
});
