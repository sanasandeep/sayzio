import { describe, it, expect, vi } from 'vitest';
import {
  mergeSync,
  getPendingUploads,
  isSyncDue,
  computeSyncState,
  computeBackoffMs,
  nextAttemptAt,
  isQueueItemDue,
  getDueQueueItems,
  profileSyncEntityKey,
  SYNC_INTERVALS,
  RETRY_BACKOFF,
  type SyncRecord,
  type SyncQueueItem,
} from '../src/shared/sync-engine';

function makeRecord(id: string, updatedAt: string, syncedAt?: string | null, deleted = false): SyncRecord {
  return {
    local_id: id,
    updated_at: updatedAt,
    deleted,
    synced_at: syncedAt ?? null,
    data: { title: `Record ${id}` },
  };
}

describe('mergeSync', () => {
  it('inserts new server records that are not present locally', () => {
    const serverRecord = makeRecord('new-id', '2025-01-02T10:00:00Z');
    const result = mergeSync([], [serverRecord]);
    expect(result.toUpsert).toHaveLength(1);
    expect(result.toUpsert[0]?.local_id).toBe('new-id');
    expect(result.conflicts).toHaveLength(0);
  });

  it('does not insert deleted server records into empty local store', () => {
    const serverRecord = makeRecord('deleted-id', '2025-01-02T10:00:00Z', null, true);
    const result = mergeSync([], [serverRecord]);
    expect(result.toUpsert).toHaveLength(0);
  });

  it('server wins when server record is newer', () => {
    const local = makeRecord('id1', '2025-01-01T10:00:00Z', '2025-01-01T10:00:00Z');
    const server = makeRecord('id1', '2025-01-02T10:00:00Z');
    const result = mergeSync([local], [server]);
    expect(result.toUpsert).toHaveLength(1);
    expect(result.toUpsert[0]?.updated_at).toBe('2025-01-02T10:00:00Z');
  });

  it('local wins when local record is newer and does not upsert', () => {
    const local = makeRecord('id1', '2025-01-03T10:00:00Z', '2025-01-01T10:00:00Z');
    const server = makeRecord('id1', '2025-01-02T10:00:00Z');
    const result = mergeSync([local], [server]);
    expect(result.toUpsert).toHaveLength(0);
    // Conflict recorded because local is ahead of its own synced_at
    expect(result.conflicts[0]?.resolution).toBe('local_wins');
  });

  it('no conflict for equal timestamps', () => {
    const ts = '2025-01-01T10:00:00Z';
    const local = makeRecord('id1', ts, ts);
    const server = makeRecord('id1', ts);
    const result = mergeSync([local], [server]);
    expect(result.toUpsert).toHaveLength(0);
    expect(result.conflicts).toHaveLength(0);
  });

  it('records server-wins conflict when local had unsynchronized edits', () => {
    // Local was synced at T1, then modified at T2, then server updates at T3 > T2
    const local = makeRecord('id1', '2025-01-02T00:00:00Z', '2025-01-01T00:00:00Z');
    const server = makeRecord('id1', '2025-01-03T00:00:00Z');
    const result = mergeSync([local], [server]);
    expect(result.toUpsert).toHaveLength(1);
    expect(result.conflicts[0]?.resolution).toBe('server_wins');
  });

  it('handles multiple records', () => {
    const locals = [
      makeRecord('a', '2025-01-01T00:00:00Z', '2025-01-01T00:00:00Z'),
      makeRecord('b', '2025-01-03T00:00:00Z', '2025-01-01T00:00:00Z'),
    ];
    const servers = [
      makeRecord('a', '2025-01-02T00:00:00Z'), // server wins for 'a'
      makeRecord('b', '2025-01-02T00:00:00Z'), // local wins for 'b'
      makeRecord('c', '2025-01-01T00:00:00Z'), // new record
    ];
    const result = mergeSync(locals, servers);
    const upsertIds = result.toUpsert.map(r => r.local_id);
    expect(upsertIds).toContain('a');
    expect(upsertIds).toContain('c');
    expect(upsertIds).not.toContain('b');
  });
});

describe('getPendingUploads', () => {
  it('includes never-synced records', () => {
    const record = makeRecord('id1', '2025-01-01T00:00:00Z', null);
    expect(getPendingUploads([record])).toHaveLength(1);
  });

  it('excludes up-to-date records', () => {
    const ts = '2025-01-01T00:00:00Z';
    const record = makeRecord('id1', ts, ts);
    expect(getPendingUploads([record])).toHaveLength(0);
  });

  it('includes records updated after sync', () => {
    const record = makeRecord('id1', '2025-01-02T00:00:00Z', '2025-01-01T00:00:00Z');
    expect(getPendingUploads([record])).toHaveLength(1);
  });

  it('returns empty for empty input', () => {
    expect(getPendingUploads([])).toHaveLength(0);
  });
});

describe('isSyncDue', () => {
  it('returns true when never synced', () => {
    expect(isSyncDue(null, SYNC_INTERVALS.BACKGROUND_MS)).toBe(true);
  });

  it('returns true when interval has elapsed', () => {
    const past = new Date(Date.now() - SYNC_INTERVALS.BACKGROUND_MS - 1000).toISOString();
    expect(isSyncDue(past, SYNC_INTERVALS.BACKGROUND_MS)).toBe(true);
  });

  it('returns false when interval has not elapsed', () => {
    const recent = new Date(Date.now() - 1000).toISOString();
    expect(isSyncDue(recent, SYNC_INTERVALS.BACKGROUND_MS)).toBe(false);
  });
});

