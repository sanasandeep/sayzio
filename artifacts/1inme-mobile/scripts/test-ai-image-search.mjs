// Mobile parity coverage for the AI builder's "Search the web for images"
// picker (app/links/[id]/ai-builder.tsx) — the counterpart of the web spec
// artifacts/1inme/tests/Browser/ai-builder-image-search.spec.ts:
//
//   1. The whole search section is gated on `intake.image_search_enabled`
//      (statically asserted against the shipped JSX), so when the server
//      reports Google CSE unconfigured the entry point never renders.
//   2. The searchM mutation config (REAL source, lifted verbatim) shows a
//      friendly "Search failed" alert on error — never a broken grid — and
//      clears nothing it shouldn't; empty results get a "No images found"
//      nudge; successful results land in state with the rights disclaimer.
//   3. The intake API contract (lib/api/aiBuilder.ts) exposes
//      image_search_enabled, and the search client posts to the
//      /ai-builder/image-search endpoint.
//
// Follows the source-driven convention of test-og-preview-card.mjs: we run
// what ships (transpiled from the shipped TSX), not a re-implementation.
//
// Run via `node scripts/test-ai-image-search.mjs` (package script
// `test:ai-image-search`, chained into `test:unit` → the mobile-unit workflow).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall as runExtractedCallShared } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "links", "[id]", "ai-builder.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api", "aiBuilder.ts"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const runExtractedCall = (expr, scope, label) =>
  runExtractedCallShared(expr, scope, label, { test: "test-ai-image-search" });

// ---------------------------------------------------------------------------
// 1. The section is gated on intake.image_search_enabled.
// ---------------------------------------------------------------------------
console.log("[test-ai-image-search] section hidden when server reports disabled");

const gateAt = screenSrc.indexOf("intake.image_search_enabled ?");
assert.notEqual(
  gateAt,
  -1,
  "the search section must be gated on `intake.image_search_enabled ?` — " +
    "when Google CSE is unconfigured the picker must not render at all",
);
const labelAt = screenSrc.indexOf("Search the web for images");
assert.notEqual(labelAt, -1, "search section label not found");
assert.ok(
  labelAt > gateAt && labelAt - gateAt < 2000,
  "the 'Search the web for images' entry point must render INSIDE the " +
    "image_search_enabled conditional",
);
// The conditional must fall back to rendering nothing (`: null`), balancing
// from the gate's `(`.
{
  const open = screenSrc.indexOf("(", gateAt);
  let depth = 0;
  let close = -1;
  for (let i = open; i < screenSrc.length; i++) {
    if (screenSrc[i] === "(") depth++;
    else if (screenSrc[i] === ")") {
      depth--;
      if (depth === 0) {
        close = i;
        break;
      }
    }
  }
  assert.notEqual(close, -1, "could not balance the gated JSX expression");
  assert.match(
    screenSrc.slice(close, close + 20),
    /^\)\s*:\s*null/,
    "disabled state must render null (no placeholder, no broken section)",
  );
  assert.ok(
    labelAt < close,
    "the search label must sit within the gated expression",
  );
}
ok("search picker renders only when intake.image_search_enabled is true");

// The intake type documents the flag; the API client hits the right endpoint.
assert.match(
  apiSrc,
  /image_search_enabled\?:\s*boolean/,
  "AiBuilderIntake must carry image_search_enabled",
);
assert.match(
  apiSrc,
  /\/ai-builder\/image-search/,
  "searchAiBuilderImages must POST to the image-search endpoint",
);
ok("intake contract exposes image_search_enabled; client hits image-search");

// ---------------------------------------------------------------------------
// 2. searchM behavior: friendly error, empty-result nudge, results in state.
// ---------------------------------------------------------------------------
console.log("[test-ai-image-search] search mutation error/empty/success paths");

