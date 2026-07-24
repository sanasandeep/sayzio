/**
 * Sync retry runner — drains the persisted `sync_queue` table with
 * exponential back-off so failed sync pushes are never silently dropped.
 *
 * Runs in the Electron main process. Push execution is injected so the
 * runner itself stays testable and the queue survives app restarts
 * (items live in SQLite, not memory).
 */
import {
  getDueQueueItems,
  RETRY_BACKOFF,
  type SyncEntityKind,
  type SyncQueueItem,
} from '../shared/sync-engine';
import type { SyncItem } from '../shared/api-client';
import { ApiClient } from '../shared/api-client';
import {
  getSyncQueueItems,
  countSyncQueue,
  markSyncQueueFailure,
  removeSyncQueueItem,
  markRecordsSynced,
  getPreference,
  getActiveProfileId,
  getProfileWorkspaceId,
  profileExists,
} from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import { retrieveToken } from './auth-store';

export type SyncPushFn = (entity: SyncEntityKind, items: SyncItem[], profileId?: string | null) => Promise<void>;

export interface SyncRetryRunnerOptions {
  /** Executes an actual push. Defaults to the ApiClient-based pusher. */
  pushFn?: SyncPushFn;
  /** Called whenever the queue size changes (enqueue handled by caller). */
  onQueueChanged?: (pendingCount: number) => void;
  /** Poll interval for due items, ms. */
  tickMs?: number;
}

const DEFAULT_TICK_MS = 1000;

/**
 * Default pusher: talks to the Sayzio /api/v1/browser/* endpoints using the
 * stored auth token, configured base URL, and registered device id.
 * Throws when auth/device configuration is missing so the item stays queued.
 */
export async function defaultSyncPush(entity: SyncEntityKind, items: SyncItem[], profileId?: string | null): Promise<void> {
  const token = retrieveToken();
  if (!token) throw new Error('Not signed in');

  const baseUrl = getPreference(PREFERENCE_KEYS.SAYZIO_API_BASE_URL);
  const deviceId = getPreference(PREFERENCE_KEYS.DEVICE_ID);
  if (!baseUrl || !deviceId) throw new Error('Sync not configured (missing base URL or device id)');

  // Scope the push to the workspace bucket of the profile the item was
  // enqueued under (so retries land in the right bucket even after a profile
  // switch). Legacy rows without a recorded profile fall back to the active
  // profile. Workspace profiles carry X-Browser-Workspace-Id; the personal
  // profile sends none.
  const workspaceId = getProfileWorkspaceId(profileId ?? getActiveProfileId());

  const client = new ApiClient({ baseUrl, token, workspaceId });
  if (entity === 'bookmarks') await client.syncBookmarks(deviceId, items);
  else if (entity === 'collections') await client.syncCollections(deviceId, items);
  else await client.syncHistory(deviceId, items);
}

export class SyncRetryRunner {
  private timer: NodeJS.Timeout | null = null;
  private processing = false;
  private readonly pushFn: SyncPushFn;
  private readonly onQueueChanged: ((pendingCount: number) => void) | undefined;
  private readonly tickMs: number;

  constructor(options: SyncRetryRunnerOptions = {}) {
    this.pushFn = options.pushFn ?? defaultSyncPush;
    this.onQueueChanged = options.onQueueChanged;
    this.tickMs = options.tickMs ?? DEFAULT_TICK_MS;
  }

  start(): void {
    if (this.timer) return;
    this.timer = setInterval(() => { void this.tick(); }, this.tickMs);
    // Kick immediately so items queued before a restart flush without waiting
    void this.tick();
  }

  stop(): void {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }

  /**
   * Attempt every due item once. Returns the number of items flushed.
   * Ignores items whose back-off window has not elapsed (cap 5 min —
   * see RETRY_BACKOFF in sync-engine).
   */
  async tick(nowMs: number = Date.now()): Promise<number> {
    if (this.processing) return 0;
    this.processing = true;
    let flushed = 0;
    try {
      const due = getDueQueueItems(getSyncQueueItems(), nowMs);
      for (const item of due) {
        const ok = await this.attempt(item);
        if (ok) flushed += 1;
      }
    } catch (err) {
      // Never let a background tick crash the process (e.g. DB unavailable) —
      // log and try again on the next interval.
      console.error('Sync retry tick failed:', err);
    } finally {
      this.processing = false;
    }
    if (flushed > 0) this.notify();
    return flushed;
  }

  /** Force-attempt every queued item regardless of back-off (manual flush). */
  async flushAll(): Promise<number> {
    return this.tick(Date.now() + RETRY_BACKOFF.CAP_MS + 1);
  }

  notify(): void {
    this.onQueueChanged?.(countSyncQueue());
  }

  private async attempt(item: SyncQueueItem): Promise<boolean> {
    let items: SyncItem[];
    try {
      items = JSON.parse(item.payload) as SyncItem[];
    } catch {
      // Corrupt payload — drop it rather than retrying forever
      removeSyncQueueItem(item.id);
      return true;
    }

    // If the profile this item was enqueued under has since been deleted,
    // drop the item instead of pushing — otherwise the workspace lookup
    // would resolve to null and the data would land in the personal bucket.
    if (item.profile_id !== null && !profileExists(item.profile_id)) {
      removeSyncQueueItem(item.id);
      return true;
    }

    try {
      await this.pushFn(item.entity, items, item.profile_id ?? null);
      removeSyncQueueItem(item.id);
      markRecordsSynced(item.entity, items.map(i => i.local_id));
      return true;
    } catch (err) {
      markSyncQueueFailure(item.id, err instanceof Error ? err.message : String(err));
      return false;
    }
  }
}
