// Smoke test for citationHref / citationLabel — the helpers the
// mobile Ask Coach screen uses to turn Mind-backed citations into
// tappable links to the source detail page (parity with the web).
//
// Run via `node scripts/test-citation-href.mjs` (also wired into
// the package script `test:citation-href`). We intentionally avoid
// pulling in a full TS test runner — the helpers are pure and the
// rest of the screen is exercised by manual + e2e flows.

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = readFileSync(
  join(__dirname, "..", "lib", "api", "ask-coach.ts"),
  "utf8",
);

// Strip TS type annotations enough to evaluate the two pure helpers
// in isolation. We only need their bodies, not the full module.
function extractFn(name) {
  const re = new RegExp(
    `export function ${name}\\b[\\s\\S]*?\\n\\}`,
    "m",
  );
  const m = src.match(re);
  if (!m) throw new Error(`could not find ${name} in ask-coach.ts`);
  return m[0];
}

const stripped =
  extractFn("citationHref") + "\n" + extractFn("citationLabel");
const js = stripped
  .replace(/:\s*string\s*\|\s*null/g, "")
  .replace(/:\s*CoachCitation/g, "")
  .replace(/:\s*string\b/g, "")
  .replace(/export function/g, "function");

// eslint-disable-next-line no-new-func
const mod = new Function(
  `${js}; return { citationHref, citationLabel };`,
)();
const { citationHref, citationLabel } = mod;

const BASE = "https://app.example.com";

// 1. Mind-backed citation → tappable URL pointing at the source page.
assert.equal(
  citationHref({ id: 7, mind_id: 3, title: "Brand voice" }, BASE),
  "https://app.example.com/user/minds/3/sources/7",
  "mind-backed citation should produce a /user/minds/{m}/sources/{s} link",
);

// 2. Trailing slash on the base URL is normalised.
assert.equal(
  citationHref({ id: 7, mind_id: 3 }, BASE + "/"),
  "https://app.example.com/user/minds/3/sources/7",
);

// 3. Legacy / tool-only citations (missing ids) → null so the UI
//    falls back to plain text instead of a broken link.
assert.equal(citationHref({ label: "tool" }, BASE), null);
assert.equal(citationHref({ id: 7 }, BASE), null);
assert.equal(citationHref({ mind_id: 3 }, BASE), null);
assert.equal(citationHref({ id: 0, mind_id: 0 }, BASE), null);

// 4. Label prefers title → label → source.
assert.equal(citationLabel({ title: "T", label: "L", source: "S" }), "T");
assert.equal(citationLabel({ label: "L", source: "S" }), "L");
assert.equal(citationLabel({ source: "S" }), "S");
assert.equal(citationLabel({}), "");

console.log("ok — citationHref / citationLabel");
