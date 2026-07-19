/**
 * Integration tests: private windows must leave no trace behind.
 *
 * Uses the REAL modules (private-session, ipc-handlers, download-manager, db
 * with an in-memory SQLite database) and a mocked `electron` module whose fake
 * sessions carry actual cookie/storage state, so we can verify:
 *   - no rows land in `history` / `sync_queue` for private senders
 *   - no `downloads` rows are written for private-session downloads
 *   - the in-memory partition's cookies/storage/cache are wiped when the
 *     last private window closes
 */
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest';
import { EventEmitter } from 'events';

// ── Fake electron primitives ─────────────────────────────────────────────────

interface FakeSession {
  cookies: { store: Map<string, string>; get: (f: unknown) => Promise<unknown[]>; remove: (u: string, n: string) => Promise<void> };
  storageKeys: Set<string>;
  cacheEntries: Set<string>;
  clearStorageData: ReturnType<typeof vi.fn>;
  clearCache: ReturnType<typeof vi.fn>;
  on: (event: string, cb: (...args: unknown[]) => void) => void;
  emit: (event: string, ...args: unknown[]) => boolean;
  setCookie: (name: string, value: string) => void;
}

function makeFakeSession(): FakeSession {
  const emitter = new EventEmitter();
  const cookieStore = new Map<string, string>();
  const storageKeys = new Set<string>();
  const cacheEntries = new Set<string>();
  const sess: FakeSession = {
    cookies: {
      store: cookieStore,
      get: async () => [...cookieStore.entries()].map(([name, value]) => ({ name, value })),
      remove: async (_url: string, name: string) => { cookieStore.delete(name); },
    },
    storageKeys,
    cacheEntries,
    clearStorageData: vi.fn(async () => {
      cookieStore.clear();
      storageKeys.clear();
    }),
    clearCache: vi.fn(async () => {
      cacheEntries.clear();
    }),
    on: (event, cb) => { emitter.on(event, cb); },
    emit: (event, ...args) => emitter.emit(event, ...args),
    setCookie: (name, value) => { cookieStore.set(name, value); },
  };
  return sess;
}

interface FakeWindow {
  id: number;
  webContents: { id: number; send: ReturnType<typeof vi.fn> };
  once: (event: string, cb: () => void) => void;
  emitClosed: () => void;
}

const windowsByWebContents = new Map<unknown, FakeWindow>();
const partitionSessions = new Map<string, FakeSession>();
let fromPartitionCalls: string[] = [];
const ipcHandlers = new Map<string, (...args: unknown[]) => unknown>();

let nextWindowId = 1;
function makeFakeWindow(): FakeWindow {
  const emitter = new EventEmitter();
  const win: FakeWindow = {
    id: nextWindowId++,
    webContents: { id: nextWindowId * 1000, send: vi.fn() },
    once: (event, cb) => { emitter.once(event, cb); },
    emitClosed: () => { emitter.emit('closed'); },
  };
  windowsByWebContents.set(win.webContents, win);
  return win;
}

vi.mock('electron', () => {
  return {
    app: {
      getPath: (_name: string) => '/tmp/zio-browser-test',
    },
    session: {
      fromPartition: (partition: string) => {
        fromPartitionCalls.push(partition);
        let sess = partitionSessions.get(partition);
        if (!sess) {
          sess = makeFakeSession();
          partitionSessions.set(partition, sess);
        }
        return sess;
      },
      defaultSession: makeFakeSession(),
    },
    BrowserWindow: {
      fromWebContents: (wc: unknown) => windowsByWebContents.get(wc) ?? null,
    },
    ipcMain: {
      handle: (channel: string, fn: (...args: unknown[]) => unknown) => {
        ipcHandlers.set(channel, fn);
      },
    },
    safeStorage: {
      isEncryptionAvailable: () => false,
      encryptString: (s: string) => Buffer.from(s),
      decryptString: (b: Buffer) => b.toString(),
    },
    shell: { openPath: vi.fn(), showItemInFolder: vi.fn(), openExternal: vi.fn() },
    dialog: { showSaveDialog: vi.fn() },
    clipboard: { writeText: vi.fn(), readText: vi.fn() },
    nativeTheme: { shouldUseDarkColors: false, themeSource: 'system' },
    nativeImage: { createFromDataURL: vi.fn() },
  };
});

