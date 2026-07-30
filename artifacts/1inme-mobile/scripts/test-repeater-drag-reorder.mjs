// Source-driven coverage for the long-press drag-to-reorder math in
// components/DraggableRepeaterRows.tsx (gallery images, list/numbered-list
// items, pricing rows and profile-card social links in the block editor).
//
// The component's gesture worklet and commit path both delegate to two pure
// exported functions — shiftSlots (uid→slot map updates as the dragged row
// crosses midpoints) and slotsToPermutation (final map → permutation of
// ORIGINAL indexes handed to onReorder). The parents all apply it as
// `perm.map((i) => prev[i])`, so this test lifts the REAL functions out of
// the shipped source, transpiles them verbatim, and asserts the full
// drag → commit → state round-trip. It also pins the wiring: all four
// repeaters in [blockId].tsx render through DraggableRepeaterRows and keep
// their arrow-button fallbacks.
//
// Follows the source-driven convention of test-sticker-drag-place.mjs:
// run what ships, not a re-implementation.
//
// Run via `node scripts/test-repeater-drag-reorder.mjs` (package script
// `test:repeater-drag-reorder`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const compPath = join(root, "components", "DraggableRepeaterRows.tsx");
const compSrc = readFileSync(compPath, "utf8");
const screenPath = join(root, "app", "links", "[id]", "blocks", "[blockId].tsx");
const screenSrc = readFileSync(screenPath, "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

function extractTopLevelFn(src, name, file) {
  const sig = `export function ${name}(`;
  const at = src.indexOf(sig);
  assert.notEqual(at, -1, `${sig} not found in ${file}`);
  const end = src.indexOf("\n}", at);
  assert.notEqual(end, -1, `could not find end of ${name}`);
  return src.slice(at, end + 2);
}

const fnSrc = ["shiftSlots", "slotsToPermutation"]
  .map((n) => extractTopLevelFn(compSrc, n, "DraggableRepeaterRows.tsx"))
  .join("\n\n");

const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;
const js = ts.transpileModule(
  `${fnSrc}\n\nmodule.exports = { shiftSlots, slotsToPermutation };`,
  {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
    },
    fileName: "repeater-drag.ts",
  },
).outputText;

const moduleObj = { exports: {} };
// eslint-disable-next-line no-new-func
new Function("module", "exports", js)(moduleObj, moduleObj.exports);
const { shiftSlots, slotsToPermutation } = moduleObj.exports;

// ---------------------------------------------------------------------------
// 1. shiftSlots
// ---------------------------------------------------------------------------
console.log("[test-repeater-drag-reorder] shiftSlots");

const identity = { a: 0, b: 1, c: 2, d: 3 };

assert.equal(shiftSlots(identity, "b", 1, 1), null);
ok("no-op when target equals current slot");

// Drag row b (slot 1) down past c into slot 2: c slides up.
assert.deepEqual(shiftSlots(identity, "b", 1, 2), { a: 0, b: 2, c: 1, d: 3 });
ok("dragging down slides the crossed row up");

// Drag row d (slot 3) to the top: everyone else slides down one.
assert.deepEqual(shiftSlots(identity, "d", 3, 0), { a: 1, b: 2, c: 3, d: 0 });
ok("dragging to the top slides all crossed rows down");

// Mid-drag continuation: after b moved to slot 2, dragging back to 0
// restores a and c around it.
const mid = shiftSlots(identity, "b", 1, 2);
assert.deepEqual(shiftSlots(mid, "b", 1, 0), { a: 1, b: 0, c: 2, d: 3 });
ok("reversing direction mid-drag re-slides rows correctly");

// Slots always remain a permutation of 0..n-1 under random churn.
{
  let slots = { ...identity };
  const uids = ["a", "b", "c", "d"];
  let seed = 42;
  const rnd = () => (seed = (seed * 1103515245 + 12345) % 2 ** 31) / 2 ** 31;
  for (let step = 0; step < 200; step++) {
    const uid = uids[Math.floor(rnd() * 4)];
    const origIdx = uids.indexOf(uid);
    const target = Math.floor(rnd() * 4);
    const next = shiftSlots(slots, uid, origIdx, target);
    if (next) slots = next;
    const sorted = Object.values(slots).sort();
    assert.deepEqual(sorted, [0, 1, 2, 3], `step ${step} broke the permutation`);
  }
  ok("200 random shifts keep slots a valid permutation");
}

// ---------------------------------------------------------------------------
// 2. slotsToPermutation + parent round-trip
// ---------------------------------------------------------------------------
console.log("[test-repeater-drag-reorder] slotsToPermutation round-trip");

{
  const uids = ["a", "b", "c", "d"];
  // Drag d to the top, as above.
  const finalSlots = shiftSlots(identity, "d", 3, 0);
  const perm = slotsToPermutation(uids, finalSlots);
  assert.deepEqual(perm, [3, 0, 1, 2]);
  ok("permutation lists original indexes in new visual order");

  // Exactly how every repeater applies it: perm.map((i) => prev[i]).
  const items = [{ t: "one" }, { t: "two" }, { t: "three" }, { t: "four" }];
  const reordered = perm.map((i) => items[i]);
  assert.deepEqual(
    reordered.map((r) => r.t),
    ["four", "one", "two", "three"],
  );
  ok("perm.map((i) => prev[i]) yields the dragged order");
}

{
  // Missing slot entries fall back to original index (pre-measure frame).
  const perm = slotsToPermutation(["a", "b", "c"], { b: 0, a: 1 });
  assert.deepEqual(perm, [1, 0, 2]);
  ok("uids missing from the slot map keep their original index");
}

// ---------------------------------------------------------------------------
// 3. Wiring: all four repeaters render via DraggableRepeaterRows and keep
//    their arrow fallbacks + save-path state setters.
// ---------------------------------------------------------------------------
console.log("[test-repeater-drag-reorder] block editor wiring");

assert.match(
  screenSrc,
  /import \{ DraggableRepeaterRows \} from "@\/components\/DraggableRepeaterRows"/,
);
ok("[blockId].tsx imports DraggableRepeaterRows");

for (const setter of [
  "setListItems",
  "setPricingItems",
  "setGalleryImages",
  "setProfileSocials",
]) {
  const wire = new RegExp(
    `onReorder=\\{\\(perm\\) =>\\s*${setter}\\(\\(prev\\) => perm\\.map\\(\\(i\\) => prev\\[i\\]\\)\\)`,
  );
  assert.match(screenSrc, wire, `${setter} drag onReorder wiring missing`);
  ok(`${setter} repeater reorders via perm.map((i) => prev[i])`);
}

for (const tid of [
  "list-item-up-",
  "list-item-down-",
  "pricing-item-up-",
  "pricing-item-down-",
  "gallery-img-up-",
  "gallery-img-down-",
  "profile-social-up-",
  "profile-social-down-",
]) {
  assert.ok(
    screenSrc.includes(`testID={\`${tid}\${idx}\`}`),
    `arrow fallback ${tid} removed`,
  );
}
ok("arrow-button fallbacks remain on all four repeaters");

assert.equal(
  (compSrc.match(/activateAfterLongPress\(/g) || []).length,
  1,
  "drag should activate via long-press",
);
ok("drag handle activates after long-press");

console.log(`\nPASS — ${passed} checks`);
