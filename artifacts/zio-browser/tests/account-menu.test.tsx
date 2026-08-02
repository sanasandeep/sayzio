// @vitest-environment jsdom
/**
 * Guards the browser-mode account menu contract:
 *
 * The avatar button lives inside the tab strip, which scrolls horizontally
 * (`overflow-x: auto; overflow-y: hidden`) — an absolutely-positioned
 * dropdown would be clipped to the 28px row, hiding "Sign out". The menu
 * must therefore render with `position: fixed` (viewport-anchored, immune
 * to ancestor overflow clipping) and engage the chrome overlay (hide the
 * native WebContentsViews) while open, same pattern as ModeSwitcher.
 *
 * Also verifies the signed-out state renders a "Sign in" button and that
 * clicking "Sign out" clears auth via window.zio.auth.clear().
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act, useEffect } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { AccountButton } from '../src/renderer/components/AccountButton';
import { useAuthStore } from '../src/renderer/store/auth-store';
import { buildZioMock } from './helpers/zio-mock';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

const USER = { id: 1, name: 'Test User', email: 'test@example.com' };

let overlaySpy: ReturnType<typeof vi.fn>;
let clearSpy: ReturnType<typeof vi.fn>;
let storedToken: string | null;
let storedUser: Record<string, unknown> | null;

function makeZio() {
  overlaySpy = vi.fn(() => Promise.resolve());
  clearSpy = vi.fn(() => {
    storedToken = null;
    storedUser = null;
    return Promise.resolve(true);
  });
  return buildZioMock({
    overrides: {
      auth: {
        getToken: vi.fn(() => Promise.resolve(storedToken)),
        getUser: vi.fn(() => Promise.resolve(storedUser)),
        clear: clearSpy,
      },
      window: {
        setChromeOverlay: overlaySpy,
      },
    },
  }).zio;
}

/** Mounts AccountButton after hydrating the auth store from the mock. */
function Harness({ onOpenAuth }: { onOpenAuth: () => void }) {
  const { init } = useAuthStore();
  useEffect(() => { void init(); }, [init]);
  return <AccountButton onOpenAuth={onOpenAuth} compact />;
}

let container: HTMLDivElement;
let root: Root;

async function flush(): Promise<void> {
  await act(async () => { await Promise.resolve(); });
}

async function mount(onOpenAuth: () => void = () => {}): Promise<void> {
  container = document.createElement('div');
  document.body.appendChild(container);
  root = createRoot(container);
  await act(async () => {
    root.render(<Harness onOpenAuth={onOpenAuth} />);
  });
  await flush();
}

function avatarButton(): HTMLButtonElement {
  const btn = container.querySelector('button[title]') as HTMLButtonElement | null;
  expect(btn, 'avatar button should render').toBeTruthy();
  return btn!;
}

function findByText(text: string): HTMLElement | null {
  return Array.from(document.body.querySelectorAll('button, div'))
    .find(el => el.textContent?.trim() === text || (el.textContent?.includes(text) && el.tagName === 'BUTTON')) as HTMLElement | null;
}

beforeEach(() => {
  storedToken = 'test-token';
  storedUser = USER;
  (window as unknown as { zio: unknown }).zio = makeZio();
});

afterEach(async () => {
  await act(async () => { root.unmount(); });
  container.remove();
  // Reset module-level auth state between tests.
  storedToken = null;
  storedUser = null;
});

