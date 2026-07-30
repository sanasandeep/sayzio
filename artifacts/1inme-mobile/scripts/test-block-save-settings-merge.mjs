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
// The assertions are deliberately FORMATTING-INSENSITIVE: snippets are
// compiled into token-based regexes (via `flex()` below) where any amount
// of whitespace/line breaks may separate tokens and commas/semicolons are
// optional. A prettier reformat must not break this harness; removing the
// actual protection (e.g. the `...prevSettings` spread) still must.
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

// Compile a code snippet into a whitespace-agnostic regex:
// - tokens are runs of word chars or single punctuation chars
// - any whitespace (including none, where tokens can't merge) is allowed
//   between tokens
// - commas and semicolons are optional (prettier adds/removes them)
function flex(snippet, flags = "") {
  const tokens = snippet.trim().match(/\w+|\S/g) ?? [];
  let pattern = "";
  let prevTok = null;
  for (const tok of tokens) {
    const esc = tok.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
    if (prevTok !== null) {
      const needsGap = /\w/.test(prevTok.slice(-1)) && /\w/.test(tok[0]);
      pattern += needsGap ? "\\s+" : "\\s*";
    }
    pattern += tok === "," || tok === ";" ? `${esc}?` : esc;
    prevTok = tok;
  }
  return new RegExp(pattern, flags);
}

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
  flex(
    "const prevSettings = (block?.settings as Record<string, unknown> | undefined) ?? {};",
  ).test(editorSrc),
  "save reads the block's current settings as the payload base",
);
ok(
  flex(
    "const nextSettings: Record<string, unknown> = { ...prevSettings, ...values, };",
  ).test(editorSrc),
  "nextSettings spreads previous settings BEFORE the edited values",
);

// The old payload deleted `_style` outright, which (combined with the
// wholesale replace) wiped web-configured styling for every block type
// without a bespoke re-merge branch. That wholesale strip must stay gone.
// The ONE legitimate delete is the bg-preset branch's guarded clear: it
// only fires when the merged `styleOut` ended up empty (`else` arm of the
// `Object.keys(styleOut).length > 0` re-assign), i.e. when there is
// genuinely no style left to persist — not a strip of live styling.
const styleDeleteRe = flex("delete nextSettings._style", "g");
const styleDeletes = [...editorSrc.matchAll(styleDeleteRe)];
ok(
  styleDeletes.length <= 1,
  "at most one _style delete exists (the bg-preset empty-style clear)",
);
if (styleDeletes.length === 1) {
  // Structural check instead of an exact-text one: within a short window
  // BEFORE the delete, the empty-styleOut guard and its `else` must appear,
  // so the delete is provably the guarded clear (survives brace-style or
  // line-break reformatting of the if/else).
  const before = editorSrc.slice(
    Math.max(0, styleDeletes[0].index - 400),
    styleDeletes[0].index,
  );
  ok(
    flex("if (Object.keys(styleOut).length > 0)").test(before) &&
      flex("nextSettings._style = styleOut").test(before) &&
      /\belse\b/.test(before),
    "the only _style delete is the guarded empty-styleOut clear in the bg-preset branch",
  );
}

// A real mobile edit still clears the seeded placeholder flag (previously
// dropped implicitly by the values-only payload).
ok(
  /delete\s+nextSettings\s*\.\s*_placeholder(?![\w$])/.test(editorSrc) &&
    /delete\s+nextSettings\s*\.\s*_placeholder_seed\b/.test(editorSrc),
  "placeholder seed flags are cleared on save",
);

// The bespoke merge branches for profile-card avatar frames and image-block
// photo stickers still merge INTO the persisted _style rather than
// replacing it with only their own keys.
ok(
  [
    ...editorSrc.matchAll(
      flex(
        "const prevStyle = (block?.settings?._style as Record<string, unknown> | undefined) ?? {}; const styleOut: Record<string, unknown> = { ...prevStyle };",
        "g",
      ),
    ),
  ].length >= 2,
  "profile-card + image branches merge into the persisted _style",
);

console.log(`\nAll ${passed} checks passed.`);
