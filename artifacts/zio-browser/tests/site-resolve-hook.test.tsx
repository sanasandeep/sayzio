// @vitest-environment jsdom
/**
 * Component-level coverage for the "On Sayzio" badge lifecycle (Task: badge
 * must disappear the moment the user signs out or the window goes private
 * mid-visit).
 *
 * Renders the shared useSiteResolve hook — the exact hook ChromeBar uses for
 * its address-bar badge — through a small harness and asserts:
 *   - a resolved badge clears immediately when token flips to null (sign-out)
 *   - a resolved badge clears immediately when isPrivate flips to true
 *   - a pending debounced lookup is cancelled on URL change and on unmount
 *     (the resolver is never called / a late result never lands)
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
  useSiteResolve,
  clearSiteResolveCache,
  SITE_RESOLVE_DEBOUNCE_MS,
} from '../src/renderer/hooks/use-site-resolve';
import type { SiteResolveResult } from '../src/shared/api-client';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

const ON_SAYZIO: SiteResolveResult = {
  on_sayzio: true,
  owner: { handle: 'jane', name: 'Jane Doe' },
} as unknown as SiteResolveResult;

interface HarnessProps {
  url: string | undefined;
  isPrivate: boolean;
  token: string | null;
  resolver: (host: string) => Promise<SiteResolveResult>;
}

function Harness({ url, isPrivate, token, resolver }: HarnessProps) {
  const siteResolve = useSiteResolve({ url, isPrivate, token, resolver });
  return (
    <div data-testid="badge">
      {siteResolve?.on_sayzio ? 'on-sayzio' : 'none'}
    </div>
  );
}

let container: HTMLDivElement;
let root: Root;

function badgeText(): string {
  return container.querySelector('[data-testid="badge"]')!.textContent ?? '';
}

function render(props: HarnessProps) {
  act(() => {
    root.render(<Harness {...props} />);
  });
}

/** Advance past the debounce and flush the resolver promise. */
async function settle() {
  await act(async () => {
    vi.advanceTimersByTime(SITE_RESOLVE_DEBOUNCE_MS + 1);
    await Promise.resolve();
  });
}

beforeEach(() => {
  vi.useFakeTimers();
  clearSiteResolveCache();
  container = document.createElement('div');
  document.body.appendChild(container);
  act(() => {
    root = createRoot(container);
  });
});

afterEach(() => {
  act(() => {
    root.unmount();
  });
  container.remove();
  vi.useRealTimers();
});

describe('useSiteResolve badge lifecycle', () => {
  it('shows the badge after the debounced lookup resolves', async () => {
    const resolver = vi.fn().mockResolvedValue(ON_SAYZIO);
    const props: HarnessProps = {
      url: 'https://example.com/page',
      isPrivate: false,
      token: 'tok',
      resolver,
    };
    render(props);
    expect(badgeText()).toBe('none');
    await settle();
    expect(resolver).toHaveBeenCalledWith('example.com');
    expect(badgeText()).toBe('on-sayzio');
  });

  it('clears the badge immediately when the token flips to null (sign-out)', async () => {
    const resolver = vi.fn().mockResolvedValue(ON_SAYZIO);
    const props: HarnessProps = {
      url: 'https://example.com/page',
      isPrivate: false,
      token: 'tok',
      resolver,
    };
    render(props);
    await settle();
    expect(badgeText()).toBe('on-sayzio');

    render({ ...props, token: null });
    // Synchronous — no timers advanced: badge must already be gone, even
    // though the host is still in the per-host cache.
    expect(badgeText()).toBe('none');
  });

  it('clears the badge immediately when the window flips private', async () => {
    const resolver = vi.fn().mockResolvedValue(ON_SAYZIO);
    const props: HarnessProps = {
      url: 'https://example.com/page',
      isPrivate: false,
      token: 'tok',
      resolver,
    };
    render(props);
    await settle();
    expect(badgeText()).toBe('on-sayzio');

    render({ ...props, isPrivate: true });
    expect(badgeText()).toBe('none');
  });

  it('cancels a pending debounced lookup when the URL changes', async () => {
    const resolver = vi.fn().mockResolvedValue(ON_SAYZIO);
    const props: HarnessProps = {
      url: 'https://example.com/page',
      isPrivate: false,
      token: 'tok',
      resolver,
    };
    render(props);
    // Halfway through the debounce, navigate elsewhere.
    act(() => {
      vi.advanceTimersByTime(SITE_RESOLVE_DEBOUNCE_MS / 2);
    });
    render({ ...props, url: 'https://other.org/' });
    await settle();
    // Only the new host was ever looked up — the first timer was cancelled.
    expect(resolver).toHaveBeenCalledTimes(1);
    expect(resolver).toHaveBeenCalledWith('other.org');
  });

  it('cancels a pending debounced lookup on unmount', () => {
    const resolver = vi.fn().mockResolvedValue(ON_SAYZIO);
    render({
      url: 'https://example.com/page',
      isPrivate: false,
      token: 'tok',
      resolver,
    });
    act(() => {
      root.unmount();
    });
    act(() => {
      vi.advanceTimersByTime(SITE_RESOLVE_DEBOUNCE_MS * 2);
    });
    expect(resolver).not.toHaveBeenCalled();
    // Recreate a root so afterEach's unmount stays valid.
    act(() => {
      root = createRoot(container);
    });
  });

  it('does not apply a late in-flight result after sign-out mid-lookup', async () => {
    let resolvePromise!: (v: SiteResolveResult) => void;
    const resolver = vi.fn(
      () => new Promise<SiteResolveResult>((res) => { resolvePromise = res; }),
    );
    const props: HarnessProps = {
      url: 'https://example.com/page',
      isPrivate: false,
      token: 'tok',
      resolver,
    };
    render(props);
    // Fire the debounce so the network call is in flight.
    act(() => {
      vi.advanceTimersByTime(SITE_RESOLVE_DEBOUNCE_MS + 1);
    });
    expect(resolver).toHaveBeenCalledTimes(1);
    // Sign out while the request is still pending, then let it resolve.
    render({ ...props, token: null });
    await act(async () => {
      resolvePromise(ON_SAYZIO);
      await Promise.resolve();
    });
    expect(badgeText()).toBe('none');
  });
});
