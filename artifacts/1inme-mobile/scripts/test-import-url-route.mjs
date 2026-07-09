// Deep-link ROUTING coverage for the "Import from URL" share-sheet shortcut
// (task: make sure sharing a URL from another app actually opens the Import
// screen). scripts/test-import-url.mjs already covers URL parsing, the
// picker, and the shorten flow's API contract — but nothing verified that
// sayzio://import-url?url=... / https://sayzio.app/import-url?url=... resolve
// to app/import-url.tsx via Expo Router. A route rename, a linking-config
// change (app.json scheme / associatedDomains / intentFilters), a
// +native-intent.ts edit (e.g. dropping "import-url" from the RESERVED set so
// the biolink catch-all swallows it), or a ShareIntentHandler drift would
// break the share-sheet shortcut while every existing test stays green.
//
// What this pins (all against the REAL shipped source — no re-implementation):
//   1. The Expo Router file route exists: app/import-url.tsx is present,
//      default-exports a screen, and reads url/title via useLocalSearchParams.
//   2. Linking config in app.json: custom scheme "sayzio", iOS universal-link
//      associatedDomains and Android https intentFilters cover the hosts the
//      screen documents (sayzio.app / 1in.me + www variants).
//   3. The REAL redirectSystemPath from app/+native-intent.ts (transpiled and
//      executed) routes every incoming shape correctly:
//        - sayzio://dataUrl=<key> (expo-share-intent relaunch) → /import-url
//        - /import-url?url=...&title=... passes through UNCHANGED (params
//          survive) for both scheme and universal-link forms
//        - "import-url" stays in the RESERVED set (case-insensitively), so
//          the /biolink/[handle] catch-all cannot swallow it — while a
//          non-reserved single segment still maps to /biolink/<handle>,
//          proving the catch-all is active and the exemption is real.
//   4. The REAL ShareIntentHandler component (transpiled, effect executed
//      against a stubbed share intent) pushes { pathname: "/import-url",
//      params: { url, title } } — and extracts a URL out of plain shared text.
//
// Follows the source-driven convention of test-native-route.mjs /
// test-import-url.mjs. Run via `node scripts/test-import-url-route.mjs`
// (package script `test:import-url-route`, chained into `test:unit` → the
// mobile-unit workflow).

import assert from "node:assert/strict";
import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;

// Transpile a shipped TS/TSX module to CJS and execute it with its imports
// stubbed (same helper shape as test-import-url.mjs).
function loadModule(source, fileName, requireMap = {}) {
  const js = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
      esModuleInterop: true,
      jsx: ts.JsxEmit.React,
    },
    fileName,
  }).outputText;
  const module = { exports: {} };
  const req = (name) => {
    if (name in requireMap) return requireMap[name];
    throw new Error(`unexpected import "${name}" in ${fileName}`);
  };
  // eslint-disable-next-line no-new-func
  new Function("require", "module", "exports", "__DEV__", "React", js)(
    req,
    module,
    module.exports,
    false,
    { createElement: () => null },
  );
  return module.exports;
}

// ===========================================================================
// 1. The Expo Router file route exists and accepts url/title params.
// ===========================================================================
console.log("[test-import-url-route] route file registration");

const screenPath = join(root, "app", "import-url.tsx");
assert.ok(
  existsSync(screenPath),
  "app/import-url.tsx must exist — Expo Router derives the /import-url route from this file path; renaming it silently breaks every deep link",
);
ok("app/import-url.tsx exists (file-based /import-url route)");

const screenSrc = readFileSync(screenPath, "utf8");
assert.match(
  screenSrc,
  /export default function \w+/,
  "app/import-url.tsx must default-export a screen component or Expo Router won't register the route",
);
ok("screen default-exports a component");

assert.match(
  screenSrc,
  /useLocalSearchParams<\{\s*url\?\:\s*string;\s*title\?\:\s*string\s*\}>/,
  "the screen must read url/title via useLocalSearchParams — these are the query params the share-sheet deep link carries",
);
ok("screen reads url/title via useLocalSearchParams");

// ===========================================================================
// 2. Linking config in app.json: custom scheme + universal-link hosts.
// ===========================================================================
console.log("[test-import-url-route] app.json linking config");

const appJson = JSON.parse(readFileSync(join(root, "app.json"), "utf8"));
const expo = appJson.expo ?? {};

assert.equal(
  expo.scheme,
  "sayzio",
  "app.json expo.scheme must stay 'sayzio' — sayzio://import-url?... depends on it",
);
ok("custom scheme is sayzio (sayzio://import-url works)");

const HOSTS = ["sayzio.app", "www.sayzio.app", "1in.me", "www.1in.me"];

const assoc = expo.ios?.associatedDomains ?? [];
for (const host of HOSTS) {
  assert.ok(
    assoc.includes(`applinks:${host}`),
    `iOS associatedDomains must include applinks:${host} for universal links (https://${host}/import-url?...)`,
  );
}
ok("iOS associatedDomains cover all universal-link hosts");

const filters = expo.android?.intentFilters ?? [];
const viewFilter = filters.find(
  (f) => f.action === "VIEW" && f.autoVerify === true,
);
assert.ok(
  viewFilter,
  "Android needs an autoVerify VIEW intent filter for app links",
);
const androidHosts = (viewFilter.data ?? [])
  .filter((d) => d.scheme === "https")
  .map((d) => d.host);
for (const host of HOSTS) {
  assert.ok(
    androidHosts.includes(host),
    `Android intent filter must include https host ${host}`,
  );
}
assert.ok(
  (viewFilter.category ?? []).includes("BROWSABLE"),
  "Android VIEW intent filter must be BROWSABLE",
);
ok("Android autoVerify intent filter covers all https hosts");

