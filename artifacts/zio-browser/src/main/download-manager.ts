/**
 * Download manager for Zio Browser.
 * Intercepts Electron download events and tracks them in the local DB.
 */
import path from 'path';
import { app, BrowserWindow } from 'electron';
import type { Session, DownloadItem } from 'electron';
import { generateId } from '../shared/collection-store';
import { recordDownload, updateDownload } from './db';
import { getPreference } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

export function setupDownloadManager(sess: Session, win: BrowserWindow): void {
  sess.on('will-download', (_, item: DownloadItem) => {
    const id = generateId();
    const filename = item.getFilename();

    // Determine download path
    const prefPath = getPreference(PREFERENCE_KEYS.DOWNLOAD_PATH);
    const downloadDir = prefPath ?? app.getPath('downloads');
    const savePath = path.join(downloadDir, filename);

    item.setSavePath(savePath);

    // Record the download
    recordDownload({
      id,
      url: item.getURL(),
      filename,
      save_path: savePath,
      mime_type: item.getMimeType() || null,
      total_bytes: item.getTotalBytes() || null,
      received_bytes: 0,
      state: 'pending',
    });

    // Notify renderer
    win.webContents.send('download:started', {
      id,
      url: item.getURL(),
      filename,
      totalBytes: item.getTotalBytes(),
    });

    item.on('updated', (__, state) => {
      const received = item.getReceivedBytes();
      const total = item.getTotalBytes();

      updateDownload(id, {
        received_bytes: received,
        total_bytes: total || null,
        state: state === 'progressing' ? 'progressing' : 'interrupted',
      });

      win.webContents.send('download:progress', {
        id,
        receivedBytes: received,
        totalBytes: total,
        state,
      });
    });

    item.once('done', (__, state) => {
      const completed = state === 'completed';
      updateDownload(id, {
        state: state as 'completed' | 'interrupted' | 'cancelled',
        completed_at: completed ? new Date().toISOString() : null,
        save_path: item.getSavePath() ?? savePath,
      });

      win.webContents.send('download:done', {
        id,
        state,
        savePath: item.getSavePath(),
        filename,
      });
    });
  });
}
