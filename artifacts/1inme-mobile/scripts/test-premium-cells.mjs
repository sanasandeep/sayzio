// Smoke test for the mobile Premium Features screen's per-plan cell rendering.
//
// The mobile screen (app/premium-features.tsx) renders the resolved per-plan
// value for every feature from the `cells` map the billing API serializes out
// of the canonical Laravel resolver (PremiumFeatures::resolveCell()). The pure
// `formatPlanCell` helper turns one resolved cell into a render descriptor that
// mirrors the web comparison grid's $renderCell
// (artifacts/1inme/resources/views/public/pricing/features.blade.php):
//   - number:    unlimited → "Unlimited"; not-on → dash mark; else value text
//                ("500" / "Custom")
//   - analytics: the Basic/Advanced text (on=true only when Advanced)
//   - bool:      a check mark when on, a dash mark when off
//
// Following the convention in test-upgrade-hint.mjs / test-citation-href.mjs we
// avoid a full TS runner: the helper is pure, so we extract its body from the
// real source and strip the (simple) TS annotations so it runs as plain JS.
// This keeps the test honest — it exercises the shipped source, not a copy.
//
// Run via `node scripts/test-premium-cells.mjs` (package script
// `test:premium-cells`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(join(root, "app", "premium-features.tsx"), "utf8");

// Pull `export function formatPlanCell(...) { ... }` (multi-line signature),
// matching up to the first closing brace at column 0.
function extractFn(src, name) {
  const re = new RegExp(`export function ${name}\\b[\\s\\S]*?\\n\\}`, "m");
  const m = src.match(re);
  if (!m) throw new Error(`could not find ${name}`);
  return m[0];
}

const fnJs = extractFn(screenSrc, "formatPlanCell")
  // Strip the typed signature down to a plain JS one.
  .replace(
    /export function formatPlanCell\([^)]*\)\s*:\s*CellDisplay\s*\{/,
    "function formatPlanCell(cell) {",
  );

// eslint-disable-next-line no-new-func
const { formatPlanCell } = new Function(
  `${fnJs}; return { formatPlanCell };`,
)();

// ---------------------------------------------------------------------------
// number kind — the special numeric cells.
// ---------------------------------------------------------------------------
// Unlimited (-1) renders as the green "Unlimited" text, not a mark.
assert.deepEqual(
  formatPlanCell({ kind: "number", on: true, unlimited: true, text: "Unlimited" }),
  { type: "text", text: "Unlimited", on: true },
  "unlimited numeric → green 'Unlimited' text",
);
// A plain numeric allowance renders its formatted value text.
assert.deepEqual(
  formatPlanCell({ kind: "number", on: true, unlimited: false, text: "500" }),
  { type: "text", text: "500", on: true },
  "numeric allowance → its value text",
);
// The per-type alias map resolves to the "Custom" text cell.
assert.deepEqual(
  formatPlanCell({ kind: "number", on: true, unlimited: false, text: "Custom" }),
  { type: "text", text: "Custom", on: true },
  "per-type alias map → 'Custom' text",
);
// Zero / not-included numeric → an off dash mark (no value text).
assert.deepEqual(
  formatPlanCell({ kind: "number", on: false, unlimited: false, text: "" }),
  { type: "mark", on: false },
  "zero numeric → off dash mark",
);

// ---------------------------------------------------------------------------
// analytics kind — the Basic/Advanced select.
// ---------------------------------------------------------------------------
assert.deepEqual(
  formatPlanCell({ kind: "analytics", on: true, unlimited: false, text: "Advanced" }),
  { type: "text", text: "Advanced", on: true },
  "advanced analytics → green 'Advanced' text",
);
assert.deepEqual(
  formatPlanCell({ kind: "analytics", on: false, unlimited: false, text: "Basic" }),
  { type: "text", text: "Basic", on: false },
  "basic analytics → muted 'Basic' text",
);

// ---------------------------------------------------------------------------
// bool kind — plain on/off capabilities render as a check / dash mark.
// ---------------------------------------------------------------------------
assert.deepEqual(
  formatPlanCell({ kind: "bool", on: true, unlimited: false, text: "" }),
  { type: "mark", on: true },
  "on capability → check mark",
);
assert.deepEqual(
  formatPlanCell({ kind: "bool", on: false, unlimited: false, text: "" }),
  { type: "mark", on: false },
  "off capability → dash mark",
);

// ---------------------------------------------------------------------------
// Missing cell (older server / unknown plan slug) → off dash, never a crash.
// ---------------------------------------------------------------------------
assert.deepEqual(
  formatPlanCell(undefined),
  { type: "mark", on: false },
  "missing cell → off dash mark",
);

console.log("ok — premium feature per-plan cell rendering (formatPlanCell)");
