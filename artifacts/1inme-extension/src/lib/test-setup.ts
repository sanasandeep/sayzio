// Stub the WebExtension globals so importing modules that pull in
// `webextension-polyfill` (transitively via ./browser) doesn't blow up
// when the tests run under Node. The polyfill throws on load unless it
// detects a browser-extension environment via `chrome.runtime.id`.
const g = globalThis as unknown as { chrome?: unknown; browser?: unknown };
if (!g.chrome) {
  g.chrome = { runtime: { id: "test-extension" } };
}