/** Invoke a captured ipcMain.handle handler as if `win`'s renderer sent it. */
function invoke(channel: string, win: FakeWindow, ...args: unknown[]): unknown {
  const handler = ipcHandlers.get(channel);
  if (!handler) throw new Error(`No handler registered for ${channel}`);
  return handler({ sender: win.webContents }, ...args);
}

// ── Fake DownloadItem ─────────────────────────────────────────────────────────

function makeFakeDownloadItem(url: string, filename: string) {
  const emitter = new EventEmitter();
  let savePath = '';
  return {
    getURL: () => url,
    getFilename: () => filename,
    getMimeType: () => 'application/octet-stream',
    getTotalBytes: () => 1000,
    getReceivedBytes: () => 1000,
    getSavePath: () => savePath,
    setSavePath: (p: string) => { savePath = p; },
    isPaused: () => false,
    on: (event: string, cb: (...a: unknown[]) => void) => emitter.on(event, cb),
    once: (event: string, cb: (...a: unknown[]) => void) => emitter.once(event, cb),
    emit: (event: string, ...a: unknown[]) => emitter.emit(event, ...a),
  };
}

// ── Test suite ────────────────────────────────────────────────────────────────

type Db = import('better-sqlite3').Database;

let db: Db;
let privateSessionMod: typeof import('../src/main/private-session');
let downloadManagerMod: typeof import('../src/main/download-manager');

function countRows(table: string): number {
  const row = db.prepare(`SELECT COUNT(*) AS n FROM ${table}`).get() as { n: number };
  return row.n;
}

beforeAll(async () => {
  // SyncRetryRunner starts a setInterval inside registerIpcHandlers; freeze it.
  vi.useFakeTimers();

  const dbMod = await import('../src/main/db');
  db = dbMod.initDb(':memory:');

  privateSessionMod = await import('../src/main/private-session');
  downloadManagerMod = await import('../src/main/download-manager');
  const ipcMod = await import('../src/main/ipc-handlers');

  const mainWindow = makeFakeWindow();
  ipcMod.registerIpcHandlers(mainWindow as never);
});

afterAll(() => {
  vi.useRealTimers();
});