// ===========================================================================
// 3. Real +native-intent.ts redirectSystemPath routing.
// ===========================================================================
console.log("[test-import-url-route] +native-intent redirectSystemPath");

const intentSrc = readFileSync(join(root, "app", "+native-intent.ts"), "utf8");
const { redirectSystemPath } = loadModule(intentSrc, "app/+native-intent.ts");
assert.equal(
  typeof redirectSystemPath,
  "function",
  "+native-intent.ts must export redirectSystemPath",
);

// 3a. Native share-sheet relaunch (expo-share-intent): sayzio://dataUrl=<key>
// is not a real route — it must land on the import picker.
assert.equal(
  redirectSystemPath({ path: "sayzio://dataUrl=shared-key-123", initial: true }),
  "/import-url",
  "expo-share-intent relaunch (dataUrl=) must route to /import-url",
);
assert.equal(
  redirectSystemPath({ path: "dataUrl=shared-key-123", initial: false }),
  "/import-url",
  "bare dataUrl= payload must also route to /import-url",
);
ok("share-sheet relaunch (dataUrl=) routes to /import-url");

// 3b. Custom-scheme deep link with params passes through UNCHANGED so
// Expo Router resolves it to app/import-url.tsx with url/title intact.
const schemePath =
  "/import-url?url=https%3A%2F%2Fexample.com%2Fpage&title=Example%20Page";
assert.equal(
  redirectSystemPath({ path: schemePath, initial: true }),
  schemePath,
  "sayzio://import-url?url=...&title=... must pass through unchanged (params preserved)",
);
ok("custom-scheme /import-url?url&title passes through with params intact");

// 3c. Universal-link form (full https URL) also passes through unchanged.
for (const host of HOSTS) {
  const universal = `https://${host}/import-url?url=https%3A%2F%2Fexample.com`;
  assert.equal(
    redirectSystemPath({ path: universal, initial: false }),
    universal,
    `https://${host}/import-url?... must pass through unchanged`,
  );
}
ok("universal-link https://<host>/import-url passes through for every host");

// 3d. The biolink catch-all is ACTIVE (a non-reserved single segment maps to
// /biolink/<handle>) …
assert.equal(
  redirectSystemPath({ path: "/somecreator", initial: false }),
  "/biolink/somecreator",
  "sanity: a plain handle should hit the /biolink/[handle] catch-all",
);
// … and "import-url" is exempt from it, case-insensitively. If someone drops
// it from the RESERVED set, share links would open a bogus biolink page.
assert.equal(
  redirectSystemPath({ path: "/import-url", initial: false }),
  "/import-url",
  "/import-url (no params) must NOT be swallowed by the biolink catch-all",
);
assert.equal(
  redirectSystemPath({ path: "/IMPORT-URL?url=x", initial: false }),
  "/IMPORT-URL?url=x",
  "reservation must be case-insensitive",
);
ok("import-url is reserved — biolink catch-all cannot swallow it");

// ===========================================================================
// 4. Real ShareIntentHandler pushes /import-url with url + title params.
// ===========================================================================
console.log("[test-import-url-route] ShareIntentHandler routing");

const handlerSrc = readFileSync(
  join(root, "components", "ShareIntentHandler.tsx"),
  "utf8",
);

function runHandler(shareIntent) {
  const pushes = [];
  let resets = 0;
  const effects = [];
  const mod = loadModule(handlerSrc, "components/ShareIntentHandler.tsx", {
    "expo-constants": { default: { appOwnership: null } },
    "expo-router": { router: { push: (arg) => pushes.push(arg) } },
    react: { useEffect: (fn) => effects.push(fn) },
    "expo-share-intent": {
      useShareIntent: (opts) => {
        assert.equal(
          opts?.disabled,
          false,
          "useShareIntent must be enabled outside Expo Go",
        );
        return {
          hasShareIntent: true,
          shareIntent,
          resetShareIntent: () => {
            resets += 1;
          },
        };
      },
    },
  });
  mod.ShareIntentHandler();
  for (const fn of effects) fn();
  return { pushes, resets };
}

// 4a. A shared web URL (Safari/Chrome share) routes to /import-url with
// url + title params — exactly what app/import-url.tsx reads.
{
  const { pushes, resets } = runHandler({
    webUrl: "https://example.com/article",
    text: null,
    meta: { title: "An Article" },
  });
  assert.equal(pushes.length, 1, "exactly one router.push expected");
  assert.deepEqual(pushes[0], {
    pathname: "/import-url",
    params: { url: "https://example.com/article", title: "An Article" },
  });
  assert.equal(resets, 1, "share intent must be reset after handling");
  ok("shared web URL → router.push(/import-url, {url, title})");
}

// 4b. Plain shared text containing a URL (e.g. shared from a notes app)
// still extracts the URL; no title → params carry url only.
{
  const { pushes } = runHandler({
    webUrl: null,
    text: "check this out https://example.com/thing soon",
    meta: {},
  });
  assert.equal(pushes.length, 1);
  assert.deepEqual(pushes[0], {
    pathname: "/import-url",
    params: { url: "https://example.com/thing" },
  });
  ok("URL inside shared text → router.push(/import-url, {url})");
}

// 4c. Shared text without any URL must NOT navigate.
{
  const { pushes, resets } = runHandler({
    webUrl: null,
    text: "just some words, no link here",
    meta: {},
  });
  assert.equal(pushes.length, 0, "no URL → no navigation");
  assert.equal(resets, 1, "intent still reset");
  ok("shared text without a URL does not navigate");
}

console.log(
  `test-import-url-route: all assertions passed (${passed} checks)`,
);
