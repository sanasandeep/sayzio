// Regression coverage for the inline "Upload instead" action inside the
// AI-builder auto-sourced image preview box (app/links/[id]/ai-builder.tsx).
//
// The preview box (extracted thumbnails / generation-slot chips) only renders
// while the creator has NO uploads attached, and its "Upload instead" button
// must wire to the SAME shared addImage flow as the Photos section — pick an
// image, upload it to the vault via uploadWizardImage, append to `images`.
// Because the whole box is gated on `images.length === 0`, a successful
// upload replaces the extracted/generated preview flow outright, and
// buildPayload stops sending kept_images/skip_generated_slots.
//
// Source-driven checks (we assert on what ships, not a re-implementation):
//   1. The preview box is gated on `images.length === 0`.
//   2. The "Upload instead" button lives INSIDE that gated box, shows the
//      Uploading… label while busy, and its onPress is the shared addImage.
//   3. addImage really is the vault-upload flow: uploadWizardImage + a
//      setImages append + the max_images cap guard.
//   4. buildPayload only attaches kept_images / skip_generated_slots when
//      `images.length === 0` (uploads win outright server-side too).
//
// Run via `node scripts/test-ai-builder-upload-instead.mjs` (package script
// `test:ai-builder-upload-instead`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const src = readFileSync(
  join(root, "app", "links", "[id]", "ai-builder.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Balanced-brace extraction of the JSX block that a `{cond ? (` ternary opens.
// ---------------------------------------------------------------------------
function extractGatedBlock(gate) {
  const at = src.indexOf(gate);
  assert.notEqual(at, -1, `gate not found in source: ${gate}`);
  // The gate string ends with the "(" that opens the block — balance from it.
  const open = at + gate.length - 1;
  assert.equal(src[open], "(", "gate must end with the opening paren");
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "(") depth++;
    else if (src[i] === ")") {
      depth--;
      if (depth === 0) return src.slice(at, i + 1);
    }
  }
  assert.fail(`could not balance parens for gate: ${gate}`);
}

function extractFn(signature) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found`);
  const open = src.indexOf("{", at);
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") {
      depth--;
      if (depth === 0) return src.slice(at, i + 1);
    }
  }
  assert.fail(`could not balance braces for ${signature}`);
}

console.log("ai-builder 'Upload instead' wiring");

// 1. The preview box only renders while there are no uploads.
const previewBox = extractGatedBlock("{images.length === 0 ? (");
assert.match(
  previewBox,
  /No uploads — preview the images we'd use/,
  "gated block is the preview box",
);
ok("preview box is gated on images.length === 0 (uploads hide it)");

// 2. The Upload instead button is INSIDE the gated preview box and wires to
//    the shared addImage flow with the uploading state on the label/loading.
assert.match(
  previewBox,
  /uploads replace the[\s\S]{0,40}extracted and generated images/,
  "explanatory copy inside the preview box",
);
const btnAt = previewBox.indexOf('label={uploading ? "Uploading…" : "Upload instead"}');
assert.notEqual(btnAt, -1, "Upload instead button label with uploading state");
const btnTail = previewBox.slice(btnAt, btnAt + 400);
assert.match(btnTail, /loading=\{uploading\}/, "button shows the loading spinner");
assert.match(btnTail, /onPress=\{addImage\}/, "button onPress is the shared addImage");
ok("'Upload instead' button (inside the box) wires onPress to addImage");

// 3. addImage is the real shared vault-upload flow.
const addImage = extractFn("async function addImage()");
assert.match(
  addImage,
  /images\.length[\s\S]{0,60}intake\?\.max_images/,
  "max_images cap guard",
);
assert.match(addImage, /launchImageLibraryAsync/, "opens the image picker");
assert.match(addImage, /uploadWizardImage\(/, "uploads to the vault");
assert.match(
  addImage,
  /setImages\(\(prev\) => \[\.\.\.prev, url\]\)/,
  "appends the vault URL to images",
);
assert.match(addImage, /setUploading\(true\)/, "sets the uploading flag");
ok("addImage uploads via uploadWizardImage and appends to images[]");

// 4. Uploads win outright in the payload: kept_images/skip_generated_slots
//    are only sent while images is empty.
const payload = extractFn("const buildPayload = ()");
assert.match(
  payload,
  /preview && images\.length === 0[\s\S]{0,120}kept_images:\s*keptImages,\s*skip_generated_slots:\s*skippedSlots/,
  "preview keys gated on images.length === 0",
);
ok("buildPayload drops kept_images/skip_generated_slots once an upload exists");

console.log(`\nPASS — ${passed} checks`);