// Lift the object literal passed to useMutation for searchM, balancing braces.
function extractMutationConfig(name) {
  const sig = `const ${name} = useMutation(`;
  const at = screenSrc.indexOf(sig);
  assert.notEqual(at, -1, `${sig} not found in ai-builder.tsx`);
  const open = screenSrc.indexOf("{", at + sig.length);
  assert.notEqual(open, -1, `${name} config brace not found`);
  let depth = 0;
  let end = -1;
  for (let i = open; i < screenSrc.length; i++) {
    if (screenSrc[i] === "{") depth++;
    else if (screenSrc[i] === "}") {
      depth--;
      if (depth === 0) {
        end = i + 1;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `could not balance braces for ${name}`);
  return screenSrc.slice(open, end);
}

const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;
function toJs(objSrc, name) {
  const out = ts.transpileModule(`const __x = ${objSrc};`, {
    compilerOptions: { target: ts.ScriptTarget.ES2020 },
    fileName: `${name}.ts`,
  }).outputText;
  const eq = out.indexOf("=");
  return out.slice(eq + 1).replace(/;\s*$/, "");
}

const searchMSrc = toJs(extractMutationConfig("searchM"), "searchM");

function makeHarness({ results, disclaimer, searchError }) {
  const state = {
    alerts: [],
    results: "UNTOUCHED",
    disclaimer: "UNTOUCHED",
    selected: "UNTOUCHED",
    unavailable: "UNTOUCHED",
    open: "UNTOUCHED",
    refetches: 0,
    searchCalls: [],
  };
  const scope = {
    linkId: 42,
    searchQuery: "  fitness logo  ",
    searchAiBuilderImages: async (id, q) => {
      state.searchCalls.push([id, q]);
      if (searchError) throw searchError;
      return { results, disclaimer };
    },
    setSearchResults: (v) => (state.results = v),
    setSearchDisclaimer: (v) => (state.disclaimer = v),
    setSelectedCandidates: (v) => (state.selected = v),
    setSearchUnavailable: (v) => (state.unavailable = v),
    setSearchOpen: (v) => (state.open = v),
    intakeQ: { refetch: () => (state.refetches += 1) },
    showAlert: (title, message) => state.alerts.push({ title, message }),
  };
  const cfg = runExtractedCall(`(${searchMSrc})`, scope, "searchM config");
  return { state, cfg };
}

{
  // Failed search → a friendly alert, results state untouched (no broken grid).
  const err = Object.assign(new Error("Image search is not configured."), {});
  const h = makeHarness({ searchError: err });
  h.cfg.onError(err);
  assert.equal(h.state.alerts.length, 1, "exactly one alert on failure");
  assert.equal(h.state.alerts[0].title, "Search failed");
  assert.equal(h.state.alerts[0].message, "Image search is not configured.");
  assert.equal(h.state.results, "UNTOUCHED", "no results written on error");
  assert.equal(h.state.disclaimer, "UNTOUCHED");
  ok("failed search → 'Search failed' alert with the server message; state untouched");
}

{
  // Error without a message → generic fallback copy.
  const h = makeHarness({ searchError: new Error() });
  h.cfg.onError({});
  assert.deepEqual(h.state.alerts, [
    { title: "Search failed", message: "Please try again." },
  ]);
  ok("message-less failure falls back to 'Please try again.'");
}

{
  // Mid-session collapse: an error carrying code=image_search_unavailable
  // (admin removed the Google CSE keys) must collapse the picker — flip
  // searchUnavailable on, close the section, clear results/selection,
  // refetch the intake — and must NOT show the retryable "Search failed"
  // alert (a friendly "unavailable" alert instead).
  const err = Object.assign(new Error("Image search is not available."), {
    code: "image_search_unavailable",
  });
  const h = makeHarness({ searchError: err });
  h.cfg.onError(err);
  assert.equal(h.state.unavailable, true, "setSearchUnavailable(true) must fire");
  assert.equal(h.state.open, false, "setSearchOpen(false) must fire");
  assert.deepEqual(h.state.results, [], "stale results cleared");
  assert.deepEqual(h.state.selected, [], "stale selection cleared");
  assert.equal(h.state.refetches, 1, "intake refetched for fresh image_search_enabled");
  assert.equal(h.state.alerts.length, 1, "exactly one alert");
  assert.notEqual(
    h.state.alerts[0].title,
    "Search failed",
    "must NOT show the retryable 'Search failed' alert",
  );
  assert.equal(h.state.alerts[0].title, "Image search unavailable");
  ok(
    "image_search_unavailable → picker collapses (unavailable=true, open=false), no 'Search failed' alert",
  );
}

{
  // Empty result set → results cleared + "No images found" nudge.
  const h = makeHarness({ results: [], disclaimer: "d" });
  h.cfg.onSuccess({ results: [], disclaimer: "d" });
  assert.deepEqual(h.state.results, []);
  assert.deepEqual(h.state.alerts, [
    { title: "No images found", message: "Try a different search." },
  ]);
  assert.deepEqual(h.state.selected, [], "selection reset");
  ok("empty results → 'No images found' nudge, selection reset");
}

{
  // Successful search → results + disclaimer land in state, no alert.
  const results = [
    { url: "https://img.example/a.png", thumbnail: null, title: "A", source: null, width: null, height: null },
  ];
  const h = makeHarness({ results, disclaimer: "Rights disclaimer" });
  h.cfg.onSuccess({ results, disclaimer: "Rights disclaimer" });
  assert.deepEqual(h.state.results, results);
  assert.equal(h.state.disclaimer, "Rights disclaimer");
  assert.deepEqual(h.state.selected, [], "selection reset for the new grid");
  assert.equal(h.state.alerts.length, 0, "no alert on success");
  ok("successful search paints results + rights disclaimer");
}

{
  // mutationFn trims the query and passes the link id.
  const h = makeHarness({ results: [], disclaimer: "" });
  await h.cfg.mutationFn();
  assert.deepEqual(h.state.searchCalls, [[42, "fitness logo"]]);
  ok("mutationFn calls searchAiBuilderImages with trimmed query");
}

console.log(`\n[test-ai-image-search] all assertions passed (${passed} groups)`);
