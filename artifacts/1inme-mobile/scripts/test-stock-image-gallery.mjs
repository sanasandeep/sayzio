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
//   4. Gallery/grid blocks hydrate + persist the `images` repeater and
//      surface the picker; the vault import helper covers both native
//      (expo-file-system download) and web (blob fetch) paths.
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
  /const addStickerFromStock[\s\S]{0,400}?importVaultFileFromUrl\(\{ url \}\);[\s\S]{0,200}?appendSticker\(file\);/.test(
    editorSrc,
  ),
  "stock sticker imports into the vault first, then appends (owned file_id)",
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
  /isGalleryBlock \? \([\s\S]{0,6000}?<StockImageGalleryPicker/.test(editorSrc),
  "gallery section surfaces the stock picker",
);

// ── Editor labels for the gallery kinds ─────────────────────────────
ok(
  /type: "image_grid",\s*label: "Image grid"/.test(kindsSrc) &&
    /type: "image_slider_v2"/.test(kindsSrc),
  "BLOCK_KINDS labels the gallery kinds so the blocks list names them",
);

// ── Vault import helper: native + web branches ──────────────────────
ok(
  /export async function importVaultFileFromUrl/.test(filesSrc) &&
    /expo-file-system\/legacy/.test(filesSrc) &&
    /Platform\.OS === "web"/.test(filesSrc),
  "importVaultFileFromUrl covers native (download to cache) and web (blob) paths",
);
ok(
  /deleteAsync\(dl\.uri, \{ idempotent: true \}\)/.test(filesSrc),
  "native import cleans up the cached download",
);

console.log(`\nAll ${passed} checks passed.`);