describe('computeSyncState', () => {
  it('reports pending when there are unsynced records', () => {
    const records = [makeRecord('id1', '2025-01-02T00:00:00Z', null)];
    const state = computeSyncState(records, null);
    expect(state.isPending).toBe(true);
    expect(state.pendingCount).toBe(1);
  });

  it('reports not pending when all synced', () => {
    const ts = '2025-01-01T00:00:00Z';
    const records = [makeRecord('id1', ts, ts)];
    const state = computeSyncState(records, ts);
    expect(state.isPending).toBe(false);
    expect(state.pendingCount).toBe(0);
  });

  it('includes lastError', () => {
    const state = computeSyncState([], null, 'Network error');
    expect(state.lastError).toBe('Network error');
  });
});

function makeQueueItem(id: string, overrides: Partial<SyncQueueItem> = {}): SyncQueueItem {
  return {
    id,
    entity: 'history',
    payload: '[]',
    attempts: 1,
    next_attempt_at: '2025-01-01T00:00:00Z',
    last_error: null,
    created_at: '2025-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('computeBackoffMs', () => {
  it('starts at 1 second', () => {
    expect(computeBackoffMs(0)).toBe(1000);
  });

  it('doubles per attempt: 1s → 2s → 4s → 8s', () => {
    expect(computeBackoffMs(1)).toBe(2000);
    expect(computeBackoffMs(2)).toBe(4000);
    expect(computeBackoffMs(3)).toBe(8000);
  });

  it('caps at 5 minutes', () => {
    expect(computeBackoffMs(9)).toBe(RETRY_BACKOFF.CAP_MS); // 512s > 300s
    expect(computeBackoffMs(50)).toBe(RETRY_BACKOFF.CAP_MS);
    expect(computeBackoffMs(1000)).toBe(RETRY_BACKOFF.CAP_MS);
  });

  it('clamps negative attempts to base delay', () => {
    expect(computeBackoffMs(-3)).toBe(1000);
  });
});

describe('nextAttemptAt', () => {
  it('schedules the first retry 1s out', () => {
    const now = Date.parse('2025-01-01T00:00:00Z');
    expect(nextAttemptAt(1, now)).toBe(new Date(now + 1000).toISOString());
  });

  it('schedules later retries with exponential delay', () => {
    const now = Date.parse('2025-01-01T00:00:00Z');
    expect(nextAttemptAt(3, now)).toBe(new Date(now + 4000).toISOString());
  });

  it('never exceeds the 5-minute cap', () => {
    const now = Date.parse('2025-01-01T00:00:00Z');
    expect(nextAttemptAt(30, now)).toBe(new Date(now + RETRY_BACKOFF.CAP_MS).toISOString());
  });
});

describe('isQueueItemDue / getDueQueueItems', () => {
  const now = Date.parse('2025-01-01T00:01:00Z');

  it('due when next_attempt_at is in the past', () => {
    expect(isQueueItemDue(makeQueueItem('a', { next_attempt_at: '2025-01-01T00:00:30Z' }), now)).toBe(true);
  });

  it('not due when next_attempt_at is in the future', () => {
    expect(isQueueItemDue(makeQueueItem('a', { next_attempt_at: '2025-01-01T00:02:00Z' }), now)).toBe(false);
  });

  it('filters to due items and sorts oldest first', () => {
    const items = [
      makeQueueItem('newer', { next_attempt_at: '2025-01-01T00:00:30Z', created_at: '2025-01-01T00:00:20Z' }),
      makeQueueItem('future', { next_attempt_at: '2025-01-01T00:05:00Z' }),
      makeQueueItem('older', { next_attempt_at: '2025-01-01T00:00:10Z', created_at: '2025-01-01T00:00:05Z' }),
    ];
    const due = getDueQueueItems(items, now);
    expect(due.map(i => i.id)).toEqual(['older', 'newer']);
  });

  it('after network recovers, an item queued at max backoff is due within one cycle', () => {
    const failedAt = Date.parse('2025-01-01T00:00:00Z');
    const item = makeQueueItem('a', { attempts: 20, next_attempt_at: nextAttemptAt(20, failedAt) });
    // One full cap-length backoff cycle later, the item is due
    expect(isQueueItemDue(item, failedAt + RETRY_BACKOFF.CAP_MS)).toBe(true);
  });
});

// ── Profile-scoped sync entity keys ─────────────────────────────────────────

describe('profileSyncEntityKey', () => {
  it('builds a colon-separated entity:profileId key', () => {
    expect(profileSyncEntityKey('bookmarks', 'default')).toBe('bookmarks:default');
    expect(profileSyncEntityKey('history', '42')).toBe('history:42');
    expect(profileSyncEntityKey('collections', 'ws-99')).toBe('collections:ws-99');
  });

  it('keeps the default profile key distinct from a numeric workspace id', () => {
    const personalKey = profileSyncEntityKey('bookmarks', 'default');
    const workspaceKey = profileSyncEntityKey('bookmarks', '1');
    expect(personalKey).not.toBe(workspaceKey);
  });

  it('each entity kind produces a unique key for the same profile', () => {
    const pid = 'default';
    const keys = ['bookmarks', 'collections', 'history'].map(e => profileSyncEntityKey(e, pid));
    const unique = new Set(keys);
    expect(unique.size).toBe(3);
  });

  it('same entity + different profiles produce different keys', () => {
    const k1 = profileSyncEntityKey('bookmarks', 'default');
    const k2 = profileSyncEntityKey('bookmarks', '7');
    const k3 = profileSyncEntityKey('bookmarks', '8');
    expect(k1).not.toBe(k2);
    expect(k2).not.toBe(k3);
  });
});
