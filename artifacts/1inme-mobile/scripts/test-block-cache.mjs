// Regression tests for the mobile block-editor cache-patching paths.
//
// The editor (app/links/[id]/blocks/index.tsx) updates its on-screen list
// *in place* by patching the React Query cache instead of refetching after
// every add / move / delete / toggle, and re-syncs from the server (via
// invalidateQueries) only when a mutation fails. Those paths are easy to
// regress silently, so this covers them two ways:
//
//   1. Unit — the pure transforms in lib/api/blockCache.ts.
//   2. Integration — the same transforms wired through a REAL QueryClient
//      (the actual cache the component talks to), plus the onError
//      invalidate behaviour, so a drift between helper and cache is caught.
//
// Following the convention in test-citation-href.mjs, we avoid pulling in a
// full TS test runner: the helpers are pure, so we strip their type
// annotations and evaluate them in isolation. The QueryClient is the same
// implementation the app imports.
//
// Run via `node scripts/test-block-cache.mjs` (package script
// `test:block-cache`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { QueryClient } from "@tanstack/react-query";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

// ---------------------------------------------------------------------------
// Load the pure helpers from lib/api/blockCache.ts.
//
// We pull each `export function` body out and strip the (simple) TS type
// annotations so the bodies can run as plain JS. This keeps the test honest
// — it exercises the real source, not a re-implementation.
// ---------------------------------------------------------------------------
const cacheSrc = readFileSync(
  join(root, "lib", "api", "blockCache.ts"),
  "utf8",
);

function extractFn(name) {
  // Match `export function NAME(...) {  ...balanced... }` up to the first
  // line that is a closing brace at column 0 (our functions are top-level).
  const re = new RegExp(
    `export function ${name}\\b[\\s\\S]*?\\n\\}`,
    "m",
  );
  const m = cacheSrc.match(re);
  if (!m) throw new Error(`could not find ${name} in blockCache.ts`);
  return m[0];
}

const NAMES = [
  "appendBlock",
  "replaceBlock",
  "removeBlockTree",
  "moveBlock",
  "orderIds",
];

const stripped = NAMES.map(extractFn).join("\n\n");
const js = stripped
  // Drop the param / return type annotations our helpers use.
  .replace(/:\s*Block\[\]\s*\|\s*undefined/g, "")
  .replace(/:\s*Block\[\]\s*\|\s*null/g, "")
  .replace(/:\s*Block\[\]/g, "")
  .replace(/:\s*Block\b/g, "")
  .replace(/:\s*number\[\]/g, "")
  .replace(/:\s*number\b/g, "")
  .replace(/:\s*-1\s*\|\s*1/g, "")
  .replace(/export function/g, "function");

// eslint-disable-next-line no-new-func
const { appendBlock, replaceBlock, removeBlockTree, moveBlock, orderIds } =
  new Function(
    `${js}; return { appendBlock, replaceBlock, removeBlockTree, moveBlock, orderIds };`,
  )();

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------
const KEY = ["blocks", 42];

function block(id, extra = {}) {
  return {
    id,
    link_id: 42,
    type: "link",
    sort_order: id,
    parent_id: null,
    is_active: true,
    settings: {},
    created_at: null,
    updated_at: null,
    ...extra,
  };
}

function ids(list) {
  return (list ?? []).map((b) => b.id);
}

function freshClient(seed) {
  const qc = new QueryClient();
  qc.setQueryData(KEY, seed);
  return qc;
}

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ===========================================================================
// 1. Pure helper unit tests
// ===========================================================================
console.log("[test-block-cache] pure helpers");

// appendBlock
assert.deepEqual(ids(appendBlock([block(1), block(2)], block(3))), [1, 2, 3]);
assert.deepEqual(ids(appendBlock(undefined, block(9))), [9]);
ok("appendBlock appends to the end (and seeds an empty cache)");

// replaceBlock
{
  const out = replaceBlock(
    [block(1), block(2, { is_active: true })],
    block(2, { is_active: false }),
  );
  assert.equal(out.find((b) => b.id === 2).is_active, false);
  assert.equal(out.find((b) => b.id === 1).is_active, true);
  // Untouched cache stays a no-op.
  assert.equal(replaceBlock(undefined, block(2)), undefined);
}
ok("replaceBlock swaps a single block by id, leaves others untouched");

// removeBlockTree — parent + its children
{
  const list = [
    block(1),
    block(2, { type: "card" }),
    block(3, { parent_id: 2 }),
    block(4, { parent_id: 2 }),
    block(5),
  ];
  assert.deepEqual(ids(removeBlockTree(list, 2)), [1, 5]);
  // Deleting a leaf only removes that leaf.
  assert.deepEqual(ids(removeBlockTree(list, 5)), [1, 2, 3, 4]);
  // Unknown id is a no-op.
  assert.deepEqual(ids(removeBlockTree(list, 999)), [1, 2, 3, 4, 5]);
}
ok("removeBlockTree drops the block and every child by parent_id");

// moveBlock
{
  const list = [block(1), block(2), block(3)];
  assert.deepEqual(ids(moveBlock(list, 0, 1)), [2, 1, 3]); // down
  assert.deepEqual(ids(moveBlock(list, 2, -1)), [1, 3, 2]); // up
  assert.equal(moveBlock(list, 0, -1), null); // off the top
  assert.equal(moveBlock(list, 2, 1), null); // off the bottom
  // The original list is never mutated in place.
  assert.deepEqual(ids(list), [1, 2, 3]);
}
ok("moveBlock swaps within bounds, returns null at the edges, no mutation");

