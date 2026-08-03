// @vitest-environment jsdom
/**
 * Sync-paused pill regression guard (empty queue).
 *
 * The ChromeBar toolbar must show the "Sync paused — upgrade to resume" pill
 * whenever the plan gate blocks sync (planStatus.gate.blocked === true), even
 * when the sync queue is EMPTY (pendingSyncCount === 0) — otherwise the paused
 * state is invisible. Clicking the pill must invoke onOpenSyncSettings, which
 * App wires to open the Settings panel directly on the Sync section
 * (SettingsPanel initialSection="sync").
 *
 * Covered here:
 *   - gate.blocked=true + pendingCount=0 → pill visible with paused label
 *   - clicking the pill calls onOpenSyncSettings exactly once
 *   - gate.blocked=false + pendingCount=0 → no pill at all
 *   - a live 'sync:plan-status-changed' event flips the pill on/off
 *   - SettingsPanel initialSection="sync" opens on the Sync section
 *     (mirroring App's settingsInitialSection wiring)
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import React, { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ChromeBar } from '../src/renderer/components/ChromeBar';
import { SettingsPanel } from '../src/renderer/components/SettingsPanel';
import { buildZioMock, resolved, type ZioMockResult } from './helpers/zio-mock';

(globalThis as Record<string, unknown>).IS_REACT_ACT_ENVIRONMENT = true;

const PAUSED_LABEL = 'Sync paused — upgrade to resume';
const PENDING_LABEL = 'Sync pending';

let mock: ZioMockResult;

function installZio(opts: { blocked: boolean; pendingCount?: number }) {
  mock = buildZioMock({
    overrides: {
      sync: {
        pendingCount: resolved(opts.pendingCount ?? 0),
        pendingByProfile: resolved([]),
        planStatus: resolved({
          gate: { blocked: opts.blocked, feature: null, recommended_plan: null, blocked_at: null },
          rejected: null,
        }),
      },
    },
  });
  (window as unknown as Record<string, unknown>).zio = mock.zio;
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
  // Flush the async mount effects (planStatus/pendingCount promises).
  await act(async () => { await Promise.resolve(); });
  await act(async () => { await Promise.resolve(); });
}

function findButtonByText(text: string): HTMLButtonElement | null {
  return (
    Array.from(document.body.querySelectorAll('button')).find(
      el => el.textContent?.includes(text),
    ) ?? null
  );
}

function renderChromeBar(onOpenSyncSettings: () => void) {
  return (
    <ChromeBar
      zioPanelOpen={false}
      onToggleZio={() => {}}
      onOpenAuth={() => {}}
      onOpenTabSearch={() => {}}
      readingListOpen={false}
      onToggleReadingList={() => {}}
      onOpenSyncSettings={onOpenSyncSettings}
    />
  );
}

beforeEach(() => {
  vi.restoreAllMocks();
});

afterEach(async () => {
  await act(async () => { root.unmount(); });
  container.remove();
});

describe('sync-paused pill (empty queue)', () => {
  it('shows the paused pill when gate.blocked=true and pendingCount=0, and click opens Sync settings', async () => {
    installZio({ blocked: true, pendingCount: 0 });
    const openSyncSettings = vi.fn();
    await mount(renderChromeBar(openSyncSettings));

    const pill = findButtonByText(PAUSED_LABEL);
    expect(pill, 'paused pill should render with an empty sync queue').not.toBeNull();
    // Tooltip explains the paused state and where the click goes.
    expect(pill!.title).toContain('Sync is paused');
    expect(pill!.title).toContain('Settings → Sync');

    await act(async () => {
      pill!.click();
    });
    expect(openSyncSettings).toHaveBeenCalledTimes(1);
  });

  it('renders no pill when the gate is open and the queue is empty', async () => {
    installZio({ blocked: false, pendingCount: 0 });
    await mount(renderChromeBar(vi.fn()));

    expect(findButtonByText(PAUSED_LABEL)).toBeNull();
    expect(findButtonByText(PENDING_LABEL)).toBeNull();
  });

  it('shows the plain pending pill (not paused) when only the queue is non-empty', async () => {
    installZio({ blocked: false, pendingCount: 3 });
    await mount(renderChromeBar(vi.fn()));

    const pill = findButtonByText(PENDING_LABEL);
    expect(pill).not.toBeNull();
    expect(pill!.textContent).not.toContain(PAUSED_LABEL);
  });

  it('flips the pill on/off via the live sync:plan-status-changed event', async () => {
    installZio({ blocked: false, pendingCount: 0 });
    await mount(renderChromeBar(vi.fn()));
    expect(findButtonByText(PAUSED_LABEL)).toBeNull();

    await act(async () => {
      mock.emit('sync:plan-status-changed', {
        gate: { blocked: true, feature: null, recommended_plan: null, blocked_at: null },
        rejected: null,
      });
    });
    expect(findButtonByText(PAUSED_LABEL)).not.toBeNull();

    await act(async () => {
      mock.emit('sync:plan-status-changed', {
        gate: { blocked: false, feature: null, recommended_plan: null, blocked_at: null },
        rejected: null,
      });
    });
    expect(findButtonByText(PAUSED_LABEL)).toBeNull();
  });
});

describe('SettingsPanel initialSection="sync"', () => {
  it('opens directly on the Sync section (the pill-click landing)', async () => {
    installZio({ blocked: true, pendingCount: 0 });
    await mount(<SettingsPanel initialSection="sync" onClose={() => {}} />);

    // The active nav entry is bold (fontWeight 700) — find the Sync nav button.
    const navButtons = Array.from(document.body.querySelectorAll('button'));
    const syncNav = navButtons.find(b => b.textContent?.includes('Sync') && b.style.fontWeight);
    expect(syncNav, 'Sync nav entry should exist').toBeTruthy();
    expect(syncNav!.style.fontWeight).toBe('700');

    // The content header shows the active section label.
    const headers = Array.from(document.body.querySelectorAll('div')).filter(
      d => d.textContent === 'Sync' && d.style.fontWeight === '700',
    );
    expect(headers.length, 'Sync section header should be rendered').toBeGreaterThan(0);
  });

  it('defaults to General when no initialSection is given', async () => {
    installZio({ blocked: false, pendingCount: 0 });
    await mount(<SettingsPanel onClose={() => {}} />);

    const navButtons = Array.from(document.body.querySelectorAll('button'));
    const generalNav = navButtons.find(b => b.textContent?.includes('General'));
    expect(generalNav).toBeTruthy();
    expect(generalNav!.style.fontWeight).toBe('700');
  });
});
