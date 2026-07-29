// Source-driven coverage for the image-block sticker drag/placement math in
// app/links/[id]/blocks/[blockId].tsx — the mobile half of the sticker
// round-trip that tests/Feature/ApiBiolinkBlockStickerRoundTripTest.php pins
// on the server side.
//
// The screen's PanResponder derives every save payload from three pure
// helpers: normalizePhotoStickers (hydrating _photo_stickers from the API),
// phAnchorBase (anchor preset → top-left px) and phNearestPlacement
// (dragged top-left → nearest preset + clamped dx/dy remainder). A silent
// drift in any of them breaks drag placement or persists garbage pos/dx/dy,
// so this test lifts the REAL functions (plus PHOTO_STICKER_POSITIONS and
// clampNum) out of the shipped source, transpiles the extracted TS verbatim,
// and asserts:
//
//   1. normalizePhotoStickers — non-arrays → [], invalid file_id rows are
//      dropped, unknown pos falls back to top_right, size/rotate/dx/dy are
//      rounded + clamped to their editor bounds, max 4 stickers survive.
//   2. phAnchorBase — the exact per-preset top-left math (identical to the
//      web editor's anchorBase()) for all six presets.
//   3. phNearestPlacement — drops on/near each anchor pick that preset with
//      the remainder as dx/dy; far drags clamp dx/dy to ±80; the grant→move
//      round-trip (base + dx/dy re-derived as start, then re-placed) is
//      stable (re-placing a placed sticker is a no-op).
//
// Follows the source-driven convention of test-block-cache.mjs /
// test-import-url.mjs: run what ships, not a re-implementation.
//
// Run via `node scripts/test-sticker-drag-place.mjs` (package script
// `test:sticker-drag-place`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenPath = join(root, "app", "links", "[id]", "blocks", "[blockId].tsx");
const screenSrc = readFileSync(screenPath, "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Lift the real declarations out of the screen source (balanced braces /
// brackets so signatures spanning multiple lines survive), then transpile
// the extracted TypeScript verbatim so type annotations, `as const` and the
// `best!` assertion run as plain JS.
// ---------------------------------------------------------------------------
function extractBalanced(src, signature, openCh, closeCh, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  const open = src.indexOf(openCh, at);
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    if (src[i] === openCh) depth++;
    else if (src[i] === closeCh) {
      depth--;
      if (depth === 0) {
        end = i + 1;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `could not balance ${openCh}${closeCh} for ${signature}`);
  return src.slice(at, end);
}

const positionsConst =
  extractBalanced(
    screenSrc,
    "const PHOTO_STICKER_POSITIONS = [",
    "[",
    "]",
    "[blockId].tsx",
  ) + " as const;";

// Functions are top-level in the screen, so their closing brace is the
// first `}` at column 0 after the signature (brace-balancing would trip on
// phAnchorBase's object return-type annotation).
function extractTopLevelFn(name) {
  const sig = `function ${name}(`;
  const at = screenSrc.indexOf(sig);
  assert.notEqual(at, -1, `${sig} not found in [blockId].tsx`);
  const end = screenSrc.indexOf("\n}", at);
  assert.notEqual(end, -1, `could not find end of ${name}`);
  return screenSrc.slice(at, end + 2);
}

const fnSrc = ["clampNum", "normalizePhotoStickers", "phAnchorBase", "phNearestPlacement"]
  .map(extractTopLevelFn)
  .join("\n\n");

const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;
const js = ts.transpileModule(
  `${positionsConst}\n\ntype PhotoSticker = { file_id: number; url: string; pos: string; size: number; rotate: number; dx: number; dy: number };\n\n${fnSrc}\n\nmodule.exports = { normalizePhotoStickers, phAnchorBase, phNearestPlacement, clampNum, PHOTO_STICKER_POSITIONS };`,
  {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
    },
    fileName: "sticker-placement.ts",
  },
).outputText;

const moduleObj = { exports: {} };
// eslint-disable-next-line no-new-func
new Function("module", "exports", js)(moduleObj, moduleObj.exports);
const {
  normalizePhotoStickers,
  phAnchorBase,
  phNearestPlacement,
  PHOTO_STICKER_POSITIONS,
} = moduleObj.exports;

// ---------------------------------------------------------------------------
// 1. normalizePhotoStickers
// ---------------------------------------------------------------------------
console.log("[test-sticker-drag-place] normalizePhotoStickers");

assert.deepEqual(normalizePhotoStickers(undefined), []);
assert.deepEqual(normalizePhotoStickers(null), []);
assert.deepEqual(normalizePhotoStickers("nope"), []);
assert.deepEqual(normalizePhotoStickers({}), []);
ok("non-array input normalizes to []");

assert.deepEqual(
  normalizePhotoStickers([
    { file_id: 0 },
    { file_id: -3, url: "/f/x" },
    { file_id: "abc" },
    {},
    null,
    "junk",
  ]),
  [],
);
ok("rows without a positive numeric file_id are dropped");

const one = normalizePhotoStickers([
  { file_id: "7.6", url: "/f/abc", pos: "bottom_left", size: 90.4, rotate: -12.6, dx: 5.5, dy: -3.4 },
]);
assert.equal(one.length, 1);
assert.deepEqual(one[0], {
  file_id: 8,
  url: "/f/abc",
  pos: "bottom_left",
  size: 90,
  rotate: -13,
  dx: 6,
  dy: -3,
});
ok("numeric strings coerce, values round to integers");

const fallback = normalizePhotoStickers([
  { file_id: 1, pos: "middle" },
  { file_id: 2 },
  { file_id: 3, pos: 42, url: 99 },
])[0];
assert.equal(fallback.pos, "top_right");
assert.equal(
  normalizePhotoStickers([{ file_id: 3, pos: 42, url: 99 }])[0].url,
  "",
);
ok("unknown/non-string pos falls back to top_right; non-string url → ''");

for (const pos of PHOTO_STICKER_POSITIONS) {
  assert.equal(normalizePhotoStickers([{ file_id: 1, pos }])[0].pos, pos);
}
ok("all six presets survive normalization untouched");

const clamped = normalizePhotoStickers([
  { file_id: 1, size: 999, rotate: 720, dx: 500, dy: -500 },
  { file_id: 2, size: 1, rotate: -999, dx: -81, dy: 81 },
]);
assert.deepEqual(
  clamped.map((s) => [s.size, s.rotate, s.dx, s.dy]),
  [
    [160, 180, 80, -80],
    [24, -180, -80, 80],
  ],
);
ok("size clamps to [24,160], rotate to ±180, dx/dy to ±80");

const defaults = normalizePhotoStickers([{ file_id: 4 }])[0];
assert.deepEqual(defaults, {
  file_id: 4,
  url: "",
  pos: "top_right",
  size: 48,
  rotate: 0,
  dx: 0,
  dy: 0,
});
ok("missing fields get editor defaults (size 48, no offset/rotation)");

const many = normalizePhotoStickers(
  Array.from({ length: 7 }, (_v, i) => ({ file_id: i + 1 })),
);
assert.equal(many.length, 4);
assert.deepEqual(many.map((s) => s.file_id), [1, 2, 3, 4]);
ok("at most 4 stickers kept (first four valid rows win)");

const mixedCap = normalizePhotoStickers([
  { file_id: 0 },
  { file_id: 1 },
  { file_id: "x" },
  { file_id: 2 },
  { file_id: 3 },
  { file_id: 4 },
  { file_id: 5 },
]);
assert.deepEqual(mixedCap.map((s) => s.file_id), [1, 2, 3, 4]);
ok("cap counts only VALID rows — invalid rows don't consume slots");

// ---------------------------------------------------------------------------
// 2. phAnchorBase — the exact preset math on a 300×200 stage, size 48
// ---------------------------------------------------------------------------
console.log("[test-sticker-drag-place] phAnchorBase");

const W = 300;
const H = 200;
const S = 48;

assert.deepEqual(phAnchorBase("top_left", S, W, H), { x: -10, y: -10 });
assert.deepEqual(phAnchorBase("top_right", S, W, H), { x: W - S + 10, y: -10 });
assert.deepEqual(phAnchorBase("bottom_left", S, W, H), { x: -10, y: H - S + 10 });
assert.deepEqual(phAnchorBase("bottom_right", S, W, H), {
  x: W - S + 10,
  y: H - S + 10,
});
assert.deepEqual(phAnchorBase("center_left", S, W, H), {
  x: -12,
  y: H / 2 - S / 2,
});
assert.deepEqual(phAnchorBase("center_right", S, W, H), {
  x: W - S + 12,
  y: H / 2 - S / 2,
});
ok("all six presets produce the web editor's anchorBase() coordinates");

assert.deepEqual(phAnchorBase("garbage", S, W, H), phAnchorBase("top_right", S, W, H));
ok("unknown pos falls back to the top_right anchor");

// ---------------------------------------------------------------------------
// 3. phNearestPlacement — snap-to-nearest-anchor + clamped remainder
// ---------------------------------------------------------------------------
console.log("[test-sticker-drag-place] phNearestPlacement");

for (const pos of PHOTO_STICKER_POSITIONS) {
  const b = phAnchorBase(pos, S, W, H);
  const exact = phNearestPlacement(b.x, b.y, S, W, H);
  assert.deepEqual(exact, { pos, dx: 0, dy: 0 }, `exact drop on ${pos}`);
}
ok("dropping exactly on each anchor picks it with dx/dy = 0");

for (const pos of PHOTO_STICKER_POSITIONS) {
  const b = phAnchorBase(pos, S, W, H);
  const near = phNearestPlacement(b.x + 7, b.y - 5, S, W, H);
  assert.deepEqual(near, { pos, dx: 7, dy: -5 }, `near drop keeps ${pos}`);
}
ok("small offsets stay on the same preset with the remainder as dx/dy");

// Far off-anchor drag: remainder clamps to ±80 even though the raw offset
// is larger (drop deep into the middle from top_left's side).
const farLeft = phNearestPlacement(-10 + 200, -10 + 30, S, W, H);
assert.ok(Math.abs(farLeft.dx) <= 80 && Math.abs(farLeft.dy) <= 80);
ok("dx/dy always clamp to ±80 regardless of drag distance");

// Dead-center drop on the stage: nearest anchors are the two center presets;
// verify the pick is one of them and the remainder is the true offset.
const cx = W / 2 - S / 2;
const cy = H / 2 - S / 2;
const center = phNearestPlacement(cx, cy, S, W, H);
assert.ok(
  center.pos === "center_left" || center.pos === "center_right",
  `center drop picked ${center.pos}`,
);
{
  const b = phAnchorBase(center.pos, S, W, H);
  assert.equal(center.dx, Math.round(clampAbs(cx - b.x)));
  assert.equal(center.dy, Math.round(clampAbs(cy - b.y)));
}
ok("center drop snaps to a center preset with the exact remainder");

function clampAbs(v) {
  return Math.min(80, Math.max(-80, v));
}

// Grant→move round-trip stability: the PanResponder starts each drag from
// base(pos) + dx/dy of the COMMITTED placement, so a zero-distance drag
// (place once, then re-derive from the placed point) must be a no-op —
// otherwise stickers jump on touch. Placement itself may snap an arbitrary
// point to a different anchor, so the invariant is idempotence, not that a
// raw offset survives verbatim.
for (const pos of PHOTO_STICKER_POSITIONS) {
  for (const [dx, dy] of [
    [0, 0],
    [17, -23],
    [-40, 40],
    [80, -80],
    [200, 150],
  ]) {
    const b = phAnchorBase(pos, S, W, H);
    const first = phNearestPlacement(b.x + dx, b.y + dy, S, W, H);
    const fb = phAnchorBase(first.pos, S, W, H);
    const second = phNearestPlacement(fb.x + first.dx, fb.y + first.dy, S, W, H);
    assert.deepEqual(second, first, `${pos} +(${dx},${dy}) idempotent`);
  }
}
ok("re-placing a placed sticker is a no-op (grant→move round-trip stable)");

// Moderate offsets that stay nearest their own anchor DO survive verbatim.
for (const pos of PHOTO_STICKER_POSITIONS) {
  const b = phAnchorBase(pos, S, W, H);
  const re = phNearestPlacement(b.x + 17, b.y + 21, S, W, H);
  assert.deepEqual(re, { pos, dx: 17, dy: 21 }, `${pos} keeps small offset`);
}
ok("moderate offsets commit verbatim as dx/dy on the same preset");

// Crossing the midline flips to the nearer preset: drag from top_left's
// anchor most of the way toward top_right's anchor.
const trBase = phAnchorBase("top_right", S, W, H);
const crossed = phNearestPlacement(trBase.x - 20, -10, S, W, H);
assert.deepEqual(crossed, { pos: "top_right", dx: -20, dy: 0 });
ok("dragging past the midpoint snaps to the nearer preset");

// The snapped result always survives normalizePhotoStickers unchanged —
// i.e. what the drag produces is exactly what the API save/reload keeps.
{
  const placed = phNearestPlacement(trBase.x + 33, -10 + 12, S, W, H);
  const round = normalizePhotoStickers([
    { file_id: 9, url: "/f/s", size: S, rotate: 0, ...placed },
  ])[0];
  assert.equal(round.pos, placed.pos);
  assert.equal(round.dx, placed.dx);
  assert.equal(round.dy, placed.dy);
}
ok("drag output round-trips through normalizePhotoStickers unchanged");

console.log(`\n[test-sticker-drag-place] PASS — ${passed} assertions groups`);