// orderIds
assert.deepEqual(orderIds([block(3), block(1), block(2)]), [3, 1, 2]);
ok("orderIds maps the list to the id array sent to the reorder endpoint");

// ===========================================================================
// 2. Integration tests through a real QueryClient
//
// These mirror the component's mutation handlers exactly: success patches
// the cache via the helper above; failure calls invalidateQueries so the
// list re-syncs from the server. We assert against the live cache state.
// ===========================================================================
console.log("[test-block-cache] real QueryClient cache patching");

// --- adding a block: appends + highlights it (create.onSuccess) ----------
{
  const qc = freshClient([block(1), block(2)]);
  let highlightId = null;

  // create.onSuccess(b)
  const created = block(3);
  qc.setQueryData(KEY, (old) => appendBlock(old, created));
  highlightId = created.id;

  assert.deepEqual(ids(qc.getQueryData(KEY)), [1, 2, 3]);
  assert.equal(highlightId, 3, "newly added block is the highlight target");
}
ok("add → cache gets the new block appended and it becomes the highlight");

// --- inserting a form/Buzz/AI block from the special panel (onInserted) ---
{
  const qc = freshClient([block(1)]);
  let highlightId = null;

  // SpecialPanel insert.onSuccess(b) -> onInserted(b) -> appendBlockToCache + highlight
  const inserted = block(7, { type: "form" });
  qc.setQueryData(KEY, (old) => appendBlock(old, inserted));
  highlightId = inserted.id;

  assert.deepEqual(ids(qc.getQueryData(KEY)), [1, 7]);
  assert.equal(highlightId, 7);
}
ok("special-panel insert → block appended in place and highlighted");

// --- toggling active state (toggle.onSuccess) ----------------------------
{
  const qc = freshClient([block(1, { is_active: true }), block(2)]);
  const updated = block(1, { is_active: false });
  qc.setQueryData(KEY, (old) => replaceBlock(old, updated));

  assert.equal(qc.getQueryData(KEY).find((b) => b.id === 1).is_active, false);
}
ok("toggle → the flipped block is replaced in the cache by the server copy");

// --- deleting removes the block and its children (remove.onSuccess) -------
{
  const qc = freshClient([
    block(1),
    block(2, { type: "card" }),
    block(3, { parent_id: 2 }),
    block(4, { parent_id: 2 }),
  ]);
  qc.setQueryData(KEY, (old) => removeBlockTree(old, 2));

  assert.deepEqual(ids(qc.getQueryData(KEY)), [1]);
}
ok("delete → the card and its children all leave the cache");

// --- reordering persists optimistically (move) ---------------------------
{
  const qc = freshClient([block(1), block(2), block(3)]);
  const order = qc.getQueryData(KEY);
  const next = moveBlock(order, 0, 1);
  let persisted = null;
  // move() writes the optimistic order to the cache, then persists the ids.
  qc.setQueryData(KEY, next);
  persisted = orderIds(next);

  assert.deepEqual(ids(qc.getQueryData(KEY)), [2, 1, 3]);
  assert.deepEqual(persisted, [2, 1, 3], "reorder endpoint gets the new ids");
}
ok("reorder → optimistic order written to cache and the id list persisted");

// ===========================================================================
// 3. Error paths re-sync from the server (onError invalidate behaviour)
// ===========================================================================
console.log("[test-block-cache] error paths invalidate (re-sync)");

for (const label of ["toggle", "delete", "reorder"]) {
  const qc = freshClient([block(1), block(2)]);
  assert.equal(
    qc.getQueryState(KEY).isInvalidated,
    false,
    "query starts fresh",
  );
  // The mutation's onError: () => qc.invalidateQueries({ queryKey: KEY })
  qc.invalidateQueries({ queryKey: KEY });
  assert.equal(
    qc.getQueryState(KEY).isInvalidated,
    true,
    `${label} failure marks the list stale so it refetches`,
  );
}
ok("toggle/delete/reorder failures invalidate the list → server re-sync");

// ===========================================================================
// 4. Component wiring guards
//
// The integration tests above replicate the handlers; these source-level
// assertions make sure the component actually routes through the shared
// helpers and keeps the onError invalidate lines, so the two can't drift.
// ===========================================================================
console.log("[test-block-cache] component wiring");

const editorSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "index.tsx"),
  "utf8",
);

for (const helper of NAMES) {
  assert.ok(
    new RegExp(`\\b${helper}\\b`).test(editorSrc),
    `editor should use the shared ${helper} helper`,
  );
}
// Three mutations must re-sync on failure (toggle, remove, persistOrder).
const onErrorCount = (
  editorSrc.match(
    /onError:\s*\(\)\s*=>\s*qc\.invalidateQueries\(\{\s*queryKey:\s*\["blocks", id\]\s*\}\)/g,
  ) ?? []
).length;
assert.ok(
  onErrorCount >= 3,
  `expected >=3 onError invalidate handlers, found ${onErrorCount}`,
);
// Card-template apply re-syncs (it creates a parent + children sub-tree).
assert.ok(
  /onApplied=\{\(blockId\)\s*=>\s*\{[\s\S]*?invalidateQueries\(\{\s*queryKey:\s*\["blocks", id\]\s*\}\)/.test(
    editorSrc,
  ),
  "card-template apply should invalidate to pull in the new sub-tree",
);
ok("editor wires the shared helpers and keeps the onError/apply re-sync");

console.log(`\n[test-block-cache] all ${passed} checks passed`);
