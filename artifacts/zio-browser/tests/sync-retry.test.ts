import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { SyncQueueItem } from '../src/shared/sync-engine';

// Mock the db module (it pulls in better-sqlite3 native bindings) so the
// retry runner can be exercised in isolation.
vi.mock('../src/main/db', () => ({
  getSyncQueueItems: vi.fn(() => [] as SyncQueueItem[]),
  countSyncQueue: vi.fn(() => 0),
  markSyncQueueFailure: vi.fn(),
  removeSyncQueueItem: vi.fn(),
  markRecordsSynced: vi.fn(),
  getPreference: vi.fn(() => null),
  getActiveProfileId: vi.fn(() => 'default'),
  getProfileWorkspaceId: vi.fn(() => null),
  profileExists: vi.fn(() => true),
}));
vi.mock('../src/main/auth-store', () => ({
  retrieveToken: vi.fn(() => null),
}));

import { SyncRetryRunner } from '../src/main/sync-retry';
import { getSyncQueueItems, removeSyncQueueItem, markSyncQueueFailure, markRecordsSynced, profileExists } from '../src/main/db';

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
