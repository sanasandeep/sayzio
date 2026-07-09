// Regression tests for the onboarding intro-slide cache (lib/secure.ts) and
// the intro screen's hydrate-from-cache effect (app/onboarding.tsx).
//
// Guarantees under test: a corrupt or malformed saved slide set can NEVER
// break the intro screen — bad entries are treated as a cache miss (and
// proactively cleared) so the bundled slides keep rendering, while a valid
// cached set is served (and rendered) as-is.
//
// Source-driven (see scripts/lib/extract.mjs notes): the REAL implementation
// is lifted out of the shipped files and transpiled, so the test exercises
// the actual code, not a re-implementation. AsyncStorage is replaced by an
// in-memory mock so every storage state (missing / corrupt / wrong shape /
// partially invalid) can be staged deterministically.
//
// Run via `node scripts/test-onboarding-slides-cache.mjs` (package script
// `test:onboarding-slides-cache`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import ts from "typescript";
import { runExtractedStatements } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// In-memory AsyncStorage mock. `failing` simulates a broken storage layer.
// ---------------------------------------------------------------------------
function makeStorage(initial = {}, { failing = false } = {}) {
  const map = new Map(Object.entries(initial));
  const calls = { removeItem: [] , setItem: [] };
  return {
    calls,
    map,
    async getItem(key) {
      if (failing) throw new Error("storage unavailable");
      return map.has(key) ? map.get(key) : null;
    },
    async setItem(key, value) {
      if (failing) throw new Error("storage unavailable");
      calls.setItem.push([key, value]);
      map.set(key, value);
    },
    async removeItem(key) {
      if (failing) throw new Error("storage unavailable");
      calls.removeItem.push(key);
      map.delete(key);
    },
  };
}

const CACHE_KEY = "1inme.onboarding.slides.cache.v1";

// ---------------------------------------------------------------------------
// Lift the REAL cache section out of lib/secure.ts.
//
// Everything from the cache-key const to the end of the file is the
// onboarding-slide cache implementation (key, validator, getter, clearer,
// setter). Transpile the TS away and evaluate with the mocked AsyncStorage.
// ---------------------------------------------------------------------------
const secureSrc = readFileSync(join(root, "lib", "secure.ts"), "utf8");

const sectionStart = secureSrc.indexOf("const ONBOARDING_SLIDES_CACHE_KEY");
assert.ok(sectionStart >= 0, "secure.ts should define ONBOARDING_SLIDES_CACHE_KEY");
const sectionTs = secureSrc
  .slice(sectionStart)
  .replace(/^export /gm, "");

const sectionJs = ts.transpileModule(sectionTs, {
  compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext },
}).outputText;

// Assert the lifted section actually contains what we're testing, so a
// refactor that moves/renames these fails loudly here instead of silently
// testing nothing.
for (const name of [
  "isCachedSlideLike",
  "getCachedOnboardingSlides",
  "setCachedOnboardingSlides",
]) {
  assert.ok(sectionJs.includes(name), `lifted secure.ts section should include ${name}`);
}

function makeCache(storage) {
  return runExtractedStatements(
    sectionJs,
    "({ getCachedOnboardingSlides, setCachedOnboardingSlides })",
    { AsyncStorage: storage },
    "onboarding slide cache",
    { test: "test-onboarding-slides-cache" },
  );
}

const slide = (id, extra = {}) => ({
  id,
  slug: `slide-${id}`,
  category: "intro",
  title: `Slide ${id}`,
  subtitle: "extra fields must be preserved",
  ...extra,
});

console.log("[test-onboarding-slides-cache] getCachedOnboardingSlides");

// --- valid cached array is returned as-is ---------------------------------
{
  const valid = [slide(1), slide(2)];
  const storage = makeStorage({ [CACHE_KEY]: JSON.stringify(valid) });
  const { getCachedOnboardingSlides } = makeCache(storage);
  const out = await getCachedOnboardingSlides();
  assert.deepEqual(out, valid);
  assert.deepEqual(storage.calls.removeItem, [], "valid cache is not cleared");
}
ok("valid cached array is returned intact (extra fields preserved, not cleared)");

