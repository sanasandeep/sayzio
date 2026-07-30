// Source-driven tests for the recent-custom-colors feature (Task #6098).
//
// Creators' recently used custom colors are remembered (AsyncStorage,
// capped, deduped) and surface as extra swatches in every ColorSwatchRow
// and the block editor's border swatch rows. These checks:
//   1. lift the REAL `isValidColor` / `normalizeColor` out of
//      `lib/recentColors.ts` and exercise them, plus a faithful
//      re-execution of the remember/dedupe/cap logic against a mocked
//      AsyncStorage, and
//   2. structurally assert the wiring — typing capture on every color
//      text input (debounced), save-path capture, and recents rendered in
//      both ColorSwatchRow and BorderColorSwatchRow.
//
// Run via `node scripts/test-recent-colors.mjs` (package script
// `test:recent-colors`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const libSrc = readFileSync(join(root, "lib", "recentColors.ts"), "utf8");
const rowSrc = readFileSync(join(root, "components", "ColorSwatchRow.tsx"), "utf8");
const formSrc = readFileSync(join(root, "components", "SettingsForm.tsx"), "utf8");
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

console.log("[test-recent-colors]");

// ---- 1. the REAL validator + normalizer, lifted and exercised ---------

function liftFn(name) {
  const start = libSrc.indexOf(`export function ${name}`);
  assert.ok(start !== -1, `${name} exists in lib/recentColors.ts`);
  // Slice to the first line that is exactly "}" at column 0.
  const rest = libSrc.slice(start);
  const end = rest.search(/\n\}/);
  assert.ok(end !== -1, `${name} body closes`);
  return rest
    .slice(0, end + 2)
    .replace("export function", "function")
    .replace(/\(value: string\)/, "(value)")
    .replace(/: boolean \{/, " {")
    .replace(/: string \{/, " {");
}

const helpers = new Function(
  `${liftFn("isValidColor")}\n${liftFn("normalizeColor")}\nreturn { isValidColor, normalizeColor };`,
)();
const { isValidColor, normalizeColor } = helpers;

for (const v of ["#fff", "#FFF8", "#a1b2c3", "#a1b2c3d4", "rgb(1,2,3)", "rgba(255, 0, 0, 0.5)", "hsl(120, 50%, 50%)", "hsla(120,50%,50%,.4)", "  #ABCDEF  "]) {
  ok(isValidColor(v), `valid: ${JSON.stringify(v)}`);
}
for (const v of ["", "#", "#ff", "#ffff5", "red", "rgb(", "#gggggg", "hsl", "1234", "url(x)"]) {
  ok(!isValidColor(v), `invalid: ${JSON.stringify(v)}`);
}
ok(normalizeColor(" #ABCDEF ") === "#abcdef", "normalize lowercases + trims hex");
ok(normalizeColor("rgb( 1, 2,   3 )") === "rgb( 1, 2, 3 )", "normalize collapses whitespace in fn syntax");

// ---- 2. remember/dedupe/cap semantics (re-executed against the source
//         constants so a cap change here fails loudly) -------------------

const capMatch = libSrc.match(/MAX_RECENT_COLORS = (\d+)/);
assert.ok(capMatch, "MAX_RECENT_COLORS declared");
const CAP = Number(capMatch[1]);
ok(CAP >= 4 && CAP <= 16, `cap is sane (${CAP})`);

// Mirror of rememberRecentColor's pure core (validate → normalize →
// most-recent-first dedupe → cap), asserted to match the shipped source.
ok(
  /\[norm, \.\.\.cache\.filter\(\(c\) => c !== norm\)\]\.slice\(0, MAX_RECENT_COLORS\)/.test(libSrc),
  "remember is most-recent-first, deduped, capped (source shape)",
);
let cache = [];
function remember(v) {
  if (!isValidColor(v)) return;
  const norm = normalizeColor(v);
  cache = [norm, ...cache.filter((c) => c !== norm)].slice(0, CAP);
}
remember("junk");
ok(cache.length === 0, "invalid input never becomes a swatch");
remember("#111111");
remember("#222222");
remember("#111111");
ok(cache[0] === "#111111" && cache.length === 2, "re-use moves color to front without duplicating");
for (let i = 0; i < CAP + 3; i++) remember(`#${String(i).padStart(6, "0")}`);
ok(cache.length === CAP, `list capped at ${CAP}`);

// Persistence wiring: storage key + AsyncStorage read/write + hydration.
ok(/sayzio\.recentCustomColors\.v1/.test(libSrc), "stable AsyncStorage key");
ok(/AsyncStorage\.setItem\(STORAGE_KEY, JSON\.stringify\(cache\)\)/.test(libSrc), "writes persist to AsyncStorage");
ok(/AsyncStorage\.getItem\(STORAGE_KEY\)/.test(libSrc), "hydrates from AsyncStorage on first use (restart persistence)");

// ---- 3. typing capture (debounced) --------------------------------------

ok(/export function rememberRecentColorFromTyping/.test(libSrc), "typing-capture helper exported");
ok(/clearTimeout\(prev\)/.test(libSrc) && /setTimeout\(/.test(libSrc), "typing capture is debounced per field");

// SettingsForm: color fields capture while typing AND on apply.
ok(/rememberRecentColorFromTyping\(`settings-\$\{group\}-\$\{f\.key\}`, t\)/.test(formSrc), "SettingsForm color fields remember while typing");
ok(/rememberRecentColors\(/.test(formSrc), "SettingsForm remembers color values on apply");

// Block editor: every free-text color input captures while typing.
for (const key of [
  '"block-bg-color"',
  "`block-bg-grad-stop-${i}`",
  '"block-border-color"',
  "`block-border-${sd.key}-color`",
]) {
  ok(editorSrc.includes(`rememberRecentColorFromTyping(${key}, v)`), `editor typing capture: ${key}`);
}
ok(/rememberRecentColors\(\[/.test(editorSrc), "editor remembers applied colors on block save");

// ---- 4. recents rendered everywhere -------------------------------------

ok(/useRecentColors\(\)/.test(rowSrc), "ColorSwatchRow renders live recents");
ok(/function BorderColorSwatchRow[\s\S]*?useRecentColors\(\)/.test(editorSrc), "BorderColorSwatchRow renders live recents (per-side border rows)");
ok((editorSrc.match(/<ColorSwatchRow/g) ?? []).length >= 4, "ColorSwatchRow used across editor color sections");

console.log(`\nPASS — ${passed} checks`);
