// Regression coverage for the block editor's "Fetch details" OG preview card
// (app/links/[id]/blocks/[blockId].tsx). The mobile editor stages fetched OG
// metadata in a confirm-before-apply preview card (Apply / Dismiss) instead of
// silently auto-filling the form. This test pins that contract so a regression
// can't silently restore the old auto-fill behavior or break Apply's
// fill-only-empty-fields rules:
//
//   1. runOgFetch (REAL source, lifted verbatim) stages the fetched meta into
//      `ogPreview` WITHOUT touching form values — `setValues` is never called
//      during fetch (asserted at runtime with a throwing stub AND statically
//      against the source). Empty-URL / empty-meta / fetch-error paths surface
//      errors without staging a preview.
//   2. applyOgPreview (REAL source) fills ONLY empty fields: the title lands
//      in the block kind's title-ish key ("label" for link, "text" for the
//      featured variants) and is skipped when ANY title-ish key already has a
//      value; description fills only when empty; thumbnail = image_url with
//      favicon fallback, only when empty, and NEVER for featured_pin. Apply
//      then clears the preview and flips the success flag.
//   3. Dismiss is wired to `setOgPreview(null)` only — no form writes.
//
// Follows the source-driven convention of test-import-url.mjs: we run what
// ships (transpiled from the shipped TSX), not a re-implementation.
//
// Run via `node scripts/test-og-preview-card.mjs` (package script
// `test:og-preview-card`, chained into `test:unit` → the mobile-unit workflow).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall as runExtractedCallShared } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "[blockId].tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const runExtractedCall = (expr, scope, label) =>
  runExtractedCallShared(expr, scope, label, { test: "test-og-preview-card" });

// ---------------------------------------------------------------------------
// Lift the arrow function passed to useCallback for a given const name,
// balancing braces from the arrow body's opening `{`.
// ---------------------------------------------------------------------------
function extractCallbackArrow(name) {
  const sig = `const ${name} = useCallback(`;
  const at = screenSrc.indexOf(sig);
  assert.notEqual(at, -1, `${sig} not found in blockId.tsx`);
  const arrowStart = at + sig.length;
  const open = screenSrc.indexOf("{", arrowStart);
  assert.notEqual(open, -1, `${name} arrow body brace not found`);
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
  return screenSrc.slice(arrowStart, end);
}

// The shipped TSX contains TS-only syntax (`e as {...}`); transpile the lifted
// arrow to plain JS before evaluating — we still run the REAL logic.
const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;
function toJs(arrowSrc, name) {
  const out = ts.transpileModule(`const __x = ${arrowSrc};`, {
    compilerOptions: { target: ts.ScriptTarget.ES2020 },
    fileName: `${name}.ts`,
  }).outputText;
  const eq = out.indexOf("=");
  return out.slice(eq + 1).replace(/;\s*$/, "");
}

const runOgFetchSrc = toJs(extractCallbackArrow("runOgFetch"), "runOgFetch");
const applyOgPreviewSrc = toJs(
  extractCallbackArrow("applyOgPreview"),
  "applyOgPreview",
);

// ===========================================================================
// 1. runOgFetch: fetch stages the preview, never touches form values.
// ===========================================================================
console.log("[test-og-preview-card] runOgFetch stages preview without form writes");

// Statically: the fetch callback must not write the form.
assert.ok(
  !extractCallbackArrow("runOgFetch").includes("setValues"),
  "runOgFetch must never call setValues (fetch must not auto-fill the form)",
);
ok("runOgFetch source contains no setValues call");

function makeFetchHarness({ linkUrl, meta, fetchError }) {
  const state = {
    ogError: "",
    ogSuccess: null,
    ogFetching: null,
    ogPreview: undefined,
    fetchCalls: [],
  };
  const scope = {
    linkUrl,
    fetchOgMeta: async (u) => {
      state.fetchCalls.push(u);
      if (fetchError) throw fetchError;
      return meta;
    },
    setOgFetching: (v) => (state.ogFetching = v),
    setOgError: (v) => (state.ogError = v),
    setOgSuccess: (v) => (state.ogSuccess = v),
    setOgPreview: (v) => (state.ogPreview = v),
    setValues: () => {
      throw new Error(
        "REGRESSION: runOgFetch wrote form values — fetch must only stage the preview card",
      );
    },
  };
  const fn = runExtractedCall(`(${runOgFetchSrc})`, scope, "runOgFetch");
  return { state, run: fn };
}