// --- missing key is a plain miss ------------------------------------------
{
  const storage = makeStorage();
  const { getCachedOnboardingSlides } = makeCache(storage);
  assert.equal(await getCachedOnboardingSlides(), null);
  assert.deepEqual(storage.calls.removeItem, [], "nothing stored → nothing to clear");
}
ok("missing cache entry → null without touching storage");

// --- corrupt JSON is cleared and returns null ------------------------------
{
  const storage = makeStorage({ [CACHE_KEY]: '{"broken": [truncated' });
  const { getCachedOnboardingSlides } = makeCache(storage);
  assert.equal(await getCachedOnboardingSlides(), null);
  assert.deepEqual(storage.calls.removeItem, [CACHE_KEY], "corrupt JSON is deleted");
  assert.equal(storage.map.has(CACHE_KEY), false);
}
ok("corrupt JSON → null AND the bad entry is cleared from storage");

// --- non-array shapes are cleared ------------------------------------------
for (const [label, raw] of [
  ["object", JSON.stringify({ items: [slide(1)] })],
  ["string", JSON.stringify("not-slides")],
  ["number", "42"],
  ["null literal", "null"],
  ["empty array", "[]"],
]) {
  const storage = makeStorage({ [CACHE_KEY]: raw });
  const { getCachedOnboardingSlides } = makeCache(storage);
  assert.equal(await getCachedOnboardingSlides(), null, `${label} → null`);
  assert.deepEqual(storage.calls.removeItem, [CACHE_KEY], `${label} entry is cleared`);
}
ok("non-array / empty-array shapes → null and cleared");

// --- entries missing slug/title/category (or id) are filtered out ----------
{
  const good = slide(1);
  const bad = [
    { ...slide(2), slug: undefined },       // missing slug
    { ...slide(3), title: 7 },              // wrong-typed title
    { ...slide(4), category: null },        // null category
    { ...slide(5), id: "5" },               // string id
    null,                                    // null entry
    "just a string",                         // non-object entry
  ];
  const storage = makeStorage({ [CACHE_KEY]: JSON.stringify([bad[0], good, ...bad.slice(1)]) });
  const { getCachedOnboardingSlides } = makeCache(storage);
  const out = await getCachedOnboardingSlides();
  assert.deepEqual(out, [good], "only the structurally valid slide survives");
  assert.deepEqual(storage.calls.removeItem, [], "partially valid cache is kept");
}
ok("malformed entries are filtered out; remaining valid slides still served");

// --- ALL entries malformed → treated as corrupt and cleared -----------------
{
  const storage = makeStorage({
    [CACHE_KEY]: JSON.stringify([{ nope: true }, { id: "x" }]),
  });
  const { getCachedOnboardingSlides } = makeCache(storage);
  assert.equal(await getCachedOnboardingSlides(), null);
  assert.deepEqual(storage.calls.removeItem, [CACHE_KEY]);
}
ok("array with zero valid entries → null and cleared");

// --- broken storage layer never throws --------------------------------------
{
  const storage = makeStorage({}, { failing: true });
  const { getCachedOnboardingSlides, setCachedOnboardingSlides } = makeCache(storage);
  assert.equal(await getCachedOnboardingSlides(), null, "read failure → null");
  await setCachedOnboardingSlides([slide(1)]); // must not throw
}
ok("storage failures are swallowed (never throw into the intro flow)");

console.log("[test-onboarding-slides-cache] setCachedOnboardingSlides");

// --- persisting and clearing round-trip -------------------------------------
{
  const storage = makeStorage();
  const { getCachedOnboardingSlides, setCachedOnboardingSlides } = makeCache(storage);
  const items = [slide(1), slide(2)];
  await setCachedOnboardingSlides(items);
  assert.deepEqual(await getCachedOnboardingSlides(), items, "round-trips");
  await setCachedOnboardingSlides(null);
  assert.equal(await getCachedOnboardingSlides(), null, "null clears");
  await setCachedOnboardingSlides(items);
  await setCachedOnboardingSlides([]);
  assert.equal(await getCachedOnboardingSlides(), null, "empty array clears");
}
ok("set round-trips; null/[] clear the entry");

// ===========================================================================
// The intro screen's hydrate effect (app/onboarding.tsx), run against the
// REAL getCachedOnboardingSlides: a valid cache renders (setSlides called),
// a corrupt cache leaves the bundled slides untouched.
// ===========================================================================
console.log("[test-onboarding-slides-cache] onboarding.tsx hydrate effect");

