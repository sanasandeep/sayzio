/**
 * Private session management for Zio Browser incognito mode.
 *
 * A single named non-persistent Electron session partition is shared across
 * all private windows.  When the last private window closes, the partition
 * data (cookies, cache, storage) is wiped so nothing lingers on disk.
 */
import { session, BrowserWindow } from 'electron';
import type { Session } from 'electron';

const PRIVATE_PARTITION = 'private:zio-browser';

let _privateSession: Session | null = null;
const _privateWindows = new Set<number>();

/**
 * Return (and lazily create) the shared in-memory private session.
 * The partition name has no "persist:" prefix so Electron keeps it in RAM.
 */
export function getPrivateSession(): Session {
  if (!_privateSession) {
    _privateSession = session.fromPartition(PRIVATE_PARTITION);
  }
  return _privateSession;
}

/**
 * Register a new private window so teardown knows how many are open.
 */
export function registerPrivateWindow(win: BrowserWindow): void {
  _privateWindows.add(win.id);
  win.once('closed', () => onPrivateWindowClosed(win.id));
}

/**
 * Return true if the given BrowserWindow is a private window.
 */
export function isPrivateWindow(win: BrowserWindow): boolean {
  return _privateWindows.has(win.id);
}

/**
 * Called when any private window closes.  If it was the last one, purge
 * the session data so cookies/cache don't survive until next launch.
 */
async function onPrivateWindowClosed(winId: number): Promise<void> {
  _privateWindows.delete(winId);
  if (_privateWindows.size === 0 && _privateSession) {
    try {
      await _privateSession.clearStorageData();
      await _privateSession.clearCache();
    } catch {
      // Best-effort — session may already be gone
    }
    _privateSession = null;
  }
}

/**
 * How many private windows are currently open.
 */
export function privateWindowCount(): number {
  return _privateWindows.size;
}