{
  // Empty URL → inline error, no network call, no preview.
  const h = makeFetchHarness({ linkUrl: "   " });
  await h.run();
  assert.equal(h.state.ogError, "Please enter a URL first.");
  assert.equal(h.state.fetchCalls.length, 0);
  assert.equal(h.state.ogPreview, undefined, "no preview staged");
  ok("empty URL → error, no fetch, no preview");
}

const FULL_META = {
  title: "Fetched Title",
  description: "Fetched description",
  image_url: "https://cdn.example.com/og.png",
  favicon_url: "https://example.com/favicon.ico",
};

{
  // Happy path: meta staged into ogPreview; form untouched (setValues throws).
  const h = makeFetchHarness({
    linkUrl: " https://example.com/post ",
    meta: FULL_META,
  });
  await h.run();
  assert.deepEqual(h.state.fetchCalls, ["https://example.com/post"], "URL trimmed");
  assert.deepEqual(h.state.ogPreview, FULL_META, "meta staged into the preview card");
  assert.equal(h.state.ogError, "");
  assert.equal(h.state.ogSuccess, false, "success flag stays off until Apply");
  assert.equal(h.state.ogFetching, false, "spinner cleared");
  ok("successful fetch stages the preview and leaves the form untouched");
}

{
  // All-empty meta → "no details" error, nothing staged.
  const h = makeFetchHarness({
    linkUrl: "https://example.com",
    meta: { title: "", description: "", image_url: "", favicon_url: "" },
  });
  await h.run();
  assert.equal(h.state.ogError, "No details found for that page.");
  assert.equal(h.state.ogPreview, null, "preview stays cleared");
  ok("empty meta → 'no details' error, no preview card");
}

{
  // Fetch failure → error message surfaced, nothing staged.
  const h = makeFetchHarness({
    linkUrl: "https://example.com",
    fetchError: Object.assign(new Error("Could not resolve host."), {}),
  });
  await h.run();
  assert.equal(h.state.ogError, "Could not resolve host.");
  assert.equal(h.state.ogPreview, null);
  assert.equal(h.state.ogFetching, false, "spinner cleared on failure");
  ok("fetch error → message surfaced, no preview card");
}

// ===========================================================================
// 2. applyOgPreview: fills only EMPTY fields; no thumbnail for featured_pin.
// ===========================================================================
console.log("[test-og-preview-card] applyOgPreview fill-only-empty rules");

function runApply({ meta, blockType, values }) {
  const state = {
    ogPreview: "UNTOUCHED",
    ogSuccess: null,
    next: values,
    updaterCalls: 0,
  };
  const scope = {
    ogPreview: meta,
    block: { type: blockType },
    setValues: (updater) => {
      state.updaterCalls++;
      state.next = updater(state.next);
    },
    setOgPreview: (v) => (state.ogPreview = v),
    setOgSuccess: (v) => (state.ogSuccess = v),
  };
  const fn = runExtractedCall(`(${applyOgPreviewSrc})`, scope, "applyOgPreview");
  fn();
  return state;
}

{
  // Empty "link" form: title → label, description + thumbnail filled.
  const s = runApply({ meta: FULL_META, blockType: "link", values: {} });
  assert.equal(s.next.label, "Fetched Title", "link blocks title-fill 'label'");
  assert.equal(s.next.text, undefined, "'text' untouched for plain link");
  assert.equal(s.next.description, "Fetched description");
  assert.equal(s.next.thumbnail, "https://cdn.example.com/og.png");
  assert.equal(s.ogPreview, null, "Apply clears the preview card");
  assert.equal(s.ogSuccess, true, "Apply flips the success flag");
  ok("link: empty form gets label/description/thumbnail; preview cleared");
}

{
  // Empty "link_big" form: title lands in "text".
  const s = runApply({ meta: FULL_META, blockType: "link_big", values: {} });
  assert.equal(s.next.text, "Fetched Title", "featured variants title-fill 'text'");
  assert.equal(s.next.label, undefined);
  assert.equal(s.next.thumbnail, "https://cdn.example.com/og.png");
  ok("link_big: title fills 'text', thumbnail filled");
}

