// Runtime proof of the tile-packing algorithm behind the mobile Tiles
// background preview (components/BiolinkEffectBackground.tsx, Task #6212).
//
// The web renderer uses CSS grid auto-flow; the mobile preview packs the
// same [colSpan, rowSpan] cycles onto a 4-column grid manually. This lifts
// the REAL packTiles source out of the component and asserts, for every
// layout cycle the server catalog ships (uniform / metro / brick):
//   - every grid cell is covered exactly once (no gaps, no overlaps)
//   - every placed tile stays within the canvas bounds
//   - tile gradients cycle through the palette
//
// Run via `node scripts/test-bg-effect-tiles-pack.mjs`.

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const src = readFileSync(
  join(root, "components", "BiolinkEffectBackground.tsx"),
  "utf8",
);

// Lift packTiles + its constants verbatim, strip simple TS annotations.
const constMatch = src.match(/const GRID_COLS = \d+;\s*const TILE_GAP = \d+;/);
assert.ok(constMatch, "grid constants present");
const fnMatch = src.match(/function packTiles\([\s\S]*?\n\}/m);
assert.ok(fnMatch, "packTiles present");

const js = `${constMatch[0]}\n${fnMatch[0]}`
  .replace(/spec: TilesSpec, width: number, height: number/, "spec, width, height")
  .replace(/: boolean\[\]\[\]/, "")
  .replace(/Array<boolean>\(GRID_COLS\)/, "Array(GRID_COLS)")
  .replace(/const placed: PlacedTile\[\] = \[\];/, "const placed = [];");

// eslint-disable-next-line no-new-func
const packTiles = new Function(`${js}; return packTiles;`)();

// Layout span cycles mirrored from lib/bgEffectCatalog.ts (kept literal
// here so the test fails loudly if the packer stops handling any of them).
const LAYOUTS = {
  uniform: [[1, 1]],
  metro: [[2, 2], [1, 1], [1, 1], [2, 1], [1, 2], [1, 1], [2, 1], [1, 1]],
  brick: [[2, 1], [2, 1], [1, 1], [2, 1], [1, 1], [2, 1]],
};
const PALETTE = [
  ["#a", "#b"],
  ["#c", "#d"],
  ["#e", "#f"],
];

const W = 200; // canvas width → cell = 50
const H = 356; // 9:16-ish

let passed = 0;
const ok = (label) => {
  passed += 1;
  console.log(`  ok — ${label}`);
};

for (const [name, spans] of Object.entries(LAYOUTS)) {
  const placed = packTiles({ tiles: PALETTE, spans }, W, H);
  assert.ok(placed.length > 0, `${name}: places tiles`);

  const cellW = W / 4;
  const rows = Math.ceil(H / cellW) + 1;
  const cover = Array.from({ length: rows }, () => Array(4).fill(0));
  for (const t of placed) {
    assert.ok(t.left >= -0.01 && t.left + t.width <= W + 0.01, `${name}: in width bounds`);
    assert.ok(t.top >= -0.01, `${name}: in top bounds`);
    assert.ok(typeof t.from === "string" && typeof t.to === "string", `${name}: gradient pair`);
    const c0 = Math.round(t.left / cellW);
    const r0 = Math.round(t.top / cellW);
    const cs = Math.round(t.width / cellW);
    const rs = Math.round(t.height / cellW);
    for (let r = r0; r < r0 + rs; r++) {
      for (let c = c0; c < c0 + cs; c++) {
        cover[r][c] += 1;
      }
    }
  }
  for (let r = 0; r < rows; r++) {
    for (let c = 0; c < 4; c++) {
      assert.equal(cover[r][c], 1, `${name}: cell (${r},${c}) covered exactly once`);
    }
  }
  ok(`${name} layout: full coverage, no overlaps, bounds respected`);
}

// Gradient cycling: uniform layout, first 4 tiles cycle the 3-color palette.
const uni = packTiles({ tiles: PALETTE, spans: LAYOUTS.uniform }, W, H);
assert.equal(uni[0].from, "#a");
assert.equal(uni[1].from, "#c");
assert.equal(uni[2].from, "#e");
assert.equal(uni[3].from, "#a");
ok("palette gradients cycle in placement order");

console.log(`\nPASS test-bg-effect-tiles-pack (${passed} checks)`);