describe('private window leaves no trace after closing', () => {
  it('full lifecycle: browse in private, close, verify nothing persisted', async () => {
    const { getPrivateSession, registerPrivateWindow, isPrivateWindow, privateWindowCount } =
      privateSessionMod;

    // Baselines
    expect(countRows('history')).toBe(0);
    expect(countRows('sync_queue')).toBe(0);
    expect(countRows('downloads')).toBe(0);

    // Open a private window on the shared in-memory partition
    const privateWin = makeFakeWindow();
    registerPrivateWindow(privateWin as never);
    expect(isPrivateWindow(privateWin as never)).toBe(true);
    expect(privateWindowCount()).toBe(1);

    const privateSession = getPrivateSession() as unknown as FakeSession;
    expect(fromPartitionCalls).toContain('private:zio-browser');

    // "Visit a URL": site sets a cookie + storage + cache in the private partition,
    // and the renderer fires the same IPC calls a normal page visit fires.
    privateSession.setCookie('session_id', 'secret-private-value');
    privateSession.storageKeys.add('localStorage:example.com');
    privateSession.cacheEntries.add('https://example.com/app.js');

    const historyResult = invoke('history:record', privateWin, 'https://example.com/secret', 'Secret Page');
    expect(historyResult).toBeNull();

    const syncResult = invoke('sync:queue-push', privateWin, 'history', JSON.stringify([{ local_id: 'x' }]));
    expect(syncResult).toBeNull();
    expect(invoke('sync:pending-count', privateWin)).toBe(0);
    expect(invoke('sync:pending-by-profile', privateWin)).toEqual([]);

    // "Download a file" through the private session — must not touch the DB
    downloadManagerMod.setupDownloadManager(privateSession as never, privateWin as never, true);
    const item = makeFakeDownloadItem('https://example.com/secret.pdf', 'secret.pdf');
    privateSession.emit('will-download', {}, item);
    item.emit('updated', {}, 'progressing');
    item.emit('done', {}, 'completed');

    // No history / sync / download rows were written while browsing
    expect(countRows('history')).toBe(0);
    expect(countRows('sync_queue')).toBe(0);
    expect(countRows('downloads')).toBe(0);

    // Close the (last) private window
    privateWin.emitClosed();
    await vi.waitFor(() => expect(privateSessionMod.privateWindowCount()).toBe(0));
    // teardown is async — flush microtasks
    await Promise.resolve();
    await Promise.resolve();

    // Partition data was purged
    expect(privateSession.clearStorageData).toHaveBeenCalled();
    expect(privateSession.clearCache).toHaveBeenCalled();
    expect(privateSession.cookies.store.size).toBe(0);
    expect(privateSession.storageKeys.size).toBe(0);
    expect(privateSession.cacheEntries.size).toBe(0);

    // And still zero rows anywhere after close
    expect(countRows('history')).toBe(0);
    expect(countRows('sync_queue')).toBe(0);
    expect(countRows('downloads')).toBe(0);
  });

  it('keeps the partition alive until the LAST private window closes', async () => {
    const { registerPrivateWindow, getPrivateSession, privateWindowCount } = privateSessionMod;

    const winA = makeFakeWindow();
    const winB = makeFakeWindow();
    registerPrivateWindow(winA as never);
    registerPrivateWindow(winB as never);
    expect(privateWindowCount()).toBe(2);

    const sess = getPrivateSession() as unknown as FakeSession;
    sess.clearStorageData.mockClear();
    sess.clearCache.mockClear();
    sess.setCookie('shared', 'value');

    // Closing one of two windows must NOT purge the shared partition
    winA.emitClosed();
    await Promise.resolve();
    expect(privateWindowCount()).toBe(1);
    expect(sess.clearStorageData).not.toHaveBeenCalled();
    expect(sess.cookies.store.size).toBe(1);

    // Closing the last one purges it
    winB.emitClosed();
    await Promise.resolve();
    await Promise.resolve();
    expect(privateWindowCount()).toBe(0);
    expect(sess.clearStorageData).toHaveBeenCalledTimes(1);
    expect(sess.clearCache).toHaveBeenCalledTimes(1);
    expect(sess.cookies.store.size).toBe(0);
  });

  it('creates a FRESH partition for the next private window after teardown', () => {
    const { getPrivateSession, registerPrivateWindow } = privateSessionMod;

    // The previous suite left zero private windows; the cached session was
    // dropped, so the next getPrivateSession() must call fromPartition again.
    fromPartitionCalls = [];
    const win = makeFakeWindow();
    registerPrivateWindow(win as never);
    getPrivateSession();
    expect(fromPartitionCalls).toEqual(['private:zio-browser']);
    win.emitClosed();
  });

  it('normal (non-private) windows still persist history, sync, and downloads', () => {
    const normalWin = makeFakeWindow();
    expect(privateSessionMod.isPrivateWindow(normalWin as never)).toBe(false);

    const entry = invoke('history:record', normalWin, 'https://example.com/normal', 'Normal Page');
    expect(entry).not.toBeNull();
    expect(countRows('history')).toBe(1);

    const queueId = invoke('sync:queue-push', normalWin, 'history', JSON.stringify([{ local_id: 'n1' }]));
    expect(queueId).not.toBeNull();
    expect(countRows('sync_queue')).toBe(1);

    const normalSession = makeFakeSession();
    downloadManagerMod.setupDownloadManager(normalSession as never, normalWin as never, false);
    const item = makeFakeDownloadItem('https://example.com/file.zip', 'file.zip');
    normalSession.emit('will-download', {}, item);
    item.emit('done', {}, 'completed');
    expect(countRows('downloads')).toBe(1);

    const row = db.prepare('SELECT url, state FROM downloads').get() as { url: string; state: string };
    expect(row.url).toBe('https://example.com/file.zip');
    expect(row.state).toBe('completed');
  });
});
