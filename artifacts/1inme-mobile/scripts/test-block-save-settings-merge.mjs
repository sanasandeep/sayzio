// Regression tests for the mobile block editor's save-payload assembly
// (Task: stop mobile saves from silently dropping an image block's styling
// extras).
//
// The API PATCH (/api/v1/links/{id}/blocks/{blockId}) replaces `settings`
// wholesale, so the mobile editor must spread the block's CURRENT settings
// under its edited values. Otherwise object-shaped keys the mobile UI does
// not surface — `_image_style` (mask/border/shadow), `_style` for every
// block type, `_style_custom_snapshot` — are silently wiped whenever a
// creator edits, say, a caption on mobile.
//
// Following the source-driven convention in test-block-cache.mjs, these
// checks read the real source and assert on the wiring, so a refactor that
// reverts to a values-only payload fails here.
//
// Run via `node scripts/test-block-save-settings-merge.mjs` (package script
// `test:block-save-settings-merge`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const editorSrc = readFileSync(
  join(root, "app", "links", "[id]", "blocks", "[blockId].tsx"),
  "utf8",
);

let passed = 0;
function ok(cond, label) {
  assert.ok(cond, label);
  passed += 1;
  console.log(`  ok — ${label}`);
}

console.log("[test-block-save-settings-merge]");

// The save payload must start from the block's persisted settings and only
// then overlay the string-only `values` map, so unedited object-shaped keys
// survive the API's wholesale settings replace.
ok(
  /const prevSettings =\s*\(block\?\.settings as Record<string, unknown> \| undefined\) \?\? \{\};/.test(
    editorSrc,
  ),
  "save reads the block's current settings as the payload base",
);
ok(
  /const nextSettings: Record<string, unknown> = \{\s*\.\.\.prevSettings,\s*\.\.\.values,\s*\};/.test(
    editorSrc,
  ),
  "nextSettings spreads previous settings BEFORE the edited values",
);

// The old payload deleted `_style` outright, which (combined with the
// wholesale replace) wiped web-configured styling for every block type
// without a bespoke re-merge branch. That wholesale strip must stay gone.
// The ONE legitimate delete is the bg-preset branch's guarded clear: it
// only fires when the merged `styleOut` ended up empty (`else` arm of the
// `Object.keys(styleOut).length > 0` re-assign), i.e. when there is
// genuinely no style left to persist — not a strip of live styling.
const styleDeletes = editorSrc.match(/delete nextSettings\._style;/g) ?? [];
ok(
  styleDeletes.length <= 1,
  "at most one _style delete exists (the bg-preset empty-style clear)",
);
if (styleDeletes.length === 1) {
  ok(
    /if \(Object\.keys\(styleOut\)\.length > 0\) nextSettings\._style = styleOut;\s*\n\s*else delete nextSettings\._style;\s*\n\s*\}\s*\n\s*\/\/ Map-location block/.test(
      editorSrc,
    ),
    "the only _style delete is the guarded empty-styleOut clear in the bg-preset branch",
  );
}

// A real mobile edit still clears the seeded placeholder flag (previously
// dropped implicitly by the values-only payload).
ok(
  /delete nextSettings\._placeholder;\s*\n\s*delete nextSettings\._placeholder_seed;/.test(
    editorSrc,
  ),
  "placeholder seed flags are cleared on save",
);

// The bespoke merge branches for profile-card avatar frames and image-block
// photo stickers still merge INTO the persisted _style rather than
// replacing it with only their own keys.
ok(
  (editorSrc.match(
    /const prevStyle =\s*\(block\?\.settings\?\._style as Record<string, unknown> \| undefined\) \?\? \{\};\s*const styleOut: Record<string, unknown> = \{ \.\.\.prevStyle \};/g,
  ) || []).length >= 2,
  "profile-card + image branches merge into the persisted _style",
);

console.log(`\nAll ${passed} checks passed.`);
