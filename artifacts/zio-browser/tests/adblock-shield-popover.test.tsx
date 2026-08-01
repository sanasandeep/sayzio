// @vitest-environment jsdom
/**
 * AdBlockShieldPopover — reason-code contract with the shared resolver.
 *
 * The popover must consume the EXACT hyphenated reason codes emitted by
 * resolveAdBlockPolicy in src/shared/adblock-policy.ts ('timed-pause',
 * 'page-pause', 'admin-block', …). A silent enum drift here previously
 * hid the Resume button while paused, so these tests render the real
 * component against a mocked window.zio and assert:
 *   - paused states (timed-pause / page-pause) show "Resume blocking"
 *     with the matching status copy
 *   - active global state shows the pause quick-toggles including the
 *     required 15-minute option wired to pauseTimed(15)
 *   - admin-locked state renders the "Managed by Sayzio" view with no
 *     pause controls
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { AdBlockShieldPopover } from '../src/renderer/components/AdBlockShieldPopover';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

const TAB_ID = 'tab-1';
const HOST = 'news.example';

interface MockState {
  active: boolean;
  reason: string;
  adminLocked: boolean;
  strength: 'strict' | 'balanced';
  globalEnabled: boolean;
  timedPauseUntil: number | null;
  pausedUntilRestart: boolean;
}

let mockState: MockState;
let pauseTimedSpy: ReturnType<typeof vi.fn>;
let resumeSpy: ReturnType<typeof vi.fn>;

function baseState(overrides: Partial<MockState>): MockState {
  return {
    active: true,
    reason: 'global',
    adminLocked: false,
    strength: 'balanced',
    globalEnabled: true,
    timedPauseUntil: null,
    pausedUntilRestart: false,
    ...overrides,
  };
}

function buildZioMock() {
  pauseTimedSpy = vi.fn(() => Promise.resolve());
  resumeSpy = vi.fn(() => Promise.resolve());
  return {
    on: vi.fn(),
    off: vi.fn(),
    adblock: {
      getState: vi.fn(() => Promise.resolve(mockState)),
      getLists: vi.fn(() => Promise.resolve({ allow: [], block: [] })),
      pauseTimed: pauseTimedSpy,
      pausePage: vi.fn(() => Promise.resolve()),
      resume: resumeSpy,
      addListDomain: vi.fn(() => Promise.resolve()),
      removeListDomain: vi.fn(() => Promise.resolve()),
      setEnabled: vi.fn(() => Promise.resolve()),
      setStrength: vi.fn(() => Promise.resolve()),
    },
  };
}

let container: HTMLDivElement;
let root: Root;

async function mount(): Promise<void> {
  await act(async () => {
    root.render(
      <AdBlockShieldPopover tabId={TAB_ID} host={HOST} blockedCount={0} onClose={() => {}} />,
    );
  });
  // Let the getState/getLists promises resolve.
  await act(async () => { await Promise.resolve(); });
}

function buttons(): string[] {
  return Array.from(container.querySelectorAll('button')).map(b => b.textContent ?? '');
}

beforeEach(() => {
  (window as unknown as Record<string, unknown>).zio = buildZioMock();
  container = document.createElement('div');
  document.body.appendChild(container);
  root = createRoot(container);
});

afterEach(async () => {
  await act(async () => { root.unmount(); });
  container.remove();
});

describe('AdBlockShieldPopover reason handling', () => {
  it('timed-pause shows Resume and "Paused temporarily"', async () => {
    mockState = baseState({ active: false, reason: 'timed-pause', timedPauseUntil: Date.now() + 60_000 });
    await mount();
    expect(container.textContent).toContain('Paused temporarily');
    const resumeBtn = Array.from(container.querySelectorAll('button')).find(b => /Resume blocking/.test(b.textContent ?? ''));
    expect(resumeBtn).toBeTruthy();
    await act(async () => { resumeBtn!.click(); });
    expect(resumeSpy).toHaveBeenCalled();
    // No pause options while paused.
    expect(buttons().join('|')).not.toMatch(/Pause 15 min|Pause 1 hour/);
  });

  it('timed-pause with pausedUntilRestart shows "Paused until restart"', async () => {
    mockState = baseState({ active: false, reason: 'timed-pause', pausedUntilRestart: true });
    await mount();
    expect(container.textContent).toContain('Paused until restart');
    expect(buttons().some(t => /Resume blocking/.test(t))).toBe(true);
  });

  it('page-pause shows Resume and the page-pause copy', async () => {
    mockState = baseState({ active: false, reason: 'page-pause' });
    await mount();
    expect(container.textContent).toContain('Paused on this page until you navigate');
    expect(buttons().some(t => /Resume blocking/.test(t))).toBe(true);
  });

  it('active global state offers 15 min / 1 hour / until-restart pause options; 15 min wires pauseTimed(15)', async () => {
    mockState = baseState({ active: true, reason: 'global' });
    await mount();
    const labels = buttons().join('|');
    expect(labels).toMatch(/Pause on this page/);
    expect(labels).toMatch(/Pause 15 min/);
    expect(labels).toMatch(/Pause 1 hour/);
    expect(labels).toMatch(/Until restart/);
    expect(labels).not.toMatch(/Resume blocking/);
    const btn15 = Array.from(container.querySelectorAll('button')).find(b => /Pause 15 min/.test(b.textContent ?? ''));
    await act(async () => { btn15!.click(); });
    expect(pauseTimedSpy).toHaveBeenCalledWith(15);
  });

  it('admin-block renders the locked "Managed by Sayzio" view without pause controls', async () => {
    mockState = baseState({ active: true, reason: 'admin-block', adminLocked: true });
    await mount();
    expect(container.textContent).toContain(`Ad blocking is required on ${HOST}`);
    expect(container.querySelector('[data-testid="adblock-managed"]')).toBeTruthy();
    expect(buttons().join('|')).not.toMatch(/Pause|Resume/);
  });

  it('user-allow reason renders the allow-list copy', async () => {
    mockState = baseState({ active: false, reason: 'user-allow' });
    await mount();
    expect(container.textContent).toContain(`${HOST} is on your allow list`);
  });
});
