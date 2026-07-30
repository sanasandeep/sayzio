// Regression tests for the curated stock-image gallery wiring in the
// mobile block editor (Task #6016 — mobile parity for the web
// dropzone-input "Stock" tab from Task #6015).
//
// Source-driven checks (convention: test-block-save-settings-merge.mjs):
//   1. The StockImageGalleryPicker component queries the platform-asset
//      catalog per folder tab (grid-images "Photos" + hand-drawn).
//   2. Image blocks surface the picker and a pick lands in `linkUrl`
//      (the save path writes it to settings.url — the image source).
//   3. The sticker stock flow imports the asset into the vault first
//      (server sanitizer requires an owned file_id) and then appends.
//      Task #6028: import is SERVER-side by asset key (the asset CDN
//      has no CORS headers, so the web build can't fetch the blob).
//   4. Gallery/grid blocks hydrate + persist the `images` repeater and
//      surface the picker.
//
// Run via `node scripts/test-stock-image-gallery.mjs` (package script
// `test:stock-image-gallery`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const pickerSrc = readFileSync(
  join(root, "components", "StockImageGalleryPicker.tsx"),
  "utf8",
);
const editorSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "[blockId].tsx"),
  "utf8",
);
const filesSrc = readFileSync(join(root, "lib", "api", "files.ts"), "utf8");
const kindsSrc = readFileSync(join(root, "lib", "api", "blocks.ts"), "utf8");

let passed = 0;
function ok(cond, label) {
  assert.ok(cond, label);
  passed += 1;
  console.log(`  ok — ${label}`);
}

console.log("[test-stock-image-gallery]");

// ── Picker component ────────────────────────────────────────────────
ok(
  /queryKey: \["platform-assets", tab\]/.test(pickerSrc) &&
    /getPlatformAssets\(tab\)/.test(pickerSrc),
  "picker queries the platform-asset catalog keyed by the active folder tab",
);
ok(
  /"grid-images", label: "Photos"/.test(pickerSrc) &&
    /"hand-drawn", label: "Hand-drawn"/.test(pickerSrc),
  "default tabs are Photos (grid-images) + Hand-drawn — matching the web Stock tab",
);
ok(
  /onSelect\(a\.url, a\)/.test(pickerSrc),
  "tapping a tile hands the asset's public URL to the parent",
);

// ── Image block: stock pick sets the image URL (settings.url) ──────
ok(
  /isImageBlock \? \(\s*<StockImageGalleryPicker[\s\S]{0,400}?onSelect=\{\(url\) => setLinkUrl\(url\)\}/.test(
    editorSrc,
  ),
  "image block's stock pick lands in linkUrl (saved to settings.url)",
);

// ── Sticker flow: stock pick must round-trip through the vault ─────
ok(
  /const addStickerFromStock[\s\S]{0,500}?importPlatformAsset\(\{ key: assetKey \}\);[\s\S]{0,200}?appendSticker\(file\);/.test(
    editorSrc,
  ),
  "stock sticker imports into the vault first, then appends (owned file_id)",
);
ok(
  /onSelect=\{\(_url, asset\) => void addStickerFromStock\(asset\.key\)\}/.test(
    editorSrc,
  ),
  "sticker pick hands the asset KEY to the server-side importer (CORS-free on web)",
);
ok(
  /folders=\{\[\{ folder: "hand-drawn", label: "Hand-drawn" \}\]\}/.test(
    editorSrc,
  ),
  "sticker stock picker narrows to the hand-drawn folder",
);

// ── Gallery/grid blocks: images repeater hydrate + save + picker ───
ok(
  /const isGalleryBlock = \["image_grid", "image_slider", "image_slider_v2"\]\.includes\(/.test(
    editorSrc,
  ),
  "gallery branch covers image_grid + both image_slider generations",
);
ok(
  /if \(isGalleryBlock\) \{\s*nextSettings\.images = galleryImages/.test(
    editorSrc,
  ),
  "save persists the images repeater into settings.images",
);
ok(
  /\.filter\(\(i\) => i\.url !== ""\);/.test(editorSrc),
  "rows without a URL are dropped on save",
);
ok(
  /isGalleryBlock \? \([\s\S]{0,20000}?<StockImageGalleryPicker/.test(editorSrc),
  "gallery section surfaces the stock picker",
);

// ── Editor labels for the gallery kinds ─────────────────────────────
ok(
  /type: "image_grid",\s*label: "Image grid"/.test(kindsSrc) &&
    /type: "image_slider_v2"/.test(kindsSrc),
  "BLOCK_KINDS labels the gallery kinds so the blocks list names them",
);

// ── Vault import helper: server-side, key-based (Task #6028) ────────
ok(
  /export async function importPlatformAsset/.test(filesSrc) &&
    /\/me\/files\/import-platform-asset/.test(filesSrc) &&
    /JSON\.stringify\(\{ key: args\.key \}\)/.test(filesSrc),
  "importPlatformAsset POSTs the asset key to the server-side importer (no browser CDN fetch)",
);
ok(
  !/importVaultFileFromUrl/.test(filesSrc) && !/fetch\(args\.url\)/.test(filesSrc),
  "the CORS-blocked browser-fetch import path is gone",
);

console.log(`\nAll ${passed} checks passed.`);