describe('AccountButton menu (browser-mode tab strip)', () => {
  it('opens a position:fixed menu with Sign out visible and engages the chrome overlay', async () => {
    await mount();

    await act(async () => {
      avatarButton().dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    const signOut = findByText('Sign out');
    expect(signOut, '"Sign out" must be in the open menu').toBeTruthy();
    expect(findByText('Profile settings')).toBeTruthy();
    expect(document.body.textContent).toContain(USER.email);

    // The menu container must be fixed-position so the scrollable tab strip
    // (overflow-x auto / overflow-y hidden) cannot clip it in browser mode.
    let node: HTMLElement | null = signOut;
    let fixedAncestor: HTMLElement | null = null;
    while (node) {
      if (node.style?.position === 'fixed') { fixedAncestor = node; break; }
      node = node.parentElement;
    }
    expect(fixedAncestor, 'menu must use position: fixed').toBeTruthy();

    // Chrome overlay engaged exactly once on open.
    expect(overlaySpy).toHaveBeenCalledWith(true);
    expect(overlaySpy).not.toHaveBeenCalledWith(false);
  });

  it('re-anchors the open menu when the window resizes or an ancestor scrolls', async () => {
    await mount();

    const btn = avatarButton();
    // Anchor position at click time.
    // Keep the button near the right edge (like the real avatar) so the
    // narrow-window clamping in computeMenuPos never kicks in here.
    btn.getBoundingClientRect = () =>
      ({ top: 0, bottom: 30, left: 740, right: 770, width: 30, height: 30, x: 740, y: 0, toJSON: () => ({}) }) as DOMRect;
    (window as unknown as { innerWidth: number }).innerWidth = 800;

    await act(async () => {
      btn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });

    function fixedMenu(): HTMLElement {
      let node: HTMLElement | null = findByText('Sign out');
      while (node && node.style?.position !== 'fixed') node = node.parentElement;
      expect(node, 'fixed menu container must exist').toBeTruthy();
      return node!;
    }
    expect(fixedMenu().style.top).toBe('36px');
    expect(fixedMenu().style.right).toBe(`${800 - 770}px`);

    // Simulate the button moving (tab strip scrolled / window resized).
    btn.getBoundingClientRect = () =>
      ({ top: 0, bottom: 30, left: 530, right: 560, width: 30, height: 30, x: 530, y: 0, toJSON: () => ({}) }) as DOMRect;
    (window as unknown as { innerWidth: number }).innerWidth = 600;

    await act(async () => {
      window.dispatchEvent(new Event('resize'));
    });
    expect(fixedMenu().style.right).toBe(`${600 - 560}px`);

    // Scroll of an ancestor (scroll doesn't bubble; the re-anchor hook
    // listens at the document level in the capture phase).
    btn.getBoundingClientRect = () =>
      ({ top: 0, bottom: 30, left: 510, right: 540, width: 30, height: 30, x: 510, y: 0, toJSON: () => ({}) }) as DOMRect;
    await act(async () => {
      container.dispatchEvent(new Event('scroll'));
    });
    expect(fixedMenu().style.right).toBe(`${600 - 540}px`);
  });

  it('Sign out clears auth, releases the overlay, and reverts to a Sign in button', async () => {
    await mount();

    await act(async () => {
      avatarButton().dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    const signOut = findByText('Sign out');
    expect(signOut).toBeTruthy();

    await act(async () => {
      signOut!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    await flush();

    expect(clearSpy).toHaveBeenCalledTimes(1);
    // Overlay released when the menu closed.
    expect(overlaySpy).toHaveBeenCalledWith(false);
    // Signed-out state renders the "Sign in" affordance.
    const signIn = Array.from(container.querySelectorAll('button'))
      .find(b => b.textContent?.trim() === 'Sign in');
    expect(signIn, 'button should revert to "Sign in" after logout').toBeTruthy();
  });

  it('renders a Sign in button when signed out that opens the auth modal', async () => {
    storedToken = null;
    storedUser = null;
    const onOpenAuth = vi.fn();
    await mount(onOpenAuth);

    const signIn = Array.from(container.querySelectorAll('button'))
      .find(b => b.textContent?.trim() === 'Sign in');
    expect(signIn).toBeTruthy();
    await act(async () => {
      signIn!.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    });
    expect(onOpenAuth).toHaveBeenCalledTimes(1);
  });
});
