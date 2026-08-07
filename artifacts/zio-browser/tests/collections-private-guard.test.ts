/**
 * Regression guard: private/incognito windows must never read or mutate
 * collections ("folders" on the new-tab page) through the IPC bridge.
 *
 * The new-tab page renders folders, and the toolbar can save pages into
 * them — both via window.zio.collections. tests/collection-store.test.ts
 * covers the SQLite store; this suite covers the REAL 'collections:*'
 * handlers registered by registerIpcHandlers(), asserting that:
 *   - reads from private senders return empty results (no data exposure)
 *   - writes from private senders are no-ops (nothing persisted)
 * so a future handler edit cannot silently re-expose normal-profile data.
 */
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest';
import { EventEmitter } from 'events';

// ── Fake electron primitives (same shape as toolbar-pins-ipc.test.ts) ───────

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

/** Invoke a captured ipcMain.handle handler as if `win`'s renderer sent it. */
function invoke(channel: string, win: FakeWindow, ...args: unknown[]): unknown {
  const handler = ipcHandlers.get(channel);
  if (!handler) throw new Error(`No handler registered for ${channel}`);
  return handler({ sender: win.webContents }, ...args);
}

type Db = import('better-sqlite3').Database;

let rawDb: Db;
let privateSessionMod: typeof import('../src/main/private-session');

function makePrivateWindow(): FakeWindow {
  const win = makeFakeWindow();
  privateSessionMod.registerPrivateWindow(win as never);
  return win;
}

interface CollectionRow { id: string; name: string; item_count?: number }
interface SavedLinkRow { id: string; url: string }

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

describe('collections IPC private-window guard', () => {
  it('registers every collections handler the preload bridge invokes', () => {
    for (const channel of [
      'collections:all', 'collections:create', 'collections:update',
      'collections:delete', 'collections:get-links', 'collections:save-link',
      'collections:update-ai',
    ]) {
      expect(ipcHandlers.has(channel), channel).toBe(true);
    }
  });

  it('normal windows can create folders and save links (baseline)', () => {
    const win = makeFakeWindow();
    const created = invoke('collections:create', win, 'Docs') as CollectionRow | null;
    expect(created?.name).toBe('Docs');

    const link = invoke('collections:save-link', win, created!.id, 'https://example.com/a', 'A') as SavedLinkRow | null;
    expect(link?.url).toBe('https://example.com/a');

    const all = invoke('collections:all', win) as CollectionRow[];
    expect(all.map(c => c.name)).toContain('Docs');
    const links = invoke('collections:get-links', win, created!.id) as SavedLinkRow[];
    expect(links).toHaveLength(1);
  });

  it('private senders read nothing: collections:all and get-links return empty', () => {
    const privateWin = makePrivateWindow();
    expect(privateSessionMod.isPrivateWindow(privateWin as never)).toBe(true);

    // Normal-profile data exists (created above) but must not be visible.
    expect(invoke('collections:all', privateWin)).toEqual([]);

    const existing = (invoke('collections:all', makeFakeWindow()) as CollectionRow[])[0];
    expect(existing).toBeDefined();
    expect(invoke('collections:get-links', privateWin, existing.id)).toEqual([]);
  });

  it('private senders write nothing: create/save-link/update/delete are no-ops', () => {
    const privateWin = makePrivateWindow();
    const before = invoke('collections:all', makeFakeWindow()) as CollectionRow[];
    const target = before[0];

    expect(invoke('collections:create', privateWin, 'Sneaky')).toBeNull();
    expect(invoke('collections:save-link', privateWin, target.id, 'https://evil.example/x', 'X')).toBeNull();
    expect(invoke('collections:update', privateWin, target.id, { name: 'Renamed' })).toBe(false);
    expect(invoke('collections:delete', privateWin, target.id)).toBe(false);
    expect(invoke('collections:update-ai', privateWin, target.id, 's', [], 'c', 0)).toBe(false);

    // Durable state unchanged — via IPC read and straight from SQLite.
    const after = invoke('collections:all', makeFakeWindow()) as CollectionRow[];
    expect(after.map(c => c.name)).toEqual(before.map(c => c.name));
    const rows = rawDb.prepare("SELECT name FROM collections WHERE deleted = 0").all() as Array<{ name: string }>;
    expect(rows.map(r => r.name)).not.toContain('Sneaky');
    const linkRows = rawDb.prepare('SELECT url FROM saved_links WHERE deleted = 0').all() as Array<{ url: string }>;
    expect(linkRows.map(r => r.url)).not.toContain('https://evil.example/x');
    const links = invoke('collections:get-links', makeFakeWindow(), target.id) as SavedLinkRow[];
    expect(links).toHaveLength(1);
  });
});
