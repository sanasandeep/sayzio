/**
 * Regression tests for the brand-color guard's core matcher.
 *
 * The retired-purple detection is deliberately delicate: it must flag the three
 * retired hex ramps (with or without '#', incl. Tailwind arbitrary values), the
 * rgb()/rgba() forms, and the `violet-<shade>` / `purple-<shade>` utility
 * classes — while NEVER flagging an intentional categorical palette (ALLOWLIST),
 * the blue `--color-primary-*` brand accent, or purple that lives only inside a
 * comment. A well-meaning regex or allow-list tweak could weaken any of those
 * without anyone noticing, so this file pins both the must-flag and the
 * must-not-flag cases for `scanSource` (plus the shared hex/rgb pattern builders
 * used by the post-build guard). Run: pnpm --filter @workspace/scripts run test
 */

import { test } from "node:test";
import assert from "node:assert/strict";

import {
  scanSource,
  blankComments,
  hexPatternWithAlpha,
  rgbPatternFor,
  BANNED_HEX_PATTERN,
} from "./check-brand-color.js";

// A primary-UI file that is NOT on the ALLOWLIST (retired purple here is drift).
const APP_CSS = "artifacts/1inme/public/css/app.css";
// An ALLOWLISTed intentional categorical palette surface.
const ALLOWED_FILE = "artifacts/1inme-mobile/lib/blockVariants.ts";
// An ALLOWLISTed directory (deck decorative slides).
const ALLOWED_DIR_FILE = "artifacts/1inme-deck/src/pages/intro.tsx";

function cols(offenders: { col: number }[]): number[] {
  return offenders.map((o) => o.col);
}

// ---------------------------------------------------------------------------
// scanSource — MUST-FLAG cases (retired purple in a primary UI surface)
// ---------------------------------------------------------------------------

test("scanSource flags a retired hex with '#'", () => {
  const hits = scanSource(APP_CSS, `.btn { color: #7c3aed; }`);
  assert.equal(hits.length, 1);
  assert.equal(hits[0]?.line, 1);
});

test("scanSource flags a retired hex WITHOUT the '#'", () => {
  const hits = scanSource(APP_CSS, `--accent: 8b5cf6;`);
  assert.equal(hits.length, 1);
});

test("scanSource flags a retired hex in a Tailwind arbitrary value", () => {
  const hits = scanSource(APP_CSS, `<div class="bg-[#a78bfa]">x</div>`);
  assert.equal(hits.length, 1);
});

test("scanSource flags an uppercase retired hex (case-insensitive)", () => {
  const hits = scanSource(APP_CSS, `color: #7C3AED;`);
  assert.equal(hits.length, 1);
});

test("scanSource flags an rgb() form", () => {
  const hits = scanSource(APP_CSS, `color: rgb(124, 58, 237);`);
  assert.equal(hits.length, 1);
});

test("scanSource flags an rgba() form (space-separated channels)", () => {
  const hits = scanSource(APP_CSS, `color: rgba(139 92 246 / 0.5);`);
  assert.equal(hits.length, 1);
});

test("scanSource flags a violet-<shade> utility class", () => {
  const hits = scanSource(APP_CSS, `<span class="text-violet-500">x</span>`);
  assert.equal(hits.length, 1);
});

test("scanSource flags a purple-<shade> utility class", () => {
  const hits = scanSource(APP_CSS, `<span class="border-purple-700">x</span>`);
  assert.equal(hits.length, 1);
});

test("scanSource reports the correct 1-based line/column", () => {
  const hits = scanSource(APP_CSS, `line one\n.x { color: #7c3aed; }`);
  assert.equal(hits.length, 1);
  assert.equal(hits[0]?.line, 2);
  assert.deepEqual(cols(hits), [13]); // '#' of #7c3aed on the 2nd line
});

test("scanSource flags every offending line, not just the first", () => {
  const hits = scanSource(APP_CSS, `a: #7c3aed;\nb: rgb(139,92,246);\nc: purple-300;`);
  assert.deepEqual(
    hits.map((h) => h.line),
    [1, 2, 3],
  );
});

// ---------------------------------------------------------------------------
// scanSource — MUST-NOT-FLAG cases
// ---------------------------------------------------------------------------

test("scanSource passes an ALLOWLISTed categorical-palette file", () => {
  // The exact retired hex that would fail anywhere else must pass here.
  assert.deepEqual(scanSource(ALLOWED_FILE, `const c = "#7c3aed"; // palette swatch`), []);
});

