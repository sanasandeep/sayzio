// Regression test: the animated ZioSplash on the gate screen (app/index.tsx)
// must play exactly once per process ("cold launch"), and never replay when
// the OS destroys and re-mounts the React tree after backgrounding.
//
// The session state lives at module scope in components/ZioSplash.tsx
// (`splashShownThisSession`, read via hasSplashShownThisSession() and
// stamped via markSplashShownThisSession()); GateScreen seeds `showSplash`
// from it in a lazy useState initializer and stamps it in ZioSplash's
// onDone. A refactor back to a plain `useState(true)` (or dropping the
// stamp) would silently reintroduce the splash-replay bug on every
// background/foreground cycle.
//
// Following the source-driven convention (see test-auth-next.mjs and
// scripts/lib/extract.mjs), we lift the REAL pieces out of the shipped
// sources — the session-flag block from ZioSplash.tsx plus the useState
// initializer expression and onDone callback from app/index.tsx — and run
// them in a tiny mount harness:
//
//   mount #1  -> showSplash must start true (splash plays)
//   onDone    -> flag stamped, splash dismissed
//   mount #2  -> showSplash must start false (splash skipped entirely)
//
// The shared flag must persist across mounts inside one evaluation closure
// (mirroring module scope surviving component re-mounts), so we compose the
// lifted snippets with new Function rather than per-call proxy scopes.
//
// Run via `node scripts/test-splash-once.mjs` (package script
// `test:splash-once`, wired into the `test:unit` gate).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const gateSrc = readFileSync(join(root, "app", "index.tsx"), "utf8");
const splashSrc = readFileSync(
  join(root, "components", "ZioSplash.tsx"),
  "utf8",
);

// --- 1. Lift the session-flag module block from ZioSplash.tsx -------------

// The flag declaration plus its two exported accessors. Anchored on the
// accessor names (the public contract GateScreen imports) rather than exact
// surrounding text.
const flagDecl = splashSrc.match(/^let splashShownThisSession\b[^\n]*;\s*$/m);
assert.ok(
  flagDecl,
  "ZioSplash.tsx must keep the module-scope splashShownThisSession flag " +
    "(module scope is what lets it survive GateScreen re-mounts)",
);
const hasFn = splashSrc.match(
  /export function hasSplashShownThisSession\([^)]*\)[^{]*\{[\s\S]*?\n\}/,
);
const markFn = splashSrc.match(
  /export function markSplashShownThisSession\([^)]*\)[^{]*\{[\s\S]*?\n\}/,
);
assert.ok(hasFn, "hasSplashShownThisSession() not found in ZioSplash.tsx");
assert.ok(markFn, "markSplashShownThisSession() not found in ZioSplash.tsx");

// Strip TS return-type annotations so the lifted code runs as plain JS.
const flagModuleJs = [flagDecl[0], hasFn[0], markFn[0]]
  .join("\n")
  .replace(/export function/g, "function")
  .replace(/\)\s*:\s*[A-Za-z]+\s*\{/g, ") {");

// --- 2. Lift the gate screen's initializer and onDone ---------------------

// The showSplash state must be seeded from the session flag — a plain
// useState(true) is exactly the regression this test exists to catch.
const initMatch = gateSrc.match(
  /const \[showSplash, setShowSplash\] = useState\(([\s\S]*?)\);/,
);
assert.ok(
  initMatch,
  "could not find the showSplash useState initializer in app/index.tsx",
);
const initExpr = initMatch[1].trim();
assert.ok(
  /hasSplashShownThisSession|splashShownThisSession/.test(initExpr),
  `showSplash must be seeded from the splash session flag, got: useState(${initExpr}) — ` +
    "a plain useState(true) replays the splash on every re-mount after backgrounding",
);

// The onDone callback handed to ZioSplash: must stamp the flag and dismiss.
const onDoneMatch = gateSrc.match(/onDone=\{(\(\)\s*=>\s*\{[\s\S]*?\})\}/);
assert.ok(
  onDoneMatch,
  "could not find the ZioSplash onDone callback in app/index.tsx",
);
const onDoneExpr = onDoneMatch[1];
assert.ok(
  /markSplashShownThisSession\(\)|splashShownThisSession = true/.test(onDoneExpr),
  "ZioSplash onDone must stamp the splash session flag " +
    "(markSplashShownThisSession()) or the splash replays on the next re-mount",
);

// --- 3. Behavioural check: run the REAL lifted code in a mount harness ----

// One evaluation closure == one app process: the lifted flag block lives at
// the closure's top level (module scope), while each mount() call re-runs
// the lifted useState initializer and rebuilds the lifted onDone — exactly
// what React does when GateScreen is destroyed and re-mounted after
// backgrounding.
// eslint-disable-next-line no-new-func
const makeProcess = new Function(`
  ${flagModuleJs}
  return function mount() {
    let showSplash;
    const setShowSplash = (v) => { showSplash = v; };
    const init = (${initExpr});
    showSplash = typeof init === "function" ? init() : init;
    const onDone = (${onDoneExpr});
    return { get showSplash() { return showSplash; }, onDone };
  };
`);

// Cold launch: first mount shows the splash.
const mount = makeProcess();
const first = mount();
assert.equal(
  first.showSplash,
  true,
  "first mount of GateScreen in a fresh process must show the splash",
);

// Splash finishes.
first.onDone();
assert.equal(
  first.showSplash,
  false,
  "onDone must dismiss the splash on the current mount",
);

// Background/foreground cycle re-mounts the screen in the SAME process:
// the splash must be skipped entirely.
const second = mount();
assert.equal(
  second.showSplash,
  false,
  "a re-mount of GateScreen after onDone (same process) must skip the splash entirely",
);

// And a third re-mount stays skipped.
assert.equal(mount().showSplash, false, "subsequent re-mounts must also skip the splash");

// A genuinely fresh process (new module evaluation) plays the splash again.
const freshMount = makeProcess()();
assert.equal(
  freshMount.showSplash,
  true,
  "a true cold launch (fresh module evaluation) must play the splash again",
);

console.log("test-splash-once: all assertions passed");
