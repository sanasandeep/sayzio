import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { SyncQueueItem } from '../src/shared/sync-engine';

// Mock the db module (it pulls in better-sqlite3 native bindings) so the
// retry runner can be exercised in isolation.
// In-memory preference store so plan-gate persistence can be asserted.
const prefStore = new Map<string, string>();

vi.mock('../src/main/db', () => ({
  getSyncQueueItems: vi.fn(() => [] as SyncQueueItem[]),
  countSyncQueue: vi.fn(() => 0),
  markSyncQueueFailure: vi.fn(),
  removeSyncQueueItem: vi.fn(),
  updateSyncQueuePayload: vi.fn(),
  markRecordsSynced: vi.fn(),
  getPreference: vi.fn((key: string) => prefStore.get(key) ?? null),
  setPreference: vi.fn((key: string, value: string) => { prefStore.set(key, value); }),
  getActiveProfileId: vi.fn(() => 'default'),
  getProfileWorkspaceId: vi.fn(() => null),
  profileExists: vi.fn(() => true),
}));
vi.mock('../src/main/auth-store', () => ({
  retrieveToken: vi.fn(() => null),
}));

import { SyncRetryRunner, getSyncPlanStatus } from '../src/main/sync-retry';
import { ApiClientError } from '../src/shared/api-client';
import { getSyncQueueItems, removeSyncQueueItem, markSyncQueueFailure, markRecordsSynced, profileExists, updateSyncQueuePayload } from '../src/main/db';

function makeItem(overrides: Partial<SyncQueueItem> = {}): SyncQueueItem {
  return {
    id: 'q1',
    entity: 'bookmarks',
    payload: JSON.stringify([{ local_id: 'b1', updated_at: '2026-01-01T00:00:00Z', deleted: false, data: {} }]),
    attempts: 1,
    next_attempt_at: '2026-01-01T00:00:00Z',
    last_error: null,
    created_at: '2026-01-01T00:00:00Z',
    profile_id: null,
    ...overrides,
  };
}

describe('SyncRetryRunner profile scoping', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('passes the recorded profile_id of each queued item to the push function', async () => {
    const items = [
      makeItem({ id: 'q1', profile_id: 'workspace-profile-9' }),
      makeItem({ id: 'q2', entity: 'history', profile_id: 'other-profile' }),
    ];
    vi.mocked(getSyncQueueItems).mockReturnValue(items);

    const pushFn = vi.fn().mockResolvedValue(undefined);
    const runner = new SyncRetryRunner({ pushFn });
    const flushed = await runner.tick(Date.now());

    expect(flushed).toBe(2);
    expect(pushFn).toHaveBeenCalledWith('bookmarks', expect.any(Array), 'workspace-profile-9');
    expect(pushFn).toHaveBeenCalledWith('history', expect.any(Array), 'other-profile');
    expect(removeSyncQueueItem).toHaveBeenCalledTimes(2);
  });

  it('passes null for legacy rows without a recorded profile (fallback to active profile)', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem({ profile_id: null })]);

    const pushFn = vi.fn().mockResolvedValue(undefined);
    const runner = new SyncRetryRunner({ pushFn });
    await runner.tick(Date.now());

    expect(pushFn).toHaveBeenCalledWith('bookmarks', expect.any(Array), null);
  });

  it('keeps the item queued with its profile when the push fails', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem({ profile_id: 'ws-p' })]);

    const pushFn = vi.fn().mockRejectedValue(new Error('offline'));
    const runner = new SyncRetryRunner({ pushFn });
    const flushed = await runner.tick(Date.now());

    expect(flushed).toBe(0);
    expect(markSyncQueueFailure).toHaveBeenCalledWith('q1', 'offline');
    expect(removeSyncQueueItem).not.toHaveBeenCalled();
  });

  it('drops items whose recorded profile has been deleted without pushing', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([
      makeItem({ id: 'q1', profile_id: 'deleted-profile' }),
      makeItem({ id: 'q2', profile_id: 'live-profile' }),
    ]);
    vi.mocked(profileExists).mockImplementation((id: string) => id !== 'deleted-profile');

    const pushFn = vi.fn().mockResolvedValue(undefined);
    const runner = new SyncRetryRunner({ pushFn });
    const flushed = await runner.tick(Date.now());

    expect(flushed).toBe(2);
    // The deleted profile's item was never pushed (would land in the wrong bucket)
    expect(pushFn).toHaveBeenCalledTimes(1);
    expect(pushFn).toHaveBeenCalledWith('bookmarks', expect.any(Array), 'live-profile');
    expect(removeSyncQueueItem).toHaveBeenCalledWith('q1');
    expect(removeSyncQueueItem).toHaveBeenCalledWith('q2');
    // Dropped items are not marked as synced
    expect(markRecordsSynced).toHaveBeenCalledTimes(1);
  });

  it('still pushes legacy items without a recorded profile (active-profile fallback)', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem({ profile_id: null })]);
    vi.mocked(profileExists).mockReturnValue(false);

    const pushFn = vi.fn().mockResolvedValue(undefined);
    const runner = new SyncRetryRunner({ pushFn });
    await runner.tick(Date.now());

    expect(pushFn).toHaveBeenCalledWith('bookmarks', expect.any(Array), null);
  });
});

