/**
 * Regression test: permission handlers (camera/mic/screen-sharing/location +
 * the display-media handler) must be installed on a profile partition session
 * whenever it is activated or pre-warmed via IPC — not only for the startup
 * profile in index.ts. Tabs run in `persist:zio-profile-*` partitions, so a
 * session without these handlers would silently skip permission gating.
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
  };
  windowsByWebContents.set(win.webContents, win);
  return win;
}

vi.mock('electron', () => {
  return {
    app: { getPath: (_name: string) => '/tmp/zio-browser-test' },
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
      on: vi.fn(),
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

// Spy on the permission-handler install; keep everything else real.
const setupPermissionHandlersSpy = vi.hoisted(() => vi.fn());
vi.mock('../src/main/permission-handler', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../src/main/permission-handler')>();
  return { ...actual, setupPermissionHandlers: setupPermissionHandlersSpy };
});

// The privacy/tracker hooks poke at session.webRequest, which the fake session
// doesn't implement — stub them so the try-block reaches the permission install.
vi.mock('../src/main/privacy', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../src/main/privacy')>();
  return { ...actual, installPrivacyHooks: vi.fn() };
});
vi.mock('../src/main/tracker-blocker', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../src/main/tracker-blocker')>();
  return { ...actual, installTrackerHooks: vi.fn() };
});

function invoke(channel: string, win: FakeWindow, ...args: unknown[]): unknown {
  const handler = ipcHandlers.get(channel);
  if (!handler) throw new Error(`No handler registered for ${channel}`);
  return handler({ sender: win.webContents }, ...args);
}

beforeAll(async () => {
  vi.useFakeTimers();
  const dbMod = await import('../src/main/db');
  dbMod.initDb(':memory:');
  const ipcMod = await import('../src/main/ipc-handlers');
  ipcMod.registerIpcHandlers(makeFakeWindow() as never);
});

afterAll(() => {
  vi.useRealTimers();
});

describe('profile session transitions install permission handlers', () => {
  it('profiles:warm-session installs permission gating on the warmed partition', () => {
    setupPermissionHandlersSpy.mockClear();
    const win = makeFakeWindow();
    const partition = invoke('profiles:warm-session', win, 'profile-warm-test') as string;
    expect(partition).toContain('profile-warm-test');
    expect(setupPermissionHandlersSpy).toHaveBeenCalledTimes(1);
    const [sess, passedWin] = setupPermissionHandlersSpy.mock.calls[0];
    expect(sess).toBe(partitionSessions.get(partition));
    expect(passedWin).toBe(win);
  });

  it('profiles:switch installs permission gating on the activated partition', () => {
    setupPermissionHandlersSpy.mockClear();
    const win = makeFakeWindow();
    invoke('profiles:switch', win, 'profile-switch-test');
    expect(setupPermissionHandlersSpy).toHaveBeenCalledTimes(1);
    const [sess, passedWin] = setupPermissionHandlersSpy.mock.calls[0];
    const partitionKey = [...partitionSessions.keys()].find(k => k.includes('profile-switch-test'));
    expect(partitionKey).toBeDefined();
    expect(sess).toBe(partitionSessions.get(partitionKey!));
    expect(passedWin).toBe(win);
  });
});