{
  // featured_pin: title in "text", but NEVER a thumbnail.
  const s = runApply({ meta: FULL_META, blockType: "featured_pin", values: {} });
  assert.equal(s.next.text, "Fetched Title");
  assert.equal(s.next.description, "Fetched description");
  assert.equal(
    s.next.thumbnail,
    undefined,
    "featured_pin must never receive a thumbnail from Apply",
  );
  ok("featured_pin: no thumbnail is ever applied");
}

{
  // Non-empty fields are preserved — ANY title-ish key blocks the title fill.
  for (const titleKey of ["text", "label", "title"]) {
    const s = runApply({
      meta: FULL_META,
      blockType: "link",
      values: { [titleKey]: "My own title" },
    });
    assert.equal(s.next[titleKey], "My own title", `existing ${titleKey} kept`);
    assert.equal(s.next.label ?? s.next[titleKey], "My own title");
    assert.ok(
      !Object.entries(s.next).some(
        ([k, v]) => v === "Fetched Title" && k !== titleKey,
      ),
      `fetched title must not land anywhere when ${titleKey} is set`,
    );
  }
  const s = runApply({
    meta: FULL_META,
    blockType: "link",
    values: { description: "Mine", thumbnail: "https://me.example/pic.png" },
  });
  assert.equal(s.next.description, "Mine", "existing description kept");
  assert.equal(s.next.thumbnail, "https://me.example/pic.png", "existing thumbnail kept");
  assert.equal(s.next.label, "Fetched Title", "still fills the empty title key");
  ok("Apply never overwrites non-empty title/description/thumbnail");
}

{
  // Whitespace-only counts as empty; favicon is the thumbnail fallback.
  const s = runApply({
    meta: { ...FULL_META, image_url: "" },
    blockType: "link",
    values: { description: "   " },
  });
  assert.equal(s.next.description, "Fetched description", "whitespace = empty");
  assert.equal(
    s.next.thumbnail,
    "https://example.com/favicon.ico",
    "favicon fallback when image_url missing",
  );
  ok("whitespace-only fields are fillable; favicon fallback works");
}

{
  // Null preview (already dismissed) → strict no-op.
  const s = runApply({ meta: null, blockType: "link", values: { label: "x" } });
  assert.equal(s.updaterCalls, 0, "no setValues when there is no staged preview");
  assert.equal(s.ogPreview, "UNTOUCHED");
  assert.equal(s.ogSuccess, null);
  ok("Apply with no staged preview is a no-op");
}

// ===========================================================================
// 3. UI wiring: fetch button → runOgFetch, card renders from ogPreview,
//    Apply → applyOgPreview, Dismiss → setOgPreview(null) only.
// ===========================================================================
console.log("[test-og-preview-card] preview card UI wiring");

assert.match(
  screenSrc,
  /label=\{ogFetching \? "Fetching…" : "Fetch details from URL"\}[\s\S]{0,120}?onPress=\{runOgFetch\}/,
  "the Fetch details button must call runOgFetch",
);
assert.match(
  screenSrc,
  /\{ogPreview \? \(/,
  "the preview card must render from the staged ogPreview state",
);
assert.match(
  screenSrc,
  /label="Apply" onPress=\{applyOgPreview\}/,
  "the card's Apply button must call applyOgPreview",
);
ok("Fetch button → runOgFetch; card gated on ogPreview; Apply wired");

{
  const dismissAt = screenSrc.indexOf('label="Dismiss"');
  assert.notEqual(dismissAt, -1, "Dismiss button not found");
  const snippet = screenSrc.slice(dismissAt, dismissAt + 220);
  assert.match(
    snippet,
    /onPress=\{\(\) => setOgPreview\(null\)\}/,
    "Dismiss must only clear the staged preview",
  );
  assert.ok(
    !snippet.includes("setValues"),
    "Dismiss must not touch form values",
  );
  ok("Dismiss discards the preview without any form changes");
}

assert.match(
  screenSrc,
  /Apply fills only the empty fields below\./,
  "card must explain the fill-only-empty contract to the creator",
);
ok("card copy documents the fill-only-empty behavior");

console.log(`\n[test-og-preview-card] all assertions passed (${passed} groups)`);
