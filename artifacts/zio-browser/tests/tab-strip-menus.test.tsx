// @vitest-environment jsdom
/**
 * Guards the fixed-position contract for dropdowns anchored inside the
 * browser-mode tab strip / chrome toolbar.
 *
 * The tab strip scrolls horizontally (`overflow-x: auto; overflow-y: hidden`),
 * which clips absolutely-positioned descendants to the row height — this made
 * the account menu invisible until it moved to `position: fixed`. The
 * ProfileSwitcher ("Personal" chip) popover and the TabModeSwitcher dropdown
 * follow the same pattern; these tests fail if either regresses to
 * `position: absolute`, and verify the chrome overlay is engaged while open
 * (native WebContentsViews would otherwise cover/swallow the menu).
 *
 * The tab-strip "⋮" menu (StripMenu) and tab context menu in ChromeBar.tsx
 * already render `position: fixed` inline; they are covered by the audit but
 * not unit-tested here because they are private to ChromeBar.
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ProfileSwitcher } from '../src/renderer/components/ProfileSwitcher';
import { TabModeSwitcher } from '../src/renderer/components/TabModeSwitcher';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

let overlaySpy: ReturnType<typeof vi.fn>;

function buildZioMock() {
  overlaySpy = vi.fn(() => Promise.resolve());
  return {
    platform: 'linux',
    on: vi.fn(),
    off: vi.fn(),
    window: {
      setChromeOverlay: overlaySpy,
    },
    profiles: {
      list: vi.fn(() => Promise.resolve([])),
      getActive: vi.fn(() => Promise.resolve('default')),
      switch: vi.fn(() => Promise.resolve()),
      warmSession: vi.fn(() => Promise.resolve()),
    },
    tabs: {
      create: vi.fn(() => Promise.resolve('tab-1')),
    },
  };
}

let container: HTMLDivElement;
let root: Root;

async function mount(element: React.ReactElement): Promise<void> {
  container = document.createElement('div');
  document.body.appendChild(container);
  root = createRoot(container);
  await act(async () => {
    root.render(element);
  });
  await act(async () => { await Promise.resolve(); });
}

/** Walks up from `node` looking for an inline `position: fixed` ancestor. */
function fixedAncestorOf(node: HTMLElement | null): HTMLElement | null {
  while (node) {
    if (node.style?.position === 'fixed') return node;
    node = node.parentElement;
  }
  return null;
}

function findByText(text: string): HTMLElement | null {
  return Array.from(document.body.querySelectorAll('button, div, span'))
    .find(el => el.textContent?.trim() === text) as HTMLElement | null;
}

beforeEach(() => {
  (window as unknown as { zio: unknown }).zio = buildZioMock();
});

afterEach(async () => {
  await act(async () => { root.unmount(); });
  container.remove();
});

describe('ProfileSwitcher popover (browser-mode tab strip)', () => {
  it('opens a position:fixed menu and engages the chrome overlay', async () => {
    await mount(<ProfileSwitcher isAuthenticated onOpenAuth={() => {}} />);

    const trigger = container.querySelector('button');
    expect(trigger, 'trigger chip should render').toBeTruthy();
    await act(async () => {
      trigger!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    const header = findByText('Browser Profile');
    expect(header, 'popover header must be in the open menu').toBeTruthy();
    expect(findByText('New Workspace'), 'New Workspace action must be visible').toBeTruthy();

    // Must be fixed-position so the scrollable tab strip
    // (overflow-x auto / overflow-y hidden) cannot clip it.
    expect(fixedAncestorOf(header), 'menu must use position: fixed').toBeTruthy();

    expect(overlaySpy).toHaveBeenCalledWith(true);
    expect(overlaySpy).not.toHaveBeenCalledWith(false);
  });

  it('releases the overlay when closed via outside click', async () => {
    await mount(<ProfileSwitcher isAuthenticated onOpenAuth={() => {}} />);

    const trigger = container.querySelector('button')!;
    await act(async () => {
      trigger.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    expect(findByText('Browser Profile')).toBeTruthy();

    await act(async () => {
      document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    });

    expect(findByText('Browser Profile')).toBeFalsy();
    expect(overlaySpy).toHaveBeenCalledWith(false);
  });

  it('opens the auth modal instead of the menu when signed out', async () => {
    const onOpenAuth = vi.fn();
    await mount(<ProfileSwitcher isAuthenticated={false} onOpenAuth={onOpenAuth} />);

    const trigger = container.querySelector('button')!;
    await act(async () => {
      trigger.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    expect(onOpenAuth).toHaveBeenCalledTimes(1);
    expect(findByText('Browser Profile'), 'menu must not open when signed out').toBeFalsy();
  });
});

describe('TabModeSwitcher dropdown (browser-mode tab strip)', () => {
  it('opens a position:fixed menu and engages the chrome overlay', async () => {
    await mount(<TabModeSwitcher currentMode="browser" onSetMode={() => {}} />);

    const trigger = container.querySelector('button');
    expect(trigger, 'trigger button should render').toBeTruthy();
    await act(async () => {
      trigger!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    const header = findByText('Full View');
    expect(header, 'dropdown section header must be in the open menu').toBeTruthy();
    expect(findByText('Split View'), 'Split View section must be visible').toBeTruthy();

    expect(fixedAncestorOf(header), 'menu must use position: fixed').toBeTruthy();

    expect(overlaySpy).toHaveBeenCalledWith(true);
    expect(overlaySpy).not.toHaveBeenCalledWith(false);
  });

  it('picking a mode closes the menu, releases the overlay, and calls onSetMode', async () => {
    const onSetMode = vi.fn();
    await mount(<TabModeSwitcher currentMode="browser" onSetMode={onSetMode} />);

    const trigger = container.querySelector('button')!;
    await act(async () => {
      trigger.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    // Pick a mode different from the current one.
    const dashboardItem = Array.from(document.body.querySelectorAll('button'))
      .find(b => b.textContent?.includes('Dashboard') && !b.textContent.includes('+'));
    expect(dashboardItem, 'a non-current single mode item should exist').toBeTruthy();

    await act(async () => {
      dashboardItem!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    expect(onSetMode).toHaveBeenCalledTimes(1);
    expect(findByText('Full View'), 'menu must close after picking').toBeFalsy();
    expect(overlaySpy).toHaveBeenCalledWith(false);
  });
});