const onboardingSrc = readFileSync(join(root, "app", "onboarding.tsx"), "utf8");

// Lift the effect body between `useEffect(() => {` following the hydrate
// comment marker and its closing `}, []);`.
const marker = "// Hydrate from the on-device cache";
const markerIdx = onboardingSrc.indexOf(marker);
assert.ok(markerIdx >= 0, "onboarding.tsx should keep the hydrate-from-cache effect");
const effectStart = onboardingSrc.indexOf("useEffect(() => {", markerIdx);
const effectEnd = onboardingSrc.indexOf("}, []);", effectStart);
assert.ok(effectStart > 0 && effectEnd > effectStart, "hydrate effect shape recognized");
const effectBodyTs = onboardingSrc.slice(
  effectStart + "useEffect(() => {".length,
  effectEnd,
);
const effectBodyJs = ts.transpileModule(effectBodyTs, {
  compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ESNext },
}).outputText;

async function runHydrate({ storage, index = 0, fresh = false, deferred = null }) {
  const { getCachedOnboardingSlides } = makeCache(storage);
  const setSlidesCalls = [];
  const indexRef = { current: index };
  const freshSlidesRef = { current: fresh };
  const deferredSlidesRef = { current: deferred };
  runExtractedStatements(
    effectBodyJs,
    "undefined",
    {
      getCachedOnboardingSlides,
      indexRef,
      freshSlidesRef,
      deferredSlidesRef,
      setSlides: (v) => setSlidesCalls.push(v),
    },
    "hydrate-from-cache effect",
    { test: "test-onboarding-slides-cache" },
  );
  // Let the effect's async IIFE settle.
  await new Promise((r) => setTimeout(r, 0));
  return { setSlidesCalls, deferredSlidesRef };
}

// --- valid cache renders -----------------------------------------------------
{
  const cached = [slide(1), slide(2)];
  const storage = makeStorage({ [CACHE_KEY]: JSON.stringify(cached) });
  const { setSlidesCalls } = await runHydrate({ storage });
  assert.equal(setSlidesCalls.length, 1, "cached slides are rendered");
  assert.deepEqual(setSlidesCalls[0], cached);
}
ok("hydrate: valid cached array renders (setSlides receives it)");

// --- corrupt cache leaves bundled slides in place ----------------------------
{
  const storage = makeStorage({ [CACHE_KEY]: "{{{ not json" });
  const { setSlidesCalls } = await runHydrate({ storage });
  assert.deepEqual(setSlidesCalls, [], "bundled slides stay — no blank render");
  assert.equal(storage.map.has(CACHE_KEY), false, "corrupt entry got cleared");
}
ok("hydrate: corrupt cache → intro keeps bundled slides, entry cleared");

// --- fully malformed array also falls back -----------------------------------
{
  const storage = makeStorage({ [CACHE_KEY]: JSON.stringify([{ junk: 1 }]) });
  const { setSlidesCalls } = await runHydrate({ storage });
  assert.deepEqual(setSlidesCalls, [], "no blank slides rendered");
}
ok("hydrate: all-malformed cache → fallback, never blank slides");

// --- fresh API slides win over the cache --------------------------------------
{
  const storage = makeStorage({ [CACHE_KEY]: JSON.stringify([slide(1)]) });
  const { setSlidesCalls } = await runHydrate({ storage, fresh: true });
  assert.deepEqual(setSlidesCalls, [], "cache never overwrites fresh API slides");
}
ok("hydrate: fresh API slides are never overwritten by the cache");

// --- user already swiped ahead → deferred, not swapped -------------------------
{
  const cached = [slide(1)];
  const storage = makeStorage({ [CACHE_KEY]: JSON.stringify(cached) });
  const { setSlidesCalls, deferredSlidesRef } = await runHydrate({
    storage,
    index: 2,
  });
  assert.deepEqual(setSlidesCalls, [], "carousel is not yanked mid-swipe");
  assert.deepEqual(deferredSlidesRef.current, cached, "cache deferred for slide 0");
}
ok("hydrate: mid-carousel cache arrival is deferred, not rendered");

console.log(`\n[test-onboarding-slides-cache] all ${passed} checks passed`);
