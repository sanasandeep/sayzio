// Stub the WebExtension globals so importing modules that pull in
// `webextension-polyfill` (transitively via ./browser) doesn't blow up
// when the tests run under Node. The polyfill throws on load unless it
// detects a browser-extension environment via `chrome.runtime.id`.
//
// We also wire an in-memory `chrome.storage.local` so tests that
// exercise getSettings/setSettings have a working storage backend.
// The polyfill wraps chrome's callback-style API into promises, so we
// implement the callback contract here. Tests can clear the backing
// store via `(globalThis as any).__resetExtStorage()`.
const g = globalThis as unknown as {
  chrome?: any;
  __resetExtStorage?: () => void;
};

if (!g.chrome) {
  const store: Record<string, unknown> = {};
  const lastError = undefined;

  function get(
    keys: string[] | string | Record<string, unknown> | null | undefined,
    cb: (result: Record<string, unknown>) => void,
  ): void {
    const result: Record<string, unknown> = {};
    let keyArr: string[];
    if (keys == null) keyArr = Object.keys(store);
    else if (typeof keys === "string") keyArr = [keys];
    else if (Array.isArray(keys)) keyArr = keys;
    else keyArr = Object.keys(keys);
    for (const k of keyArr) {
      if (k in store) result[k] = store[k];
      else if (keys && !Array.isArray(keys) && typeof keys === "object") {
        result[k] = (keys as Record<string, unknown>)[k];
      }
    }
    cb(result);
  }

  function set(items: Record<string, unknown>, cb?: () => void): void {
    Object.assign(store, items);
    cb?.();
  }

  function remove(keys: string | string[], cb?: () => void): void {
    const keyArr = Array.isArray(keys) ? keys : [keys];
    for (const k of keyArr) delete store[k];
    cb?.();
  }

  g.chrome = {
    runtime: { id: "test-extension", lastError },
    storage: {
      local: { get, set, remove },
    },
  };

  g.__resetExtStorage = () => {
    for (const k of Object.keys(store)) delete store[k];
  };
}
