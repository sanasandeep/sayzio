/**
 * Guard: the shared window.zio test mock must mirror the preload bridge.
 *
 * The preload script (src/preload/index.ts) is the single place new
 * renderer-facing APIs are exposed via contextBridge. The shared test mock
 * (tests/helpers/zio-mock.ts) is maintained by hand, so a new namespace or
 * method added to the preload used to silently miss the mock and the next
 * suite touching it failed with "undefined is not a function".
 *
 * This test imports the REAL preload module with `electron` mocked, captures
 * the object passed to contextBridge.exposeInMainWorld('zio', ...), and diffs
 * its namespaces/methods against the mock's defaults in both directions.
 */
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { buildZioMock } from './helpers/zio-mock';

const exposed: Record<string, unknown> = {};

vi.mock('electron', () => ({
  contextBridge: {
    exposeInMainWorld: (name: string, api: unknown) => {
      exposed[name] = api;
    },
  },
  ipcRenderer: {
    invoke: vi.fn(() => Promise.resolve(undefined)),
    on: vi.fn(),
    removeListener: vi.fn(),
  },
}));

function isPlainObject(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

/** Flatten an api object into dotted keys: top-level scalars/functions plus namespace.method. */
function surfaceKeys(api: Record<string, unknown>): Set<string> {
  const keys = new Set<string>();
  for (const [ns, value] of Object.entries(api)) {
    if (isPlainObject(value)) {
      for (const method of Object.keys(value)) keys.add(`${ns}.${method}`);
    } else {
      keys.add(ns);
    }
  }
  return keys;
}

describe('shared zio mock covers the preload bridge surface', () => {
  let preloadApi: Record<string, unknown>;
  let mockApi: Record<string, unknown>;

  beforeAll(async () => {
    await import('../src/preload/index');
    preloadApi = exposed['zio'] as Record<string, unknown>;
    mockApi = buildZioMock().zio;
  });

  it('exposes window.zio from the preload', () => {
    expect(preloadApi).toBeTruthy();
  });

  it('has a mock default for every preload namespace/method', () => {
    const preloadKeys = surfaceKeys(preloadApi);
    const mockKeys = surfaceKeys(mockApi);
    const missing = [...preloadKeys].filter(k => !mockKeys.has(k)).sort();

    expect(
      missing,
      `The preload bridge (src/preload/index.ts) exposes APIs that are missing from ` +
        `the shared test mock. Add a default stub for each of these to buildDefaults() ` +
        `in tests/helpers/zio-mock.ts:\n  - window.zio.${missing.join('\n  - window.zio.')}`,
    ).toEqual([]);
  });

  it('has no stale mock entries that the preload no longer exposes', () => {
    const preloadKeys = surfaceKeys(preloadApi);
    const mockKeys = surfaceKeys(mockApi);
    const stale = [...mockKeys].filter(k => !preloadKeys.has(k)).sort();

    expect(
      stale,
      `The shared test mock (tests/helpers/zio-mock.ts) stubs APIs the preload bridge ` +
        `(src/preload/index.ts) no longer exposes — remove or rename these defaults:\n` +
        `  - window.zio.${stale.join('\n  - window.zio.')}`,
    ).toEqual([]);
  });
});
