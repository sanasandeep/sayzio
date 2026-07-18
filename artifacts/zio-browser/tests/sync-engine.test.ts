import { describe, it, expect, vi } from 'vitest';
import {
  mergeSync,
  getPendingUploads,
  isSyncDue,
  computeSyncState,
  SYNC_INTERVALS,
  type SyncRecord,
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
