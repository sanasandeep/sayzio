/**
 * IPC-layer tests for pinned toolbar tools.
 *
 * tests/toolbar-pins-persistence.test.ts covers the main-process SQLite store
 * directly; this suite covers the remaining hop: the REAL 'prefs:get' /
 * 'prefs:set' / 'prefs:all' handlers registered by registerIpcHandlers()
 * (the same handlers window.zio.prefs in src/preload invokes) driven
 * end-to-end against the in-memory DB. It also verifies private-window
 * senders cannot clobber durable pins through 'prefs:set'.
 */
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest';
import { EventEmitter } from 'events';
import {
  PINNED_TOOLS_PREF_KEY,
  parsePinnedTools,
  serializePinnedTools,
  type PinnableTool,
} from '../src/shared/toolbar-pins';
import { PREFERENCE_KEYS } from '../src/shared/db-schema';

// ── Fake electron primitives (same shape as private-session.test.ts) ────────

function makeFakeSession() {
  const emitter = new EventEmitter();
  return {
    cookies: { get: async () => [], remove: async () => {} },
    clearStorageData: vi.fn(async () => {}),
    clearCache: vi.fn(async () => {}),
    on: (event: string, cb: (...args: unknown[]) => void) => { emitter.on(event, cb); },
    emit: (event: string, ...args: unknown[]) => emitter.emit(event, ...args),
  };
}

interface FakeWindow {
  id: number;
  webContents: { id: number; send: ReturnType<typeof vi.fn> };
  once: (event: string, cb: () => void) => void;
  emitClosed: () => void;
}

const windowsByWebContents = new Map<unknown, FakeWindow>();
const partitionSessions = new Map<string, ReturnType<typeof makeFakeSession>>();
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

/** Invoke a captured ipcMain.handle handler as if `win`'s renderer sent it —
 *  exactly what ipcRenderer.invoke via the window.zio.prefs preload bridge does. */
function invoke(channel: string, win: FakeWindow, ...args: unknown[]): unknown {
  const handler = ipcHandlers.get(channel);
  if (!handler) throw new Error(`No handler registered for ${channel}`);
  return handler({ sender: win.webContents }, ...args);
}

type Db = import('better-sqlite3').Database;

let rawDb: Db;
let privateSessionMod: typeof import('../src/main/private-session');

function storedPinsRow(): { value: string } | undefined {
  return rawDb
    .prepare('SELECT value FROM preferences WHERE key = ?')
    .get(PINNED_TOOLS_PREF_KEY) as { value: string } | undefined;
}

beforeAll(async () => {
  // SyncRetryRunner starts a setInterval inside registerIpcHandlers; freeze it.
  vi.useFakeTimers();

  const dbMod = await import('../src/main/db');
  rawDb = dbMod.initDb(':memory:');

  privateSessionMod = await import('../src/main/private-session');
  const ipcMod = await import('../src/main/ipc-handlers');

  const mainWindow = makeFakeWindow();
  ipcMod.registerIpcHandlers(mainWindow as never);
});

afterAll(() => {
  vi.useRealTimers();
});

describe('pinned toolbar tools through the prefs IPC bridge', () => {
  it('registers the prefs handlers the preload bridge invokes', () => {
    expect(ipcHandlers.has('prefs:get')).toBe(true);
    expect(ipcHandlers.has('prefs:set')).toBe(true);
    expect(ipcHandlers.has('prefs:all')).toBe(true);
  });

  it('round-trips pinned tools through prefs:set → prefs:get (restart path)', () => {
    const win = makeFakeWindow();
    const pins: PinnableTool[] = ['dialer', 'screenshot'];

    const setResult = invoke('prefs:set', win, PINNED_TOOLS_PREF_KEY, serializePinnedTools(pins));
    expect(setResult).toBe(true);

    // Reading back via prefs:get is exactly what the renderer does on next
    // launch — a different window simulates the fresh post-restart renderer.
    const freshWin = makeFakeWindow();
    const stored = invoke('prefs:get', freshWin, PINNED_TOOLS_PREF_KEY);
    expect(stored).not.toBeNull();
    expect(parsePinnedTools(stored as string)).toEqual(pins);

    // And the value actually reached the durable SQLite table.
    const row = storedPinsRow();
    expect(row).toBeDefined();
    expect(JSON.parse(row!.value)).toEqual(pins);
  });

  it('prefs:all includes the pinned tools key (bulk hydrate on boot)', () => {
    const all = invoke('prefs:all', makeFakeWindow()) as Record<string, string>;
    expect(all[PINNED_TOOLS_PREF_KEY]).toBeDefined();
    expect(parsePinnedTools(all[PINNED_TOOLS_PREF_KEY])).toEqual(['dialer', 'screenshot']);
  });

  it('the pref key used by the bridge is the registered preference key', () => {
    // A key-filter regression in the handlers/schema would break the bridge
    // while renderer + db tests stay green — pin the contract here.
    expect(PREFERENCE_KEYS.PINNED_TOOLBAR_TOOLS).toBe(PINNED_TOOLS_PREF_KEY);
  });

  it('private-window senders cannot clobber durable pins via prefs:set', () => {
    // Durable pins written by a normal window...
    const normalWin = makeFakeWindow();
    invoke('prefs:set', normalWin, PINNED_TOOLS_PREF_KEY, serializePinnedTools(['dialer', 'device_lab']));

    // ...must survive a private window attempting to overwrite them.
    const privateWin = makeFakeWindow();
    privateSessionMod.registerPrivateWindow(privateWin as never);
    expect(privateSessionMod.isPrivateWindow(privateWin as never)).toBe(true);

    const result = invoke('prefs:set', privateWin, PINNED_TOOLS_PREF_KEY, serializePinnedTools(['screenshot']));
    expect(result).toBe(false);

    // Durable value unchanged — both via IPC read and straight from SQLite.
    const stored = invoke('prefs:get', makeFakeWindow(), PINNED_TOOLS_PREF_KEY);
    expect(parsePinnedTools(stored as string)).toEqual(['dialer', 'device_lab']);
    expect(JSON.parse(storedPinsRow()!.value)).toEqual(['dialer', 'device_lab']);

    // Private windows can still READ shared pins (display-only).
    const privateRead = invoke('prefs:get', privateWin, PINNED_TOOLS_PREF_KEY);
    expect(parsePinnedTools(privateRead as string)).toEqual(['dialer', 'device_lab']);

    privateWin.emitClosed();
  });
});
