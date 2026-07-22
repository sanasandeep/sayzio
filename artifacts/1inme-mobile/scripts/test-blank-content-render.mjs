// Runtime proof of the blank-aware content-picker semantics in the public
// biolink renderer (app/biolink/[handle].tsx).
//
// Admin block defaults can EXPLICITLY blank a content key to "" — that must
// render blank on mobile (web `??` parity), while a truly MISSING key still
// gets the functional fallback label (e.g. "Send me a tip", "Get started").
// The static guard (scripts/src/check-blank-content-fallbacks.ts) only
// prevents the wrong helper/operator from appearing; this test executes the
// real pickStr/pickContentStr source lifted verbatim from the screen, plus
// the exact fallback expressions used by representative blocks (tip_jar,
// cta_button), so the runtime behaviour can't drift silently.
//
// Run via `node scripts/test-blank-content-render.mjs` (package script
// `test:blank-content-render`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const src = readFileSync(join(root, "app", "biolink", "[handle].tsx"), "utf8");

// ---------------------------------------------------------------------------
// Lift the REAL helper functions out of the screen source and strip their
// (simple) TS annotations so they run as plain JS — same convention as
// test-block-cache.mjs. No re-implementation: if the screen's semantics
// change, this test changes with it.
// ---------------------------------------------------------------------------
function extractFn(name) {
  const re = new RegExp(`function ${name}\\([\\s\\S]*?\\n\\}`, "m");
  const m = src.match(re);
  if (!m) throw new Error(`could not find function ${name} in [handle].tsx`);
  return m[0];
}

const js = [extractFn("pickStr"), extractFn("pickContentStr")]
  .join("\n\n")
  .replace(/s: Record<string, unknown> \| null/g, "s")
  .replace(/\.\.\.keys: string\[\]/g, "...keys")
  .replace(/\): string \| null \{/g, ") {");

// eslint-disable-next-line no-new-func
const { pickStr, pickContentStr } = new Function(
  `${js}; return { pickStr, pickContentStr };`,
)();

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ===========================================================================
// 1. Core semantics of the lifted helpers
// ===========================================================================
console.log("[test-blank-content-render] helper semantics");

// pickContentStr: explicit "" is a REAL blank, not "missing".
assert.equal(pickContentStr({ title: "" }, "title"), "");
assert.equal(pickContentStr({ title: "   " }, "title"), "");
ok("pickContentStr: key present as ''/whitespace → returns '' (real blank)");

// Truly absent key → null, so the caller's `??` fallback fires.
assert.equal(pickContentStr({}, "title"), null);
assert.equal(pickContentStr(null, "title"), null);
assert.equal(pickContentStr({ title: 42 }, "title"), null);
ok("pickContentStr: key absent / null settings / non-string → null (fallback fires)");

// Real content wins, trimmed; a later non-blank key beats an earlier blank.
assert.equal(pickContentStr({ title: "  Hi  " }, "title"), "Hi");
assert.equal(pickContentStr({ text: "", label: "Go" }, "text", "label"), "Go");
// All-blank multi-key reads stay blank rather than falling back.
assert.equal(pickContentStr({ text: "", label: "" }, "text", "label"), "");
assert.equal(pickContentStr({ text: "" }, "text", "label"), "");
ok("pickContentStr: non-blank wins over blank; all-blank keys stay blank");

// pickStr (non-content variant) still collapses '' to null — that's why
// content text must NOT go through it.
assert.equal(pickStr({ title: "" }, "title"), null);
assert.equal(pickStr({ title: "Hi" }, "title"), "Hi");
ok("pickStr: collapses '' to null (unsuitable for admin-blankable content)");

// ===========================================================================
// 2. Representative blocks: the exact fallback expressions from the renderer
// ===========================================================================
console.log("[test-blank-content-render] representative block expressions");

// Lift the real expressions so a renderer edit can't silently diverge.
function liftExpr(pattern, label) {
  const m = src.match(pattern);
  if (!m) throw new Error(`could not find the ${label} expression in [handle].tsx`);
  return m[1];
}

// tip_jar: const title = pickContentStr(s, "title") ?? "Send me a tip";
const tipExpr = liftExpr(
  /const title = (pickContentStr\(s, "title"\) \?\? "Send me a tip");/,
  "tip_jar title",
);
// cta_button: const label = pickContentStr(s, "text", "label", "title") ?? "Get started";
const ctaExpr = liftExpr(
  /const label = (pickContentStr\(s, "text", "label", "title"\) \?\? "Get started");/,
  "cta_button label",
);

// eslint-disable-next-line no-new-func
const evalWith = (expr, s) =>
  new Function("pickContentStr", "s", `return (${expr});`)(pickContentStr, s);

// Blanked admin default → renders blank, no sample label.
assert.equal(evalWith(tipExpr, { title: "" }), "");
assert.equal(evalWith(ctaExpr, { text: "", label: "", title: "" }), "");
ok("blanked default ('') renders blank — no 'Send me a tip' / 'Get started'");

// Missing key → functional fallback label still shown.
assert.equal(evalWith(tipExpr, {}), "Send me a tip");
assert.equal(evalWith(ctaExpr, {}), "Get started");
ok("missing key still gets the functional fallback label");

// Creator-provided content renders as-is.
assert.equal(evalWith(tipExpr, { title: "Buy me a coffee" }), "Buy me a coffee");
assert.equal(evalWith(ctaExpr, { text: "Join now" }), "Join now");
ok("real content renders untouched");

console.log(`\n[test-blank-content-render] all ${passed} checks passed`);
