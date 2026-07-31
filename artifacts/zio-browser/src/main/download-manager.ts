/**
 * Download manager for Zio Browser.
 * Intercepts Electron download events and tracks them in the local DB.
 * Maintains a live registry of in-progress DownloadItem references so the
 * renderer can pause, resume, and cancel active downloads via IPC.
 * In private mode downloads complete normally but are NOT written to the DB.
 */
import path from 'path';
import { app, BrowserWindow } from 'electron';
import type { Session, DownloadItem } from 'electron';
import { generateId } from '../shared/collection-store';
import { isViewableTextDownload } from '../shared/omnibox';
import { recordDownload, updateDownload } from './db';
import { getPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

// ── Live download registry ────────────────────────────────────────────────────
// Keyed by the download id we generate; populated while the item is in-progress.

const _activeItems = new Map<string, DownloadItem>();

export function getActiveItem(id: string): DownloadItem | undefined {
  return _activeItems.get(id);
}

export function getAllActiveItems(): Map<string, DownloadItem> {
  return _activeItems;
}

// ── Speed tracking ────────────────────────────────────────────────────────────

interface SpeedSample {
  bytes: number;
  ts: number;
}

const _speedSamples = new Map<string, SpeedSample>();

function computeSpeed(id: string, receivedBytes: number): number {
  const now = Date.now();
  const prev = _speedSamples.get(id);
  _speedSamples.set(id, { bytes: receivedBytes, ts: now });
  if (!prev || now === prev.ts) return 0;
  const elapsed = (now - prev.ts) / 1000;
  return Math.round((receivedBytes - prev.bytes) / elapsed);
}

// ── Setup ─────────────────────────────────────────────────────────────────────

export function setupDownloadManager(
  sess: Session,
  win: BrowserWindow,
  isPrivate = false,
): void {
  sess.on('will-download', (_, item: DownloadItem) => {
    const id = generateId();
    const filename = item.getFilename();

    // Determine save path from user preference
    const prefPath = getPreference(PREFERENCE_KEYS.DOWNLOAD_PATH);
    const downloadDir = prefPath ?? app.getPath('downloads');
    const alwaysAsk = getPreference(PREFERENCE_KEYS.DOWNLOAD_ASK) === '1';
    let savePath = path.join(downloadDir, filename);
    if (alwaysAsk) {
      // Let Electron show the native "Save As" dialog for this download.
      // Not calling setSavePath() triggers the OS picker automatically.
      item.setSaveDialogOptions({ title: 'Save File', defaultPath: savePath });
      savePath = '';
    } else {
      item.setSavePath(savePath);
    }

    // Register live item reference
    _activeItems.set(id, item);
    _speedSamples.set(id, { bytes: 0, ts: Date.now() });

    // Persist to DB (skip for private/incognito mode)
    if (!isPrivate) {
      recordDownload({
        id,
        url: item.getURL(),
        filename,
        save_path: savePath,
        mime_type: item.getMimeType() || null,
        total_bytes: item.getTotalBytes() || null,
        received_bytes: 0,
        state: 'progressing',
      });
    }

    // Notify renderer: download started (always — private downloads still show in the active session UI)
    win.webContents.send('download:started', {
      id,
      url: item.getURL(),
      filename,
      savePath,
      totalBytes: item.getTotalBytes() || null,
      mimeType: item.getMimeType() || null,
      isPrivate,
      isText: isViewableTextDownload(filename, item.getMimeType() || null),
    });

    item.on('updated', (__, state) => {
      const received = item.getReceivedBytes();
      const total = item.getTotalBytes();
      const speedBps = computeSpeed(id, received);

      if (!isPrivate) {
        updateDownload(id, {
          received_bytes: received,
          total_bytes: total || null,
          state: state === 'progressing' ? 'progressing' : 'interrupted',
        });
      }

      win.webContents.send('download:progress', {
        id,
        receivedBytes: received,
        totalBytes: total || null,
        speedBps,
        state,
        isPaused: item.isPaused(),
      });
    });

    item.once('done', (__, state) => {
      _activeItems.delete(id);
      _speedSamples.delete(id);

      const finalPath = item.getSavePath() ?? savePath;
      const completed = state === 'completed';

      if (!isPrivate) {
        updateDownload(id, {
          state: state as 'completed' | 'interrupted' | 'cancelled',
          completed_at: completed ? new Date().toISOString() : null,
          save_path: finalPath,
        });
      }

      win.webContents.send('download:done', {
        id,
        state,
        savePath: finalPath,
        filename,
        isPrivate,
        isText: isViewableTextDownload(filename, item.getMimeType() || null),
      });
    });
  });
}
