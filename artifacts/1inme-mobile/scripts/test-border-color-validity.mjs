// Source-driven tests for the mobile block editor's border-color validity
// hint + quick-pick swatch row (Task #6096).
//
// Border color fields stay free text (the value is saved as typed, matching
// web behavior), but an obviously invalid string shows a subtle inline
// warning. These checks lift the REAL `isLikelyCssColor` validator and the
// `borderSwatchSelected` selection predicate out of the shipped screen (via
// the `// [extract:...]` markers) and exercise them, then structurally
// assert the wiring: warning gated on the validator, swatch rows feeding
// setBdColor / setBdSides, and the save path left untouched (value saved
// as typed).
//
// Run via `node scripts/test-border-color-validity.mjs` (package script
// `test:border-color-validity`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedStatements } from "./lib/extract.mjs";

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

// ---- lifting helpers --------------------------------------------------

// Slice the source between the exact extract markers.
function lift(name) {
  const start = `// [extract:${name}:start]`;
  const end = `// [extract:${name}:end]`;
  const a = editorSrc.indexOf(start);
  const b = editorSrc.indexOf(end);
  assert.ok(a !== -1 && b !== -1 && b > a, `extract markers for ${name} exist`);
  return editorSrc.slice(a + start.length, b);
}

// The lifted blocks are TypeScript; strip the (deliberately simple) type
// annotations they carry so they evaluate as plain JS. Keep this list tiny
// and explicit — if the screen's annotations grow, extend it here.
function stripTypes(src) {
  return src
    .replace(/\(raw: string\)/g, "(raw)")
    .replace(/\(value: string, swatch: string\)/g, "(value, swatch)")
    .replace(/\): boolean \{/g, ") {");
}

// Compile a code snippet into a whitespace-agnostic regex (same convention
// as test-block-save-settings-merge.mjs).
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

console.log("[test-border-color-validity]");

// ---- 1. the REAL validator, lifted and exercised ----------------------

const isLikelyCssColor = runExtractedStatements(
  stripTypes(lift("isLikelyCssColor")),
  "isLikelyCssColor",
  {},
  "isLikelyCssColor",
  { test: "test-border-color-validity" },
);

const VALID = [
  "", // blank = default, never warns
  "   ", // whitespace-only counts as blank
  "#fff",
  "#ffff",
  "#ffffff",
  "#FFFFFF",
  "#ffffff80",
  "rgb(255, 255, 255)",
  "rgba(0,0,0,.5)",
  "hsl(210, 40%, 50%)",
  "hsla(210, 40%, 50%, 0.7)",
  "var(--color-primary-500)",
  "var(--accent, #333)",
  "white",
  "RebeccaPurple",
  "transparent",
  "currentColor",
  "  #ffffff  ", // surrounding whitespace tolerated
];
for (const v of VALID) {
  ok(isLikelyCssColor(v) === true, `valid: ${JSON.stringify(v)}`);
}

const INVALID = [
  "#fffff", // truncated 5-digit hex (the headline typo)
  "#gggggg", // non-hex digits
  "#1234567", // 7 digits
  "whiteish", // stray word
  "not a color",
  "ffffff", // missing the #
  "rgb()", // empty functional notation
  "url(javascript:alert(1))",
];
for (const v of INVALID) {
  ok(isLikelyCssColor(v) === false, `invalid: ${JSON.stringify(v)}`);
}

// ---- 2. the REAL swatch selection predicate ---------------------------

const borderSwatchSelected = runExtractedStatements(
  stripTypes(lift("borderSwatchSelected")),
  "borderSwatchSelected",
  {},
  "borderSwatchSelected",
  { test: "test-border-color-validity" },
);

ok(borderSwatchSelected("#ffffff", "#ffffff") === true, "exact match selects");
ok(borderSwatchSelected("#FFFFFF", "#ffffff") === true, "match is case-insensitive");
ok(borderSwatchSelected("  #ffffff ", "#ffffff") === true, "match trims whitespace");
ok(borderSwatchSelected("#fff", "#ffffff") === false, "short hex is a different value");
ok(borderSwatchSelected("", "#ffffff") === false, "blank never selects a swatch");

// ---- 3. structural wiring checks --------------------------------------

// The swatch-row component renders selection state via the shared predicate.
ok(
  flex("const sel = borderSwatchSelected(value, c)").test(editorSrc),
  "BorderColorSwatchRow derives selection via borderSwatchSelected",
);

// Shorthand border color: swatch row writes into bdColor, warning gated on
// the validator against the same state.
ok(
  flex(
    'BorderColorSwatchRow value={bdColor} onSelect={setBdColor} testIDPrefix="block-border-color-swatch"',
  ).test(editorSrc),
  "shorthand swatch row is wired to bdColor/setBdColor",
);
ok(
  flex('{!isLikelyCssColor(bdColor) ? ( <Text testID="block-border-color-invalid"').test(
    editorSrc,
  ),
  "shorthand warning is gated on !isLikelyCssColor(bdColor)",
);

// Per-side colors: swatch row merges into bdSides[side].color, warning gated
// on the same per-side state.
ok(
  flex(
    "BorderColorSwatchRow value={bdSides[sd.key].color} onSelect={(c) => setBdSides((prev) => ({ ...prev, [sd.key]: { ...prev[sd.key], color: c }, }))}",
  ).test(editorSrc),
  "per-side swatch row merges into bdSides[side].color",
);
ok(
  flex(
    "{!isLikelyCssColor(bdSides[sd.key].color) ? ( <Text testID={`block-border-${sd.key}-color-invalid`}",
  ).test(editorSrc),
  "per-side warning is gated on !isLikelyCssColor(bdSides[side].color)",
);

// The hint must stay a hint: the save/preview paths persist the typed value
// verbatim (no validator on the write side).
ok(
  flex('put("border_color", bdColor)').test(editorSrc) &&
    flex('putStyle("border_color", bdColor)').test(editorSrc),
  "save + preview still persist border_color exactly as typed",
);
ok(
  !/putStyle\(\s*"border_color"\s*,\s*isLikelyCssColor/.test(editorSrc),
  "the validator never rewrites the saved value",
);

console.log(`\nAll ${passed} checks passed.`);