test("scanSource passes a file inside an ALLOWLISTed directory", () => {
  assert.deepEqual(scanSource(ALLOWED_DIR_FILE, `background: rgb(124,58,237);`), []);
});

test("scanSource passes the blue --color-primary-* brand accent", () => {
  assert.deepEqual(scanSource(APP_CSS, `--color-primary-500: #3d6bff;`), []);
});

test("scanSource passes a blue primary-<shade> utility class", () => {
  assert.deepEqual(scanSource(APP_CSS, `<span class="bg-primary-600 text-blue-500">x</span>`), []);
});

test("scanSource passes retired purple inside a C-style block comment", () => {
  assert.deepEqual(scanSource(APP_CSS, `/* was #7c3aed / violet-500 */`), []);
});

test("scanSource passes retired purple inside a blade comment", () => {
  assert.deepEqual(scanSource(APP_CSS, `{{-- old bg-[#8b5cf6] --}}`), []);
});

test("scanSource passes retired purple inside a // line comment", () => {
  assert.deepEqual(scanSource(APP_CSS, `const x = 1; // TODO drop purple-500`), []);
});

test("scanSource does NOT blank a URL's '//' (not a comment)", () => {
  // The `//` in https:// must not blank the rest of the line, and there is no
  // purple here, so the line simply passes.
  assert.deepEqual(scanSource(APP_CSS, `background: url("https://cdn/x.png");`), []);
});

test("scanSource passes a shade outside the guarded 50-950 ramp", () => {
  assert.deepEqual(scanSource(APP_CSS, `<span class="text-purple-25">x</span>`), []);
});

test("scanSource passes a non-retired hex", () => {
  assert.deepEqual(scanSource(APP_CSS, `color: #123456;`), []);
});

test("scanSource does NOT match a retired hex embedded in a longer hex", () => {
  // 8-digit alpha survives only in COMPILED output; the source-level guard's
  // \b-terminated pattern must not flag it (the build guard owns that form).
  assert.deepEqual(scanSource(APP_CSS, `color: #7c3aed2e;`), []);
});

// ---------------------------------------------------------------------------
// blankComments — preserves line/column geometry
// ---------------------------------------------------------------------------

test("blankComments removes comment bodies while preserving newlines", () => {
  const src = `a\n/* #7c3aed */\nb`;
  const out = blankComments(src);
  assert.equal(out.split("\n").length, src.split("\n").length);
  assert.ok(!out.includes("7c3aed"));
  assert.equal(out.split("\n")[0], "a");
  assert.equal(out.split("\n")[2], "b");
});

test("blankComments leaves non-comment code on the same line intact", () => {
  const out = blankComments(`color: #7c3aed; /* note */`);
  assert.ok(out.includes("#7c3aed"));
  assert.ok(!out.includes("note"));
});

// ---------------------------------------------------------------------------
// hexPatternWithAlpha / rgbPatternFor — shared with the post-build guard
// ---------------------------------------------------------------------------

test("hexPatternWithAlpha matches the 6-digit and 8-digit-alpha forms", () => {
  const re = new RegExp(hexPatternWithAlpha("7c3aed"), "i");
  assert.ok(re.test("#7c3aed"));
  assert.ok(re.test("7c3aed"));
  assert.ok(re.test("#7c3aed2e")); // translucent form in compiled CSS
});

test("hexPatternWithAlpha does not match a 7-hex-digit run (no valid boundary)", () => {
  const re = new RegExp(hexPatternWithAlpha("7c3aed"), "i");
  assert.equal(re.test("#7c3aed2"), false);
});

test("BANNED_HEX_PATTERN (source-level) misses the 8-digit-alpha form", () => {
  // Documents WHY the build guard needs hexPatternWithAlpha: the \b-terminated
  // source pattern cannot see translucent purple in compiled output.
  const re = new RegExp(BANNED_HEX_PATTERN, "i");
  assert.equal(re.test("#7c3aed2e"), false);
});

test("rgbPatternFor tolerates comma- and space-separated channels", () => {
  const re = new RegExp(rgbPatternFor([124, 58, 237]), "i");
  assert.ok(re.test("rgb(124, 58, 237)"));
  assert.ok(re.test("rgba(124 58 237 / .3)"));
  assert.ok(re.test("rgb(124,58,237)"));
});