describe('plan gate (402 plan_upgrade_required)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    prefStore.clear();
  });

  it('sets the persisted gate + notifies, and keeps the item queued for auto-resume', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem()]);
    const pushFn = vi.fn().mockRejectedValue(new ApiClientError(
      'plan_upgrade_required', 'Upgrade required', 402,
      { feature: 'browser_sync', recommended_plan: 'Pro' },
    ));
    const onPlanStatusChanged = vi.fn();
    const runner = new SyncRetryRunner({ pushFn, onPlanStatusChanged });
    const flushed = await runner.tick(Date.now());

    expect(flushed).toBe(0);
    // Item stays queued so retries resume automatically after upgrade
    expect(removeSyncQueueItem).not.toHaveBeenCalled();
    expect(markSyncQueueFailure).toHaveBeenCalled();
    const status = getSyncPlanStatus();
    expect(status.gate.blocked).toBe(true);
    expect(status.gate.feature).toBe('browser_sync');
    expect(status.gate.recommended_plan).toBe('Pro');
    expect(onPlanStatusChanged).toHaveBeenCalledWith(expect.objectContaining({
      gate: expect.objectContaining({ blocked: true, recommended_plan: 'Pro' }),
    }));
  });

  it('clears the gate on the first successful push after upgrade', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem()]);
    const gated = new ApiClientError('plan_upgrade_required', 'Upgrade required', 402, { feature: 'browser_sync' });
    const pushFn = vi.fn().mockRejectedValueOnce(gated).mockResolvedValue({
      accepted: ['b1'], conflicts: [], rejected: [], server_time: '2026-01-02T00:00:00Z',
    });
    const onPlanStatusChanged = vi.fn();
    const runner = new SyncRetryRunner({ pushFn, onPlanStatusChanged });

    await runner.tick(Date.now());
    expect(getSyncPlanStatus().gate.blocked).toBe(true);

    await runner.flushAll();
    expect(getSyncPlanStatus().gate.blocked).toBe(false);
    expect(removeSyncQueueItem).toHaveBeenCalledWith('q1');
    expect(markRecordsSynced).toHaveBeenCalledWith('bookmarks', ['b1']);
  });

  it('does not treat other 402/other codes as the plan gate', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem()]);
    const pushFn = vi.fn().mockRejectedValue(new ApiClientError('payment_due', 'nope', 402));
    const runner = new SyncRetryRunner({ pushFn });
    await runner.tick(Date.now());
    expect(getSyncPlanStatus().gate.blocked).toBe(false);
  });
});

describe('over-cap rejected items', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    prefStore.clear();
  });

  const twoItemPayload = JSON.stringify([
    { local_id: 'b1', updated_at: '2026-01-01T00:00:00Z', deleted: false, data: {} },
    { local_id: 'b2', updated_at: '2026-01-01T00:00:00Z', deleted: false, data: {} },
  ]);

  it('marks accepted rows synced, requeues only rejected rows, and records the limit notice', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem({ payload: twoItemPayload })]);
    const pushFn = vi.fn().mockResolvedValue({
      accepted: ['b1'], conflicts: [], rejected: ['b2'], limit: 25, server_time: '2026-01-02T00:00:00Z',
    });
    const onPlanStatusChanged = vi.fn();
    const runner = new SyncRetryRunner({ pushFn, onPlanStatusChanged });
    const flushed = await runner.tick(Date.now());

    expect(flushed).toBe(0);
    expect(markRecordsSynced).toHaveBeenCalledWith('bookmarks', ['b1']);
    expect(removeSyncQueueItem).not.toHaveBeenCalled();
    // Only the rejected row stays in the queue payload
    const [, payload] = vi.mocked(updateSyncQueuePayload).mock.calls[0];
    expect(JSON.parse(payload).map((i: { local_id: string }) => i.local_id)).toEqual(['b2']);
    expect(markSyncQueueFailure).toHaveBeenCalledWith('q1', expect.stringContaining('limit'));

    const status = getSyncPlanStatus();
    expect(status.rejected).toEqual(expect.objectContaining({ count: 1, limit: 25 }));
    expect(onPlanStatusChanged).toHaveBeenCalled();
  });

  it('clears the notice when a later push has no rejections', async () => {
    vi.mocked(getSyncQueueItems).mockReturnValue([makeItem({ payload: twoItemPayload })]);
    const pushFn = vi.fn()
      .mockResolvedValueOnce({ accepted: ['b1'], conflicts: [], rejected: ['b2'], limit: 25, server_time: 't' })
      .mockResolvedValue({ accepted: ['b2'], conflicts: [], rejected: [], server_time: 't' });
    const runner = new SyncRetryRunner({ pushFn });

    await runner.tick(Date.now());
    expect(getSyncPlanStatus().rejected?.count).toBe(1);

    await runner.flushAll();
    expect(getSyncPlanStatus().rejected).toBeNull();
  });
});

describe('defaultSyncPush workspace resolution', () => {
  it('resolves the workspace from the recorded profile, not the active one', async () => {
    const db = await import('../src/main/db');
    const auth = await import('../src/main/auth-store');
    vi.mocked(auth.retrieveToken).mockReturnValue('tok');
    vi.mocked(db.getPreference).mockImplementation((k: string) =>
      k === 'sayzio_api_base_url' ? 'http://example.test' : k === 'device_id' ? 'dev-1' : null);
    vi.mocked(db.getActiveProfileId).mockReturnValue('active-profile');
    const workspaceLookups: string[] = [];
    vi.mocked(db.getProfileWorkspaceId).mockImplementation((id: string) => {
      workspaceLookups.push(id);
      return 'ws-123';
    });

    const { defaultSyncPush } = await import('../src/main/sync-retry');
    // The fetch inside ApiClient will fail; we only care about which profile
    // was used for the workspace lookup before the network call.
    await defaultSyncPush('bookmarks', [], 'recorded-profile').catch(() => {});
    expect(workspaceLookups).toEqual(['recorded-profile']);

    workspaceLookups.length = 0;
    await defaultSyncPush('bookmarks', [], null).catch(() => {});
    expect(workspaceLookups).toEqual(['active-profile']);
  });
});
