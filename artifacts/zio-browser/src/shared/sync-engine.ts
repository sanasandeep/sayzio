/**
 * Cloud sync engine for the Zio Browser.
 * Implements last-write-wins merge with tombstones for deletes.
 * Syncs bookmarks, collections, and history to/from /api/v1/browser/*.
 *
 * Pure logic module — no Electron, no SQLite, no fetch — all dependencies
 * injected via interfaces so the logic is fully testable.
 */

export interface SyncRecord {
  local_id: string;
  server_id?: string;
  updated_at: string; // ISO-8601
  deleted: boolean;
  synced_at?: string | null;
  data: Record<string, unknown>;
}

export interface SyncConflict {
  local_id: string;
  local_updated_at: string;
  server_updated_at: string;
  resolution: 'local_wins' | 'server_wins';
}

export interface MergeResult {
  toUpsert: SyncRecord[];   // records to write locally
  conflicts: SyncConflict[];
}

/**
 * Merge a batch of server records into local records using last-write-wins
 * semantics. The record with the latest `updated_at` wins.
 *
 * @param localRecords - records from the local SQLite store
 * @param serverRecords - records pulled from the API
 * @returns list of records to upsert locally and any conflicts noted
 */
export function mergeSync(
  localRecords: SyncRecord[],
  serverRecords: SyncRecord[],
): MergeResult {
  const toUpsert: SyncRecord[] = [];
  const conflicts: SyncConflict[] = [];

  const localMap = new Map<string, SyncRecord>(
    localRecords.map(r => [r.local_id, r]),
  );

  for (const server of serverRecords) {
    const local = localMap.get(server.local_id);

    if (!local) {
      // Not present locally — insert from server (unless it's a tombstone)
      if (!server.deleted) {
        toUpsert.push({ ...server, synced_at: new Date().toISOString() });
      }
      continue;
    }

    // Both exist — compare timestamps
    const localTs = new Date(local.updated_at).getTime();
    const serverTs = new Date(server.updated_at).getTime();

    if (serverTs > localTs) {
      // Server wins
      toUpsert.push({ ...server, synced_at: new Date().toISOString() });
      if (local.synced_at && localTs > new Date(local.synced_at).getTime()) {
        // Local had unsynchronized changes that got overwritten — log conflict
        conflicts.push({
          local_id: local.local_id,
          local_updated_at: local.updated_at,
          server_updated_at: server.updated_at,
          resolution: 'server_wins',
        });
      }
    } else if (localTs > serverTs) {
      // Local wins — no local upsert needed, but flag the conflict if server
      // had been synced before (should not happen in normal flow)
      if (local.synced_at) {
        conflicts.push({
          local_id: local.local_id,
          local_updated_at: local.updated_at,
          server_updated_at: server.updated_at,
          resolution: 'local_wins',
        });
      }
    }
    // Equal timestamps — already in sync, nothing to do
  }

  return { toUpsert, conflicts };
}

/**
 * Prepare the set of local records that need to be pushed to the server.
 * Returns records that have been modified since they were last synced.
 */
export function getPendingUploads(localRecords: SyncRecord[]): SyncRecord[] {
  return localRecords.filter(r => {
    if (!r.synced_at) return true; // Never synced
    const updatedTs = new Date(r.updated_at).getTime();
    const syncedTs = new Date(r.synced_at).getTime();
    return updatedTs > syncedTs;
  });
}

/**
 * Check whether a sync is needed based on the last sync timestamp.
 */
export function isSyncDue(lastSyncAt: string | null, intervalMs: number): boolean {
  if (!lastSyncAt) return true;
  const elapsed = Date.now() - new Date(lastSyncAt).getTime();
  return elapsed >= intervalMs;
}

export const SYNC_INTERVALS = {
  /** Background sync interval in ms (5 minutes) */
  BACKGROUND_MS: 5 * 60 * 1000,
  /** Minimum interval between manual syncs (30 seconds) */
  MANUAL_MIN_MS: 30 * 1000,
} as const;

export type SyncEntityKind = 'bookmarks' | 'collections' | 'history' | 'reading_list';

export interface SyncState {
  lastSyncAt: string | null;
  isPending: boolean;
  lastError: string | null;
  pendingCount: number;
}

// ── Retry queue ──────────────────────────────────────────────────────────────

export const RETRY_BACKOFF = {
  /** First retry delay in ms (1 second) */
  BASE_MS: 1000,
  /** Maximum retry delay in ms (5 minutes) */
  CAP_MS: 5 * 60 * 1000,
} as const;

/**
 * A persisted sync push that failed and is awaiting retry.
 * Mirrors a row in the `sync_queue` SQLite table.
 */
export interface SyncQueueItem {
  id: string;
  entity: SyncEntityKind;
  /** JSON-serialized array of SyncItem payloads to re-push */
  payload: string;
  attempts: number;
  next_attempt_at: string; // ISO-8601
  last_error: string | null;
  created_at: string;
  /**
   * Profile the item was enqueued under, so retries push to that profile's
   * workspace bucket even if the user switches profiles meanwhile.
   * Null on rows enqueued before this column existed — those fall back to
   * the active profile at retry time (legacy behavior).
   */
  profile_id: string | null;
}

/**
 * Exponential back-off delay for a given attempt count:
 * 1 s → 2 s → 4 s → 8 s → ... capped at 5 minutes.
 *
 * @param attempts - number of failed attempts so far (>= 0)
 */
export function computeBackoffMs(attempts: number): number {
  const n = Math.max(0, attempts);
  // Guard against overflow for large attempt counts: 2^19 s already > cap.
  if (n >= 20) return RETRY_BACKOFF.CAP_MS;
  return Math.min(RETRY_BACKOFF.BASE_MS * 2 ** n, RETRY_BACKOFF.CAP_MS);
}

/**
 * Compute the next attempt timestamp after a failed push.
 *
 * @param attempts - the attempt count AFTER the failure (i.e. attempts so far)
 * @param nowMs - current time in ms since epoch
 */
export function nextAttemptAt(attempts: number, nowMs: number = Date.now()): string {
  return new Date(nowMs + computeBackoffMs(attempts - 1)).toISOString();
}

/**
 * Whether a queue item is due for a retry attempt.
 */
export function isQueueItemDue(item: SyncQueueItem, nowMs: number = Date.now()): boolean {
  return new Date(item.next_attempt_at).getTime() <= nowMs;
}

/**
 * Filter the queue down to items due for retry, oldest first so pushes
 * replay in the order they were originally attempted.
 */
export function getDueQueueItems(items: SyncQueueItem[], nowMs: number = Date.now()): SyncQueueItem[] {
  return items
    .filter(i => isQueueItemDue(i, nowMs))
    .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
}

/**
 * Compute a sync state summary from local records.
 */
export function computeSyncState(
  localRecords: SyncRecord[],
  lastSyncAt: string | null,
  lastError: string | null = null,
): SyncState {
  const pending = getPendingUploads(localRecords);
  return {
    lastSyncAt,
    isPending: pending.length > 0,
    lastError,
    pendingCount: pending.length,
  };
}

/**
 * Build the sync_state entity key that isolates sync cursors per profile.
 * Keeps sync timestamps per profile so switching workspace doesn't replay
 * another profile's already-synced records.
 *
 * @example profileSyncEntityKey('bookmarks', 'default') => 'bookmarks:default'
 * @example profileSyncEntityKey('history', '42')       => 'history:42'
 */
export function profileSyncEntityKey(entity: string, profileId: string): string {
  return `${entity}:${profileId}`;
}
